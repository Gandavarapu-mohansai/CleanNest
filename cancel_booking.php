<?php
require_once "customer_session.php";

include "config.php";

// Check customer login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
// Allow cancellation only through form submission
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: my_bookings.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get booking ID
$booking_id = isset($_POST['booking_id'])
? intval($_POST['booking_id'])
: 0;

if ($booking_id <= 0) {
    header("Location: my_bookings.php");
    exit();
}

// Cancel only the customer's pending booking
$query = "UPDATE bookings
    SET status = 'cancelled'
    WHERE booking_id = ?
    AND user_id = ?
    AND status = 'pending'";

$stmt = mysqli_prepare($link, $query);

mysqli_stmt_bind_param(
$stmt,
"ii",
$booking_id,
$user_id
);

mysqli_stmt_execute($stmt);

if (mysqli_stmt_affected_rows($stmt) > 0) {
    $_SESSION['cancel_message'] = "Your booking has been cancelled successfully.";
} else {
    $_SESSION['cancel_message'] = "This booking cannot be cancelled.";
}

mysqli_stmt_close($stmt);

header("Location: my_bookings.php");
exit();
