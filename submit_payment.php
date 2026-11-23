<?php

require_once "customer_session.php";
include "config.php";
require_once "email_config.php";

// Check customer login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

// Check form submission
if (isset($_POST['submit_payment'])) {

    $booking_id = isset($_POST['booking_id'])
    ? (int)$_POST['booking_id']
    : 0;

    $transaction_reference = trim(
    $_POST['transaction_reference'] ?? ''
    );

    // Validate details
    if ($booking_id <= 0 || $transaction_reference === '') {
        die("Please enter a valid transaction reference.");
    }

    if (strlen($transaction_reference) > 100) {
        die("Transaction reference is too long.");
    }

    // Check booking ownership and payment status
    $query = "SELECT payments.payment_id
        FROM payments
        JOIN bookings
        ON payments.booking_id = bookings.booking_id
        WHERE payments.booking_id = ?
        AND bookings.user_id = ?
        AND bookings.status != 'cancelled'
        AND payments.payment_method = 'upi'
        AND payments.payment_status IN ('pending', 'rejected')";

    $stmt = mysqli_prepare($link, $query);

    mysqli_stmt_bind_param($stmt, "ii", $booking_id, $user_id);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) == 1) {

        mysqli_stmt_close($stmt);

        // Save new transaction reference
        $query = "UPDATE payments
            SET transaction_reference = ?,
            payment_status = 'verification_pending',
            rejection_reason = NULL
            WHERE booking_id = ?
            AND payment_method = 'upi'
            AND payment_status IN ('pending', 'rejected')
            AND EXISTS (
            SELECT 1
            FROM bookings
            WHERE bookings.booking_id = payments.booking_id
            AND bookings.user_id = ?
            AND bookings.status != 'cancelled'
            )";

        $stmt = mysqli_prepare($link, $query);

        mysqli_stmt_bind_param(
        $stmt,
        "sii",
        $transaction_reference,
        $booking_id,
        $user_id
        );

        if (
        mysqli_stmt_execute($stmt) &&
        mysqli_stmt_affected_rows($stmt) == 1
        ) {

            mysqli_stmt_close($stmt);

            // Get customer details
            $customer_query = "SELECT first_name, last_name, email
                FROM users
                WHERE user_id = ?";

            $customer_stmt = mysqli_prepare($link, $customer_query);
            mysqli_stmt_bind_param($customer_stmt, "i", $user_id);
            mysqli_stmt_execute($customer_stmt);

            $customer_result = mysqli_stmt_get_result($customer_stmt);
            $customer        = mysqli_fetch_assoc($customer_result);

            mysqli_stmt_close($customer_stmt);

            // Get admin email
            $admin_query = "SELECT email
                FROM users
                WHERE role = 'admin'
                LIMIT 1";

            $admin_result = mysqli_query($link, $admin_query);
            $admin        = mysqli_fetch_assoc($admin_result);

            // Get payment amount
            $amount_query = "SELECT amount
                FROM payments
                WHERE booking_id = ?";

            $amount_stmt = mysqli_prepare($link, $amount_query);
            mysqli_stmt_bind_param($amount_stmt, "i", $booking_id);
            mysqli_stmt_execute($amount_stmt);

            $amount_result = mysqli_stmt_get_result($amount_stmt);
            $payment       = mysqli_fetch_assoc($amount_result);

            mysqli_stmt_close($amount_stmt);

            // Send email to admin
            if ($admin && !empty($admin['email']) && $customer) {

                $customer_name = $customer['first_name'] . " " .
                $customer['last_name'];

                $admin_message = "
                    <h2>UPI Payment Verification Required - CleanNest</h2>

                    <p>A customer has submitted a UPI transaction reference.</p>

                    <p><strong>Booking ID:</strong> #$booking_id</p>

                    <p><strong>Customer Name:</strong> " .
                htmlspecialchars($customer_name) . "</p>

                    <p><strong>Customer Email:</strong> " .
                htmlspecialchars($customer['email']) . "</p>

                    <p><strong>Transaction Reference:</strong> " .
                htmlspecialchars($transaction_reference) . "</p>

                    <p><strong>Payment Amount:</strong> Rs. " .
                number_format((float)$payment['amount'], 2) . "</p>

                    <p><strong>Payment Status:</strong> Verification Pending</p>

                    <p>Please check your bank account and verify the payment
                    from the CleanNest admin portal.</p>

                    <br>
                    <b>CleanNest Team</b>
                    ";

                sendCleanNestEmail(
                $admin['email'],
                "UPI Payment Verification Required - Booking #$booking_id",
                $admin_message
                );
            }

            // Redirect to booking details page
            header(
            "Location: booking_details.php?booking_id=" . $booking_id
            );
            exit();

        } else {

            mysqli_stmt_close($stmt);
            die("Unable to submit payment reference. Please try again.");
        }

    } else {

        mysqli_stmt_close($stmt);
        die("Invalid booking or payment reference cannot be submitted.");
    }

} else {

    header("Location: customer_dashboard.php");
    exit();
}

?>
