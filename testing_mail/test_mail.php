<?php

// Load PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

// Create mail object
$mail = new PHPMailer(true);

try {

    // Gmail SMTP settings
    $mail->isSMTP();
    $mail->Host     = 'smtp.gmail.com';
    $mail->SMTPAuth = true;

    // Your Gmail address
    $mail->Username = 'mohansai4703@gmail.com';

    // Your 16-character Google App Password
    $mail->Password = 'exxsikwslheycbbe';

    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    // Sender details
    $mail->setFrom('mohansai4703@gmail.com', 'CleanNest');

    // Friend's email address
    $mail->addAddress('rm63bhargav@gmail.com');

    // Email content
    $mail->isHTML(true);
    $mail->Subject = 'CleanNest Email Test';

    $mail->Body = '
        <h2>Welcome to CleanNest!</h2>
        <p>This is a test email sent successfully using PHP and PHPMailer.</p>
        <p>Our email notification system is working!</p>
        <br>
        <b>CleanNest Team</b>
        ';

    $mail->AltBody = 'This is a test email from CleanNest.';

    // Send email
    $mail->send();

    echo "Email sent successfully!";

} catch (Exception $e) {

    echo "Email could not be sent. Error: " . $mail->ErrorInfo;

}
?>