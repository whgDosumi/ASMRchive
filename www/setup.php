<?php
include "library.php";
include "auth.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If users already exist, redirect to login
if (has_users()) {
    header("Location: login.php");
    exit();
}

$error_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['setup_username']) && isset($_POST['setup_password']) && isset($_POST['setup_password_confirm'])) {
        $username = trim($_POST['setup_username']);
        $password = $_POST['setup_password'];
        $password_confirm = $_POST['setup_password_confirm'];
        
        if (strlen($username) < 3) {
            $error_message = "Username must be at least 3 characters.";
        } elseif (strlen($password) < 6) {
            $error_message = "Password must be at least 6 characters.";
        } elseif ($password !== $password_confirm) {
            $error_message = "Passwords do not match.";
        } else {
            if (create_user($username, $password)) {
                // Automatically log them in after setup
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['username'] = $username;
                header("Location: admintools.php");
                exit();
            } else {
                $error_message = "Failed to create user. Check permissions on .appdata directory.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=1000">
    <title>ASMRchive - First Time Setup</title>
    <link rel="stylesheet" href="admintools.css">
    <link rel="icon" type="image/x-icon" href="./favicon.ico">
</head>
<body>
    <a href="index.php">
        <div id="backbutton"><img id="backimage" src="images/back.png"></div>
    </a>
    <div id="main">
        <a href="index.php">
            <img src="images/ASMRchive.png" alt="logo" class="top_logo">
        </a>
        <form method="post">
            <table>
                <thead>
                    <th colspan="2">First Time Setup: Create Admin</th>
                </thead>
                <tbody>
                    <tr>
                        <td class="upload_table_cell"> Username<span style="color:red;">*</span> </td>
                        <td class="upload_table_cell">
                            <input type="text" name="setup_username" id="upload_title" required>
                        </td>
                    </tr>
                    <tr>
                        <td class="upload_table_cell"> Password<span style="color:red;">*</span> </td>
                        <td class="upload_table_cell">
                            <input type="password" name="setup_password" id="upload_title" required>
                        </td>
                    </tr>
                    <tr>
                        <td class="upload_table_cell"> Confirm Password<span style="color:red;">*</span> </td>
                        <td class="upload_table_cell">
                            <input type="password" name="setup_password_confirm" id="upload_title" required>
                        </td>
                    </tr>
                    <tr>
                        <td class="upload_table_error_cell" style="color:red;"> <?=$error_message?></td>
                        <td class="upload_table_cell">
                            <input type="submit" name="send" value="Create Admin" class="submit_button">
                        </td>
                    </tr>
                </tbody>
            </table>
        </form>
    </div>
</body>
</html>