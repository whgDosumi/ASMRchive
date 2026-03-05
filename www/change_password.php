<?php
include "library.php";
include "auth.php";

require_login();

$error_message = "";
$success_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error_message = "CSRF validation failed.";
    } elseif (isset($_POST['current_password']) && isset($_POST['new_password']) && isset($_POST['confirm_password'])) {
        $username = $_SESSION['username'];
        $current = $_POST['current_password'];
        $new = $_POST['new_password'];
        $confirm = $_POST['confirm_password'];
        
        if (!verify_user($username, $current)) {
            $error_message = "Current password is incorrect.";
        } elseif (strlen($new) < 6) {
            $error_message = "New password must be at least 6 characters.";
        } elseif ($new !== $confirm) {
            $error_message = "New passwords do not match.";
        } else {
            if (change_password($username, $new)) {
                $success_message = "Password changed successfully.";
            } else {
                $error_message = "Failed to update password.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=1000">
    <title>ASMRchive - Change Password</title>
    <link rel="stylesheet" href="admintools.css">
    <link rel="icon" type="image/x-icon" href="./favicon.ico">
</head>
<body>
    <a href="admintools.php">
        <div id="backbutton"><img id="backimage" src="images/back.png"></div>
    </a>
    <div id="main">
        <a href="index.php">
            <img src="images/ASMRchive.png" alt="logo" class="top_logo">
        </a>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
            <table>
                <thead>
                    <th colspan="2">Change Password</th>
                </thead>
                <tbody>
                    <tr>
                        <td class="upload_table_cell"> Current Password </td>
                        <td class="upload_table_cell">
                            <input type="password" name="current_password" id="upload_title" required>
                        </td>
                    </tr>
                    <tr>
                        <td class="upload_table_cell"> New Password </td>
                        <td class="upload_table_cell">
                            <input type="password" name="new_password" id="upload_title" required>
                        </td>
                    </tr>
                    <tr>
                        <td class="upload_table_cell"> Confirm New Password </td>
                        <td class="upload_table_cell">
                            <input type="password" name="confirm_password" id="upload_title" required>
                        </td>
                    </tr>
                    <tr>
                        <td class="upload_table_error_cell" style="color:red;"> <?=$error_message?></td>
                        <td class="upload_table_cell">
                            <div style="color: #003300; font-weight: bold; font-size: 20px;"><?=$success_message?></div>
                            <input type="submit" name="send" value="Update Password" class="submit_button">
                        </td>
                    </tr>
                </tbody>
            </table>
        </form>
    </div>
</body>
</html>