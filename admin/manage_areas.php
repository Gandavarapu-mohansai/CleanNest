<?php
// Start admin session
session_name("CLEAN_NEST_ADMIN");
session_start();

// Connect to database
require_once "../config.php";

// Check whether admin is logged in
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

// Add a new service area
if (isset($_POST['add_area'])) {

    $area_name = trim($_POST['area_name']);
    $pincode   = trim($_POST['pincode']);

    // Check whether fields are empty
    if ($area_name == "" || $pincode == "") {

        $message      = "Please fill in all fields.";
        $message_type = "error";

    } elseif (!preg_match("/^[0-9]{6}$/", $pincode)) {

        $message      = "Please enter a valid 6-digit pincode.";
        $message_type = "error";

    } else {

        // Check whether the area already exists
        $check_query = "SELECT area_id FROM service_areas
            WHERE area_name = ? AND pincode = ?";

        $check_stmt = mysqli_prepare($link, $check_query);
        mysqli_stmt_bind_param($check_stmt, "ss", $area_name, $pincode);
        mysqli_stmt_execute($check_stmt);

        $check_result = mysqli_stmt_get_result($check_stmt);

        if (mysqli_num_rows($check_result) > 0) {

            $message      = "This service area already exists.";
            $message_type = "error";

        } else {

            // Insert the new area
            $insert_query = "INSERT INTO service_areas
                (area_name, pincode, status)
                VALUES (?, ?, 'active')";

            $insert_stmt = mysqli_prepare($link, $insert_query);
            mysqli_stmt_bind_param($insert_stmt, "ss", $area_name, $pincode);

            if (mysqli_stmt_execute($insert_stmt)) {

                $message      = "Service area added successfully!";
                $message_type = "success";

            } else {

                $message      = "Unable to add service area.";
                $message_type = "error";
            }

            mysqli_stmt_close($insert_stmt);
        }

        mysqli_stmt_close($check_stmt);
    }
}

// Activate or deactivate an area
if (isset($_POST['change_status'])) {

    $area_id    = (int)$_POST['area_id'];
    $new_status = $_POST['new_status'];

    if ($new_status == "active" || $new_status == "inactive") {

        $update_query = "UPDATE service_areas
            SET status = ?
            WHERE area_id = ?";

        $update_stmt = mysqli_prepare($link, $update_query);
        mysqli_stmt_bind_param($update_stmt, "si", $new_status, $area_id);

        if (mysqli_stmt_execute($update_stmt)) {

            $message      = "Service area status updated successfully!";
            $message_type = "success";

        } else {

            $message      = "Unable to update service area status.";
            $message_type = "error";
        }

        mysqli_stmt_close($update_stmt);
    }
}

// Get all service areas
$area_query = "SELECT * FROM service_areas
    ORDER BY area_name ASC";

$area_result = mysqli_query($link, $area_query);

?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>Manage Service Areas - CleanNest</title>

        <style>
            * {
                box-sizing: border-box;
            }

            body {
                margin: 0;
                padding: 0;
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

            .header h2 {
                margin: 0;
            }

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
                max-width: 1100px;
                margin: 30px auto;
            }

            .form-box, .table-box {
                background-color: white;
                padding: 25px;
                border-radius: 10px;
                margin-bottom: 25px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            }

            h3 {
                color: #2e8b57;
                margin-top: 0;
            }

            label {
                display: block;
                margin-top: 15px;
                margin-bottom: 8px;
                font-weight: bold;
            }

            input {
                width: 100%;
                padding: 12px;
                border: 1px solid #ccc;
                border-radius: 5px;
                font-size: 15px;
            }

            button {
                border: none;
                padding: 11px 18px;
                border-radius: 5px;
                cursor: pointer;
                font-size: 14px;
                font-weight: bold;
            }

            .add-btn {
                background-color: #2e8b57;
                color: white;
                margin-top: 20px;
            }

            .add-btn:hover {
                background-color: #246b45;
            }

            .message {
                padding: 12px;
                margin-bottom: 20px;
                border-radius: 5px;
            }

            .success {
                background-color: #d4edda;
                color: #155724;
            }

            .error {
                background-color: #f8d7da;
                color: #721c24;
            }

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
                background-color: #e5f3e9;
                color: #245c3c;
            }

            .active {
                color: #16813d;
                font-weight: bold;
            }

            .inactive {
                color: #d13b3b;
                font-weight: bold;
            }

            .activate-btn {
                background-color: #2e8b57;
                color: white;
            }

            .deactivate-btn {
                background-color: #dc3545;
                color: white;
            }

            .empty-message {
                text-align: center;
                padding: 20px;
                color: #777;
            }

            @media (max-width: 650px) {
                .header {
                    flex-direction: column;
                    gap: 15px;
                    text-align: center;
                }

                .container {
                    width: 95%;
                }

                .table-box {
                    overflow-x: auto;
                }

                table {
                    min-width: 550px;
                }
            }
        </style>
    </head>

    <body>

        <div class="header">
            <h2>Manage Service Areas</h2>
            <a href="admin_dashboard.php">Back to Dashboard</a>
        </div>

        <div class="container">

            <!-- Add Service Area Form -->
            <div class="form-box">

                <h3>Add New Service Area</h3>

                <?php if ($message != "") { ?>
                    <div class="message <?php echo $message_type; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php } ?>

                <form method="POST">

                    <label>Area / Locality Name:</label>
                    <input
                        type="text"
                        name="area_name"
                        placeholder="Example: Madhapur"
                        required
                    >

                    <label>Pincode:</label>
                    <input
                        type="text"
                        name="pincode"
                        placeholder="Example: 500081"
                        maxlength="6"
                        pattern="[0-9]{6}"
                        required
                    >

                    <button type="submit" name="add_area" class="add-btn">
                        Add Service Area
                    </button>

                </form>

            </div>

            <!-- Display Service Areas -->
            <div class="table-box">

                <h3>Service Area List</h3>

                <table>

                    <tr>
                        <th>Area ID</th>
                        <th>Area Name</th>
                        <th>Pincode</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>

                    <?php if ($area_result && mysqli_num_rows($area_result) > 0) { ?>

                        <?php while ($area = mysqli_fetch_assoc($area_result)) { ?>

                            <tr>

                                <td>
                                    <?php echo (int)$area['area_id']; ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($area['area_name']); ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($area['pincode']); ?>
                                </td>

                                <td>
                                    <span class="<?php echo $area['status']; ?>">
                                        <?php echo ucfirst($area['status']); ?>
                                    </span>
                                </td>

                                <td>

                                    <form method="POST">

                                        <input
                                            type="hidden"
                                            name="area_id"
                                            value="<?php echo (int)$area['area_id']; ?>"
                                        >

                                        <?php if ($area['status'] == 'active') { ?>

                                            <input
                                                type="hidden"
                                                name="new_status"
                                                value="inactive"
                                            >

                                            <button
                                                type="submit"
                                                name="change_status"
                                                class="deactivate-btn"
                                            >
                                                Deactivate
                                            </button>

                                        <?php } else { ?>

                                            <input
                                                type="hidden"
                                                name="new_status"
                                                value="active"
                                            >

                                            <button
                                                type="submit"
                                                name="change_status"
                                                class="activate-btn"
                                            >
                                                Activate
                                            </button>

                                        <?php } ?>

                                    </form>

                                </td>

                            </tr>

                        <?php } ?>

                    <?php } else { ?>

                        <tr>
                            <td colspan="5" class="empty-message">
                                No service areas added yet.
                            </td>
                        </tr>

                    <?php } ?>
                </table>
            </div>
        </div>
    </body>
</html>
