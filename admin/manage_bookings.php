<?php
// Start only the admin session
session_name("CLEAN_NEST_ADMIN");
session_start();

require_once "../config.php";
require_once "../email_config.php";

// Check admin login
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: admin_login.php");
    exit();
}

$message = "";
$error   = "";

// Update booking status
if (isset($_POST['update_status'])) {

    $booking_id = (int)$_POST['booking_id'];
    $status     = $_POST['status'];

    // Allow only these booking statuses
    if (in_array($status, ['pending', 'confirmed', 'completed', 'cancelled'], true)) {

        // Get current booking status and customer details
        $check_query = "SELECT b.status, u.first_name, u.last_name, u.email
            FROM bookings b
            JOIN users u ON b.user_id = u.user_id
            WHERE b.booking_id = ?";

        $check_stmt = $link->prepare($check_query);
        $check_stmt->bind_param("i", $booking_id);
        $check_stmt->execute();

        $check_result = $check_stmt->get_result();
        $booking_info = $check_result->fetch_assoc();

        $check_stmt->close();

        if ($booking_info) {

            $old_status = $booking_info['status'];

            // Do not allow updating an already cancelled booking
            if ($old_status == 'cancelled') {

                $error = "Cancelled bookings cannot be updated.";

            } else {

                // Update the booking status
                $stmt = $link->prepare(
                "UPDATE bookings SET status = ? WHERE booking_id = ?"
                );

                $stmt->bind_param("si", $status, $booking_id);

                if ($stmt->execute()) {

                    $message = "Booking status updated successfully.";

                    // Send email only when booking becomes confirmed
                    if ($old_status != 'confirmed' && $status == 'confirmed'
                    && !empty($booking_info['email'])) {

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

                        // Send confirmation email to customer
                        sendCleanNestEmail(
                        $booking_info['email'],
                        "CleanNest Booking Confirmed - #$booking_id",
                        $email_message
                        );
                    }

                } else {
                    $error = "Unable to update booking status.";
                }

                $stmt->close();
            }

        } else {
            $error = "Booking not found.";
        }

    } else {
        $error = "Invalid booking status.";
    }
}

// Get selected booking status from the filter
$filter_status   = isset($_GET['status']) ? $_GET['status'] : 'all';
$allowed_filters = ['all', 'pending', 'confirmed', 'completed', 'cancelled'];

// If an invalid filter is entered in the URL, show all bookings
if (!in_array($filter_status, $allowed_filters, true)) {
    $filter_status = 'all';
}

// Get bookings based on the selected filter
$query = "SELECT
    b.booking_id,
    b.booking_date,
    b.booking_time,
    b.address,
    b.home_size,
    b.total_price,
    b.status,
    u.first_name,
    u.last_name,
    u.phone,
    p.package_name
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

            .topbar h2 {
                margin: 0;
            }

            .back-btn {
                background: white;
                color: #16866f;
                padding: 10px 16px;
                text-decoration: none;
                border-radius: 5px;
                font-weight: bold;
            }

            @media (max-width: 600px) {
                .topbar {
                    padding: 15px;
                }
            }

            .back-link {
                color: white;
                text-decoration: none;
                font-weight: bold;
                padding: 9px 16px;
                border: 1px solid rgba(255,255,255,0.5);
                border-radius: 6px;
                display: inline-block;
            }

            .back-link:hover {
                background: white;
                color: #16866f;
            }

            .booking-page {
                width: 94%;
                max-width: 1350px;
                margin: 35px auto;
            }

            .page-heading {
                margin-bottom: 25px;
            }

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

            .filter-btn:hover {
                background: #106b59;
            }

            .table-wrapper {
                width: 100%;
                overflow-x: auto;
            }

            table {
                width: 100%;
                min-width: 1200px;
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

            tbody tr:hover {
                background: #f8fbfa;
            }

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

            .pending {
                background: #fff4dc;
                color: #a56a00;
            }

            .confirmed {
                background: #e5f0ff;
                color: #2463a5;
            }

            .completed {
                background: #e3f6eb;
                color: #18804c;
            }

            .cancelled {
                background: #fff0ed;
                color: #c0392b;
            }

            select {
                padding: 9px;
                border: 1px solid #d5e1dc;
                border-radius: 6px;
                background: white;
                font-size: 13px;
                width: 125px;
                margin-bottom: 7px;
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
            }

            .update-btn:hover {
                background: #106b59;
            }

            .empty-message {
                text-align: center;
                padding: 25px;
                color: #71817d;
            }

            @media (max-width: 700px) {
                .booking-page {
                    width: 95%;
                    margin: 22px auto;
                }

                .booking-container {
                    padding: 17px;
                }
            }
        </style>
    </head>

    <body>

        <!-- Top navigation -->
        <header>
            <div class="topbar">
                <h2>Manage Bookings</h2>
                <a href="admin_dashboard.php" class="back-btn">
                    Back to Dashboard
                </a>
            </div>
        </header>

        <main class="booking-page">

            <div class="page-heading">
                <h1>Manage Bookings</h1>
                <p>View customer bookings and update their booking status.</p>
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

                <form method="GET" class="filter-form">
                    <label for="status-filter">Filter by Status:</label>

                    <select name="status" id="status-filter">
                        <option value="all" <?php echo ($filter_status == 'all') ? 'selected' : ''; ?>>All Bookings</option>
                        <option value="pending" <?php echo ($filter_status == 'pending') ? 'selected' : ''; ?>>Pending</option>
                        <option value="confirmed" <?php echo ($filter_status == 'confirmed') ? 'selected' : ''; ?>>Confirmed</option>
                        <option value="completed" <?php echo ($filter_status == 'completed') ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo ($filter_status == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
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
                                        $booking['first_name'] . " " . $booking['last_name']
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

                                    <td>
                                        <form method="POST">

                                            <input type="hidden" name="booking_id"
                                                value="<?php echo (int)$booking['booking_id']; ?>">

                                            <select name="status" required>

                                                <option value="pending"
                                                <?php echo ($booking['status'] == 'pending') ? 'selected' : ''; ?>>
                                                    Pending
                                                </option>

                                                <option value="confirmed"
                                                <?php echo ($booking['status'] == 'confirmed') ? 'selected' : ''; ?>>
                                                    Confirmed
                                                </option>

                                                <option value="completed"
                                                <?php echo ($booking['status'] == 'completed') ? 'selected' : ''; ?>>
                                                    Completed
                                                </option>

                                                <option value="cancelled"
                                                <?php echo ($booking['status'] == 'cancelled') ? 'selected' : ''; ?>>
                                                    Cancelled
                                                </option>

                                            </select>

                                            <button type="submit" name="update_status" class="update-btn">
                                                Update
                                            </button>

                                        </form>
                                    </td>

                                </tr>

                            <?php endwhile; ?>

                            <?php else: ?>

                            <tr>
                                <td colspan="10" class="empty-message">
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
