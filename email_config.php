<?php

// Load PHPMailer from the mail test folder
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/testing_mail/vendor/autoload.php';

// This function sends all CleanNest notification emails
function sendCleanNestEmail($receiver, $subject, $message) {
    $mail = new PHPMailer(true);

    try {

        // Gmail SMTP settings
        $mail->isSMTP();
        $mail->Host     = 'smtp.gmail.com';
        $mail->SMTPAuth = true;

        // Enter your Gmail address
        $mail->Username = 'mohansai4703@gmail.com';

        // Enter your Google App Password (remove spaces)
        $mail->Password = 'kbxxfssculbjhqda';

        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Sender details
        $mail->setFrom('mohansai4703@gmail.com', 'CleanNest');

        // Receiver details
        $mail->addAddress($receiver);

        // Email content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $message;
        $mail->AltBody = strip_tags($message);

        // Send email
        $mail->send();

        return true;

    } catch (Exception $e) {

        // Record the error without stopping the booking process
        error_log("CleanNest email error: " . $mail->ErrorInfo);

        return false;
    }
}

?>
