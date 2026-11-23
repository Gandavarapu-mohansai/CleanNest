<?php
require_once "customer_session.php";

date_default_timezone_set('Asia/Kolkata');

include "config.php";
require_once "email_config.php";

// Check customer login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$user_id = (int)$_SESSION['user_id'];

// Receive selected package
$selected_package = isset($_POST['selected_package'])
? (int)$_POST['selected_package']
: 0;

// Receive selected services
$selected_services = (isset($_POST['selected_services']) && is_array($_POST['selected_services']))
? array_values(array_unique(array_map('intval', $_POST['selected_services'])))
: [];

// Receive service quantities
$service_quantities = (isset($_POST['service_quantities']) && is_array($_POST['service_quantities']))
? $_POST['service_quantities']
: [];
$package_details              = null;
$service_details              = [];
$total_price                  = 0;
$error_message                = "";
$invalid_service_found        = false;
$invalid_quantity_found       = false;
$package_unavailable_services = [];

// Get package details
if ($selected_package > 0) {
    $query = "SELECT * FROM bhk_packages WHERE package_id = ? AND status = 'active'";
    $stmt  = mysqli_prepare($link, $query);
    mysqli_stmt_bind_param($stmt, "i", $selected_package);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($result && mysqli_num_rows($result) === 1) {
        $package_details        = mysqli_fetch_assoc($result);
        $adjusted_package_price = (float)$package_details['price'];

        // Check all services included in the package and deduct inactive ones.
        $unavailable_query = "SELECT service_id, service_name, price, status
            FROM services
            WHERE service_id IN (1, 2, 3, 4)
            ORDER BY service_id ASC";
        $unavailable_result = mysqli_query($link, $unavailable_query);

        if ($unavailable_result) {
            $bhk_type = (int)$package_details['bhk_type'];

            while ($included_service = mysqli_fetch_assoc($unavailable_result)) {
                if ($included_service['status'] !== 'active') {
                    $package_unavailable_services[] = $included_service['service_name'];

                    // Bathroom and Bedroom are included once per bedroom.
                    $service_id       = (int)$included_service['service_id'];
                    $service_quantity = in_array($service_id, [1, 3], true) ? $bhk_type : 1;
                    $adjusted_package_price -= (float)$included_service['price'] * $service_quantity;
                }
            }
        }

        // Keep the adjusted package price at zero or above.
        $adjusted_package_price   = max(0, $adjusted_package_price);
        $package_details['price'] = $adjusted_package_price;
        $total_price += $adjusted_package_price;
    } else {
        $error_message = "Selected package is not available.";
    }
    mysqli_stmt_close($stmt);
}

// Get individual services and calculate their prices
foreach ($selected_services as $service_id) {
    if ($service_id <= 0) {
        $invalid_service_found = true;
        continue;
    }
    $query = "SELECT * FROM services WHERE service_id = ? AND status = 'active'";
    $stmt  = mysqli_prepare($link, $query);
    mysqli_stmt_bind_param($stmt, "i", $service_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($result && mysqli_num_rows($result) === 1) {
        $service  = mysqli_fetch_assoc($result);
        $quantity = 1;

        // Laundry service (ID 12) is charged per pair
        if ((int)$service['service_id'] === 12) {
            $raw_quantity       = $service_quantities[12] ?? 1;
            $validated_quantity = filter_var($raw_quantity, FILTER_VALIDATE_INT);

            if ($validated_quantity === false || $validated_quantity < 1) {
                $invalid_quantity_found = true;
            } else {
                $quantity = $validated_quantity;
            }
        }

        $service['quantity'] = $quantity;
        $service_details[]   = $service;
        $total_price += (float)$service['price'] * $quantity;
    } else {
        $invalid_service_found = true;
    }

    mysqli_stmt_close($stmt);
}
// Do not allow package-included services to be selected separately
if ($selected_package > 0) {
    foreach ($service_details as $service) {
        if (in_array((int)$service['service_id'], [1, 2, 3, 4], true)) {
            $error_message = "Bathroom, Hall, Kitchen and Bedroom services are already included in the package.";
            break;
        }
    }
}

// Validate selection and submitted service data
if ($selected_package > 0 && $package_details === null) {
    $error_message = "Selected package is not available.";
} elseif ($package_details === null && count($service_details) === 0) {
    $error_message = "Please select at least one valid service.";
}

if ($invalid_service_found) {
    $error_message = "One or more selected services are invalid or unavailable. Please select again.";
}

if ($invalid_quantity_found) {
    $error_message = "Please enter a valid Laundry quantity of at least 1 pair.";
}

// Save booking after customer submits the confirmation form
if (isset($_POST['confirm_booking']) && $error_message === "") {
    $booking_date   = trim($_POST['booking_date'] ?? '');
    $payment_method = trim($_POST['payment_method'] ?? '');
    $booking_time   = trim($_POST['booking_time'] ?? '');
    $house_number   = trim($_POST['house_number'] ?? '');
    $street         = trim($_POST['street'] ?? '');
    $area           = trim($_POST['area'] ?? '');
    $city           = trim($_POST['city'] ?? '');
    $state          = trim($_POST['state'] ?? '');
    $pincode        = trim($_POST['pincode'] ?? '');
    $address        = $house_number . ", " . $street . ", " . $area . ", " .
    $city . ", " . $state . ", " . $pincode;

    // Calculate the selected booking date and time.
    $selected_booking_datetime = DateTime::createFromFormat(
    '!Y-m-d H:i',
    $booking_date . ' ' . $booking_time,
    new DateTimeZone('Asia/Kolkata')
    );

    // Customers must book at least 24 hours before the service time.
    $minimum_booking_datetime = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
    $minimum_booking_datetime->modify('+24 hours');

    if (
    $booking_date === '' || $booking_time === '' ||
    $house_number === '' || $street === '' || $area === '' ||
    $city === '' || $state === '' || $pincode === ''
    ) {
        $error_message = "Please fill in all booking details.";

    } elseif (!in_array($payment_method, ['upi', 'cash_on_service'], true)) {
        $error_message = "Please select a valid payment method.";

    } elseif (!preg_match("/^[0-9]{6}$/", $pincode)) {
        $error_message = "Please enter a valid 6-digit pincode.";

    } elseif (
    !preg_match("/^\d{4}-\d{2}-\d{2}$/", $booking_date) ||
    !checkdate(
    (int)substr($booking_date, 5, 2),
    (int)substr($booking_date, 8, 2),
    (int)substr($booking_date, 0, 4)
    ) ||
    !preg_match("/^([01]\d|2[0-3]):[0-5]\d$/", $booking_time)
    ) {
        $error_message = "Please enter a valid booking date and time.";

    } elseif (
    !$selected_booking_datetime ||
    $selected_booking_datetime < $minimum_booking_datetime
    ) {
        $error_message = "Please book at least 24 hours in advance. Select a later date and time.";

    } else {
        // Keep your existing booking-saving code here.
        mysqli_begin_transaction($link);
        $query = "INSERT INTO bookings
            (user_id, package_id, booking_date, booking_time, address, total_price, status)
            VALUES (?, ?, ?, ?, ?, ?, 'pending')";
        $stmt       = mysqli_prepare($link, $query);
        $package_id = $selected_package > 0 ? $selected_package : null;
        mysqli_stmt_bind_param(
        $stmt,
        "iisssd",
        $user_id,
        $package_id,
        $booking_date,
        $booking_time,
        $address,
        $total_price
        );
        if (mysqli_stmt_execute($stmt)) {
            $booking_id = mysqli_insert_id($link);
            mysqli_stmt_close($stmt);
            $query = "INSERT INTO booking_services (booking_id, service_id, price, quantity)
                VALUES (?, ?, ?, ?)";
            $service_stmt   = mysqli_prepare($link, $query);
            $insert_success = true;
            foreach ($service_details as $service) {
                $service_id = (int)$service['service_id'];
                $price      = (float)$service['price']; // unit price; Laundry is ₹25 per pair
                $quantity   = (int)$service['quantity'];

                mysqli_stmt_bind_param(
                $service_stmt,
                "iidi",
                $booking_id,
                $service_id,
                $price,
                $quantity
                );
                if (!mysqli_stmt_execute($service_stmt)) {
                    $insert_success = false;
                    break;
                }
            }
            mysqli_stmt_close($service_stmt);
            if ($insert_success) {

                // Save payment details
                $payment_status = "pending";

                $payment_query = "INSERT INTO payments
                    (booking_id, payment_method, amount, payment_status)
                    VALUES (?, ?, ?, ?)";

                $payment_stmt = mysqli_prepare($link, $payment_query);

                mysqli_stmt_bind_param(
                $payment_stmt,
                "isds",
                $booking_id,
                $payment_method,
                $total_price,
                $payment_status
                );

                if (mysqli_stmt_execute($payment_stmt)) {

                    mysqli_stmt_close($payment_stmt);


                    mysqli_commit($link);

                    // Booking is saved. Now prepare the email notifications.

                    // Get customer details
                    $customer_query = "SELECT first_name, last_name, email, phone
                        FROM users WHERE user_id = ?";

                    $customer_stmt = mysqli_prepare($link, $customer_query);
                    mysqli_stmt_bind_param($customer_stmt, "i", $user_id);
                    mysqli_stmt_execute($customer_stmt);

                    $customer_result = mysqli_stmt_get_result($customer_stmt);
                    $customer        = mysqli_fetch_assoc($customer_result);

                    mysqli_stmt_close($customer_stmt);

                    // Get the admin email address
                    $admin_query = "SELECT email FROM users
                        WHERE role = 'admin' LIMIT 1";

                    $admin_result = mysqli_query($link, $admin_query);
                    $admin        = mysqli_fetch_assoc($admin_result);

                    // Prepare selected services list
                    $services_list = "";

                    if ($package_details !== null) {
                        $services_list .= "<p>Package: " .
                        htmlspecialchars($package_details['package_name']) . "</p>";
                    }

                    foreach ($service_details as $service) {
                        $services_list .= "<p>" .
                        htmlspecialchars($service['service_name']) .
                        " - Quantity: " . (int)$service['quantity'] . "</p>";
                    }

                    // Payment method name
                    $payment_name = ($payment_method == 'upi')
                    ? 'UPI Payment'
                    : 'Cash on Service';

                    // Send booking receipt to customer
                    if ($customer && !empty($customer['email'])) {

                        $customer_name = $customer['first_name'] . " " .
                        $customer['last_name'];

                        $customer_message = "
                            <h2>Booking Received - CleanNest</h2>

                            <p>Hello " . htmlspecialchars($customer_name) . ",</p>

                            <p>Your cleaning service booking has been received successfully.</p>

                            <p><strong>Booking ID:</strong> $booking_id</p>

                            $services_list

                            <p><strong>Booking Date:</strong> $booking_date</p>
                            <p><strong>Booking Time:</strong> $booking_time</p>
                            <p><strong>Address:</strong> " . htmlspecialchars($address) . "</p>
                            <p><strong>Total Amount:</strong> Rs. " .
                        number_format($total_price, 2) . "</p>

                            <p><strong>Payment Method:</strong> $payment_name</p>
                            <p><strong>Booking Status:</strong> Pending Admin Confirmation</p>

                            <br>
                            <p>Thank you for choosing CleanNest!</p>
                            <b>CleanNest Team</b>
                            ";

                        sendCleanNestEmail(
                        $customer['email'],
                        "CleanNest Booking Receipt - Booking #$booking_id",
                        $customer_message
                        );
                    }

                    // Send new booking notification to admin
                    if ($admin && !empty($admin['email']) && $customer) {

                        $admin_message = "
                            <h2>New Booking Received - CleanNest</h2>

                            <p>A customer has submitted a new booking.</p>

                            <p><strong>Booking ID:</strong> $booking_id</p>

                            <p><strong>Customer:</strong> " .
                        htmlspecialchars($customer_name) . "</p>

                            <p><strong>Phone:</strong> " .
                        htmlspecialchars($customer['phone']) . "</p>

                            <p><strong>Email:</strong> " .
                        htmlspecialchars($customer['email']) . "</p>

                            $services_list

                            <p><strong>Date:</strong> $booking_date</p>
                            <p><strong>Time:</strong> $booking_time</p>
                            <p><strong>Address:</strong> " .
                        htmlspecialchars($address) . "</p>

                            <p><strong>Total Amount:</strong> Rs. " .
                        number_format($total_price, 2) . "</p>

                            <p><strong>Payment Method:</strong> $payment_name</p>

                            <p>Please log in to the admin portal to review this booking.</p>
                            ";

                        sendCleanNestEmail(
                        $admin['email'],
                        "New CleanNest Booking #$booking_id",
                        $admin_message
                        );
                    }

                    // Continue to booking success page
                    header("Location: booking_success.php?booking_id=" . $booking_id);
                    exit();
                } else {

                    mysqli_stmt_close($payment_stmt);
                    mysqli_rollback($link);

                    $error_message = "Unable to save payment details.";
                }

            } else {

                mysqli_rollback($link);
                $error_message = "Unable to save selected services.";
            }
        } else {
            mysqli_stmt_close($stmt);
            mysqli_rollback($link);
            $error_message = "Unable to save booking details.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Book Your Service - CleanNest</title>
        <link rel="stylesheet" href="style.css">
    </head>
    <body class="booking-page">

        <!-- Navigation -->
        <header>
            <a href="index.html" class="logo">
                <img src="images/cleannest-logo.png" alt="CleanNest Logo">
            </a>
            <nav>
                <a href="index.html">Home</a>
                <a href="services.php">Services</a>
                <a href="logout.php">Logout</a>
            </nav>
        </header>

        <!-- Booking Summary -->
        <section>
            <h1>Complete Your Booking</h1>
            <?php if (!empty($error_message)) { ?>
                <p class="error-message">
                    <?php echo htmlspecialchars($error_message); ?>
                </p>
            <?php } ?>
            <h2>Your Selected Services</h2>
            <table border="1" cellpadding="10">
                <tr>
                    <th>Selected Item</th>
                    <th>Quantity</th>
                    <th>Amount</th>
                </tr>
                <?php if ($package_details !== null) { ?>
                    <tr>
                        <td>
                            <?php echo htmlspecialchars($package_details['package_name']); ?>
                            <?php if (count($package_unavailable_services) > 0) { ?>
                                <div style="color:#b42318; background:#fff0ed; padding:8px; margin-top:8px; border-radius:5px;">
                                    <strong>Currently not included:</strong>
                                    <?php foreach ($package_unavailable_services as $unavailable_name) { ?>
                                        <div>
                                            <?php echo htmlspecialchars($unavailable_name); ?> is currently not available and is not included in this package.
                                        </div>
                                    <?php } ?>
                                </div>
                            <?php } ?>
                        </td>
                        <td>Package</td>
                        <td>₹<?php echo number_format((float)$package_details['price'], 2); ?></td>
                    </tr>
                <?php } ?>
                <?php foreach ($service_details as $service) { ?>
                    <tr>
                        <td><?php echo htmlspecialchars($service['service_name']); ?></td>
                        <td>
                            <?php
                            if ((int)$service['service_id'] === 12) {
                                echo (int)$service['quantity'] . ' pair(s) × ₹' . number_format((float)$service['price'], 2);
                            } else {
                                echo '1';
                            }
                            ?>
                        </td>
                        <td>₹<?php echo number_format((float)$service['price'] * (int)$service['quantity'], 2); ?></td>
                    </tr>
                <?php } ?>
                <tr>
                    <th colspan="2">Total Price</th>
                    <th>₹<?php echo number_format($total_price, 2); ?></th>
                </tr>
            </table>
            <br>
            <br>

            <!-- Booking Details Form -->
            <h2>Enter Your Booking Details</h2>
            <form method="POST" action="booking.php">

                <!-- Preserve Package ID -->
                <input type="hidden"
                    name="selected_package"
                    value="<?php echo $selected_package; ?>">

                <!-- Preserve Selected Service IDs -->
                <?php foreach ($service_details as $service) { ?>
                    <input type="hidden"
                        name="selected_services[]"
                        value="<?php echo (int)$service['service_id']; ?>">
                    <input type="hidden"
                        name="service_quantities[<?php echo (int)$service['service_id']; ?>]"
                        value="<?php echo (int)$service['quantity']; ?>">
                <?php } ?>

                <!-- Payment Method -->
                <h2>Select Payment Method:</h2>
                <label>
                    <input type="radio" name="payment_method"
                        value="cash_on_service" required>
                    Cash on Service
                </label>
                <br>
                <br>
                <label>
                    <input type="radio" name="payment_method"
                        value="upi" required>
                    UPI Payment
                </label>
                <br>
                <br>

                <!-- Booking Date -->
                <label>Booking Date:</label>
                <br>
                <input type="date"
                    id="booking_date"
                    name="booking_date"
                    required>
                <br>
                <br>

                <!-- Booking Time -->
                <label>Booking Time:</label>
                <br>
                <input type="time"
                    id="booking_time"
                    name="booking_time"
                    required>
                <br>
                <br>

                <!-- Customer Address -->
                <h2>Address:</h2>
                <label>House / Flat Number:</label>
                <br>
                <input type="text"
                    name="house_number"
                    placeholder="Example: Flat No. 203"
                    required>
                <br>
                <br>
                <label>Street Name / Number:</label>
                <br>
                <input type="text"
                    name="street"
                    placeholder="Example: 2nd Main Road"
                    required>
                <br>
                <br>
                <label>Area / Locality:</label>
                <br>
                <input type="text"
                    name="area"
                    placeholder="Example: Madhapur"
                    required>
                <br>
                <br>
                <label>City:</label>
                <br>
                <input type="text"
                    name="city"
                    placeholder="Example: Hyderabad"
                    required>
                <br>
                <br>
                <label>State:</label>
                <br>
                <input type="text"
                    name="state"
                    placeholder="Example: Telangana"
                    required>
                <br>
                <br>
                <label>Pincode:</label>
                <br>
                <input type="text"
                    name="pincode"
                    placeholder="Enter 6-digit pincode"
                    maxlength="6"
                    pattern="[0-9]{6}"
                    required>
                <br>
                <br>
                <button type="submit" name="confirm_booking">
                    Confirm Booking
                </button>
            </form>

            <script>
                // Set the earliest selectable date and time to 24 hours from now.
                const bookingDateInput = document.getElementById('booking_date');
                const bookingTimeInput = document.getElementById('booking_time');

                function updateBookingMinimum() {
                    const minimumDateTime = new Date(Date.now() + (24 * 60 * 60 * 1000));
                    const year = minimumDateTime.getFullYear();
                    const month = String(minimumDateTime.getMonth() + 1).padStart(2, '0');
                    const day = String(minimumDateTime.getDate()).padStart(2, '0');
                    const minimumDate = year + '-' + month + '-' + day;
                    const minimumTime = String(minimumDateTime.getHours()).padStart(2, '0') + ':' +
                    String(minimumDateTime.getMinutes()).padStart(2, '0');

                    bookingDateInput.min = minimumDate;

                    // If the customer selects the first allowed date, also restrict the time.
                    if (bookingDateInput.value === minimumDate) {
                        bookingTimeInput.min = minimumTime;
                    } else {
                        bookingTimeInput.min = '00:00';
                    }
                }

                bookingDateInput.addEventListener('change', updateBookingMinimum);
                updateBookingMinimum();
            </script>
        </section>
    </body>
</html>