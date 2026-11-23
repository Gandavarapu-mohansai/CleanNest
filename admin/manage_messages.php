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

// Save admin reply
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_reply'])) {

    $message_id  = intval($_POST['message_id']);
    $admin_reply = trim($_POST['admin_reply']);

    if ($message_id <= 0 || $admin_reply == "") {
        $error = "Please enter a reply.";
    } else {

        $stmt = $link->prepare("
            UPDATE contact_messages
            SET admin_reply = ?,
            reply_status = 'Replied',
            replied_at = NOW()
            WHERE message_id = ?
            ");

        if ($stmt) {
            $stmt->bind_param("si", $admin_reply, $message_id);

            if ($stmt->execute()) {
                if ($stmt->affected_rows >= 0) {
                    $message = "Reply saved successfully.";
                } else {
                    $error = "Unable to save reply.";
                }
            } else {
                $error = "Unable to save reply.";
            }

            $stmt->close();
        } else {
            $error = "Database error: " . $link->error;
        }
    }
}

// Delete customer message
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_message'])) {

    $message_id = intval($_POST['message_id']);

    $stmt = $link->prepare("
        DELETE FROM contact_messages
        WHERE message_id = ?
        ");

    if ($stmt) {
        $stmt->bind_param("i", $message_id);

        if ($stmt->execute()) {
            $message = "Message deleted successfully.";
        } else {
            $error = "Unable to delete message.";
        }

        $stmt->close();
    } else {
        $error = "Database error: " . $link->error;
    }
}

// Get all customer messages
$query = "
    SELECT
    message_id,
    name,
    email,
    mobile,
    subject,
    message,
    admin_reply,
    reply_status,
    created_at,
    replied_at
    FROM contact_messages
    ORDER BY created_at DESC
    ";

$result = mysqli_query($link, $query);

if (!$result) {
    die("Unable to load messages: " . mysqli_error($link));
}
?>

<!DOCTYPE html>
<html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>Manage Messages - CleanNest</title>

        <style>
            * {
                box-sizing: border-box;
                margin: 0;
                padding: 0;
            }

            body {
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

            .message-page {
                width: 94%;
                max-width: 1400px;
                margin: 35px auto;
            }

            .page-heading {
                margin-bottom: 25px;
            }

            .page-heading h1 {
                color: #184d40;
                font-size: 29px;
                margin-bottom: 8px;
            }

            .page-heading p {
                color: #71817d;
                font-size: 14px;
            }

            .alert {
                padding: 13px 16px;
                border-radius: 7px;
                margin-bottom: 20px;
            }

            .success {
                background: #e4f6ed;
                color: #187447;
            }

            .error {
                background: #fff0f0;
                color: #c0392b;
            }

            .message-container {
                background: white;
                padding: 25px;
                border-radius: 12px;
                box-shadow: 0 3px 14px rgba(0,0,0,0.05);
                border: 1px solid #e5eeeb;
            }

            .section-heading {
                color: #184d40;
                font-size: 20px;
                margin-bottom: 22px;
            }

            .table-wrapper {
                width: 100%;
                overflow-x: auto;
            }

            table {
                width: 100%;
                min-width: 1250px;
                border-collapse: collapse;
            }

            th {
                background: #e8f4f0;
                color: #28594d;
                padding: 14px 12px;
                text-align: left;
                font-size: 13px;
                border-bottom: 2px solid #d4e7df;
            }

            td {
                padding: 14px 12px;
                border-bottom: 1px solid #e8eeeb;
                font-size: 13px;
                vertical-align: top;
            }

            tbody tr:hover {
                background: #f8fbfa;
            }

            .message-text {
                min-width: 180px;
                max-width: 250px;
                line-height: 1.6;
                white-space: normal;
                overflow-wrap: anywhere;
            }

            .reply-form {
                min-width: 230px;
            }

            .reply-form textarea {
                width: 100%;
                min-height: 95px;
                padding: 10px;
                border: 1px solid #ccdcd6;
                border-radius: 6px;
                resize: vertical;
                font-family: Arial, sans-serif;
                font-size: 13px;
                margin-bottom: 8px;
            }

            .reply-form textarea:focus {
                outline: none;
                border-color: #16866f;
            }

            .save-btn {
                background: #16866f;
                color: white;
                border: none;
                padding: 9px 14px;
                border-radius: 5px;
                cursor: pointer;
                font-weight: bold;
            }

            .save-btn:hover {
                background: #106b59;
            }

            .delete-btn {
                background: #d9534f;
                color: white;
                border: none;
                padding: 9px 13px;
                border-radius: 5px;
                cursor: pointer;
                font-weight: bold;
            }

            .delete-btn:hover {
                background: #b52b27;
            }

            .status {
                display: inline-block;
                padding: 6px 10px;
                border-radius: 15px;
                font-size: 12px;
                font-weight: bold;
            }

            .status-pending {
                background: #fff2d9;
                color: #9a6500;
            }

            .status-replied {
                background: #e3f6eb;
                color: #18804c;
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

                .message-page {
                    width: 95%;
                    margin: 22px auto;
                }

                .message-container {
                    padding: 15px;
                }
            }
        </style>
    </head>

    <body>

        <header class="topbar">
            <h2>Manage Messages</h2>

            <a href="admin_dashboard.php" class="back-btn">
                Back to Dashboard
            </a>
        </header>

        <main class="message-page">

            <div class="page-heading">
                <h1>Customer Messages</h1>
                <p>View customer enquiries and reply to their messages.</p>
            </div>

            <?php if ($message != ""): ?>
                <div class="alert success">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($error != ""): ?>
                <div class="alert error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <section class="message-container">

                <h2 class="section-heading">All Messages</h2>

                <div class="table-wrapper">

                    <table>

                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Customer</th>
                                <th>Contact</th>
                                <th>Subject</th>
                                <th>Customer Message</th>
                                <th>Date</th>
                                <th>Reply Status</th>
                                <th>Admin Reply</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php if (mysqli_num_rows($result) > 0): ?>

                            <?php while ($row = mysqli_fetch_assoc($result)): ?>

                            <tr>

                                <td>
                                    <?php echo $row['message_id']; ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($row['name']); ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($row['email']); ?>
                                    <br>
                                    <br>
                                    <?php echo htmlspecialchars($row['mobile']); ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($row['subject']); ?>
                                </td>

                                <td class="message-text">
                                    <?php
                                    echo nl2br(htmlspecialchars($row['message']));
                                    ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($row['created_at']); ?>
                                </td>

                                <td>

                                    <?php if ($row['reply_status'] == 'Replied'): ?>

                                        <span class="status status-replied">
                                            Replied
                                        </span>

                                    <?php else: ?>

                                        <span class="status status-pending">
                                            Pending
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td>

                                    <form method="POST" class="reply-form">

                                        <input type="hidden"
                                            name="message_id"
                                            value="<?php echo $row['message_id']; ?>">

                                        <textarea
                                            name="admin_reply"
                                            placeholder="Type your reply here..."
                                            required><?php
                                            echo htmlspecialchars($row['admin_reply'] ?? '');
                                        ?></textarea>
                                        <button type="submit"
                                            name="save_reply"
                                            class="save-btn">
                                            Save Reply
                                        </button>
                                    </form>
                                </td>
                                <td>
                                    <form method="POST"
                                        onsubmit="return confirm('Are you sure you want to delete this message?');">
                                        <input type="hidden"
                                            name="message_id"
                                            value="<?php echo $row['message_id']; ?>">
                                        <button type="submit"
                                            name="delete_message"
                                            class="delete-btn">
                                            Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                            <?php else: ?>
                            <tr>
                                <td colspan="9" class="empty-message">
                                    No customer messages found.
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </body>
</html>
