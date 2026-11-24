<?php
// Start admin session
session_name("CLEAN_NEST_ADMIN");
session_start();

// Connect to database
require_once "../config.php";

// Check admin login
if (
!isset($_SESSION['user_id']) ||
!isset($_SESSION['role']) ||
$_SESSION['role'] != 'admin'
) {
    header("Location: admin_login.php");
    exit();
}

$message      = "";
$message_type = "";

// Get active service areas for the form
$area_query = "SELECT area_id, area_name, pincode
    FROM service_areas
    WHERE status = 'active'
    ORDER BY area_name ASC";

$area_result = mysqli_query($link, $area_query);

// Add new worker
if (isset($_POST['add_worker'])) {

    $worker_name    = trim($_POST['worker_name']);
    $phone          = trim($_POST['phone']);
    $selected_areas = isset($_POST['areas']) ? $_POST['areas'] : array();

    if ($worker_name == "" || $phone == "") {

        $message      = "Please enter worker name and phone number.";
        $message_type = "error";

    } elseif (!preg_match("/^[0-9]{10,15}$/", $phone)) {

        $message      = "Please enter a valid phone number (10 to 15 digits).";
        $message_type = "error";

    } elseif (count($selected_areas) == 0) {

        $message      = "Please select at least one service area.";
        $message_type = "error";

    } else {

        // Add worker with Active status by default
        $insert_query = "INSERT INTO workers (worker_name, phone, status)
            VALUES (?, ?, 'Active')";

        $insert_stmt = mysqli_prepare($link, $insert_query);
        mysqli_stmt_bind_param($insert_stmt, "ss", $worker_name, $phone);

        if (mysqli_stmt_execute($insert_stmt)) {

            $worker_id = mysqli_insert_id($link);
            mysqli_stmt_close($insert_stmt);

            // Connect worker with selected service areas
            $area_stmt = mysqli_prepare(
            $link,
            "INSERT INTO worker_areas (worker_id, area_id)
                SELECT ?, area_id
                FROM service_areas
                WHERE area_id = ? AND status = 'active'"
            );

            $all_areas_added = true;

            foreach ($selected_areas as $area_id) {

                $area_id = (int)$area_id;

                mysqli_stmt_bind_param($area_stmt, "ii", $worker_id, $area_id);

                if (
                !mysqli_stmt_execute($area_stmt) ||
                mysqli_stmt_affected_rows($area_stmt) != 1
                ) {
                    $all_areas_added = false;
                    break;
                }
            }

            mysqli_stmt_close($area_stmt);

            if ($all_areas_added) {

                $message      = "Worker added successfully! Worker ID: " . $worker_id;
                $message_type = "success";

            } else {

                // Remove worker if all selected areas could not be saved
                mysqli_query(
                $link,
                "DELETE FROM worker_areas WHERE worker_id = " . (int)$worker_id
                );

                mysqli_query(
                $link,
                "DELETE FROM workers WHERE worker_id = " . (int)$worker_id
                );

                $message      = "Worker could not be added. Please check the selected areas.";
                $message_type = "error";
            }

        } else {

            $message      = "Unable to add worker.";
            $message_type = "error";
            mysqli_stmt_close($insert_stmt);
        }
    }
}

// Change worker status
if (isset($_POST['change_status'])) {

    $worker_id  = (int)$_POST['worker_id'];
    $new_status = $_POST['new_status'];

    // Working is controlled automatically by Start Work and Mark Work Done
    $allowed_statuses = array('Active', 'On Leave', 'Absent', 'Inactive');

    if (!in_array($new_status, $allowed_statuses, true)) {

        $message      = "Invalid worker status.";
        $message_type = "error";

    } else {

        // Check the worker's current status
        $check_stmt = mysqli_prepare(
        $link,
        "SELECT status FROM workers WHERE worker_id = ?"
        );

        mysqli_stmt_bind_param($check_stmt, "i", $worker_id);
        mysqli_stmt_execute($check_stmt);

        $worker = mysqli_fetch_assoc(mysqli_stmt_get_result($check_stmt));
        mysqli_stmt_close($check_stmt);

        if (!$worker) {

            $message      = "Worker not found.";
            $message_type = "error";

        } elseif ($worker['status'] == 'Working') {

            $message      = "A worker who is currently working cannot have their status changed.";
            $message_type = "error";

        } else {

            // Do not change status while the worker has an assigned or ongoing job
            $assignment_stmt = mysqli_prepare(
            $link,
            "SELECT COUNT(*) AS total
                FROM worker_assignments
                WHERE worker_id = ?
                AND status IN ('Assigned', 'Working')"
            );

            mysqli_stmt_bind_param($assignment_stmt, "i", $worker_id);
            mysqli_stmt_execute($assignment_stmt);

            $assignment_data = mysqli_fetch_assoc(
            mysqli_stmt_get_result($assignment_stmt)
            );

            mysqli_stmt_close($assignment_stmt);

            if ((int)$assignment_data['total'] > 0) {

                $message      = "This worker has an active booking assignment. Complete or release that assignment before changing the status.";
                $message_type = "error";

            } else {

                $update_stmt = mysqli_prepare(
                $link,
                "UPDATE workers SET status = ? WHERE worker_id = ?"
                );

                mysqli_stmt_bind_param($update_stmt, "si", $new_status, $worker_id);

                if (mysqli_stmt_execute($update_stmt)) {

                    $message      = "Worker status updated successfully!";
                    $message_type = "success";

                } else {

                    $message      = "Unable to update worker status.";
                    $message_type = "error";
                }

                mysqli_stmt_close($update_stmt);
            }
        }
    }
}

// Get all workers and their service areas
$worker_query = "
    SELECT
    w.worker_id,
    w.worker_name,
    w.phone,
    w.status,
    GROUP_CONCAT(
    DISTINCT CONCAT(sa.area_name, ' - ', sa.pincode)
    ORDER BY sa.area_name
    SEPARATOR ', '
    ) AS areas
    FROM workers w
    LEFT JOIN worker_areas wa ON w.worker_id = wa.worker_id
    LEFT JOIN service_areas sa ON wa.area_id = sa.area_id
    GROUP BY w.worker_id, w.worker_name, w.phone, w.status
    ORDER BY w.worker_id DESC
    ";

$worker_result = mysqli_query($link, $worker_query);
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Manage Workers - CleanNest</title>

        <style>
            * { box-sizing: border-box; }

            body {
                margin: 0;
                font-family: Arial, sans-serif;
                background-color: #f1f8f4;
                color: #333;
            }

            .header {
                background-color: #2e8b57;
                color: white;
                padding: 20px 30px;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .header h2 { margin: 0; }

            .header a {
                background-color: white;
                color: #2e8b57;
                text-decoration: none;
                padding: 10px 18px;
                border-radius: 5px;
                font-weight: bold;
            }

            .container {
                width: 90%;
                max-width: 1150px;
                margin: 30px auto;
            }

            .form-box, .table-box {
                background: white;
                padding: 25px;
                border-radius: 10px;
                margin-bottom: 25px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            }

            h3 { color: #2e8b57; margin-top: 0; }

            label {
                display: block;
                margin: 15px 0 8px;
                font-weight: bold;
            }

            input[type="text"] {
                width: 100%;
                padding: 12px;
                border: 1px solid #ccc;
                border-radius: 5px;
                font-size: 15px;
            }

            .area-list {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 12px;
                margin-top: 12px;
            }

            .area-option {
                background: #f1f8f4;
                border: 1px solid #ddd;
                padding: 12px;
                border-radius: 5px;
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .area-option label {
                margin: 0;
                font-weight: normal;
                cursor: pointer;
            }

            button {
                border: none;
                padding: 11px 18px;
                border-radius: 5px;
                cursor: pointer;
                font-size: 14px;
                font-weight: bold;
            }

            .add-btn, .activate-btn {
                background: #2e8b57;
                color: white;
            }

            .add-btn { margin-top: 20px; }
            .add-btn:hover, .activate-btn:hover { background: #246b45; }

            .message {
                padding: 12px;
                margin-bottom: 20px;
                border-radius: 5px;
            }

            .success { background: #d4edda; color: #155724; }
            .error { background: #f8d7da; color: #721c24; }

            table {
                width: 100%;
                border-collapse: collapse;
            }

            th, td {
                padding: 13px;
                border-bottom: 1px solid #ddd;
                text-align: left;
            }

            th {
                background: #e5f3e9;
                color: #245c3c;
            }

            .status-active { color: #16813d; font-weight: bold; }
            .status-working { color: #d18a00; font-weight: bold; }
            .status-inactive { color: #d13b3b; font-weight: bold; }
            .status-on-leave { color: #8e44ad; font-weight: bold; }
            .status-absent { color: #c0392b; font-weight: bold; }

            .status-select {
                padding: 8px;
                border: 1px solid #ccc;
                border-radius: 5px;
                margin-bottom: 7px;
                width: 125px;
            }

            .empty-message {
                text-align: center;
                padding: 20px;
                color: #777;
            }

            @media (max-width: 700px) {
                .header {
                    flex-direction: column;
                    gap: 15px;
                    text-align: center;
                }

                .container { width: 95%; }
                .table-box { overflow-x: auto; }
                table { min-width: 850px; }
            }
        </style>
    </head>

    <body>

        <div class="header">
            <h2>Manage Workers</h2>
            <a href="admin_dashboard.php">Back to Dashboard</a>
        </div>

        <div class="container">

            <div class="form-box">

                <h3>Add New Worker</h3>

                <?php if ($message != "") { ?>
                    <div class="message <?php echo $message_type; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php } ?>

                <?php if ($area_result && mysqli_num_rows($area_result) > 0) { ?>

                    <form method="POST">

                        <label>Worker Name:</label>
                        <input type="text" name="worker_name"
                            placeholder="Enter worker name" maxlength="100" required>

                        <label>Phone Number:</label>
                        <input type="text" name="phone"
                            placeholder="Enter phone number" maxlength="15" required>

                        <label>Select Service Areas:</label>

                        <div class="area-list">

                            <?php while ($area = mysqli_fetch_assoc($area_result)) { ?>

                                <div class="area-option">
                                    <input type="checkbox"
                                        name="areas[]"
                                        value="<?php echo (int)$area['area_id']; ?>"
                                        id="area_<?php echo (int)$area['area_id']; ?>">

                                    <label for="area_<?php echo (int)$area['area_id']; ?>">
                                        <?php
                                        echo htmlspecialchars(
                                        $area['area_name'] . " - " . $area['pincode']
                                        );
                                        ?>
                                    </label>
                                </div>

                            <?php } ?>

                        </div>

                        <button type="submit" name="add_worker" class="add-btn">
                            Add Worker
                        </button>

                    </form>

                <?php } else { ?>

                    <p>No active service areas available. Please add or activate an area first.</p>

                <?php } ?>

            </div>

            <div class="table-box">

                <h3>Worker List</h3>

                <table>
                    <tr>
                        <th>Worker ID</th>
                        <th>Worker Name</th>
                        <th>Phone</th>
                        <th>Service Areas</th>
                        <th>Status</th>
                        <th>Update Status</th>
                    </tr>

                    <?php if ($worker_result && mysqli_num_rows($worker_result) > 0) { ?>

                        <?php while ($worker = mysqli_fetch_assoc($worker_result)) { ?>

                            <tr>
                                <td><?php echo (int)$worker['worker_id']; ?></td>
                                <td><?php echo htmlspecialchars($worker['worker_name']); ?></td>
                                <td><?php echo htmlspecialchars($worker['phone']); ?></td>
                                <td>
                                    <?php
                                    echo htmlspecialchars($worker['areas'] ?? 'No areas assigned');
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    $status_class = strtolower(
                                    str_replace(' ', '-', $worker['status'])
                                    );
                                    ?>
                                    <span class="status-<?php echo $status_class; ?>">
                                        <?php echo htmlspecialchars($worker['status']); ?>
                                    </span>
                                </td>

                                <td>

                                    <?php if ($worker['status'] == "Working") { ?>

                                        <span>Currently Working</span>

                                    <?php } else { ?>

                                        <form method="POST">

                                            <input type="hidden" name="worker_id"
                                                value="<?php echo (int)$worker['worker_id']; ?>">

                                            <select name="new_status" class="status-select" required>

                                                <option value="Active"
                                                <?php echo ($worker['status'] == 'Active') ? 'selected' : ''; ?>>
                                                    Active
                                                </option>

                                                <option value="On Leave"
                                                <?php echo ($worker['status'] == 'On Leave') ? 'selected' : ''; ?>>
                                                    On Leave
                                                </option>

                                                <option value="Absent"
                                                <?php echo ($worker['status'] == 'Absent') ? 'selected' : ''; ?>>
                                                    Absent
                                                </option>

                                                <option value="Inactive"
                                                <?php echo ($worker['status'] == 'Inactive') ? 'selected' : ''; ?>>
                                                    Inactive
                                                </option>

                                            </select>

                                            <button type="submit" name="change_status"
                                                class="activate-btn">
                                                Update Status
                                            </button>

                                        </form>

                                    <?php } ?>

                                </td>
                            </tr>

                        <?php } ?>

                    <?php } else { ?>

                        <tr>
                            <td colspan="6" class="empty-message">
                                No workers added yet.
                            </td>
                        </tr>

                    <?php } ?>

                </table>

            </div>
        </div>

    </body>
</html>
