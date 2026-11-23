<?php
// Set Indian timezone
date_default_timezone_set('Asia/Kolkata');

// Connect to database
include "config.php";

$message   = "";
$token     = "";
$reset     = null;
$show_form = false;

// Get token from email link or submitted form
if (isset($_GET['token'])) {

    $token = $_GET['token'];

} elseif (isset($_POST['token'])) {

    $token = $_POST['token'];
}

// Check whether the token has the correct format
if (!preg_match('/^[a-f0-9]{64}$/', $token)) {

    $message = "Invalid password reset link.";

} else {

    // Hash the token to match the value stored in the database
    $hashed_token = hash('sha256', $token);

    // Find the reset request
    $query = "SELECT reset_id, user_id, expiry_time
        FROM password_resets
        WHERE reset_token = ?";

    $stmt = mysqli_prepare($link, $query);

    mysqli_stmt_bind_param($stmt, "s", $hashed_token);

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    $reset = mysqli_fetch_assoc($result);

    mysqli_stmt_close($stmt);

    // Check whether the reset link is valid and not expired
    if ($reset && strtotime($reset['expiry_time']) > time()) {

        // Show the password reset form
        $show_form = true;

    } else {

        $message = "This password reset link is invalid or expired.";
    }

    // Process the new password
    if ($show_form && isset($_POST['reset_password'])) {

        $password         = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];

        if (strlen($password) < 8) {

            $message = "Password must contain at least 8 characters.";

        } elseif ($password != $confirm_password) {

            $message = "Passwords do not match.";

        } else {

            // Hash the new password securely
            $hashed_password = password_hash(
            $password,
            PASSWORD_DEFAULT
            );

            // Update the customer's password
            $update_query = "UPDATE users
                SET password = ?
                WHERE user_id = ?";

            $update_stmt = mysqli_prepare($link, $update_query);

            mysqli_stmt_bind_param(
            $update_stmt,
            "si",
            $hashed_password,
            $reset['user_id']
            );

            if (mysqli_stmt_execute($update_stmt)) {

                // Delete reset tokens after successful password change
                $delete_query = "DELETE FROM password_resets
                    WHERE user_id = ?";

                $delete_stmt = mysqli_prepare($link, $delete_query);

                mysqli_stmt_bind_param(
                $delete_stmt,
                "i",
                $reset['user_id']
                );

                mysqli_stmt_execute($delete_stmt);

                mysqli_stmt_close($delete_stmt);

                $message = "Password changed successfully. You can now log in.";

                // Hide the form after successful password change
                $show_form = false;

            } else {

                $message = "Unable to update your password. Please try again.";

            }

            mysqli_stmt_close($update_stmt);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>Reset Password - CleanNest</title>

        <link rel="stylesheet" href="style.css">
    </head>

    <body class="login-page">

        <h2>Reset Your Password</h2>

        <?php if (!empty($message)) { ?>

            <p class="login-message">
                <?php echo htmlspecialchars($message); ?>
            </p>

        <?php } ?>

        <?php if ($show_form) { ?>

            <form method="POST" action="">

                <input type="hidden"
                    name="token"
                    value="<?php echo htmlspecialchars($token); ?>">

                <label>New Password:</label>

                <input type="password"
                    name="password"
                    placeholder="Enter new password"
                    minlength="8"
                    required>

                <label>Confirm New Password:</label>

                <input type="password"
                    name="confirm_password"
                    placeholder="Confirm new password"
                    minlength="8"
                    required>

                <button type="submit" name="reset_password">
                    Reset Password
                </button>

            </form>

        <?php } ?>

        <p>
            <a href="login.php">Back to Login</a>
        </p>

    </body>
</html>
