<?php
require_once "customer_session.php";

include "config.php";

// Check customer login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$success_message = "";
$error_message   = "";

// Update profile
if (isset($_POST['update_profile'])) {

    $first_name = trim($_POST['first_name']);
    $last_name  = trim($_POST['last_name']);
    $email      = trim($_POST['email']);
    $phone      = trim($_POST['phone']);

    // Validate fields
    if (
    empty($first_name) ||
    empty($last_name) ||
    empty($email) ||
    empty($phone)
    ) {

        $error_message = "Please fill in all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error_message = "Please enter a valid email address.";
    } elseif (!preg_match("/^[0-9]{10,15}$/", $phone)) {

        $error_message = "Please enter a valid phone number.";
    } else {

        // Check whether email belongs to another user
        $query = "SELECT user_id FROM users
            WHERE email = ? AND user_id != ?";

        $stmt = mysqli_prepare($link, $query);

        mysqli_stmt_bind_param($stmt, "si", $email, $user_id);

        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($result) > 0) {

            $error_message = "This email address is already registered.";
        } else {

            // Update customer details
            $query = "UPDATE users
                SET first_name = ?,
                last_name = ?,
                email = ?,
                phone = ?
                WHERE user_id = ?";

            $update_stmt = mysqli_prepare($link, $query);

            mysqli_stmt_bind_param(
            $update_stmt,
            "ssssi",
            $first_name,
            $last_name,
            $email,
            $phone,
            $user_id
            );

            if (mysqli_stmt_execute($update_stmt)) {

                $_SESSION['name'] = $first_name . " " . $last_name;

                $success_message = "Profile updated successfully.";
            } else {

                $error_message = "Unable to update your profile.";
            }

            mysqli_stmt_close($update_stmt);
        }

        mysqli_stmt_close($stmt);
    }
}

// Get customer details
$query = "SELECT first_name, last_name, email, phone
    FROM users
    WHERE user_id = ?";

$stmt = mysqli_prepare($link, $query);

mysqli_stmt_bind_param($stmt, "i", $user_id);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$user = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

?>

<!DOCTYPE html>
<html lang="en">

    <head>

        <meta charset="UTF-8">

        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>My Profile - CleanNest</title>

        <link rel="stylesheet" href="style.css">

    </head>

    <body class="profile-page">

        <!-- Navigation -->

        <header class="customer-header">

            <a href="index.html" class="logo">
                <img src="images/cleannest-logo.png" alt="CleanNest Logo">
            </a>

            <nav>
                <a href="customer_dashboard.php">Dashboard</a>
                <a href="services.php">Services</a>
                <a href="my_bookings.php">My Bookings</a>
                <a href="logout.php">Logout</a>
            </nav>

        </header>


        <div class="profile-container">

            <h1>My Profile</h1>

            <p>View and update your personal information.</p>


            <?php if (!empty($success_message)) { ?>

                <p class="profile-success">
                    <?php echo htmlspecialchars($success_message); ?>
                </p>

            <?php } ?>


            <?php if (!empty($error_message)) { ?>

                <p class="profile-error">
                    <?php echo htmlspecialchars($error_message); ?>
                </p>

            <?php } ?>


            <form method="POST" action="my_profile.php">

                <label>First Name:</label>

                <input type="text"
                    name="first_name"
                    value="<?php echo htmlspecialchars($user['first_name']); ?>"
                    required>


                <label>Last Name:</label>

                <input type="text"
                    name="last_name"
                    value="<?php echo htmlspecialchars($user['last_name']); ?>"
                    required>


                <label>Email Address:</label>

                <input type="email"
                    name="email"
                    value="<?php echo htmlspecialchars($user['email']); ?>"
                    required>


                <label>Phone Number:</label>

                <input type="text"
                    name="phone"
                    value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                    pattern="[0-9]{10,15}"
                    required>


                <button type="submit" name="update_profile">
                    Update Profile
                </button>

            </form>

            <a href="customer_dashboard.php" class="profile-back-btn">
                Back to Dashboard
            </a>

        </div>

    </body>

</html>