<?php

// Customer session name
session_name("CLEAN_NEST_CUSTOMER");

// Allow customer sessions to remain stored for 60 minutes
ini_set('session.gc_maxlifetime', 3600);

// Use a separate folder for customer session files
$customerSessionPath = sys_get_temp_dir()
. DIRECTORY_SEPARATOR . "cleannest_customer_sessions";

// Create the folder if it does not exist
if (!is_dir($customerSessionPath)) {
    mkdir($customerSessionPath, 0777, true);
}

// Check whether the folder is available
if (!is_dir($customerSessionPath) || !is_writable($customerSessionPath)) {
    die("Customer session folder is not writable.");
}

// Set the customer session storage location
session_save_path($customerSessionPath);

// Configure the session cookie
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Lax'
]);

// Start customer session
session_start();

// Check whether the customer has been inactive for 60 minutes
if (isset($_SESSION['user_id'])) {

    if (
    isset($_SESSION['last_activity']) &&
    (time() - $_SESSION['last_activity'] > 3600)
    ) {

        // Clear expired customer session
        $_SESSION = [];

        // Generate a new session ID
        session_regenerate_id(true);
    }

    // Update last activity time
    if (isset($_SESSION['user_id'])) {
        $_SESSION['last_activity'] = time();
    }
}

?>
