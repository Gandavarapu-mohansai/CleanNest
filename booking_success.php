<?php
require_once "customer_session.php";

include "config.php";

// Check customer login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get booking ID from URL
$booking_id = isset($_GET['booking_id'])
? intval($_GET['booking_id'])
: 0;

// Variables
$booking          = null;
$booking_services = [];
$payment          = null;

// Check valid booking ID
if ($booking_id > 0) {

    // Get booking details
    $query = "SELECT
        b.booking_id, b.booking_date, b.booking_time, b.address,
        b.total_price, b.status, u.first_name, u.last_name,
        p.package_name
        FROM bookings b
        JOIN users u ON b.user_id = u.user_id
        LEFT JOIN bhk_packages p ON b.package_id = p.package_id
        WHERE b.booking_id = ? AND b.user_id = ?";

    $stmt = mysqli_prepare($link, $query);
    mysqli_stmt_bind_param($stmt, "ii", $booking_id, $user_id);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) == 1) {
        $booking = mysqli_fetch_assoc($result);
    }

    mysqli_stmt_close($stmt);

    if ($booking !== null) {

        // Get individually selected services
        $query = "SELECT
            s.service_name, bs.price, bs.quantity
            FROM booking_services bs
            JOIN services s ON bs.service_id = s.service_id
            WHERE bs.booking_id = ?";

        $stmt = mysqli_prepare($link, $query);
        mysqli_stmt_bind_param($stmt, "i", $booking_id);
        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);

        while ($row = mysqli_fetch_assoc($result)) {
            $booking_services[] = $row;
        }

        mysqli_stmt_close($stmt);

        // Get payment details
        $query = "SELECT * FROM payments WHERE booking_id = ?";

        $stmt = mysqli_prepare($link, $query);
        mysqli_stmt_bind_param($stmt, "i", $booking_id);
        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($result) == 1) {
            $payment = mysqli_fetch_assoc($result);
        }

        mysqli_stmt_close($stmt);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Booking Confirmation - CleanNest</title>
        <link rel="stylesheet" href="style.css">
    </head>

    <body class="booking-success-page">

        <!-- Navigation -->
        <header class="customer-header">

            <a href="index.html" class="logo">
                <img src="images/cleannest-logo.png" alt="CleanNest Logo">
            </a>

            <nav>
                <a href="customer_dashboard.php">Dashboard</a>
                <a href="services.php">Services</a>
                <a href="my_bookings.php">My Bookings</a>
                <a href="my_profile.php">My Profile</a>
                <a href="logout.php">Logout</a>
            </nav>

        </header>

        <!-- Booking Confirmation -->

        <?php if ($booking !== null) { ?>

            <div class="success-container">

                <div class="success-icon">
                    &#10004;
                </div>

                <h1>Booking Successful!</h1>

                <p class="success-message">
                    Thank you for choosing CleanNest.
                    Your booking has been placed successfully.
                </p>

                <div class="booking-details">

                    <h2>Booking Details</h2>

                    <p>
                        <strong>Booking ID:</strong>
                        #<?php echo $booking['booking_id']; ?>
                    </p>

                    <p>
                        <strong>Customer Name:</strong>
                        <?php
                        echo htmlspecialchars(
                        $booking['first_name'] . " " . $booking['last_name']
                        );
                        ?>
                    </p>

                    <?php if (!empty($booking['package_name'])) { ?>
                        <p>
                            <strong>Selected Package:</strong>
                            <?php echo htmlspecialchars($booking['package_name']); ?>
                        </p>
                    <?php } ?>

                    <?php if (count($booking_services) > 0) { ?>

                        <h3>Selected Individual Services</h3>

                        <?php foreach ($booking_services as $service) { ?>

                            <p>
                                <?php echo htmlspecialchars($service['service_name']); ?>
                                - ₹<?php echo number_format($service['price'], 2); ?>
                                (Quantity: <?php echo $service['quantity']; ?>)
                            </p>

                        <?php } ?>

                    <?php } ?>

                    <p>
                        <strong>Booking Date:</strong>
                        <?php echo date("d-m-Y", strtotime($booking['booking_date'])); ?>
                    </p>

                    <p>
                        <strong>Booking Time:</strong>
                        <?php echo date("h:i A", strtotime($booking['booking_time'])); ?>
                    </p>

                    <p>
                        <strong>Service Address:</strong>
                        <?php echo nl2br(htmlspecialchars($booking['address'])); ?>
                    </p>

                    <p>
                        <strong>Total Amount:</strong>
                        ₹<?php echo number_format($booking['total_price'], 2); ?>
                    </p>

                    <p>
                        <strong>Booking Status:</strong>
                        <span class="booking-status">
                            <?php echo htmlspecialchars(ucfirst($booking['status'])); ?>
                        </span>
                    </p>
                </div>

                <!-- Payment Section -->
                <div class="booking-details">
                    <h2>Payment Details</h2>
                    <?php if ($payment !== null) { ?>
                        <p>
                            <strong>Payment Method:</strong>
                            <?php
                            echo $payment['payment_method'] === 'upi'
                            ? 'UPI Payment'
                            : 'Cash on Service';
                            ?>
                        </p>
                        <p>
                            <strong>Payment Amount:</strong>
                            ₹<?php echo number_format($payment['amount'], 2); ?>
                        </p>
                        <p>
                            <strong>Payment Status:</strong>
                            <?php echo htmlspecialchars(
                            ucfirst(str_replace('_', ' ', $payment['payment_status']))
                            ); ?>
                        </p>
                        <?php if ($payment['payment_method'] === 'cash_on_service') { ?>
                            <p>
                                You can pay the amount in cash after the cleaning service is completed.
                            </p>
                        <?php } elseif ($payment['payment_method'] === 'upi') { ?>
                            <h3>Pay Using UPI</h3>
                            <p>
                                <strong>CleanNest UPI ID:</strong>
                                cleannest@okaxis
                            </p>
                            <p>
                                <strong>Amount to Pay:</strong>
                                ₹<?php echo number_format($payment['amount'], 2); ?>
                            </p>
                            <p>
                                Open Google Pay, PhonePe or another UPI app and use the UPI ID above.
                            </p>
                            <p>
                                <strong>Important:</strong>
                                This is a temporary demo UPI ID and has not been verified.
                                Do not transfer real money to it.
                            </p>
                            <?php if (empty($payment['transaction_reference'])) { ?>
                                <form action="submit_payment.php" method="POST">
                                    <input type="hidden" name="booking_id"
                                        value="<?php echo $booking_id; ?>">
                                    <label for="transaction_reference">
                                        Enter UPI Transaction Reference Number:
                                    </label>
                                    <input
                                        type="text"
                                        id="transaction_reference"
                                        name="transaction_reference"
                                        maxlength="100"
                                        required
                                        placeholder="Enter transaction reference">
                                    <button type="submit" name="submit_payment">
                                        Submit Payment Reference
                                    </button>
                                </form>
                            <?php } else { ?>
                                <p>
                                    <strong>Transaction Reference:</strong>
                                    <?php echo htmlspecialchars($payment['transaction_reference']); ?>
                                </p>
                                <p>Your payment reference has been submitted for verification.</p>
                            <?php } ?>
                        <?php } ?>
                    <?php } else { ?>
                        <p>Payment details are not available for this booking.</p>
                    <?php } ?>
                </div>
                <p class="thank-you">
                    We will contact you regarding your booking.
                </p>
                <a href="customer_dashboard.php" class="dashboard-btn">
                    Back to Dashboard
                </a>
            </div>
        <?php } else { ?>
            <div class="success-container">

                <h2>Booking Not Found</h2>
                <p>
                    We could not find this booking.
                    Please check your booking details.
                </p>
                <a href="customer_dashboard.php" class="dashboard-btn">
                    Back to Dashboard
                </a>
            </div>
        <?php } ?>
    </body>
</html>
