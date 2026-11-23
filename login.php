<?php

// Connect to database
include "config.php";

// Select the correct session based on account role
$is_admin = false;

if (isset($_POST['login'])) {

    $email = trim($_POST['email']);

    if ($email !== "") {

        $query = "SELECT role FROM users WHERE email = ?";
        $stmt  = mysqli_prepare($link, $query);

        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);

        $result  = mysqli_stmt_get_result($stmt);
        $account = mysqli_fetch_assoc($result);

        if ($account && $account['role'] === 'admin') {
            $is_admin = true;
        }

        mysqli_stmt_close($stmt);
    }
}

// Start the appropriate session
if ($is_admin) {

    // Admin session
    session_name("CLEAN_NEST_ADMIN");
    session_start();

} else {

    // Customer session
    require_once "customer_session.php";
}

$message = "";

if (isset($_POST['login'])) {

    $email    = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {

        $message = "Please enter email and password.";

    } else {

        $query = "SELECT * FROM users WHERE email = ?";
        $stmt  = mysqli_prepare($link, $query);

        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($result) == 1) {

            $user = mysqli_fetch_assoc($result);

            if (password_verify($password, $user['password'])) {

                session_regenerate_id(true);

                $_SESSION['user_id'] = $user['user_id'];

                $_SESSION['name'] =
                $user['first_name'] . " " . $user['last_name'];

                $_SESSION['role'] = $user['role'];

                $_SESSION['last_activity'] = time();

                if ($user['role'] == 'admin') {

                    header("Location: admin/admin_dashboard.php");

                } else {

                    header("Location: customer_dashboard.php");
                }

                exit();

            } else {

                $message = "Incorrect email or password.";
            }

        } else {

            $message = "No account found with this email.";
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

        <title>Customer Login - CleanNest</title>

        <link rel="stylesheet" href="style.css">
    </head>

    <body class="login-page">

        <header class="customer-header">

            <a href="index.html" class="logo">
                <img src="images/cleannest-logo.png" alt="CleanNest Logo">
            </a>

            <nav>
                <a href="index.html">Home</a>
                <a href="register.php">Register</a>
            </nav>

        </header>

        <h2>CleanNest - Login</h2>

        <form method="POST" action="">

            <label>Email:</label>
            <input type="email" name="email"
                placeholder="Enter your email" required>

            <label>Password:</label>
            <input type="password" name="password"
                placeholder="Enter your password" required>

            <button type="submit" name="login">Login</button>

            <p>
                <a href="forgot_password.php">Forgot Password?</a>

            </p>

            <p class="register-link">
                Don't have an account?
                <a href="register.php">Register Here</a>
            </p>

        </form>

        <?php if (!empty($message)) { ?>

            <p class="login-message">
                <?php echo htmlspecialchars($message); ?>
            </p>

        <?php } ?>

    </body>
</html>
