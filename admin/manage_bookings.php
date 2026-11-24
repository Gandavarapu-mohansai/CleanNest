<?php
// Set Indian time
date_default_timezone_set("Asia/Kolkata");

// Start admin session
session_name("CLEAN_NEST_ADMIN");
session_start();

require_once "../config.php";
require_once "../email_config.php";

// Check admin login
if (
!isset($_SESSION['user_id']) ||
!isset($_SESSION['role']) ||
$_SESSION['role'] != 'admin'
) {
    header("Location: admin_login.php");
    exit();
}

$message = "";
$error   = "";

// ==================================================
// 1. CONFIRM BOOKING AND ASSIGN WORKERS
// ==================================================

if (isset($_POST['update_status'])) {

    $booking_id = (int)$_POST['booking_id'];
    $status     = $_POST['status'];

    // Get booking details
    $check_query = "SELECT
        b.status, b.area_id, b.booking_date, b.booking_time,
        b.required_workers, b.expected_duration_hours,
        p.bhk_type,
        u.first_name, u.last_name, u.email
        FROM bookings b
        JOIN users u ON b.user_id = u.user_id
        LEFT JOIN bhk_packages p ON b.package_id = p.package_id
        WHERE b.booking_id = ?";

    $check_stmt = $link->prepare($check_query);
    $check_stmt->bind_param("i", $booking_id);
    $check_stmt->execute();

    $booking_info = $check_stmt->get_result()->fetch_assoc();
    $check_stmt->close();

    if (!$booking_info) {

        $error = "Booking not found.";

    } elseif ($booking_info['status'] == 'cancelled') {

        $error = "Cancelled bookings cannot be updated.";

    } elseif ($booking_info['status'] == 'completed') {

        $error = "Completed bookings cannot be updated.";

    } elseif ($status == 'confirmed') {

        // Get team size and duration entered by admin
        $required_workers = (int)($_POST['required_workers'] ?? 0);
        $duration         = (float)($_POST['expected_duration_hours'] ?? 0);

        // Decide allowed team size
        $bhk_type = $booking_info['bhk_type'];

        if ($bhk_type === null) {
            $minimum_workers = 1;
            $maximum_workers = 2;
        } elseif ($bhk_type <= 2) {
            $minimum_workers = 3;
            $maximum_workers = 4;
        } else {
            $minimum_workers = 5;
            $maximum_workers = 6;
        }

        // Check team size and duration
        if (
        $required_workers < $minimum_workers ||
        $required_workers > $maximum_workers ||
        $duration < 0.5 ||
        $duration > 24
        ) {

            $error = "Enter a valid team size and duration.";

        } elseif (empty($booking_info['area_id'])) {

            $error = "This booking has no service area. Please update its area first.";

        } else {

            // Calculate start and expected end time
            $start_datetime = $booking_info['booking_date'] . " " .
            $booking_info['booking_time'];

            $end_datetime = date(
            "Y-m-d H:i:s",
            strtotime($start_datetime) + (int)round($duration * 3600)
            );

            // Check whether workers are already assigned
            $count_stmt = $link->prepare(
            "SELECT COUNT(*) AS total
                FROM worker_assignments
                WHERE booking_id = ?"
            );

            $count_stmt->bind_param("i", $booking_id);
            $count_stmt->execute();

            $assignment_count = (int)$count_stmt->get_result()->fetch_assoc()['total'];
            $count_stmt->close();

            if ($assignment_count > 0) {

                $error = "Workers are already assigned to this booking.";

            } else {

                // Confirm booking and save team details
                $update_query = "UPDATE bookings
                    SET status = 'confirmed',
                    required_workers = ?,
                    expected_duration_hours = ?
                    WHERE booking_id = ?";

                $update_stmt = $link->prepare($update_query);
                $update_stmt->bind_param(
                "idi",
                $required_workers,
                $duration,
                $booking_id
                );

                if (!$update_stmt->execute()) {

                    $error = "Unable to confirm booking.";

                } else {

                    $message = "Booking confirmed.";

                    // Find available Active workers for this area.
                    // Other statuses cannot be assigned to a new booking.
                    $worker_query = "SELECT w.worker_id
                        FROM workers w
                        JOIN worker_areas wa ON w.worker_id = wa.worker_id
                        WHERE wa.area_id = ?
                        AND w.status = 'Active'
                        AND NOT EXISTS (
                        SELECT 1
                        FROM worker_assignments a
                        WHERE a.worker_id = w.worker_id
                        AND a.status != 'Work Done'
                        AND a.start_time < ?
                        AND a.expected_end_time > ?
                        )
                        ORDER BY w.worker_id
                        LIMIT ?";

                    $worker_stmt = $link->prepare($worker_query);
                    $area_id     = (int)$booking_info['area_id'];

                    $worker_stmt->bind_param(
                    "issi",
                    $area_id,
                    $end_datetime,
                    $start_datetime,
                    $required_workers
                    );

                    $worker_stmt->execute();
                    $worker_result = $worker_stmt->get_result();

                    $available_workers = array();

                    while ($worker = $worker_result->fetch_assoc()) {
                        $available_workers[] = (int)$worker['worker_id'];
                    }

                    $worker_stmt->close();

                    // Check whether enough workers are available
                    if (count($available_workers) < $required_workers) {

                        $pending_status = "Pending Worker Assignment";

                        $stmt = $link->prepare(
                        "UPDATE bookings
                            SET worker_assignment_status = ?
                            WHERE booking_id = ?"
                        );

                        $stmt->bind_param("si", $pending_status, $booking_id);
                        $stmt->execute();
                        $stmt->close();

                        $message .= " Not enough workers are available. Assignment is pending.";

                    } else {

                        // Assign all workers together
                        $link->begin_transaction();

                        try {

                            $insert_stmt = $link->prepare(
                            "INSERT INTO worker_assignments
                                (booking_id, worker_id, start_time,
                                expected_end_time, status)
                                VALUES (?, ?, ?, ?, 'Assigned')"
                            );

                            foreach ($available_workers as $worker_id) {

                                $insert_stmt->bind_param(
                                "iiss",
                                $booking_id,
                                $worker_id,
                                $start_datetime,
                                $end_datetime
                                );

                                if (!$insert_stmt->execute()) {
                                    throw new Exception("Unable to assign worker.");
                                }
                            }

                            $insert_stmt->close();

                            // Update booking assignment status
                            $stmt = $link->prepare(
                            "UPDATE bookings
                                SET worker_assignment_status = 'Assigned'
                                WHERE booking_id = ?"
                            );

                            $stmt->bind_param("i", $booking_id);
                            $stmt->execute();
                            $stmt->close();

                            $link->commit();

                            $message .= " $required_workers workers assigned successfully.";

                        } catch (Exception $e) {

                            $link->rollback();
                            $error = "Worker assignment failed. Please try again.";
                        }
                    }

                    // Send confirmation email only when booking is confirmed first time
                    if (
                    $booking_info['status'] != 'confirmed' &&
                    !empty($booking_info['email'])
                    ) {

                        $customer_name = $booking_info['first_name'] . " " .
                        $booking_info['last_name'];

                        $email_message = "
                            <h2>Booking Confirmed - CleanNest</h2>
                            <p>Hello " . htmlspecialchars($customer_name) . ",</p>
                            <p>Good news! Your cleaning service booking has been confirmed.</p>
                            <p><strong>Booking ID:</strong> #$booking_id</p>
                            <p><strong>Booking Status:</strong> Confirmed</p>
                            <p>Our team will provide the cleaning service
                            according to your scheduled booking.</p>
                            <br>
                            <p>Thank you for choosing CleanNest!</p>
                            <b>CleanNest Team</b>
                            ";

                        sendCleanNestEmail(
                        $booking_info['email'],
                        "CleanNest Booking Confirmed - #$booking_id",
                        $email_message
                        );
                    }
                }

                $update_stmt->close();
            }
        }

    } elseif ($status == 'pending' || $status == 'cancelled') {

        // Check whether any worker has already started this job
        $working_stmt = $link->prepare(
        "SELECT COUNT(*) AS total
            FROM worker_assignments
            WHERE booking_id = ?
            AND status = 'Working'"
        );

        $working_stmt->bind_param("i", $booking_id);
        $working_stmt->execute();

        $working_count = (int)$working_stmt->get_result()->fetch_assoc()['total'];
        $working_stmt->close();

        if ($working_count > 0) {

            $error = "This booking cannot be changed because workers have already started the job.";

        } else {

            // Release workers who were assigned but have not started
            $link->begin_transaction();

            try {

                $delete_stmt = $link->prepare(
                "DELETE FROM worker_assignments
                    WHERE booking_id = ?
                    AND status = 'Assigned'"
                );

                $delete_stmt->bind_param("i", $booking_id);
                $delete_stmt->execute();
                $delete_stmt->close();

                if ($status == 'cancelled') {
                    $assignment_status = "Cancelled";
                } else {
                    $assignment_status = "Not Assigned";
                }

                $update_stmt = $link->prepare(
                "UPDATE bookings
                    SET status = ?,
                    worker_assignment_status = ?
                    WHERE booking_id = ?"
                );

                $update_stmt->bind_param(
                "ssi",
                $status,
                $assignment_status,
                $booking_id
                );

                $update_stmt->execute();
                $update_stmt->close();

                $link->commit();

                $message = "Booking status updated successfully.";

            } catch (Exception $e) {

                $link->rollback();
                $error = "Unable to update booking status.";
            }
        }

    } else {

        $error = "Invalid booking status.";
    }
}

// ==================================================
// 2. START WORK
// ==================================================

if (isset($_POST['start_work'])) {

    $booking_id = (int)$_POST['booking_id'];

    // Check booking time and assignment status
    $check_stmt = $link->prepare(
    "SELECT booking_date, booking_time, status,
        worker_assignment_status
        FROM bookings
        WHERE booking_id = ?"
    );

    $check_stmt->bind_param("i", $booking_id);
    $check_stmt->execute();

    $work_info = $check_stmt->get_result()->fetch_assoc();
    $check_stmt->close();

    if (
    !$work_info ||
    $work_info['status'] != 'confirmed' ||
    $work_info['worker_assignment_status'] != 'Assigned'
    ) {

        $error = "This booking is not ready to start.";

    } else {

        // Check that every assigned worker is still Active
        $worker_check = $link->prepare(
        "SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN w.status = 'Active' THEN 1 ELSE 0 END) AS active_total
            FROM worker_assignments a
            JOIN workers w ON a.worker_id = w.worker_id
            WHERE a.booking_id = ?
            AND a.status = 'Assigned'"
        );

        $worker_check->bind_param("i", $booking_id);
        $worker_check->execute();

        $worker_data = $worker_check->get_result()->fetch_assoc();
        $worker_check->close();

        if (
        (int)$worker_data['total'] == 0 ||
        (int)$worker_data['total'] != (int)$worker_data['active_total']
        ) {

            $error = "Some assigned workers are no longer available. Please check the worker assignments.";

        } else {

            $start_datetime = $work_info['booking_date'] . " " .
            $work_info['booking_time'];

            // Do not start a future booking
            if (strtotime($start_datetime) > time()) {

                $error = "You cannot start work before the booking time.";

            } else {

                $link->begin_transaction();

                try {

                    // Change assignment status to Working
                    $stmt = $link->prepare(
                    "UPDATE worker_assignments
                        SET status = 'Working'
                        WHERE booking_id = ?
                        AND status = 'Assigned'"
                    );

                    $stmt->bind_param("i", $booking_id);
                    $stmt->execute();

                    if ($stmt->affected_rows == 0) {
                        throw new Exception("No assigned workers found.");
                    }

                    $stmt->close();

                    // Change worker status to Working
                    $stmt = $link->prepare(
                    "UPDATE workers w
                        JOIN worker_assignments a
                        ON w.worker_id = a.worker_id
                        SET w.status = 'Working'
                        WHERE a.booking_id = ?
                        AND a.status = 'Working'"
                    );

                    $stmt->bind_param("i", $booking_id);
                    $stmt->execute();
                    $stmt->close();

                    // Update booking assignment status
                    $stmt = $link->prepare(
                    "UPDATE bookings
                        SET worker_assignment_status = 'Working'
                        WHERE booking_id = ?"
                    );

                    $stmt->bind_param("i", $booking_id);
                    $stmt->execute();
                    $stmt->close();

                    $link->commit();

                    $message = "Work started successfully.";

                } catch (Exception $e) {

                    $link->rollback();
                    $error = "Unable to start work.";
                }
            }
        }
    }
}

// ==================================================
// 3. MARK WORK AS DONE
// ==================================================

if (isset($_POST['mark_work_done'])) {

    $booking_id = (int)$_POST['booking_id'];

    // Check that work has actually started
    $check_stmt = $link->prepare(
    "SELECT status, worker_assignment_status
        FROM bookings
        WHERE booking_id = ?"
    );

    $check_stmt->bind_param("i", $booking_id);
    $check_stmt->execute();

    $work_info = $check_stmt->get_result()->fetch_assoc();
    $check_stmt->close();

    if (
    !$work_info ||
    $work_info['status'] != 'confirmed' ||
    $work_info['worker_assignment_status'] != 'Working'
    ) {

        $error = "Work must be started before marking it as completed.";

    } else {

        $link->begin_transaction();

        try {

            // Mark all working assignments as finished
            $stmt = $link->prepare(
            "UPDATE worker_assignments
                SET status = 'Work Done',
                actual_end_time = NOW()
                WHERE booking_id = ?
                AND status = 'Working'"
            );

            $stmt->bind_param("i", $booking_id);
            $stmt->execute();
            $stmt->close();

            // Update booking status
            $stmt = $link->prepare(
            "UPDATE bookings
                SET status = 'completed',
                worker_assignment_status = 'Work Done'
                WHERE booking_id = ?"
            );

            $stmt->bind_param("i", $booking_id);
            $stmt->execute();
            $stmt->close();

            // Make workers Active only if they have no other ongoing job.
            // Workers manually marked On Leave, Absent or Inactive are not changed.
            $stmt = $link->prepare(
            "UPDATE workers w
                SET w.status = 'Active'
                WHERE w.status = 'Working'
                AND NOT EXISTS (
                SELECT 1
                FROM worker_assignments a
                WHERE a.worker_id = w.worker_id
                AND a.status = 'Working'
                )"
            );

            $stmt->execute();
            $stmt->close();

            $link->commit();

            $message = "Work marked as completed.";

        } catch (Exception $e) {

            $link->rollback();
            $error = "Unable to complete the work.";
        }
    }
}

// ==================================================
// 4. BOOKING FILTER
// ==================================================

$filter_status = $_GET['status'] ?? 'all';

$allowed_filters = array(
    'all',
    'pending',
    'confirmed',
    'completed',
    'cancelled'
);

if (!in_array($filter_status, $allowed_filters, true)) {
    $filter_status = 'all';
}

// ==================================================
// 5. GET BOOKINGS AND ASSIGNED WORKERS
// ==================================================

$query = "SELECT
    b.booking_id,
    b.booking_date,
    b.booking_time,
    b.address,
    b.home_size,
    b.total_price,
    b.status,
    b.area_id,
    b.required_workers,
    b.expected_duration_hours,
    b.worker_assignment_status,
    u.first_name,
    u.last_name,
    u.phone,
    p.package_name,
    p.bhk_type,

    (SELECT GROUP_CONCAT(w.worker_name SEPARATOR ', ')
    FROM worker_assignments a
    JOIN workers w ON a.worker_id = w.worker_id
    WHERE a.booking_id = b.booking_id) AS assigned_workers

    FROM bookings b
    JOIN users u ON b.user_id = u.user_id
    LEFT JOIN bhk_packages p ON b.package_id = p.package_id";

if ($filter_status != 'all') {
    $query .= " WHERE b.status = ?";
}

$query .= " ORDER BY b.created_at DESC";

if ($filter_status == 'all') {

    $bookings = $link->query($query);

} else {

    $stmt = $link->prepare($query);
    $stmt->bind_param("s", $filter_status);
    $stmt->execute();

    $bookings = $stmt->get_result();
}

if (!$bookings) {
    die("Unable to load bookings: " . $link->error);
}
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Manage Bookings - CleanNest</title>

        <style>
            * {
                box-sizing: border-box;
                margin: 0;
                padding: 0;
            }

            body {
                font-family: Arial, sans-serif;
                background: #f4f8f7;
                color: #263b38;
            }

            .topbar {
                background: #16866f;
                color: white;
                padding: 18px 30px;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .topbar h2 { margin: 0; }

            .back-btn {
                background: white;
                color: #16866f;
                padding: 10px 16px;
                text-decoration: none;
                border-radius: 5px;
                font-weight: bold;
            }

            .booking-page {
                width: 94%;
                max-width: 1350px;
                margin: 35px auto;
            }

            .page-heading { margin-bottom: 25px; }

            .page-heading h1 {
                color: #184d40;
                font-size: 29px;
                margin-bottom: 8px;
            }

            .page-heading p {
                color: #71817d;
                font-size: 14px;
            }

            .message {
                padding: 13px 16px;
                border-radius: 7px;
                margin-bottom: 20px;
            }

            .success-message {
                background: #e4f6ed;
                color: #187447;
            }

            .error-message {
                background: #fff0f0;
                color: #c0392b;
            }

            .booking-container {
                background: white;
                padding: 25px;
                border-radius: 12px;
                box-shadow: 0 3px 14px rgba(0,0,0,0.05);
                border: 1px solid #e5eeeb;
            }

            .section-heading {
                color: #184d40;
                font-size: 20px;
                margin-bottom: 22px;
            }

            .filter-form {
                display: flex;
                align-items: center;
                gap: 10px;
                flex-wrap: wrap;
                margin-bottom: 20px;
            }

            .filter-form label {
                font-weight: bold;
                color: #31594f;
                font-size: 14px;
            }

            .filter-form select {
                width: 180px;
                margin: 0;
            }

            .filter-btn {
                background: #16866f;
                color: white;
                border: none;
                padding: 10px 16px;
                border-radius: 6px;
                cursor: pointer;
                font-weight: bold;
            }

            .table-wrapper {
                width: 100%;
                overflow-x: auto;
            }

            table {
                width: 100%;
                min-width: 1400px;
                border-collapse: collapse;
            }

            th {
                background: #e8f4f0;
                color: #28594d;
                padding: 15px 12px;
                text-align: left;
                font-size: 13px;
                border-bottom: 2px solid #d4e7df;
            }

            td {
                padding: 14px 12px;
                border-bottom: 1px solid #e8eeeb;
                font-size: 13px;
                vertical-align: middle;
            }

            tbody tr:hover { background: #f8fbfa; }

            .booking-id {
                font-weight: bold;
                color: #16866f;
            }

            .customer-name {
                font-weight: bold;
                color: #31594f;
            }

            .address {
                max-width: 200px;
                line-height: 1.5;
            }

            .price {
                font-weight: bold;
                color: #184d40;
                white-space: nowrap;
            }

            .status {
                display: inline-block;
                padding: 6px 10px;
                border-radius: 20px;
                font-size: 12px;
                font-weight: bold;
                text-transform: capitalize;
            }

            .pending { background: #fff4dc; color: #a56a00; }
            .confirmed { background: #e5f0ff; color: #2463a5; }
            .completed { background: #e3f6eb; color: #18804c; }
            .cancelled { background: #fff0ed; color: #c0392b; }

            select {
                padding: 9px;
                border: 1px solid #d5e1dc;
                border-radius: 6px;
                background: white;
                font-size: 13px;
                width: 125px;
                margin-bottom: 7px;
            }

            input[type="number"] {
                width: 110px;
                padding: 9px;
                border: 1px solid #d5e1dc;
                border-radius: 6px;
                margin: 7px 0;
            }

            .update-btn {
                display: block;
                background: #16866f;
                color: white;
                border: none;
                padding: 9px 12px;
                border-radius: 6px;
                cursor: pointer;
                font-weight: bold;
                font-size: 12px;
                margin-top: 6px;
            }

            .update-btn:hover { background: #106b59; }

            .cancel-btn { background: #c0392b; }
            .cancel-btn:hover { background: #a93226; }

            .empty-message {
                text-align: center;
                padding: 25px;
                color: #71817d;
            }

            @media (max-width: 700px) {
                .topbar { padding: 15px; }
                .booking-page {
                    width: 95%;
                    margin: 22px auto;
                }
                .booking-container { padding: 17px; }
            }
        </style>
    </head>

    <body>

        <!-- Top navigation -->
        <header>
            <div class="topbar">
                <h2>Manage Bookings</h2>
                <a href="admin_dashboard.php" class="back-btn">Back to Dashboard</a>
            </div>
        </header>

        <main class="booking-page">

            <div class="page-heading">
                <h1>Manage Bookings</h1>
                <p>View bookings, confirm services and manage worker assignments.</p>
            </div>

            <?php if ($message != ""): ?>
                <p class="message success-message">
                    <?php echo htmlspecialchars($message); ?>
                </p>
            <?php endif; ?>

            <?php if ($error != ""): ?>
                <p class="message error-message">
                    <?php echo htmlspecialchars($error); ?>
                </p>
            <?php endif; ?>

            <section class="booking-container">

                <h2 class="section-heading">Customer Bookings</h2>

                <!-- Booking filter -->
                <form method="GET" class="filter-form">

                    <label for="status-filter">Filter by Status:</label>

                    <select name="status" id="status-filter">

                        <option value="all"
                        <?php echo ($filter_status == 'all') ? 'selected' : ''; ?>>
                            All Bookings
                        </option>

                        <option value="pending"
                        <?php echo ($filter_status == 'pending') ? 'selected' : ''; ?>>
                            Pending
                        </option>

                        <option value="confirmed"
                        <?php echo ($filter_status == 'confirmed') ? 'selected' : ''; ?>>
                            Confirmed
                        </option>

                        <option value="completed"
                        <?php echo ($filter_status == 'completed') ? 'selected' : ''; ?>>
                            Completed
                        </option>

                        <option value="cancelled"
                        <?php echo ($filter_status == 'cancelled') ? 'selected' : ''; ?>>
                            Cancelled
                        </option>

                    </select>

                    <button type="submit" class="filter-btn">Apply Filter</button>

                </form>

                <div class="table-wrapper">

                    <table>

                        <thead>
                            <tr>
                                <th>Booking ID</th>
                                <th>Customer</th>
                                <th>Phone</th>
                                <th>Package</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Address</th>
                                <th>Total Price</th>
                                <th>Status</th>
                                <th>Worker Assignment</th>
                                <th>Update Status</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php if ($bookings->num_rows > 0): ?>

                                <?php while ($booking = $bookings->fetch_assoc()): ?>

                                <tr>

                                    <td class="booking-id">
                                        #<?php echo (int)$booking['booking_id']; ?>
                                    </td>

                                    <td class="customer-name">
                                        <?php
                                        echo htmlspecialchars(
                                        $booking['first_name'] . " " .
                                        $booking['last_name']
                                        );
                                        ?>
                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars($booking['phone'] ?? ''); ?>
                                    </td>

                                    <td>
                                        <?php
                                        echo !empty($booking['package_name'])
                                        ? htmlspecialchars($booking['package_name'])
                                        : "Individual Services";
                                        ?>
                                    </td>

                                    <td>
                                        <?php echo date("d-m-Y", strtotime($booking['booking_date'])); ?>
                                    </td>

                                    <td>
                                        <?php echo date("h:i A", strtotime($booking['booking_time'])); ?>
                                    </td>

                                    <td class="address">
                                        <?php echo htmlspecialchars($booking['address']); ?>
                                    </td>

                                    <td class="price">
                                        ₹<?php echo number_format($booking['total_price'], 2); ?>
                                    </td>

                                    <td>
                                        <span class="status <?php echo htmlspecialchars($booking['status']); ?>">
                                            <?php echo htmlspecialchars(ucfirst($booking['status'])); ?>
                                        </span>
                                    </td>

                                    <!-- Worker assignment details -->
                                    <td>

                                        <strong>
                                            <?php
                                            echo htmlspecialchars(
                                            $booking['worker_assignment_status'] ?? 'Not Assigned'
                                            );
                                            ?>
                                        </strong>

                                        <br>
                                        <br>

                                        <?php if (!empty($booking['assigned_workers'])): ?>
                                        <small>
                                            Workers:
                                            <?php echo htmlspecialchars($booking['assigned_workers']); ?>
                                        </small>
                                    <?php endif; ?>

                                    <?php
                                    $assignment_status = $booking['worker_assignment_status'] ?? '';
                                    ?>

                                    <!-- Confirm booking or retry worker assignment -->
                                    <?php
                                    if (
                                    $booking['status'] == 'pending' ||
                                    (
                                    $booking['status'] == 'confirmed' &&
                                    $assignment_status == 'Pending Worker Assignment'
                                    )
                                    ):
                                    ?>

                                    <?php
                                    if ($booking['bhk_type'] === null) {
                                        $minimum = 1;
                                        $maximum = 2;
                                    } elseif ($booking['bhk_type'] <= 2) {
                                        $minimum = 3;
                                        $maximum = 4;
                                    } else {
                                        $minimum = 5;
                                        $maximum = 6;
                                    }
                                    ?>

                                    <form method="POST" style="margin-top:10px;">

                                        <input type="hidden" name="booking_id"
                                            value="<?php echo (int)$booking['booking_id']; ?>">

                                        <input type="hidden" name="status" value="confirmed">

                                        <label>Number of workers:</label>

                                        <select name="required_workers" required>
                                            <?php for ($i = $minimum; $i <= $maximum; $i++): ?>
                                                <option value="<?php echo $i; ?>"
                                                <?php echo ($booking['required_workers'] == $i) ? 'selected' : ''; ?>>
                                                    <?php echo $i; ?> workers
                                                </option>
                                            <?php endfor; ?>
                                        </select>

                                        <label>Duration (hours):</label>

                                        <input type="number"
                                            name="expected_duration_hours"
                                            min="0.5"
                                            max="24"
                                            step="0.5"
                                            value="<?php echo htmlspecialchars($booking['expected_duration_hours'] ?? '2'); ?>"
                                            required>

                                        <button type="submit"
                                            name="update_status"
                                            class="update-btn">
                                            <?php
                                            echo ($booking['status'] == 'pending')
                                            ? 'Confirm & Assign'
                                            : 'Retry Assignment';
                                            ?>
                                        </button>

                                    </form>

                                    <?php endif; ?>

                                    <!-- Start work button -->
                                    <?php if (
                                    $booking['status'] == 'confirmed' &&
                                    $assignment_status == 'Assigned'
                                    ): ?>

                                    <form method="POST" style="margin-top:8px;">

                                        <input type="hidden" name="booking_id"
                                            value="<?php echo (int)$booking['booking_id']; ?>">

                                        <button type="submit"
                                            name="start_work"
                                            class="update-btn">
                                            Start Work
                                        </button>

                                    </form>

                                    <?php endif; ?>

                                    <!-- Mark work done button -->
                                    <?php if (
                                    $booking['status'] == 'confirmed' &&
                                    $assignment_status == 'Working'
                                    ): ?>

                                    <form method="POST" style="margin-top:8px;">

                                        <input type="hidden" name="booking_id"
                                            value="<?php echo (int)$booking['booking_id']; ?>">

                                        <button type="submit"
                                            name="mark_work_done"
                                            class="update-btn">
                                            Mark Work Done
                                        </button>

                                    </form>

                                    <?php endif; ?>

                                </td>

                                <!-- Update or cancel booking -->
                                <td>

                                    <?php if (
                                    $booking['status'] != 'completed' &&
                                    $booking['status'] != 'cancelled'
                                    ): ?>

                                    <form method="POST">

                                        <input type="hidden" name="booking_id"
                                            value="<?php echo (int)$booking['booking_id']; ?>">

                                        <select name="status" required>
                                            <option value="pending">Pending</option>
                                            <option value="cancelled">Cancelled</option>
                                        </select>

                                        <button type="submit"
                                            name="update_status"
                                            class="update-btn cancel-btn">
                                            Update Status
                                        </button>

                                    </form>

                                    <?php else: ?>

                                    <small>No further updates</small>

                                    <?php endif; ?>

                                </td>

                            </tr>

                            <?php endwhile; ?>

                            <?php else: ?>

                            <tr>
                                <td colspan="11" class="empty-message">
                                    No bookings found.
                                </td>
                            </tr>

                            <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </section>

        </main>

    </body>
</html>
