<?php
// Set Indian timezone
date_default_timezone_set('Asia/Kolkata');

// Connect to database and email configuration
include "config.php";
require_once "email_config.php";

$message = "";

if (isset($_POST['send_link'])) {

    $email = trim($_POST['email']);

    if (empty($email)) {

        $message = "Please enter your registered email.";

    } else {

        // Check whether this email belongs to a customer
        $query = "SELECT user_id, first_name
            FROM users
            WHERE email = ? AND role = 'customer'";

        $stmt = mysqli_prepare($link, $query);
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);

        $result   = mysqli_stmt_get_result($stmt);
        $customer = mysqli_fetch_assoc($result);

        mysqli_stmt_close($stmt);

        if ($customer) {

            // Generate a secure random reset token
            $token = bin2hex(random_bytes(32));

            // Store only the hashed token in the database
            $hashed_token = hash('sha256', $token);

            // Set link expiry to 30 minutes
            $expiry_time = date('Y-m-d H:i:s', time() + 1800);

            // Remove older reset links for this customer
            $delete_query = "DELETE FROM password_resets WHERE user_id = ?";

            $delete_stmt = mysqli_prepare($link, $delete_query);

            mysqli_stmt_bind_param(
            $delete_stmt,
            "i",
            $customer['user_id']
            );

            mysqli_stmt_execute($delete_stmt);
            mysqli_stmt_close($delete_stmt);

            // Save the new reset token
            $insert_query = "INSERT INTO password_resets
                (user_id, reset_token, expiry_time)
                VALUES (?, ?, ?)";

            $insert_stmt = mysqli_prepare($link, $insert_query);

            mysqli_stmt_bind_param(
            $insert_stmt,
            "iss",
            $customer['user_id'],
            $hashed_token,
            $expiry_time
            );

            if (mysqli_stmt_execute($insert_stmt)) {

                // Create password reset link
                $reset_link = "http://localhost/Cleannest_project/reset_password.php?token="
                . urlencode($token);

                // Email content
                $email_message = "
                    <h2>CleanNest Password Reset</h2>

                    <p>Hello " . htmlspecialchars($customer['first_name']) . ",</p>

                    <p>We received a request to reset your CleanNest password.</p>

                    <p>Click the link below to create a new password:</p>

                    <p>
                    <a href='" . htmlspecialchars($reset_link) . "'>
                    Reset My Password
                    </a>
                    </p>

                    <p>This link expires in 30 minutes.</p>

                    <p>If you did not request this, you can ignore this email.</p>

                    <b>CleanNest Team</b>
                    ";

                // Send reset email
                $email_sent = sendCleanNestEmail(
                $email,
                "CleanNest - Password Reset",
                $email_message
                );

                if (!$email_sent) {

                    // Delete the token if email sending failed
                    $delete_query = "DELETE FROM password_resets WHERE user_id = ?";

                    $delete_stmt = mysqli_prepare($link, $delete_query);

                    mysqli_stmt_bind_param(
                    $delete_stmt,
                    "i",
                    $customer['user_id']
                    );

                    mysqli_stmt_execute($delete_stmt);
                    mysqli_stmt_close($delete_stmt);
                }
            }

            mysqli_stmt_close($insert_stmt);
        }

        // Same message whether email exists or not
        $message = "If a customer account exists with that email, a password reset link will be sent.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>Forgot Password - CleanNest</title>

        <link rel="stylesheet" href="style.css">
    </head>

    <body class="login-page">

        <h2>Forgot Password</h2>

        <form method="POST" action="">

            <label>Enter your registered email:</label>

            <input type="email"
                name="email"
                placeholder="Enter your email"
                required>

            <button type="submit" name="send_link">
                Send Reset Link
            </button>

            <p>
                <a href="login.php">Back to Login</a>
            </p>

        </form>

        <?php if (!empty($message)) { ?>

            <p class="login-message">
                <?php echo htmlspecialchars($message); ?>
            </p>

        <?php } ?>

    </body>
</html>
