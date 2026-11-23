<?php
session_name("CLEAN_NEST_ADMIN");
session_start();

require_once "../config.php";
require_once "../email_config.php";

// Check whether admin is logged in
if (
!isset($_SESSION['user_id']) ||
!isset($_SESSION['role']) ||
$_SESSION['role'] != 'admin'
) {
    header("Location: admin_login.php");
    exit();
}

$message = "";
$error   = "";

// Update payment status
if (isset($_POST['update_payment'])) {

    $payment_id = (int)($_POST['payment_id'] ?? 0);
    $action     = $_POST['action'] ?? '';

    if ($payment_id <= 0) {

        $error = "Invalid payment ID.";

    } else {

        // Get customer and payment details before updating
        $info_query = "SELECT
            payments.booking_id,
            payments.payment_method,
            payments.amount,
            bookings.status AS booking_status,
            users.first_name,
            users.last_name,
            users.email
            FROM payments
            INNER JOIN bookings
            ON payments.booking_id = bookings.booking_id
            INNER JOIN users
            ON bookings.user_id = users.user_id
            WHERE payments.payment_id = ?";

        $info_stmt = mysqli_prepare($link, $info_query);
        mysqli_stmt_bind_param($info_stmt, "i", $payment_id);
        mysqli_stmt_execute($info_stmt);

        $info_result  = mysqli_stmt_get_result($info_stmt);
        $payment_info = mysqli_fetch_assoc($info_result);

        mysqli_stmt_close($info_stmt);

        if (!$payment_info) {

            $error = "Payment details not found.";

        } elseif ($payment_info['booking_status'] == 'cancelled') {

            $error = "Cannot update payment for a cancelled booking.";

        } elseif ($action == 'mark_paid') {

            // Mark cash or verified UPI payment as paid
            $query = "UPDATE payments
                SET payment_status = 'paid',
                payment_date = NOW()
                WHERE payment_id = ?
                AND (
                (payment_method = 'cash_on_service'
                AND payment_status = 'pending')
                OR
                (payment_method = 'upi'
                AND payment_status = 'verification_pending'
                AND transaction_reference IS NOT NULL
                AND transaction_reference != '')
                )";

            $stmt = mysqli_prepare($link, $query);

            if ($stmt) {

                mysqli_stmt_bind_param($stmt, "i", $payment_id);

                if (
                mysqli_stmt_execute($stmt) &&
                mysqli_stmt_affected_rows($stmt) == 1
                ) {

                    $message = "Payment marked as paid successfully.";

                    // Send payment confirmation email to customer
                    if (!empty($payment_info['email'])) {

                        $customer_name = $payment_info['first_name'] . " " .
                        $payment_info['last_name'];

                        $booking_id = $payment_info['booking_id'];
                        $amount     = number_format(
                        (float)$payment_info['amount'],
                        2
                        );

                        if ($payment_info['payment_method'] == 'upi') {

                            $email_subject = "UPI Payment Successful - CleanNest";

                            $email_message = "
                                <h2>Payment Successful - CleanNest</h2>

                                <p>Hello " . htmlspecialchars($customer_name) . ",</p>

                                <p>Your UPI payment has been verified successfully.</p>

                                <p><strong>Booking ID:</strong> #$booking_id</p>

                                <p><strong>Payment Amount:</strong> Rs. $amount</p>

                                <p><strong>Payment Status:</strong> Paid</p>

                                <p>Thank you for choosing CleanNest.</p>

                                <br>
                                <b>CleanNest Team</b>
                                ";

                        } else {

                            $email_subject = "Cash Payment Received - CleanNest";

                            $email_message = "
                                <h2>Cash Payment Received - CleanNest</h2>

                                <p>Hello " . htmlspecialchars($customer_name) . ",</p>

                                <p>We have confirmed that your cash payment
                                has been received successfully.</p>

                                <p><strong>Booking ID:</strong> #$booking_id</p>

                                <p><strong>Payment Amount:</strong> Rs. $amount</p>

                                <p><strong>Payment Status:</strong> Paid</p>

                                <p>Thank you for choosing CleanNest.</p>

                                <br>
                                <b>CleanNest Team</b>
                                ";
                        }

                        // Email failure will not affect the payment update
                        sendCleanNestEmail(
                        $payment_info['email'],
                        $email_subject,
                        $email_message
                        );
                    }

                } else {

                    $error = "Unable to update payment. Please check its current status.";
                }

                mysqli_stmt_close($stmt);

            } else {

                $error = "Unable to prepare the payment update.";
            }

        } elseif ($action == 'reject') {

            // Get and check the rejection reason
            $rejection_reason = trim($_POST['rejection_reason'] ?? '');

            $allowed_reasons = [
                'Payment not received',
                'Incorrect transaction reference',
                'Amount does not match',
                'Duplicate transaction reference',
                'Other'
            ];

            if (!in_array($rejection_reason, $allowed_reasons, true)) {

                $error = "Please select a valid rejection reason.";

            } else {

                // Reject only UPI payments waiting for verification
                $query = "UPDATE payments
                    SET payment_status = 'rejected',
                    rejection_reason = ?
                    WHERE payment_id = ?
                    AND payment_method = 'upi'
                    AND payment_status = 'verification_pending'
                    AND transaction_reference IS NOT NULL
                    AND transaction_reference != ''";

                $stmt = mysqli_prepare($link, $query);

                if ($stmt) {

                    mysqli_stmt_bind_param(
                    $stmt,
                    "si",
                    $rejection_reason,
                    $payment_id
                    );

                    if (
                    mysqli_stmt_execute($stmt) &&
                    mysqli_stmt_affected_rows($stmt) == 1
                    ) {

                        $message = "UPI payment rejected successfully.";

                        // Send rejection email to customer
                        if (!empty($payment_info['email'])) {

                            $customer_name = $payment_info['first_name'] . " " .
                            $payment_info['last_name'];

                            $booking_id = $payment_info['booking_id'];
                            $amount     = number_format(
                            (float)$payment_info['amount'],
                            2
                            );

                            $email_subject = "UPI Payment Rejected - CleanNest";

                            $email_message = "
                                <h2>UPI Payment Rejected - CleanNest</h2>

                                <p>Hello " . htmlspecialchars($customer_name) . ",</p>

                                <p>We could not approve your UPI payment.</p>

                                <p><strong>Booking ID:</strong> #$booking_id</p>

                                <p><strong>Payment Amount:</strong> Rs. $amount</p>

                                <p><strong>Rejection Reason:</strong> " .
                            htmlspecialchars($rejection_reason) . "</p>

                                <p>Please check your transaction details and
                                submit your payment reference again from the
                                booking details page.</p>

                                <br>
                                <b>CleanNest Team</b>
                                ";

                            sendCleanNestEmail(
                            $payment_info['email'],
                            $email_subject,
                            $email_message
                            );
                        }

                    } else {

                        $error = "Unable to reject payment. Please check its current status.";
                    }

                    mysqli_stmt_close($stmt);

                } else {

                    $error = "Unable to prepare the rejection update.";
                }
            }

        } else {

            $error = "Invalid payment action.";
        }
    }
}

// Payment status filter
$filter_status = $_GET['status'] ?? 'all';

$allowed_filters = [
    'all',
    'pending',
    'verification_pending',
    'paid',
    'rejected'
];

if (!in_array($filter_status, $allowed_filters, true)) {
    $filter_status = 'all';
}

// Get payment details with customer information
if ($filter_status == 'all') {

    $query = "SELECT
        payments.payment_id,
        payments.booking_id,
        payments.payment_method,
        payments.amount,
        payments.payment_status,
        bookings.status AS booking_status,
        payments.transaction_reference,
        payments.payment_date,
        users.first_name,
        users.last_name,
        users.email,
        users.phone
        FROM payments
        INNER JOIN bookings
        ON payments.booking_id = bookings.booking_id
        INNER JOIN users
        ON bookings.user_id = users.user_id
        ORDER BY payments.created_at DESC";

    $stmt = mysqli_prepare($link, $query);

} else {

    $query = "SELECT
        payments.payment_id,
        payments.booking_id,
        payments.payment_method,
        payments.amount,
        payments.payment_status,
        bookings.status AS booking_status,
        payments.transaction_reference,
        payments.payment_date,
        users.first_name,
        users.last_name,
        users.email,
        users.phone
        FROM payments
        INNER JOIN bookings
        ON payments.booking_id = bookings.booking_id
        INNER JOIN users
        ON bookings.user_id = users.user_id
        WHERE payments.payment_status = ?
        ORDER BY payments.created_at DESC";

    $stmt = mysqli_prepare($link, $query);

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $filter_status);
    }
}

$payments = false;

if ($stmt) {
    mysqli_stmt_execute($stmt);
    $payments = mysqli_stmt_get_result($stmt);
}

?>


<!DOCTYPE html>


<html lang="en">


    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">


        <title>Manage Payments - CleanNest</title>

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

            .back-btn:hover {
                background: #e8f4f0;
            }

            .payment-page {
                width: 94%;
                max-width: 1450px;
                margin: 35px auto;
            }

            .page-heading {
                margin-bottom: 25px;
            }

            .page-heading h1 {
                color: #184d40;
                font-size: 29px;
                margin-bottom: 8px;
            }

            .page-heading p {
                color: #71817d;
                font-size: 14px;
            }

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

            .payment-container {
                background: white;
                padding: 25px;
                border-radius: 12px;
                box-shadow: 0 3px 14px rgba(0,0,0,0.05);
                border: 1px solid #e5eeeb;
            }

            .section-heading {
                color: #184d40;
                font-size: 20px;
                margin-bottom: 22px;
            }

            .filter-form {
                display: flex;
                align-items: center;
                gap: 10px;
                flex-wrap: wrap;
                margin-bottom: 20px;
            }

            .filter-form label {
                font-weight: bold;
                color: #31594f;
                font-size: 14px;
            }

            .filter-form select {
                width: 210px;
                margin: 0;
            }

            select {
                padding: 9px;
                border: 1px solid #d5e1dc;
                border-radius: 6px;
                background: white;
                font-size: 13px;
            }

            .filter-btn {
                background: #16866f;
                color: white;
                border: none;
                padding: 10px 16px;
                border-radius: 6px;
                cursor: pointer;
                font-weight: bold;
            }

            .filter-btn:hover {
                background: #106b59;
            }

            .table-wrapper {
                width: 100%;
                overflow-x: auto;
            }

            table {
                width: 100%;
                min-width: 1250px;
                border-collapse: collapse;
            }

            th {
                background: #e8f4f0;
                color: #28594d;
                padding: 15px 12px;
                text-align: left;
                font-size: 13px;
                border-bottom: 2px solid #d4e7df;
            }

            td {
                padding: 14px 12px;
                border-bottom: 1px solid #e8eeeb;
                font-size: 13px;
                vertical-align: middle;
            }

            tbody tr:hover {
                background: #f8fbfa;
            }

            .payment-id {
                font-weight: bold;
                color: #16866f;
            }

            .customer-name {
                font-weight: bold;
                color: #31594f;
            }

            .price {
                font-weight: bold;
                color: #184d40;
                white-space: nowrap;
            }

            .reference {
                max-width: 180px;
                overflow-wrap: anywhere;
            }

            .method {
                font-weight: bold;
            }

            .status {
                display: inline-block;
                padding: 6px 10px;
                border-radius: 20px;
                font-size: 12px;
                font-weight: bold;
            }

            .pending {
                background: #fff4dc;
                color: #a56a00;
            }

            .verification_pending {
                background: #e5f0ff;
                color: #2463a5;
            }

            .paid {
                background: #e3f6eb;
                color: #18804c;
            }

            .rejected {
                background: #fff0ed;
                color: #c0392b;
            }

            .action-form {
                display: flex;
                flex-direction: column;
                gap: 7px;
            }

            .update-btn,
            .reject-btn {
                border: none;
                padding: 9px 12px;
                border-radius: 6px;
                cursor: pointer;
                font-weight: bold;
                font-size: 12px;
                width: 125px;
            }

            .update-btn {
                background: #16866f;
                color: white;
            }

            .update-btn:hover {
                background: #106b59;
            }

            .reject-btn {
                background: #fff0ed;
                color: #c0392b;
            }

            .reject-btn:hover {
                background: #fbdcd7;
            }

            .no-action {
                color: #71817d;
                font-size: 12px;
            }

            .empty-message {
                text-align: center;
                padding: 25px;
                color: #71817d;
            }

            @media (max-width: 700px) {
                .topbar {
                    padding: 15px;
                }

                .payment-page {
                    width: 95%;
                    margin: 22px auto;
                }

                .payment-container {
                    padding: 17px;
                }
            }
        </style>

    </head>


    <body>


        <header>
            <div class="topbar">
                <h2>Manage Payments</h2>

                <a href="admin_dashboard.php" class="back-btn">
                    Back to Dashboard
                </a>
            </div>
        </header>

        <main class="payment-page">

            <div class="page-heading">
                <h1>Manage Payments</h1>
                <p>View customer payments and verify their payment status.</p>
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

            <section class="payment-container">

                <h2 class="section-heading">Customer Payments</h2>

                <form method="GET" class="filter-form">

                    <label for="status-filter">Filter by Payment Status:</label>

                    <select name="status" id="status-filter">

                        <option value="all"
                        <?php echo ($filter_status == 'all') ? 'selected' : ''; ?>>
                            All Payments
                        </option>

                        <option value="pending"
                        <?php echo ($filter_status == 'pending') ? 'selected' : ''; ?>>
                            Pending
                        </option>

                        <option value="verification_pending"
                        <?php echo ($filter_status == 'verification_pending') ? 'selected' : ''; ?>>
                            Verification Pending
                        </option>

                        <option value="paid"
                        <?php echo ($filter_status == 'paid') ? 'selected' : ''; ?>>
                            Paid
                        </option>

                        <option value="rejected"
                        <?php echo ($filter_status == 'rejected') ? 'selected' : ''; ?>>
                            Rejected
                        </option>

                    </select>

                    <button type="submit" class="filter-btn">
                        Apply Filter
                    </button>

                </form>

                <div class="table-wrapper">

                    <table>

                        <thead>
                            <tr>
                                <th>Payment ID</th>
                                <th>Booking ID</th>
                                <th>Customer</th>
                                <th>Phone</th>
                                <th>Payment Method</th>
                                <th>Amount</th>
                                <th>Transaction Reference</th>
                                <th>Payment Status</th>
                                <th>Payment Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php if ($payments && mysqli_num_rows($payments) > 0): ?>

                            <?php while ($payment = mysqli_fetch_assoc($payments)): ?>

                            <tr>

                                <td class="payment-id">
                                    #<?php echo (int)$payment['payment_id']; ?>
                                </td>

                                <td>
                                    #<?php echo (int)$payment['booking_id']; ?>
                                </td>

                                <td class="customer-name">

                                    <?php
                                    echo htmlspecialchars(
                                    $payment['first_name'] . " " .
                                    $payment['last_name']
                                    );
                                    ?>

                                    <br>

                                    <?php echo htmlspecialchars($payment['email']); ?>

                                </td>

                                <td>
                                    <?php echo htmlspecialchars($payment['phone'] ?? ''); ?>
                                </td>

                                <td class="method">

                                    <?php
                                    echo ($payment['payment_method'] == 'upi')
                                    ? 'UPI Payment'
                                    : 'Cash on Service';
                                    ?>

                                </td>

                                <td class="price">
                                    ₹<?php echo number_format($payment['amount'], 2); ?>
                                </td>

                                <td class="reference">

                                    <?php
                                    echo !empty($payment['transaction_reference'])
                                    ? htmlspecialchars($payment['transaction_reference'])
                                    : 'Not submitted';
                                    ?>

                                </td>

                                <td>

                                    <span class="status <?php echo htmlspecialchars($payment['payment_status']); ?>">

                                        <?php
                                        echo htmlspecialchars(
                                        ucfirst(
                                        str_replace(
                                        '_',
                                        ' ',
                                        $payment['payment_status']
                                        )
                                        )
                                        );
                                        ?>

                                    </span>

                                </td>

                                <td>

                                    <?php
                                    if (!empty($payment['payment_date'])) {

                                        echo date(
                                        "d-m-Y h:i A",
                                        strtotime($payment['payment_date'])
                                        );

                                    } else {

                                        echo "Not paid";
                                    }
                                    ?>

                                </td>


                                <td>

                                    <?php if ($payment['booking_status'] == 'cancelled') { ?>

                                        <span class="no-action">
                                            Booking Cancelled - No Payment Action
                                        </span>

                                    <?php } elseif (
                                    in_array($payment['payment_status'],['pending', 'verification_pending'],true)
                                    ) { ?>

                                        <?php if (
                                        $payment['payment_method'] == 'cash_on_service'
                                        ): ?>

                                        <form method="POST" class="action-form"
                                            onsubmit="return confirm('Confirm that you received this cash payment?');">

                                            <input type="hidden" name="payment_id"
                                                value="<?php echo (int)$payment['payment_id']; ?>">

                                            <input type="hidden" name="action"
                                                value="mark_paid">

                                            <button type="submit"
                                                name="update_payment"
                                                value="1"
                                                class="update-btn">
                                                Mark as Paid
                                            </button>

                                        </form>

                                        <?php elseif (
                                        $payment['payment_method'] == 'upi' &&
                                        $payment['payment_status'] == 'verification_pending' &&
                                        !empty($payment['transaction_reference'])
                                        ): ?>

                                        <form method="POST" class="action-form"
                                            onsubmit="return confirm('Have you verified this UPI transaction?');">

                                            <input type="hidden" name="payment_id"
                                                value="<?php echo (int)$payment['payment_id']; ?>">

                                            <input type="hidden" name="action"
                                                value="mark_paid">

                                            <button type="submit"
                                                name="update_payment"
                                                value="1"
                                                class="update-btn">
                                                Verify & Mark Paid
                                            </button>

                                        </form>

                                        <form method="POST" class="action-form"
                                            onsubmit="return confirm('Are you sure you want to reject this UPI payment?');">

                                            <input type="hidden" name="payment_id"
                                                value="<?php echo (int)$payment['payment_id']; ?>">

                                            <input type="hidden" name="action"
                                                value="reject">

                                            <select name="rejection_reason" required>

                                                <option value="">Select rejection reason</option>

                                                <option value="Payment not received">
                                                    Payment not received
                                                </option>

                                                <option value="Incorrect transaction reference">
                                                    Incorrect transaction reference
                                                </option>

                                                <option value="Amount does not match">
                                                    Amount does not match
                                                </option>

                                                <option value="Duplicate transaction reference">
                                                    Duplicate transaction reference
                                                </option>

                                                <option value="Other">
                                                    Other
                                                </option>

                                            </select>

                                            <button type="submit"
                                                name="update_payment"
                                                value="1"
                                                class="reject-btn">
                                                Reject Payment
                                            </button>

                                        </form>

                                        <?php else: ?>

                                        <span class="no-action">
                                            Waiting for customer reference
                                        </span>

                                        <?php endif; ?>
                                    <?php } else { ?>
                                        <span class="no-action">
                                            No action available
                                        </span>
                                    <?php } ?>
                                </td>
                            </tr>

                            <?php endwhile; ?>

                            <?php else: ?>

                            <tr>
                                <td colspan="10" class="empty-message">

                                    <?php
                                    echo $payments
                                    ? "No payments found."
                                    : "Unable to load payments. Please check the database query.";
                                    ?>

                                </td>
                            </tr>

                            <?php endif; ?>

                        </tbody>

                    </table>
                </div>
            </section>
        </main>
        <?php
        if ($stmt) {
            mysqli_stmt_close($stmt);
        }
        ?>
    </body>
</html>