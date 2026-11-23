<?php
// Start only the admin session
session_name("CLEAN_NEST_ADMIN");
session_start();
require_once "../config.php";

// Check admin login
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: admin_login.php");
    exit();
}
$message = "";
$error   = "";

// Add new package
if (isset($_POST['add_package'])) {
    $package_name = trim($_POST['package_name']);
    $bhk_type     = (int)$_POST['bhk_type'];
    $description  = trim($_POST['description']);
    $price        = $_POST['price'];
    if ($package_name == "" || $bhk_type < 1 || $bhk_type > 4 ||
    !is_numeric($price) || $price < 0) {
        $error = "Please enter valid package details.";
    } else {
        $stmt = $link->prepare(
        "INSERT INTO bhk_packages
            (package_name, bhk_type, price, description, status)
            VALUES (?, ?, ?, ?, 'active')"
        );
        $stmt->bind_param("sids", $package_name, $bhk_type, $price, $description);
        if ($stmt->execute()) {
            $message = "Package added successfully.";
        } elseif ($stmt->errno == 1062) {
            $error = "A package for this BHK type already exists.";
        } else {
            $error = "Unable to add package.";
        }
        $stmt->close();
    }
}
// Update package
if (isset($_POST['update_package'])) {
    $package_id   = (int)$_POST['package_id'];
    $package_name = trim($_POST['package_name']);
    $bhk_type     = (int)$_POST['bhk_type'];
    $description  = trim($_POST['description']);
    $price        = $_POST['price'];
    if ($package_name == "" || $bhk_type < 1 || $bhk_type > 4 ||
    !is_numeric($price) || $price < 0) {
        $error = "Please enter valid package details.";
    } else {
        $stmt = $link->prepare(
        "UPDATE bhk_packages
            SET package_name = ?, bhk_type = ?, price = ?, description = ?
            WHERE package_id = ?"
        );
        $stmt->bind_param("sidsi", $package_name, $bhk_type, $price, $description, $package_id);

        if ($stmt->execute()) {
            $message = "Package updated successfully.";
        } elseif ($stmt->errno == 1062) {
            $error = "A package for this BHK type already exists.";
        } else {
            $error = "Unable to update package.";
        }
        $stmt->close();
    }
}

// Activate / deactivate package
if (isset($_POST['change_status'])) {
    $package_id = (int)$_POST['package_id'];
    $new_status = $_POST['new_status'];
    if (in_array($new_status, ['active', 'inactive'], true)) {
        $stmt = $link->prepare(
        "UPDATE bhk_packages SET status = ? WHERE package_id = ?"
        );
        $stmt->bind_param("si", $new_status, $package_id);

        if ($stmt->execute()) {
            $message = "Package status updated successfully.";
        } else {
            $error = "Unable to update package status.";
        }

        $stmt->close();
    }
}

// Get all packages
$packages = $link->query(
"SELECT package_id, package_name, bhk_type, price, description, status
    FROM bhk_packages
    ORDER BY bhk_type ASC"
);
if (!$packages) {
    die("Unable to load packages: " . $link->error);
}
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Manage Packages - CleanNest</title>
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

            /* Top sea-green bar */

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
                font-size: 15px;
                padding: 9px 16px;
                border: 1px solid rgba(255,255,255,0.5);
                border-radius: 6px;
                transition: 0.3s;
            }

            .back-link:hover {
                background: white;
                color: #16866f;
            }

            .package-page {
                width: 92%;
                max-width: 1250px;
                margin: 35px auto;
            }

            .page-heading {
                margin-bottom: 25px;
            }

            .page-heading h1 {
                font-size: 29px;
                color: #184d40;
                margin-bottom: 8px;
            }

            .page-heading p {
                color: #71817d;
                font-size: 14px;
            }

            /* Messages */
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

            /* Cards */
            .package-form,
            .package-table-container {
                background: white;
                padding: 25px;
                border-radius: 12px;
                margin-bottom: 28px;
                box-shadow: 0 3px 14px rgba(0,0,0,0.05);
                border: 1px solid #e5eeeb;
            }

            .section-heading {
                font-size: 20px;
                color: #184d40;
                margin-bottom: 22px;
            }

            /* Add package form */
            .add-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 18px 22px;
            }

            .form-group {
                display: flex;
                flex-direction: column;
            }

            .full-width {
                grid-column: 1 / -1;
            }

            label {
                font-size: 14px;
                font-weight: 600;
                margin-bottom: 8px;
                color: #425b55;
            }
            input,
            textarea,
            select {
                width: 100%;
                padding: 11px 12px;
                border: 1px solid #d5e1dc;
                border-radius: 6px;
                font-family: Arial, sans-serif;
                font-size: 13px;
                outline: none;
                background: white;
                color: #263b38;
            }

            input:focus,
            textarea:focus,
            select:focus {
                border-color: #16866f;
                box-shadow: 0 0 0 2px rgba(22,134,111,0.10);
            }

            textarea {
                resize: vertical;
                min-height: 65px;
            }

            /* Buttons */
            button {
                border: none;
                border-radius: 6px;
                padding: 10px 14px;
                font-size: 13px;
                font-weight: bold;
                cursor: pointer;
                transition: 0.2s;
            }

            .add-btn,
            .save-btn,
            .activate-btn {
                background: #16866f;
                color: white;
            }

            .add-btn {
                margin-top: 20px;
                padding: 12px 23px;
            }

            .add-btn:hover,
            .save-btn:hover,
            .activate-btn:hover {
                background: #106b59;
            }

            .deactivate-btn {
                background: #fff0f0;
                color: #c0392b;
                border: 1px solid #f0cccc;
            }

            .deactivate-btn:hover {
                background: #fce0e0;
            }

            /* Package table */
            .table-wrapper {
                width: 100%;
                overflow-x: auto;
            }

            .package-table {
                width: 100%;
                min-width: 1050px;
                border-collapse: collapse;
                table-layout: fixed;
            }

            .package-table th {
                background: #e8f4f0;
                color: #28594d;
                padding: 15px 12px;
                text-align: left;
                font-size: 13px;
                border-bottom: 2px solid #d4e7df;
            }

            .package-table td {
                padding: 13px 10px;
                border-bottom: 1px solid #e8eeeb;
                vertical-align: middle;
            }

            .package-table tbody tr:hover {
                background: #f8fbfa;
            }
            .package-table th:nth-child(1) { width: 6%; }
            .package-table th:nth-child(2) { width: 17%; }
            .package-table th:nth-child(3) { width: 10%; }
            .package-table th:nth-child(4) { width: 25%; }
            .package-table th:nth-child(5) { width: 11%; }
            .package-table th:nth-child(6) { width: 10%; }
            .package-table th:nth-child(7) { width: 10%; }
            .package-table th:nth-child(8) { width: 11%; }
            .package-table input,
            .package-table textarea,
            .package-table select {
                font-size: 13px;
                padding: 9px;
            }

            .package-id {
                font-weight: bold;
                color: #61736d;
            }

            .status-badge {
                display: inline-block;
                padding: 6px 10px;
                border-radius: 20px;
                font-size: 12px;
                font-weight: bold;
            }

            .status-active {
                background: #e3f6eb;
                color: #18804c;
            }

            .status-inactive {
                background: #fff0ed;
                color: #c0392b;
            }

            .save-btn,
            .activate-btn,
            .deactivate-btn {
                width: 100%;
            }

            .empty-message {
                text-align: center;
                padding: 25px;
                color: #71817d;
            }

            @media (max-width: 700px) {
                .package-page {
                    width: 95%;
                    margin: 22px auto;
                }

                .package-form,
                .package-table-container {
                    padding: 17px;
                }

                .add-grid {
                    grid-template-columns: 1fr;
                }

                .full-width {
                    grid-column: auto;
                }
            }
        </style>
    </head>
    <body>
        <header>
            <div class="topbar">
                <h2>Manage Packages</h2>
                <a href="admin_dashboard.php" class="back-btn">
                    Back to Dashboard
                </a>
            </div>
        </header>
        <main class="package-page">
            <div class="page-heading">
                <h1>Manage Packages</h1>
                <p>Add, update and manage the BHK cleaning packages offered by CleanNest.</p>
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

            <!-- Add New Package -->
            <section class="package-form">
                <h2 class="section-heading">Add New Package</h2>
                <form method="POST">
                    <div class="add-grid">
                        <div class="form-group">
                            <label>Package Name</label>
                            <input type="text" name="package_name"
                                maxlength="50" placeholder="Example: 2 BHK Package" required>
                        </div>
                        <div class="form-group">
                            <label>BHK Type</label>
                            <select name="bhk_type" required>
                                <option value="">Select BHK Type</option>
                                <option value="1">1 BHK</option>
                                <option value="2">2 BHK</option>
                                <option value="3">3 BHK</option>
                                <option value="4">4 BHK</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Price (₹)</label>
                            <input type="number" name="price"
                                min="0" step="0.01" placeholder="Enter package price" required>
                        </div>

                        <div class="form-group full-width">
                            <label>Description</label>
                            <textarea name="description" maxlength="500"
                                placeholder="Enter package description">
                            </textarea>
                        </div>
                    </div>
                    <button type="submit" name="add_package" class="add-btn">
                        + Add Package
                    </button>
                </form>
            </section>
            <!-- Existing Packages -->
            <section class="package-table-container">
                <h2 class="section-heading">Existing Packages</h2>
                <div class="table-wrapper">
                    <table class="package-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Package Name</th>
                                <th>BHK Type</th>
                                <th>Description</th>
                                <th>Price (₹)</th>
                                <th>Status</th>
                                <th>Save</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($packages->num_rows > 0): ?>
                                <?php while ($package = $packages->fetch_assoc()): ?>
                                <?php $form_id = "edit_package_" . (int)$package['package_id']; ?>
                                <tr>
                                    <td class="package-id">
                                        #<?php echo (int)$package['package_id']; ?>
                                        <form id="<?php echo $form_id; ?>" method="POST"></form>
                                    </td>
                                    <td>
                                        <input form="<?php echo $form_id; ?>"
                                            type="hidden" name="package_id"
                                            value="<?php echo (int)$package['package_id']; ?>">

                                        <input form="<?php echo $form_id; ?>"
                                            type="text" name="package_name"
                                            maxlength="50" required
                                            value="<?php echo htmlspecialchars($package['package_name']); ?>">
                                    </td>
                                    <td>
                                        <select form="<?php echo $form_id; ?>"
                                            name="bhk_type" required>
                                            <option value="1" <?php echo ((int)$package['bhk_type'] == 1) ? 'selected' : ''; ?>>1 BHK</option>
                                            <option value="2" <?php echo ((int)$package['bhk_type'] == 2) ? 'selected' : ''; ?>>2 BHK</option>
                                            <option value="3" <?php echo ((int)$package['bhk_type'] == 3) ? 'selected' : ''; ?>>3 BHK</option>
                                            <option value="4" <?php echo ((int)$package['bhk_type'] == 4) ? 'selected' : ''; ?>>4 BHK</option>
                                        </select>
                                    </td>
                                    <td>
                                        <textarea form="<?php echo $form_id; ?>"
                                            name="description" maxlength="500"><?php echo htmlspecialchars($package['description'] ?? ''); ?>
                                        </textarea>
                                    </td>
                                    <td>
                                        <input form="<?php echo $form_id; ?>"
                                            type="number" name="price"
                                            min="0" step="0.01" required
                                            value="<?php echo htmlspecialchars($package['price']); ?>">
                                    </td>
                                    <td>
                                        <?php if ($package['status'] == 'active'): ?>
                                            <span class="status-badge status-active">Active</span>
                                        <?php else: ?>
                                            <span class="status-badge status-inactive">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button form="<?php echo $form_id; ?>"
                                            type="submit" name="update_package"
                                            class="save-btn">
                                            Save Changes
                                        </button>
                                    </td>
                                    <td>
                                        <form method="POST">
                                            <input type="hidden" name="package_id"
                                                value="<?php echo (int)$package['package_id']; ?>">
                                            <?php if ($package['status'] == 'active'): ?>
                                                <input type="hidden" name="new_status"
                                                    value="inactive">
                                                <button type="submit" name="change_status"
                                                    class="deactivate-btn">
                                                    Deactivate
                                                </button>
                                            <?php else: ?>
                                                <input type="hidden" name="new_status"
                                                    value="active">
                                                <button type="submit" name="change_status"
                                                    class="activate-btn">
                                                    Activate
                                                </button>
                                            <?php endif; ?>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                            <?php else: ?>
                            <tr>
                                <td colspan="8" class="empty-message">
                                    No packages found.
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
