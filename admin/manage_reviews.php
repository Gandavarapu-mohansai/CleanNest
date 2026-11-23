<?php
session_name("CLEAN_NEST_ADMIN");
session_start();
require_once "../config.php";

// Check admin login
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: admin_login.php");
    exit();
}
$message = "";
$error   = "";

// Delete review
if (isset($_POST['delete_review'])) {
    $review_id = (int)$_POST['review_id'];
    $stmt      = $link->prepare(
    "DELETE FROM reviews WHERE review_id = ?"
    );
    $stmt->bind_param("i", $review_id);
    if ($stmt->execute()) {
        $message = "Review deleted successfully.";
    } else {
        $error = "Unable to delete review.";
    }
    $stmt->close();
}

// Get all reviews with customer details
$sql = "SELECT r.review_id, r.booking_id, r.rating, r.feedback,
    r.created_at, u.first_name, u.last_name
    FROM reviews r
    JOIN bookings b ON r.booking_id = b.booking_id
    JOIN users u ON b.user_id = u.user_id
    ORDER BY r.created_at DESC";
$reviews = $link->query($sql);
if (!$reviews) {
    die("Unable to load reviews: " . $link->error);
}
$total_reviews = $reviews->num_rows;
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Manage Reviews - CleanNest</title>
        <style>
            * {
                box-sizing: border-box;
            }

            body {
                margin: 0;
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

            .container {
                width: 94%;
                max-width: 1300px;
                margin: 30px auto;
            }

            .card {
                background: white;
                padding: 25px;
                border-radius: 10px;
                margin-bottom: 25px;
                box-shadow: 0 3px 14px rgba(0,0,0,0.05);
                border: 1px solid #e5eeeb;
            }

            .card h3 {
                margin-top: 0;
                color: #184d40;
            }

            .count {
                font-size: 28px;
                color: #16866f;
                font-weight: bold;
            }

            .message {
                padding: 13px;
                border-radius: 6px;
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

            .table-wrapper {
                width: 100%;
                overflow-x: auto;
            }

            table {
                width: 100%;
                min-width: 850px;
                border-collapse: collapse;
                table-layout: fixed;
            }

            th {
                background: #e8f4f0;
                color: #28594d;
                padding: 14px 12px;
                text-align: left;
                border-bottom: 2px solid #d4e7df;
            }

            td {
                padding: 14px 12px;
                border-bottom: 1px solid #e8eeeb;
                vertical-align: top;
            }

            tr:hover {
                background: #f8fbfa;
            }

            th:nth-child(1), td:nth-child(1) { width: 7%; }
            th:nth-child(2), td:nth-child(2) { width: 10%; }
            th:nth-child(3), td:nth-child(3) { width: 20%; }
            th:nth-child(4), td:nth-child(4) { width: 12%; }
            th:nth-child(5), td:nth-child(5) { width: 29%; }
            th:nth-child(6), td:nth-child(6) { width: 12%; }
            th:nth-child(7), td:nth-child(7) { width: 10%; }

            .feedback {
                white-space: normal;
                overflow-wrap: break-word;
                line-height: 1.6;
            }

            .rating {
                color: #d99a00;
                font-weight: bold;
            }

            .delete-btn {
                background: #fff0f0;
                color: #c0392b;
                border: 1px solid #f0cccc;
                padding: 9px 13px;
                border-radius: 5px;
                font-weight: bold;
                cursor: pointer;
            }

            .delete-btn:hover {
                background: #fce0e0;
            }

            .empty-message {
                text-align: center;
                padding: 25px;
                color: #71817d;
            }

            @media (max-width: 600px) {
                .topbar {
                    padding: 15px;
                }

                .container {
                    width: 96%;
                    margin: 20px auto;
                }

                .card {
                    padding: 15px;
                }
            }
        </style>
    </head>
    <body>

        <!-- Header -->
        <div class="topbar">
            <h2>Manage Reviews</h2>
            <a href="admin_dashboard.php" class="back-btn">
                Back to Dashboard
            </a>
        </div>
        <div class="container">
            <?php if ($message != ""): ?>
                <div class="message success-message">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            <?php if ($error != ""): ?>
                <div class="message error-message">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Total Reviews -->
            <div class="card">
                <h3>Total Customer Reviews</h3>
                <div class="count">
                    <?php echo $total_reviews; ?>
                </div>
            </div>

            <!-- Reviews Table -->
            <div class="card">
                <h3>Customer Reviews</h3>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Booking ID</th>
                                <th>Customer Name</th>
                                <th>Rating</th>
                                <th>Feedback</th>
                                <th>Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($total_reviews > 0): ?>
                                <?php while ($row = $reviews->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        #<?php echo (int)$row['review_id']; ?>
                                    </td>
                                    <td>
                                        #<?php echo (int)$row['booking_id']; ?>
                                    </td>
                                    <td>
                                        <?php
                                        echo htmlspecialchars(
                                        $row['first_name'] . " " . $row['last_name']
                                        );
                                        ?>
                                    </td>
                                    <td class="rating">
                                        <?php echo (int)$row['rating']; ?> / 5
                                    </td>
                                    <td class="feedback">
                                        <?php
                                        echo nl2br(htmlspecialchars(trim($row['feedback'] ?? '')));
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        echo date("d-m-Y", strtotime($row['created_at']));
                                        ?>
                                    </td>
                                    <td>
                                        <form method="POST"
                                            onsubmit="return confirm('Are you sure you want to delete this review?');">
                                            <input type="hidden" name="review_id"
                                                value="<?php echo (int)$row['review_id']; ?>">
                                            <button type="submit" name="delete_review"
                                                class="delete-btn">
                                                Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                            <?php else: ?>
                            <tr>
                                <td colspan="7" class="empty-message">
                                    No customer reviews found.
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </body>
</html>
