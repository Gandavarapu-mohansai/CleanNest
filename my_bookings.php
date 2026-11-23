<?php
require_once "customer_session.php";
include "config.php";

// Check customer login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

// Get all bookings and their payment details
$query = "SELECT
    b.booking_id,
    b.booking_date,
    b.booking_time,
    b.total_price,
    b.status,
    p.package_name,
    pay.payment_method,
    pay.payment_status
    FROM bookings b
    LEFT JOIN bhk_packages p
    ON b.package_id = p.package_id
    LEFT JOIN payments pay
    ON b.booking_id = pay.booking_id
    WHERE b.user_id = ?
    ORDER BY b.created_at DESC";

$stmt = mysqli_prepare($link, $query);

mysqli_stmt_bind_param($stmt, "i", $user_id);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
?>

<!DOCTYPE html>
<html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>My Bookings - CleanNest</title>
        <link rel="stylesheet" href="style.css">
    </head>

    <body class="my-bookings-page">

        <!-- Navigation -->
        <header class="customer-header">

            <a href="index.html" class="logo">
                <img src="images/cleannest-logo.png" alt="CleanNest Logo">
            </a>

            <nav>
                <a href="customer_dashboard.php">Dashboard</a>
                <a href="services.php">Services</a>
                <a href="my_profile.php">My Profile</a>
                <a href="logout.php">Logout</a>
            </nav>

        </header>

        <!-- My Bookings Section -->
        <section class="my-bookings-container">

            <h1>My Bookings</h1>

            <?php
            if (isset($_SESSION['cancel_message'])) {
                echo "<p>" . htmlspecialchars($_SESSION['cancel_message']) . "</p>";
                unset($_SESSION['cancel_message']);
            }
            ?>

            <p>Here you can view all your cleaning service bookings.</p>

            <?php if (mysqli_num_rows($result) > 0) { ?>

                <div class="booking-table-container">

                    <table>

                        <tr>
                            <th>Booking ID</th>
                            <th>Package</th>
                            <th>Booking Date</th>
                            <th>Booking Time</th>
                            <th>Total Amount</th>
                            <th>Booking Status</th>
                            <th>Payment Status</th>
                            <th>Action</th>
                        </tr>

                        <?php while ($booking = mysqli_fetch_assoc($result)) { ?>

                            <tr>

                                <td>
                                    #<?php echo $booking['booking_id']; ?>
                                </td>

                                <td>
                                    <?php
                                    echo !empty($booking['package_name'])
                                    ? htmlspecialchars($booking['package_name'])
                                    : "Individual Services";
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    echo date(
                                    "d-m-Y",
                                    strtotime($booking['booking_date'])
                                    );
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    echo date(
                                    "h:i A",
                                    strtotime($booking['booking_time'])
                                    );
                                    ?>
                                </td>

                                <td>
                                    ₹<?php echo number_format($booking['total_price'], 2); ?>
                                </td>

                                <!-- Booking Status -->
                                <td>
                                    <span class="booking-status">
                                        <?php
                                        echo htmlspecialchars(
                                        ucwords(str_replace('_', ' ', $booking['status']))
                                        );
                                        ?>
                                    </span>
                                </td>

                                <!-- Payment Status -->
                                <td>

                                    <?php if ($booking['payment_status'] === 'rejected') { ?>

                                        <span style="color:#b42318; font-weight:bold;">
                                            Payment Rejected
                                        </span>

                                    <?php } elseif ($booking['payment_status'] === 'verification_pending') { ?>

                                        <span style="color:#b7791f; font-weight:bold;">
                                            Verification Pending
                                        </span>

                                    <?php } elseif ($booking['payment_status'] === 'paid') { ?>

                                        <span style="color:green; font-weight:bold;">
                                            Paid
                                        </span>

                                    <?php } elseif ($booking['payment_status'] === 'pending') { ?>

                                        <span style="color:#b7791f; font-weight:bold;">
                                            Payment Pending
                                        </span>

                                    <?php } else { ?>

                                        <span>Not Available</span>

                                    <?php } ?>

                                </td>

                                <!-- Actions -->
                                <td>

                                    <a class="view-booking-btn"
                                        href="booking_details.php?booking_id=<?php echo $booking['booking_id']; ?>">
                                        View Details
                                    </a>

                                    <?php if ($booking['status'] == 'pending') { ?>

                                        <form method="POST"
                                            action="cancel_booking.php"
                                            onsubmit="return confirm('Are you sure you want to cancel this booking?');">

                                            <input type="hidden"
                                                name="booking_id"
                                                value="<?php echo $booking['booking_id']; ?>">

                                            <button type="submit" class="cancel-booking-btn">
                                                Cancel Booking
                                            </button>

                                        </form>

                                    <?php } ?>

                                </td>

                            </tr>

                        <?php } ?>

                    </table>

                </div>

            <?php } else { ?>

                <div class="no-bookings">

                    <h3>No Bookings Found</h3>

                    <p>You have not booked any cleaning services yet.</p>

                    <a href="services.php">Explore Services</a>

                </div>

            <?php } ?>

            <br>

            <a href="customer_dashboard.php" class="back-dashboard-btn">
                Back to Dashboard
            </a>

        </section>

    </body>
</html>

<?php
mysqli_stmt_close($stmt);
?>
