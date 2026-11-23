<?php
require_once "customer_session.php";
include "config.php";

// Load active packages from the database
$packages_result = mysqli_query(
$link,
"SELECT package_id, package_name, bhk_type, price, description
    FROM bhk_packages
    WHERE status = 'active'
    ORDER BY bhk_type ASC"
);
if (!$packages_result) {
    die("Unable to load packages: " . mysqli_error($link));
}
$packages = [];
while ($package = mysqli_fetch_assoc($packages_result)) {
    $packages[] = $package;
}

// Load all services, including inactive services, so customers can see availability
$services_result = mysqli_query(
$link,
"SELECT service_id, service_name, description, price, status
    FROM services
    ORDER BY service_id ASC"
);
if (!$services_result) {
    die("Unable to load services: " . mysqli_error($link));
}
$services       = [];
$service_status = [];

while ($service = mysqli_fetch_assoc($services_result)) {
    $services[]                                  = $service;
    $service_status[(int)$service['service_id']] = $service;
}

// Images for the services already used in CleanNest
$service_images = [
    1  => "https://images.unsplash.com/photo-1584622650111-993a426fbf0a?w=600",
    2  => "https://images.unsplash.com/photo-1556911220-e15b29be8c8f?w=600",
    3  => "images/bedroom.jpg",
    4  => "https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?w=600",
    5  => "images/dinning.jpg",
    6  => "images/balcony.jpg",
    7  => "https://static.wixstatic.com/media/e9a7f3_6a2a14484625461b9786ce9e90e85b72~mv2.webp/v1/fill/w_1000%2Ch_903%2Cal_c%2Cq_85%2Cusm_0.66_1.00_0.01/e9a7f3_6a2a14484625461b9786ce9e90e85b72~mv2.webp",
    8  => "images/study.jpg",
    9  => "images/window.jpg",
    10 => "https://img.staticmb.com/mbcontent/images/crop/uploads/ver2/XIwvQlc61t8ZIpanz4mAnTU5mXTv0JniK84Hi2xDB5DKVg/rooftop-room-ideas-for-indian-homes_0_1200.jpg",
    11 => "images/ac-fan-cleaning.jpg",
    12 => "images/landry and iron.jpg"
];

$package_icons = [
    1 => "🏠",
    2 => "🏡",
    3 => "🏘️",
    4 => "🏢"
];

// Service IDs 1, 2, 3 and 4 are included in BHK packages
$package_service_ids = [1, 2, 3, 4];
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Our Services - CleanNest</title>
        <link rel="stylesheet" href="style.css">
        <style>
            .service-card.service-unavailable {
                opacity: 0.58;
                filter: grayscale(0.65);
            }
            .availability-message {
                color: #b42318;
                background: #fff0ed;
                border: 1px solid #f2c7c2;
                border-radius: 6px;
                padding: 8px 10px;
                margin: 10px 0;
                font-weight: bold;
                font-size: 14px;
            }
            .service-card input:disabled {
                cursor: not-allowed;
            }
            .package-unavailable-list {
                color: #b42318;
                background: #fff7f5;
                border-left: 3px solid #d94b40;
                padding: 9px 12px;
                margin: 10px 0;
                font-size: 14px;
            }
        </style>
    </head>

    <body class="services-page">

        <!-- Navigation -->
        <header class="services-head">

            <a href="index.html" class="logo">
                <img src="images/cleannest-logo.png" alt="CleanNest Logo">
            </a>

            <nav>
                <?php if (isset($_SESSION['user_id'])) { ?>

                    <a href="customer_dashboard.php">Dashboard</a>
                    <a href="my_bookings.php">My Bookings</a>
                    <a href="logout.php">Logout</a>

                <?php } else { ?>

                    <a href="index.html">Home</a>
                    <a href="login.php">Login</a>
                    <a href="register.php">Register</a>

                <?php } ?>
            </nav>

        </header>


        <!-- Page Introduction -->
        <div class="services-intro">

            <p class="services-subtitle">WELCOME TO CLEANNEST</p>

            <h1>Our Cleaning Services</h1>

            <p>
                A clean home is a happy home.
                Choose the cleaning services that best suit your needs.
            </p>

        </div>


        <form method="POST" action="booking.php" id="booking-form">


            <!-- BHK Packages Section -->
            <section class="package-section">

                <h1>Choose Your Home Cleaning Package</h1>

                <p>Select a package according to your home size.</p>

                <div class="service-container">

                    <?php if (count($packages) > 0): ?>
                    <?php foreach ($packages as $package): ?>
                        <?php
                        $bhk_type               = (int)$package['bhk_type'];
                        $unavailable_services   = [];
                        $adjusted_package_price = (float)$package['price'];

                        // Deduct the price of every inactive service included in this package.
                        foreach ($package_service_ids as $included_id) {
                            if (isset($service_status[$included_id]) &&
                            $service_status[$included_id]['status'] !== 'active') {
                                $unavailable_services[] = $service_status[$included_id]['service_name'];

                                // Bathroom and Bedroom are included once per bedroom.
                                $service_quantity = in_array($included_id, [1, 3], true) ? $bhk_type : 1;
                                $service_price    = (float)$service_status[$included_id]['price'];
                                $adjusted_package_price -= $service_price * $service_quantity;
                            }
                        }

                        // Do not allow the adjusted package price to become negative.
                        $adjusted_package_price = max(0, $adjusted_package_price);
                        ?>

                        <div class="service-card package-card">
                            <div class="package-icon">
                                <?php echo $package_icons[$bhk_type] ?? "🏠"; ?>
                            </div>

                            <h3><?php echo htmlspecialchars($package['package_name']); ?></h3>

                            <p>
                                <?php echo $bhk_type; ?> Bedroom<?php echo $bhk_type > 1 ? 's' : ''; ?>
                                + Kitchen + Hall + <?php echo $bhk_type; ?> Bathroom<?php echo $bhk_type > 1 ? 's' : ''; ?>
                            </p>

                            <?php if (count($unavailable_services) > 0): ?>
                            <div class="package-unavailable-list">
                                <strong>Currently not included:</strong>
                                <?php foreach ($unavailable_services as $unavailable_name): ?>
                                    <div>
                                        <?php echo htmlspecialchars($unavailable_name); ?> is currently not available and is not included in this package.
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <h4>₹<?php echo number_format($adjusted_package_price, 2); ?></h4>

                        <label>
                            <input type="radio" name="selected_package"
                                class="package-option"
                                value="<?php echo (int)$package['package_id']; ?>"
                                data-price="<?php echo htmlspecialchars((string)$adjusted_package_price); ?>">
                            Select Package
                        </label>
                    </div>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <p>No packages are currently available.</p>
                    <?php endif; ?>

                </div>

                <button type="button" id="clear-package">
                    Clear Package
                </button>

            </section>


            <!-- Individual Services Section -->
            <section class="individual-services">

                <h1>Individual Cleaning Services</h1>

                <p>
                    Need cleaning for only specific rooms?
                    Select the services you want.
                </p>


                <div class="service-container">

                    <?php if (count($services) > 0): ?>
                    <?php foreach ($services as $service): ?>
                        <?php
                        $service_id         = (int)$service['service_id'];
                        $is_active          = ($service['status'] === 'active');
                        $image              = $service_images[$service_id] ?? "images/bedroom.jpg";
                        $is_package_service = in_array($service_id, $package_service_ids, true);
                        ?>

                        <div class="service-card <?php echo $is_active ? '' : 'service-unavailable'; ?>">
                            <img src="<?php echo htmlspecialchars($image); ?>"
                                alt="<?php echo htmlspecialchars($service['service_name']); ?>"
                                class="service-image">

                            <div class="service-content">
                                <h3><?php echo htmlspecialchars($service['service_name']); ?></h3>

                                <p><?php echo htmlspecialchars($service['description'] ?? ''); ?></p>

                                <?php if ($service_id === 12): ?>
                                    <h4>Price: ₹<?php echo number_format((float)$service['price'], 2); ?> per pair</h4>
                                <?php else: ?>
                                    <h4>Price: ₹<?php echo number_format((float)$service['price'], 2); ?></h4>
                                <?php endif; ?>

                                <?php if (!$is_active): ?>
                                    <p class="availability-message">Currently Not Available</p>
                                    <label>
                                        <input type="checkbox" disabled>
                                        Service Unavailable
                                    </label>
                                <?php else: ?>
                                    <?php if ($service_id === 12): ?>
                                        <label>Number of Pairs:</label>
                                        <input type="number"
                                            id="laundry-pairs"
                                            name="service_quantities[12]"
                                            min="1"
                                            step="1"
                                            value="1">
                                    <?php endif; ?>

                                    <label>
                                        <input type="checkbox" name="selected_services[]"
                                            data-price="<?php echo htmlspecialchars($service['price']); ?>"
                                            value="<?php echo $service_id; ?>"
                                            data-package-included="<?php echo $is_package_service ? '1' : '0'; ?>">
                                        Select this service
                                    </label>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <p>No cleaning services are currently available.</p>
                    <?php endif; ?>

                </div>


                <!-- Booking Summary -->
                <div class="booking-summary">

                    <h2>Your Booking Summary</h2>

                    <p>Your selected services total amount</p>

                    <h3>
                        ₹<span id="total-price">0.00</span>
                    </h3>

                </div>


                <button type="submit" id="proceed-booking">
                    Proceed to Booking →
                </button>

            </section>

        </form>


        <!-- JavaScript for Price Calculation -->
        <script>
            document.getElementById("booking-form").addEventListener("submit", function(event) {

                let selectedPackage = document.querySelector(
                'input[name="selected_package"]:checked'
                );

                let selectedServices = document.querySelectorAll(
                'input[name="selected_services[]"]:checked:not(:disabled)'
                );

                if (!selectedPackage && selectedServices.length === 0) {

                    event.preventDefault();

                    alert("Please select at least one package or service.");

                }

            });


            const checkboxes = document.querySelectorAll(
            'input[name="selected_services[]"]'
            );

            const packages = document.querySelectorAll(".package-option");

            const totalPrice = document.getElementById("total-price");


            function calculateTotal() {

                let total = 0;

                packages.forEach(function(packageOption) {

                    if (packageOption.checked) {

                        total += Number(packageOption.dataset.price);

                    }

                });


                checkboxes.forEach(function(checkbox) {

                    if (checkbox.checked && !checkbox.disabled) {

                        let servicePrice = Number(checkbox.dataset.price);

                        // Laundry is charged per pair
                        if (checkbox.value === "12") {
                            const pairsInput = document.getElementById("laundry-pairs");
                            const pairs = Math.max(1, parseInt(pairsInput.value, 10) || 1);
                            total += servicePrice * pairs;
                        } else {
                            total += servicePrice;
                        }

                    }

                });

                totalPrice.textContent = total.toFixed(2);

            }


            // Handle package selection
            packages.forEach(function(packageOption) {

                packageOption.addEventListener("change", function() {

                    checkboxes.forEach(function(checkbox) {

                        if (checkbox.dataset.packageIncluded === "1") {
                            checkbox.checked = false;
                            checkbox.disabled = true;
                        }

                    });

                    calculateTotal();

                });

            });


            // Clear package selection
            document.getElementById("clear-package").addEventListener("click", function() {

                packages.forEach(function(packageOption) {

                    packageOption.checked = false;

                });

                checkboxes.forEach(function(checkbox) {

                    if (checkbox.dataset.packageIncluded === "1") {
                        checkbox.disabled = false;
                    }

                });

                calculateTotal();

            });


            // Individual service selection
            checkboxes.forEach(function(checkbox) {

                checkbox.addEventListener("change", calculateTotal);

            });

            // Recalculate total whenever Laundry pair count changes
            const laundryPairsInput = document.getElementById("laundry-pairs");
            if (laundryPairsInput) {
                laundryPairsInput.addEventListener("input", calculateTotal);
            }

            // Show the correct total when the page first loads
            calculateTotal();
        </script>

    </body>

</html>