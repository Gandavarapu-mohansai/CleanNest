<?php
require_once "customer_session.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$name = $_SESSION['name'];
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Customer Dashboard - CleanNest</title>
        <style>
            * {
                box-sizing: border-box;
            }

            body {
                margin: 0;
                font-family: Arial, sans-serif;
                background-color: #f4f8f6;
                color: #333;
            }

            /* Navigation */

            header {
                background-color: seagreen;
                padding: 18px 7%;
                display: flex;
                justify-content: space-between;
                align-items: center;
                box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
            }
            header .logo img {
                width: 150px;
                height: 55px;
                object-fit: contain;
                display: block;
            }

            header h2 {
                color: white;
                margin: 0;
                font-size: 26px;
            }

            nav a {
                text-decoration: none;
                color: white;
                margin-left: 20px;
                font-weight: bold;
                font-size: 14px;
                transition: 0.3s;
            }

            nav a:hover {
                color: #c8f5df;
            }

            /* Welcome Section */

            .welcome-section {
                background: linear-gradient(135deg, #e0f5eb, #c8ead9);
                padding: 65px 20px;
                text-align: center;
            }

            .welcome-section h1 {
                color: seagreen;
                font-size: 34px;
                margin-bottom: 15px;
            }

            .welcome-section h3 {
                font-size: 21px;
                color: #333;
                margin-bottom: 12px;
            }

            .welcome-section p {
                font-size: 16px;
                color: #555;
                line-height: 1.6;
            }

            .book-now-btn {
                display: inline-block;
                background-color: seagreen;
                color: white;
                padding: 12px 28px;
                text-decoration: none;
                border-radius: 6px;
                margin: 8px 0 12px;
                font-size: 15px;
                font-weight: bold;
                transition: 0.3s;
            }

            .book-now-btn:hover {
                background-color: #17633e;
                transform: translateY(-2px);
            }


            /* Dashboard Section */

            .dashboard-container {
                max-width: 1150px;
                margin: 50px auto;
                padding: 0 25px;
            }

            .dashboard-heading {
                text-align: center;
                margin-bottom: 35px;
            }

            .dashboard-heading h2 {
                color: #245c43;
                font-size: 28px;
                margin-bottom: 10px;
            }

            .dashboard-heading p {
                color: #777;
                font-size: 15px;
            }

            /* Dashboard Cards */

            .dashboard-cards {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 25px;
            }

            .dashboard-card {
                background-color: white;
                padding: 32px 22px;
                text-align: center;
                border-radius: 12px;
                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.06);
                transition: 0.3s;
                border: 1px solid #e8efeb;
            }

            .dashboard-card:hover {
                transform: translateY(-6px);
                box-shadow: 0 8px 22px rgba(0, 0, 0, 0.12);
            }

            .card-icon {
                font-size: 42px;
                margin-bottom: 15px;
            }

            .dashboard-card h3 {
                color: seagreen;
                font-size: 20px;
                margin-bottom: 12px;
            }

            .dashboard-card p {
                color: #666;
                font-size: 14px;
                line-height: 1.6;
                min-height: 45px;
            }

            .dashboard-card a {
                display: inline-block;
                background-color: seagreen;
                color: white;
                padding: 11px 24px;
                text-decoration: none;
                border-radius: 6px;
                margin-top: 12px;
                font-size: 14px;
                font-weight: bold;
                transition: 0.3s;
            }

            .dashboard-card a:hover {
                background-color: #17633e;
            }

            /* Footer */

            footer {
                background-color: #245c43;
                color: white;
                text-align: center;
                padding: 18px;
                margin-top: 60px;
                font-size: 14px;
            }

            /* Responsive Design */

            @media (max-width: 900px) {

                .dashboard-cards {
                    grid-template-columns: repeat(2, 1fr);
                }

            }

            @media (max-width: 768px) {

                header {
                    flex-direction: column;
                    gap: 18px;
                }

                nav {
                    text-align: center;
                    line-height: 2.5;
                }

                nav a {
                    margin: 6px 10px;
                }

                .welcome-section h1 {
                    font-size: 27px;
                }

                .dashboard-cards {
                    grid-template-columns: 1fr;
                }
            }
        </style>
    </head>
    <body>



        <!-- Navigation -->
        <header>
            <a href="index.html" class="logo">
                <img src="images/cleannest-logo.png" alt="CleanNest Logo">
            </a>
            <nav>
                <a href="services.php">Services</a>
                <a href="my_bookings.php">My Bookings</a>
                <a href="my_profile.php">My Profile</a>
                <a href="contact.php">Contact Us</a>
                <a href="reviews.php">Reviews</a>
                <a href="logout.php">Logout</a>
            </nav>
        </header>

        <!-- Welcome Section -->
        <section class="welcome-section">
            <h1>Welcome to CleanNest!</h1>
            <h3>Hello, <?php echo htmlspecialchars($name); ?>!</h3>
            <a href="services.php" class="book-now-btn">Book Now</a>
            <p>
                Your home cleaning journey starts here.
                Manage your bookings and explore our cleaning services easily.
            </p>
        </section>

        <!-- Dashboard Cards -->
        <div class="dashboard-container">
            <div class="dashboard-heading">
                <h2>Your Dashboard</h2>
                <p>What would you like to do today?</p>
            </div>
            <div class="dashboard-cards">

                <!-- Services Card -->
                <div class="dashboard-card">
                    <div class="card-icon">🧹</div>
                    <h3>Explore Services</h3>
                    <p>
                        Choose from our home cleaning packages
                        and individual cleaning services.
                    </p>
                    <a href="services.php">Explore Services</a>
                </div>

                <!-- Bookings Card -->
                <div class="dashboard-card">
                    <div class="card-icon">📅</div>
                    <h3>My Bookings</h3>
                    <p>
                        View your bookings and check their
                        current booking status.
                    </p>
                    <a href="my_bookings.php">View Bookings</a>
                </div>

                <!-- Profile Card -->
                <div class="dashboard-card">
                    <div class="card-icon">👤</div>
                    <h3>My Profile</h3>
                    <p>
                        View and update your personal
                        information whenever required.
                    </p>
                    <a href="my_profile.php">View Profile</a>
                </div>

                <!-- Contact Card -->
                <div class="dashboard-card">
                    <div class="card-icon">📩</div>
                    <h3>Contact Us</h3>
                    <p>
                        Have questions or need assistance?
                        Send a message to our team.
                    </p>
                    <a href="contact.php">Contact Us</a>
                </div>

                <!-- Reviews Card -->
                <div class="dashboard-card">
                    <div class="card-icon">⭐</div>
                    <h3>Reviews</h3>
                    <p>
                        Share your experience and tell us
                        what you think about our services.
                    </p>
                    <a href="reviews.php">Write a Review</a>
                </div>
            </div>
        </div>
        <!-- Footer -->
        <footer>
            <p>&copy; 2026 CleanNest. All Rights Reserved.</p>
        </footer>
    </body>
</html>