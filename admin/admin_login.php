<?php
// Start separate admin session
session_name("CLEAN_NEST_ADMIN");
session_start();

require_once "../config.php";

// If admin is already logged in, go to dashboard
if (
isset($_SESSION['user_id']) &&
isset($_SESSION['role']) &&
$_SESSION['role'] == 'admin'
) {
    header("Location: admin_dashboard.php");
    exit();
}
$error = "";

// Check login form submission
if (isset($_POST['login'])) {

    $email    = trim($_POST['email']);
    $password = $_POST['password'];

    // Find user using email
    $query = "SELECT * FROM users WHERE email = ?";

    $stmt = mysqli_prepare($link, $query);

    mysqli_stmt_bind_param($stmt, "s", $email);

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) == 1) {

        $user = mysqli_fetch_assoc($result);

        // Check password and admin role
        if (
        password_verify($password, $user['password']) &&
        $user['role'] == 'admin'
        ) {

            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['name']    = $user['first_name'];
            $_SESSION['role']    = $user['role'];

            header("Location: admin_dashboard.php");
            exit();

        } else {

            $error = "Invalid email or password.";
        }

    } else {

        $error = "Invalid email or password.";
    }

    mysqli_stmt_close($stmt);
}
?>

<!DOCTYPE html>
<html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>Admin Login - CleanNest</title>

        <link rel="stylesheet" href="admin_style.css">
    </head>

    <body>

        <div class="login-container">

            <h1>CleanNest</h1>

            <h2>Admin Login</h2>

            <p>Welcome back! Please login to continue.</p>

            <?php if ($error != "") { ?>

                <p class="error">
                    <?php echo htmlspecialchars($error); ?>
                </p>

            <?php } ?>

            <form method="POST" action="">

                <label>Email Address</label>

                <input type="email" name="email"
                    placeholder="Enter your email" required>

                <label>Password</label>

                <input type="password" name="password"
                    placeholder="Enter your password" required>

                <button type="submit" name="login">Login</button>

            </form>

            <a href="../index.html">Back to Home</a>

        </div>

    </body>
</html>
