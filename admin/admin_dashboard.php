<?php
// Start only the admin session
session_name("CLEAN_NEST_ADMIN");
session_start();

require_once "../config.php";

// Check whether admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: admin_login.php");
    exit();
}

// Get dashboard statistics
// Total customers
$customer_query  = "SELECT COUNT(*) AS total FROM users WHERE role = 'customer'";
$customer_result = mysqli_query($link, $customer_query);
$customer_data   = mysqli_fetch_assoc($customer_result);
$total_customers = $customer_data['total'];

// Total services
$service_query  = "SELECT COUNT(*) AS total FROM services";
$service_result = mysqli_query($link, $service_query);
$service_data   = mysqli_fetch_assoc($service_result);
$total_services = $service_data['total'];

// Total bookings
$booking_query  = "SELECT COUNT(*) AS total FROM bookings";
$booking_result = mysqli_query($link, $booking_query);
$booking_data   = mysqli_fetch_assoc($booking_result);
$total_bookings = $booking_data['total'];

// Pending bookings
$pending_query    = "SELECT COUNT(*) AS total FROM bookings WHERE status = 'pending'";
$pending_result   = mysqli_query($link, $pending_query);
$pending_data     = mysqli_fetch_assoc($pending_result);
$pending_bookings = $pending_data['total'];

// Completed services
$completed_query    = "SELECT COUNT(*) AS total FROM bookings WHERE status = 'completed'";
$completed_result   = mysqli_query($link, $completed_query);
$completed_data     = mysqli_fetch_assoc($completed_result);
$completed_services = $completed_data['total'];

// Total Payments
$total_payments_query  = "SELECT COUNT(*) AS total FROM payments";
$total_payments_result = mysqli_query($link, $total_payments_query);
$total_payments_data   = mysqli_fetch_assoc($total_payments_result);
$total_payments        = $total_payments_data['total'];


// Pending Payments
$pending_payments_query  = "SELECT COUNT(*) AS total FROM payments WHERE payment_status = 'pending'";
$pending_payments_result = mysqli_query($link, $pending_payments_query);
$pending_payments_data   = mysqli_fetch_assoc($pending_payments_result);
$pending_payments        = $pending_payments_data['total'];


// Completed Payments
$completed_payments_query  = "SELECT COUNT(*) AS total FROM payments WHERE payment_status = 'paid'";
$completed_payments_result = mysqli_query($link, $completed_payments_query);
$completed_payments_data   = mysqli_fetch_assoc($completed_payments_result);
$completed_payments        = $completed_payments_data['total'];

// Get the latest 5 bookings
$recent_query = "
    SELECT
    b.booking_id,
    u.first_name,
    u.last_name,
    u.phone,
    b.booking_date,
    b.booking_time,
    b.total_price,
    b.status,
    p.package_name
    FROM bookings b
    INNER JOIN users u ON b.user_id = u.user_id
    LEFT JOIN bhk_packages p ON b.package_id = p.package_id
    ORDER BY b.created_at DESC
    LIMIT 5
    ";
$recent_result = mysqli_query($link, $recent_query);
if (!$recent_result) {
    die("Recent bookings query failed: " . mysqli_error($link));
}
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Dashboard - CleanNest</title>
        <link rel="stylesheet" href="admin_style.css">
    </head>
    <body class="admin-dashboard">

        <!-- Admin Header -->
        <header class="admin-header">
            <h2>CleanNest Admin</h2>
            <div>
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></span>
                <a href="admin_logout.php" class="logout-btn">Logout</a>
            </div>
        </header>

        <!-- Dashboard Content -->
        <main class="dashboard-container">
            <h1>Admin Dashboard</h1>
            <p class="dashboard-subtitle">Manage your CleanNest home cleaning services.</p>

            <!-- Statistics Cards -->
            <div class="stats-container">
                <div class="stat-card">
                    <h3>Total Customers</h3>
                    <h2><?php echo $total_customers; ?></h2>
                </div>
                <div class="stat-card">
                    <h3>Total Services</h3>
                    <h2><?php echo $total_services; ?></h2>
                </div>
                <div class="stat-card">
                    <h3>Total Bookings</h3>
                    <h2><?php echo $total_bookings; ?></h2>
                </div>
                <div class="stat-card">
                    <h3>Pending Bookings</h3>
                    <h2><?php echo $pending_bookings; ?></h2>
                </div>
                <div class="stat-card">
                    <h3>Completed Services</h3>
                    <h2><?php echo $completed_services; ?></h2>
                </div>

                <div class="stat-card">
                    <h3>Total Payments</h3>
                    <h2><?php echo $total_payments; ?></h2>
                </div>


                <div class="stat-card">
                    <h3>Pending Payments</h3>
                    <h2><?php echo $pending_payments; ?></h2>
                </div>


                <div class="stat-card">
                    <h3>Completed Payments</h3>
                    <h2><?php echo $completed_payments; ?></h2>
                </div>

            </div>

            <!-- Admin Management Options -->
            <h2 class="section-title">Management</h2>
            <div class="management-container">
                <a href="manage_services.php" class="management-card">
                    <h3>Manage Services</h3>
                    <p>Add, edit and manage cleaning services.</p>
                </a>
                <a href="manage_packages.php" class="management-card">
                    <h3>Manage Packages</h3>
                    <p>View and manage cleaning packages.</p>
                </a>
                <a href="manage_bookings.php" class="management-card">
                    <h3>Manage Bookings</h3>
                    <p>View bookings and update their status.</p>
                </a>
                <a href="manage_customers.php" class="management-card">
                    <h3>Manage Customers</h3>
                    <p>View registered customer details.</p>
                </a>
                <a href="manage_messages.php" class="management-card">
                    <h3>Contact Messages</h3>
                    <p>View messages received from customers.</p>
                </a>
                <a href="manage_reviews.php" class="management-card">
                    <h3>Manage Reviews</h3>
                    <p>View customer feedback and reviews.</p>
                </a>
                <a href="manage_payments.php" class="management-card">
                    <h3>Manage Payments</h3>
                    <p>View customer payments and verify UPI transactions.</p>
                </a>
            </div>

            <!-- Recent Bookings Section -->
            <div class="recent-bookings">
                <h2 class="section-title">Recent Bookings</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Booking ID</th>
                                <th>Customer Name</th>
                                <th>Phone</th>
                                <th>Booking Date & Time</th>
                                <th>Package</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($recent_result) > 0) { ?>
                                <?php while ($booking = mysqli_fetch_assoc($recent_result)) { ?>
                                    <tr>
                                        <td><?php echo $booking['booking_id']; ?></td>
                                        <td>
                                            <?php
                                            echo htmlspecialchars($booking['first_name'] . " " . $booking['last_name']);
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($booking['phone']); ?></td>
                                        <td>
                                            <?php
                                            echo date("d-m-Y", strtotime($booking['booking_date']));
                                            echo "<br>";
                                            echo date("h:i A", strtotime($booking['booking_time']));
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                            echo $booking['package_name']
                                            ? htmlspecialchars($booking['package_name'])
                                            : "Individual Services";
                                            ?>
                                        </td>
                                        <td>₹<?php echo number_format($booking['total_price'], 2); ?></td>

                                        <td>
                                            <span class="booking-status">
                                                <?php echo htmlspecialchars(ucfirst($booking['status'])); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php } ?>
                            <?php } else { ?>
                                <tr>
                                    <td colspan="7">No bookings available.</td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </body>
</html>