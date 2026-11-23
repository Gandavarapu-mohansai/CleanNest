<?php
require_once "customer_session.php";

include "config.php";

$message = "";

if (isset($_POST['register'])) {
    //form values
    $first_name       = trim($_POST['first_name']);
    $last_name        = trim($_POST['last_name']);
    $email            = trim($_POST['email']);
    $phone            = trim($_POST['phone']);
    $password         = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    //validate form details
    if (empty($first_name) || empty($last_name) || empty($email) || empty($phone) || empty($password) || empty($confirm_password)) {
        $message = "Please fill all the required fields.";
    } elseif (preg_match('/[A-Z]/', $email)) {
        $message = "Email must contain only lowercase letters.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
    } elseif (!preg_match("/^[0-9]{10,15}$/", $phone)) {
        $message = "Please enter a valid phone number.";
    } elseif ($password != $confirm_password) {

        $message = "Passwords do not match.";
    } elseif (strlen($password) < 8) {
        $message = "password must contain at least 8 characters.";
    } else {
        //hash the password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        //insert customer details
        $query = "insert into users (first_name, last_name, email, phone, password)
            values(?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($link, $query);

        mysqli_stmt_bind_param(
        $stmt,
        "sssss",
        $first_name,
        $last_name,
        $email,
        $phone,
        $hashed_password
        );

        try {
            if (mysqli_stmt_execute($stmt)) {
                $message = "Registration successful! You can now login.";
            }
        } catch (mysqli_sql_exception $e) {

            if ($e->getCode() == 1062) {
                $message = "This email is already registered. Please use another email.";
            } else {
                $message = "Registration failed. Please try again.";
            }

        }

        mysqli_stmt_close($stmt);
    }
}
?>

<!DOCTYPE html>
<html>

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Customer Registration - CleanNest</title>
        <link rel="stylesheet" href="style.css">
    </head>

    <body class="register-body">

        <header class="customer-header register-header">
            <a href="index.html" class="logo">
                <img src="images/cleannest-logo.png" alt="CleanNest Logo">
            </a>
            <nav>
                <a href="index.html">Home</a>
                <a href="login.php">Login</a>
            </nav>
        </header>

        <div class="register-page">

            <h2>CleanNest - Customer Registration</h2>

            <form method="POST" action="">

                <div class="form-row">
                    <label>First Name:</label>
                    <input type="text" name="first_name" required>
                </div>
                <br>


                <div class="form-row">
                    <label>Last Name:</label>
                    <input type="text" name="last_name" required>
                </div>
                <br>

                <div class="form-row">
                    <label>Email:</label>
                    <input type="email" name="email" required>
                </div>
                <br>

                <div class="form-row">
                    <label>Phone Number:</label>
                    <input type="tel" name="phone" required>
                </div>
                <br>

                <div class="form-row">
                    <label>Password:</label>
                    <input type="password" name="password" required>
                </div>
                <br>

                <div class="form-row">
                    <label>Confirm Password:</label>
                    <input type="password" name="confirm_password" required>
                </div>
                <br>

                <button type="submit" name="register">Register</button>

                <p class="message <?php echo ($message == "Registration successful! You can now login.") ? "success" : "error"; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </p>
                <p class="login-link">
                    Already have an account?
                    <a href="login.php">Login Here</a>
                </p>

            </form>

        </div>

    </body>

</html>