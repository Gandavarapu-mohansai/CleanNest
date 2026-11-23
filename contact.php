<?php
require_once "customer_session.php";
include "config.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$success = "";
$error   = "";

// Get the customer's registered details
$user_query = "SELECT first_name, last_name, email
    FROM users
    WHERE user_id = ?";

$user_stmt = mysqli_prepare($link, $user_query);
mysqli_stmt_bind_param($user_stmt, "i", $user_id);
mysqli_stmt_execute($user_stmt);

$user_result = mysqli_stmt_get_result($user_stmt);
$user        = mysqli_fetch_assoc($user_result);

mysqli_stmt_close($user_stmt);

if (!$user) {
    die("Customer details not found.");
}

$name  = $user['first_name'] . " " . $user['last_name'];
$email = $user['email'];

// Handle contact form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $mobile  = trim($_POST['mobile']);
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);

    if (empty($mobile) || empty($subject) || empty($message)) {
        $error = "Please fill in all fields.";
    } elseif (!preg_match("/^[0-9]{10,15}$/", $mobile)) {
        $error = "Please enter a valid mobile number.";
    } else {

        $query = "INSERT INTO contact_messages
            (name, email, mobile, subject, message)
            VALUES (?, ?, ?, ?, ?)";

        $stmt = mysqli_prepare($link, $query);

        mysqli_stmt_bind_param(
        $stmt,
        "sssss",
        $name,
        $email,
        $mobile,
        $subject,
        $message
        );

        if (mysqli_stmt_execute($stmt)) {
            $success = "Thank you! Your message has been sent successfully.";
        } else {
            $error = "Something went wrong. Please try again.";
        }

        mysqli_stmt_close($stmt);
    }
}

// Get this customer's previous queries and admin replies
$messages_query = "SELECT message_id, subject, message, created_at,
    admin_reply, reply_status, replied_at
    FROM contact_messages
    WHERE email = ?
    ORDER BY created_at DESC";

$messages_stmt = mysqli_prepare($link, $messages_query);
mysqli_stmt_bind_param($messages_stmt, "s", $email);
mysqli_stmt_execute($messages_stmt);

$messages_result = mysqli_stmt_get_result($messages_stmt);
?>

<!DOCTYPE html>
<html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>Contact Us - CleanNest</title>

        <style>
            * {
                box-sizing: border-box;
            }

            body {
                margin: 0;
                font-family: Arial, sans-serif;
                background-color: #f4f7f6;
                color: #333;
            }

            .customer-header {
                background-color: seagreen;
                padding: 18px 7%;
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 15px;
                box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
            }

            .logo img {
                width: 180px;
                height: 60px;
                object-fit: contain;
                display: block;
            }

            .customer-header nav {
                text-align: center;
                line-height: 2.5;
            }

            .customer-header nav a {
                text-decoration: none;
                color: white;
                margin: 5px 10px;
                font-weight: bold;
                font-size: 14px;
                transition: 0.3s;
            }

            .customer-header nav a:hover {
                color: #c8f5df;
            }

            .contact-container,
            .queries-container {
                max-width: 750px;
                margin: 40px auto;
                padding: 35px;
                background-color: white;
                border-radius: 12px;
                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            }

            .contact-container h1,
            .queries-container h2 {
                text-align: center;
                color: #159b75;
                margin-top: 0;
            }

            .contact-description {
                text-align: center;
                color: #666;
                margin-bottom: 30px;
            }

            .contact-container label {
                display: block;
                margin-bottom: 8px;
                font-weight: bold;
            }

            .contact-container input,
            .contact-container textarea,
            .contact-container select {
                width: 100%;
                padding: 12px;
                margin-bottom: 20px;
                border: 1px solid #ccc;
                border-radius: 6px;
                font-size: 15px;
            }

            .contact-container input:focus,
            .contact-container textarea:focus,
            .contact-container select:focus {
                outline: none;
                border-color: #159b75;
            }

            .contact-container input[readonly] {
                background-color: #f1f1f1;
                cursor: not-allowed;
            }

            .contact-container textarea {
                height: 130px;
                resize: vertical;
            }

            .contact-container button {
                width: 100%;
                padding: 13px;
                background-color: #159b75;
                color: white;
                border: none;
                border-radius: 6px;
                font-size: 16px;
                font-weight: bold;
                cursor: pointer;
            }

            .contact-container button:hover {
                background-color: #117b5d;
            }

            .contact-success,
            .contact-error {
                padding: 12px;
                border-radius: 6px;
                margin-bottom: 20px;
                text-align: center;
            }

            .contact-success {
                background-color: #d4edda;
                color: #155724;
            }

            .contact-error {
                background-color: #f8d7da;
                color: #721c24;
            }

            .query-card {
                border: 1px solid #ddd;
                border-radius: 10px;
                padding: 22px;
                margin-top: 22px;
                background-color: #fff;
            }

            .query-card h3 {
                color: #159b75;
                margin-top: 0;
                margin-bottom: 12px;
            }

            .query-date {
                color: #777;
                font-size: 13px;
            }

            .query-message {
                background-color: #f4f7f6;
                padding: 15px;
                border-radius: 6px;
                line-height: 1.6;
                white-space: pre-wrap;
                overflow-wrap: anywhere;
            }

            .reply-box {
                background-color: #eaf7f0;
                border-left: 4px solid seagreen;
                padding: 15px;
                margin-top: 18px;
                border-radius: 6px;
            }

            .reply-box h4 {
                color: #16734c;
                margin: 0 0 10px;
            }

            .reply-box p {
                line-height: 1.6;
                white-space: pre-wrap;
                overflow-wrap: anywhere;
            }

            .pending-message {
                background-color: #fff3cd;
                color: #856404;
                padding: 12px;
                margin-top: 18px;
                border-radius: 6px;
            }

            .no-queries {
                text-align: center;
                color: #777;
                padding: 20px;
            }

            @media (max-width: 768px) {
                .customer-header {
                    flex-direction: column;
                }

                .contact-container,
                .queries-container {
                    margin: 25px 15px;
                    padding: 22px;
                }
            }
        </style>
    </head>

    <body>

        <!-- Customer Navigation -->
        <header class="customer-header">

            <a href="index.html" class="logo">
                <img src="images/cleannest-logo.png" alt="CleanNest Logo">
            </a>

            <nav>
                <a href="customer_dashboard.php">Dashboard</a>
                <a href="services.php">Services</a>
                <a href="my_bookings.php">My Bookings</a>
                <a href="my_profile.php">My Profile</a>
                <a href="reviews.php">Reviews</a>
                <a href="logout.php">Logout</a>
            </nav>

        </header>

        <!-- Contact Form -->
        <div class="contact-container">

            <h1>Contact Us</h1>

            <p class="contact-description">
                Have a question or need help? Send us a message.
            </p>

            <?php if ($success != "") { ?>
                <div class="contact-success">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php } ?>

            <?php if ($error != "") { ?>
                <div class="contact-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php } ?>

            <form method="POST" action="">

                <label>Your Name</label>
                <input type="text"
                    value="<?php echo htmlspecialchars($name); ?>"
                    readonly>

                <label>Email Address</label>
                <input type="email"
                    value="<?php echo htmlspecialchars($email); ?>"
                    readonly>

                <label>Mobile Number</label>
                <input type="tel"
                    name="mobile"
                    placeholder="Enter your mobile number"
                    maxlength="15"
                    pattern="[0-9]{10,15}"
                    required>

                <label>What is your issue?</label>

                <select name="subject" required>
                    <option value="">-- Select Your Issue --</option>
                    <option value="Booking Related">Booking Related</option>
                    <option value="Service Related">Service Related</option>
                    <option value="Cleaning Staff Related">Cleaning Staff Related</option>
                    <option value="Payment Related">Payment Related</option>
                    <option value="Website or Technical Issue">Website or Technical Issue</option>
                    <option value="General Enquiry">General Enquiry</option>
                    <option value="Other">Other</option>
                </select>

                <label>Your Message</label>
                <textarea name="message"
                    placeholder="Describe your issue..."
                    required>
                </textarea>

                <button type="submit">Send Message</button>

            </form>

        </div>

        <!-- Customer Queries and Admin Replies -->
        <div class="queries-container">

            <h2>My Queries & Admin Replies</h2>

            <p class="contact-description">
                Check your previous queries and replies from the CleanNest team.
            </p>

            <?php if (mysqli_num_rows($messages_result) > 0) { ?>

                <?php while ($row = mysqli_fetch_assoc($messages_result)) { ?>

                    <div class="query-card">

                        <h3>
                            <?php echo htmlspecialchars($row['subject']); ?>
                        </h3>

                        <p class="query-date">
                            Submitted on:
                            <?php echo date("d M Y, h:i A", strtotime($row['created_at'])); ?>
                        </p>

                        <strong>Your Query:</strong>

                        <div class="query-message"><?php
                            echo htmlspecialchars($row['message']);
                        ?></div>

                        <?php if (
                        !empty($row['admin_reply']) &&
                        $row['reply_status'] == 'Replied'
                        ) { ?>

                            <div class="reply-box">

                                <h4>Admin Reply</h4>

                                <p><?php
                                    echo htmlspecialchars($row['admin_reply']);
                                ?></p>

                                <?php if (!empty($row['replied_at'])) { ?>
                                    <small>
                                        Replied on:
                                        <?php echo date("d M Y, h:i A", strtotime($row['replied_at'])); ?>
                                    </small>
                                <?php } ?>

                            </div>

                        <?php } else { ?>

                            <div class="pending-message">
                                <strong>Waiting for Admin Reply</strong>
                                <br>
                                Our team will respond to your query soon.
                            </div>

                        <?php } ?>

                    </div>

                <?php } ?>

            <?php } else { ?>

                <div class="no-queries">
                    You haven't submitted any queries yet.
                </div>

            <?php } ?>

        </div>

    </body>
</html>

<?php
mysqli_stmt_close($messages_stmt);
?>
