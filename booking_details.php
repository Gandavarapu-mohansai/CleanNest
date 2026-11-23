<?php
require_once "customer_session.php";
include "config.php";

// Check customer login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

// Get booking ID from URL
$booking_id = isset($_GET['booking_id'])
? (int)$_GET['booking_id']
: 0;

$booking             = null;
$payment             = null;
$package_services    = [];
$individual_services = [];

// Get booking details
if ($booking_id > 0) {

    $query = "SELECT
        b.*,
        p.package_name,
        u.first_name,
        u.last_name
        FROM bookings b
        LEFT JOIN bhk_packages p
        ON b.package_id = p.package_id
        JOIN users u
        ON b.user_id = u.user_id
        WHERE b.booking_id = ?
        AND b.user_id = ?";

    $stmt = mysqli_prepare($link, $query);
    mysqli_stmt_bind_param($stmt, "ii", $booking_id, $user_id);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) == 1) {
        $booking = mysqli_fetch_assoc($result);
    }

    mysqli_stmt_close($stmt);

    // Get payment details
    if ($booking !== null) {

        $query = "SELECT
            payment_id,
            payment_method,
            amount,
            payment_status,
            transaction_reference,
            rejection_reason
            FROM payments
            WHERE booking_id = ?";

        $stmt = mysqli_prepare($link, $query);
        mysqli_stmt_bind_param($stmt, "i", $booking_id);
        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($result) == 1) {
            $payment = mysqli_fetch_assoc($result);
        }

        mysqli_stmt_close($stmt);
    }

    // Get services included in the package
    if ($booking !== null && !empty($booking['package_id'])) {

        $query = "SELECT
            s.service_name,
            ps.quantity
            FROM package_services ps
            JOIN services s
            ON ps.service_id = s.service_id
            WHERE ps.package_id = ?";

        $stmt = mysqli_prepare($link, $query);

        mysqli_stmt_bind_param(
        $stmt,
        "i",
        $booking['package_id']
        );

        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        while ($row = mysqli_fetch_assoc($result)) {
            $package_services[] = $row;
        }

        mysqli_stmt_close($stmt);
    }

    // Get individually selected services
    if ($booking !== null) {

        $query = "SELECT
            s.service_name,
            bs.price,
            bs.quantity
            FROM booking_services bs
            JOIN services s
            ON bs.service_id = s.service_id
            WHERE bs.booking_id = ?";

        $stmt = mysqli_prepare($link, $query);
        mysqli_stmt_bind_param($stmt, "i", $booking_id);
        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);

        while ($row = mysqli_fetch_assoc($result)) {
            $individual_services[] = $row;
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
        <title>Booking Details - CleanNest</title>
        <link rel="stylesheet" href="style.css">
    </head>

    <body class="booking-details-page">

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

        <?php if ($booking !== null) { ?>

            <div class="booking-details-container">

                <h1>Booking Details</h1>

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

                <h2>Selected Services</h2>

                <?php if (!empty($booking['package_name'])) { ?>

                    <h3>
                        <?php echo htmlspecialchars($booking['package_name']); ?>
                    </h3>

                    <p>
                        <strong>Services included in this package:</strong>
                    </p>

                    <?php foreach ($package_services as $service) { ?>

                        <p>
                            <?php echo htmlspecialchars($service['service_name']); ?>
                            (Quantity: <?php echo $service['quantity']; ?>)
                        </p>

                    <?php } ?>

                <?php } ?>

                <?php if (count($individual_services) > 0) { ?>

                    <h3>Additional Individual Services</h3>

                    <?php foreach ($individual_services as $service) { ?>

                        <p>
                            <?php echo htmlspecialchars($service['service_name']); ?>

                            - ₹<?php echo number_format($service['price'], 2); ?>

                            (Quantity: <?php echo $service['quantity']; ?>)
                        </p>

                    <?php } ?>

                <?php } ?>

                <h2>Booking Information</h2>

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
                    <br>
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

                <!-- Payment Details -->
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
                        <?php
                        echo htmlspecialchars(
                        ucwords(
                        str_replace('_', ' ', $payment['payment_status'])
                        )
                        );
                        ?>
                    </p>

                    <?php if ($payment['payment_method'] === 'upi') { ?>

                        <?php if (
                        $booking['status'] !== 'cancelled' &&
                        (
                        $payment['payment_status'] === 'rejected' ||
                        (
                        $payment['payment_status'] === 'pending' &&
                        empty($payment['transaction_reference'])
                        )
                        )
                        ) { ?>

                            <div style="background:#f5f8ff; padding:20px; border-radius:10px; margin:20px 0;">

                                <?php if ($payment['payment_status'] === 'rejected') { ?>

                                    <h3 style="color:#b42318;">
                                        UPI Payment Rejected
                                    </h3>

                                    <p style="color:#b42318;">
                                        Your previous payment reference was rejected.
                                    </p>

                                    <p>
                                        <strong>Rejection Reason:</strong>
                                        <?php
                                        echo htmlspecialchars(
                                        $payment['rejection_reason'] ?? 'Not specified'
                                        );
                                        ?>
                                    </p>

                                    <p>Please check your payment details and submit a new transaction reference.</p>

                                <?php } else { ?>

                                    <h3>Complete UPI Payment</h3>

                                <?php } ?>

                                <p>
                                    <strong>UPI ID:</strong> cleannest@okaxis
                                </p>

                                <p>
                                    <strong>Amount to Pay:</strong>
                                    ₹<?php echo number_format($payment['amount'], 2); ?>
                                </p>

                                <p>
                                    Open your UPI application and make the payment.
                                    After completing it, enter your transaction reference below.
                                </p>

                                <p style="color:#b42318;">
                                    Note: This is a temporary demo UPI ID.
                                    Do not send real money to this ID.
                                </p>

                                <form method="POST" action="submit_payment.php">

                                    <input
                                        type="hidden"
                                        name="booking_id"
                                        value="<?php echo $booking_id; ?>">

                                    <label for="transaction_reference">
                                        UPI Transaction Reference:
                                    </label>

                                    <br>
                                    <br>

                                    <input
                                        type="text"
                                        id="transaction_reference"
                                        name="transaction_reference"
                                        maxlength="100"
                                        placeholder="Enter transaction reference"
                                        required>

                                    <br>
                                    <br>

                                    <button type="submit" name="submit_payment">
                                        Submit Payment Reference
                                    </button>

                                </form>

                            </div>

                        <?php } elseif (
                        $payment['payment_status'] === 'verification_pending'
                        ) { ?>

                            <p>
                                <strong>Transaction Reference:</strong>
                                <?php
                                echo htmlspecialchars(
                                $payment['transaction_reference']
                                );
                                ?>
                            </p>

                            <p style="color:#b7791f;">
                                Your transaction reference has been submitted.
                                It is waiting for admin verification.
                            </p>

                        <?php } elseif (
                        $payment['payment_status'] === 'paid'
                        ) { ?>

                            <p style="color:green;">
                                Your payment has been successfully verified.
                            </p>

                            <?php if (!empty($payment['transaction_reference'])) { ?>

                                <p>
                                    <strong>Transaction Reference:</strong>
                                    <?php
                                    echo htmlspecialchars(
                                    $payment['transaction_reference']
                                    );
                                    ?>
                                </p>

                            <?php } ?>

                        <?php } ?>

                    <?php } else { ?>

                        <p>
                            Please pay the amount to the cleaning service staff
                            after the service is completed.
                        </p>

                        <?php if ($payment['payment_status'] === 'paid') { ?>

                            <p style="color:green;">
                                Cash payment has been confirmed by the admin.
                            </p>

                        <?php } ?>

                    <?php } ?>

                <?php } else { ?>

                    <p>Payment details are not available for this booking.</p>

                <?php } ?>

                <br>

                <a href="my_bookings.php" class="back-dashboard-btn">
                    Back to My Bookings
                </a>

            </div>

        <?php } else { ?>

            <div class="booking-details-container">

                <h2>Booking Not Found</h2>

                <p>We could not find this booking.</p>

                <a href="my_bookings.php" class="back-dashboard-btn">
                    Back to My Bookings
                </a>
            </div>
        <?php } ?>
    </body>
</html>
