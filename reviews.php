<?php
require_once "customer_session.php";

include "config.php";

// Check customer login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$success = "";
$error   = "";

// Handle review submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $booking_id = intval($_POST['booking_id']);
    $rating     = intval($_POST['rating']);
    $feedback   = trim($_POST['feedback']);

    if ($booking_id <= 0 || $rating < 1 || $rating > 5 || empty($feedback)) {
        $error = "Please fill in all fields correctly.";
    } else {

        // Check whether the booking belongs to this customer
        // and has been completed
        $check_query = "SELECT booking_id FROM bookings
            WHERE booking_id = ?
            AND user_id = ?
            AND status = 'completed'";

        $check_stmt = mysqli_prepare($link, $check_query);
        mysqli_stmt_bind_param($check_stmt, "ii", $booking_id, $user_id);
        mysqli_stmt_execute($check_stmt);

        $check_result = mysqli_stmt_get_result($check_stmt);

        if (mysqli_num_rows($check_result) == 0) {
            $error = "Invalid booking or booking is not completed.";
        } else {

            // Check whether the customer already reviewed this booking
            $review_check = "SELECT review_id FROM reviews WHERE booking_id = ?";

            $review_stmt = mysqli_prepare($link, $review_check);
            mysqli_stmt_bind_param($review_stmt, "i", $booking_id);
            mysqli_stmt_execute($review_stmt);

            $review_result = mysqli_stmt_get_result($review_stmt);

            if (mysqli_num_rows($review_result) > 0) {
                $error = "You have already submitted a review for this booking.";
            } else {

                // Insert review into database
                $insert_query = "INSERT INTO reviews
                    (booking_id, rating, feedback)
                    VALUES (?, ?, ?)";

                $insert_stmt = mysqli_prepare($link, $insert_query);

                mysqli_stmt_bind_param(
                $insert_stmt,
                "iis",
                $booking_id,
                $rating,
                $feedback
                );

                if (mysqli_stmt_execute($insert_stmt)) {
                    $success = "Thank you! Your review has been submitted successfully.";
                } else {
                    $error = "Something went wrong. Please try again.";
                }

                mysqli_stmt_close($insert_stmt);
            }

            mysqli_stmt_close($review_stmt);
        }

        mysqli_stmt_close($check_stmt);
    }
}

// Get completed bookings that have not been reviewed
$booking_query = "SELECT b.booking_id, b.booking_date
    FROM bookings b
    LEFT JOIN reviews r ON b.booking_id = r.booking_id
    WHERE b.user_id = ?
    AND b.status = 'completed'
    AND r.review_id IS NULL
    ORDER BY b.created_at DESC";

$booking_stmt = mysqli_prepare($link, $booking_query);
mysqli_stmt_bind_param($booking_stmt, "i", $user_id);
mysqli_stmt_execute($booking_stmt);

$booking_result = mysqli_stmt_get_result($booking_stmt);

// Get customer's previously submitted reviews
$my_reviews_query = "SELECT r.rating, r.feedback, r.created_at,
    b.booking_id
    FROM reviews r
    INNER JOIN bookings b ON r.booking_id = b.booking_id
    WHERE b.user_id = ?
    ORDER BY r.created_at DESC";

$reviews_stmt = mysqli_prepare($link, $my_reviews_query);
mysqli_stmt_bind_param($reviews_stmt, "i", $user_id);
mysqli_stmt_execute($reviews_stmt);

$my_reviews_result = mysqli_stmt_get_result($reviews_stmt);

?>

<!DOCTYPE html>
<html lang="en">

    <head>

        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>Reviews - CleanNest</title>

        <style>
            body {
                margin: 0;
                font-family: Arial, sans-serif;
                background-color: #f4f7f6;
                color: #333;
            }

            /* Customer Navigation */

            .customer-header {
                background-color: seagreen;
                padding: 18px 7%;
                display: flex;
                justify-content: space-between;
                align-items: center;
                box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
            }

            .customer-header h2 {
                color: white;
                margin: 0;
                font-size: 26px;
            }

            .customer-header nav a {
                text-decoration: none;
                color: white;
                margin-left: 20px;
                font-weight: bold;
                font-size: 14px;
                transition: 0.3s;
            }

            .customer-header nav a:hover {
                color: #c8f5df;
            }

            .review-container {
                max-width: 750px;
                margin: 40px auto;
                padding: 30px;
            }

            .review-form,
            .review-card {
                background-color: white;
                padding: 25px;
                margin-bottom: 25px;
                border-radius: 10px;
                box-shadow: 0 3px 12px rgba(0, 0, 0, 0.08);
            }

            h1,
            h2 {
                color: #159b75;
                text-align: center;
            }
            /* Logo */

            .logo img {
                width: 180px;
                height: 60px;
                object-fit: contain;
                display: block;
            }

            .review-form label {
                display: block;
                font-weight: bold;
                margin-bottom: 8px;
            }

            .review-form select,
            .review-form textarea {
                width: 100%;
                padding: 12px;
                margin-bottom: 20px;
                border: 1px solid #ccc;
                border-radius: 6px;
                font-size: 15px;
                box-sizing: border-box;
            }

            .review-form textarea {
                height: 120px;
                resize: vertical;
            }

            .review-form button {
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

            .review-form button:hover {
                background-color: #117b5d;
            }

            .success-message {
                background-color: #d4edda;
                color: #155724;
                padding: 12px;
                border-radius: 6px;
                text-align: center;
                margin-bottom: 20px;
            }

            .error-message {
                background-color: #f8d7da;
                color: #721c24;
                padding: 12px;
                border-radius: 6px;
                text-align: center;
                margin-bottom: 20px;
            }

            .review-card p {
                line-height: 1.6;
            }

            .rating {
                color: #e5a000;
                font-size: 20px;
            }

            .no-reviews {
                text-align: center;
                color: #666;
                padding: 20px;
            }

            @media (max-width: 768px) {

                .customer-header {
                    flex-direction: column;
                    gap: 15px;
                }

                .customer-header nav {
                    text-align: center;
                    line-height: 2.5;
                }

                .customer-header nav a {
                    display: inline-block;
                    margin: 5px 10px;
                }

            }
        </style>

    </head>

    <body>

        <!-- Navigation -->
        <header class="customer-header">

            <a href="index.html" class="logo">
                <img src="images/cleannest-logo.png" alt="CleanNest Logo">
            </a>

            <nav>
                <a href="customer_dashboard.php">Dashboard</a>
                <a href="services.php">Services</a>
                <a href="my_bookings.php">My Bookings</a>
                <a href="my_profile.php">My Profile</a>
                <a href="contact.php">Contact Us</a>
                <a href="logout.php">Logout</a>
            </nav>

        </header>


        <div class="review-container">

            <h1>Customer Reviews</h1>

            <p style="text-align:center;">
                Share your experience with CleanNest.
                Your feedback helps us improve our services.
            </p>


            <!-- Review Form -->
            <div class="review-form">

                <h2>Write a Review</h2>

                <?php if ($success != "") { ?>
                    <div class="success-message">
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php } ?>

                <?php if ($error != "") { ?>
                    <div class="error-message">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php } ?>


                <?php if (mysqli_num_rows($booking_result) > 0) { ?>

                    <form method="POST" action="">

                        <label>Select Completed Booking</label>

                        <select name="booking_id" required>

                            <option value="">-- Select Booking --</option>

                            <?php while ($booking = mysqli_fetch_assoc($booking_result)) { ?>

                                <option value="<?php echo $booking['booking_id']; ?>">
                                    Booking #<?php echo $booking['booking_id']; ?>
                                    - <?php echo $booking['booking_date']; ?>
                                </option>

                            <?php } ?>

                        </select>


                        <label>Rating</label>

                        <select name="rating" required>

                            <option value="">-- Select Rating --</option>
                            <option value="5">5 - Excellent</option>
                            <option value="4">4 - Very Good</option>
                            <option value="3">3 - Good</option>
                            <option value="2">2 - Average</option>
                            <option value="1">1 - Poor</option>

                        </select>


                        <label>Your Feedback</label>

                        <textarea name="feedback"
                            placeholder="Write your experience here..."
                            required>
                        </textarea>


                        <button type="submit">Submit Review</button>

                    </form>

                <?php } else { ?>

                    <p class="no-reviews">
                        You don't have any completed bookings available for review.
                    </p>

                <?php } ?>

            </div>


            <!-- Previously Submitted Reviews -->
            <h2>My Previous Reviews</h2>

            <?php if (mysqli_num_rows($my_reviews_result) > 0) { ?>

                <?php while ($review = mysqli_fetch_assoc($my_reviews_result)) { ?>

                    <div class="review-card">

                        <h3>
                            Booking #<?php echo $review['booking_id']; ?>
                        </h3>

                        <div class="rating">
                            <?php
                            echo str_repeat("★", $review['rating']);
                            echo str_repeat("☆", 5 - $review['rating']);
                            ?>
                        </div>

                        <p>
                            <?php echo htmlspecialchars($review['feedback']); ?>
                        </p>

                        <small>
                            Submitted on:
                            <?php echo htmlspecialchars($review['created_at']); ?>
                        </small>

                    </div>

                <?php } ?>

            <?php } else { ?>

                <p class="no-reviews">
                    You haven't submitted any reviews yet.
                </p>

            <?php } ?>

        </div>

    </body>

</html>