<?php
session_name("CLEAN_NEST_ADMIN");
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: admin_login.php");
    exit();
}

require_once "../config.php";

// Get all customers
$sql = "SELECT user_id, first_name, last_name, email, phone, created_at
    FROM users
    WHERE role = 'customer'
    ORDER BY created_at DESC";
$result          = mysqli_query($link, $sql);
$total_customers = mysqli_num_rows($result);
?>

<!DOCTYPE html>
<html>
    <head>
        <title>Manage Customers - CleanNest</title>
        <style>
            * {
                box-sizing: border-box;
            }

            body {
                margin: 0;
                font-family: Arial, sans-serif;
                background: #f4f7f6;
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

            .container {
                padding: 30px;
            }

            .card {
                background: white;
                padding: 22px;
                border-radius: 8px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.08);
                margin-bottom: 20px;
            }

            .card h3 {
                margin-top: 0;
                color: #333;
            }

            .count {
                font-size: 28px;
                color: #16866f;
                font-weight: bold;
            }

            .table-container {
                overflow-x: auto;
            }

            table {
                width: 100%;
                border-collapse: collapse;
                min-width: 800px;
            }

            th, td {
                padding: 13px;
                text-align: left;
                border-bottom: 1px solid #ddd;
            }

            th {
                background: #e8f5f1;
                color: #333;
            }

            tr:hover {
                background: #f8fbfa;
            }

            .empty {
                text-align: center;
                padding: 25px;
                color: #777;
            }
            @media (max-width: 600px) {
                .container {
                    padding: 15px;
                }
                .topbar {
                    padding: 15px;
                }
            }
        </style>
    </head>
    <body>
        <div class="topbar">
            <h2>Manage Customers</h2>
            <a href="admin_dashboard.php" class="back-btn">
                Back to Dashboard
            </a>
        </div>
        <div class="container">
            <div class="card">
                <h3>Total Registered Customers</h3>
                <div class="count">
                    <?php echo $total_customers; ?>
                </div>
            </div>
            <div class="card">
                <h3>Customer Details</h3>
                <div class="table-container">
                    <table>
                        <tr>
                            <th>ID</th>
                            <th>Customer Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Registered Date</th>
                        </tr>
                        <?php if ($total_customers > 0) { ?>
                            <?php while ($customer = mysqli_fetch_assoc($result)) { ?>
                                <tr>
                                    <td>
                                        <?php echo $customer['user_id']; ?>
                                    </td>
                                    <td>
                                        <?php
                                        echo htmlspecialchars(
                                        $customer['first_name'] . " " .
                                        $customer['last_name']
                                        );
                                        ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($customer['email']); ?>
                                    </td>
                                    <td>
                                        <?php
                                        echo htmlspecialchars(
                                        $customer['phone'] ?? 'Not provided'
                                        );
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        echo date(
                                        "d-m-Y",
                                        strtotime($customer['created_at'])
                                        );
                                        ?>
                                    </td>
                                </tr>
                            <?php } ?>
                        <?php } else { ?>
                            <tr>
                                <td colspan="5" class="empty">
                                    No customers registered yet.
                                </td>
                            </tr>
                        <?php } ?>
                    </table>
                </div>
            </div>
        </div>
    </body>
</html>
