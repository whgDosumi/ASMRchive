<?php
include "library.php";
include "auth.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If no users exist, redirect to setup
if (!has_users()) {
    header("Location: setup.php");
    exit();
}

// If already logged in, redirect to admintools
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: admintools.php");
    exit();
}

$error_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['login_username']) && isset($_POST['login_password'])) {
        $username = trim($_POST['login_username']);
        $password = $_POST['login_password'];
        
        if (verify_user($username, $password)) {
            session_regenerate_id(true); // Prevent session fixation
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['username'] = $username;
            $_SESSION['is_owner'] = is_user_owner($username);
            header("Location: admintools.php");
            exit();
        } else {
            $error_message = "Invalid username or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=1000">
    <title>ASMRchive - Login</title>
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
                    <th colspan="2">Admin Login</th>
                </thead>
                <tbody>
                    <tr>
                        <td class="upload_table_cell"> Username </td>
                        <td class="upload_table_cell">
                            <input type="text" name="login_username" id="upload_title" required>
                        </td>
                    </tr>
                    <tr>
                        <td class="upload_table_cell"> Password </td>
                        <td class="upload_table_cell">
                            <input type="password" name="login_password" id="upload_title" required>
                        </td>
                    </tr>
                    <tr>
                        <td class="upload_table_error_cell" style="color:red;"> <?=$error_message?></td>
                        <td class="upload_table_cell">
                            <input type="submit" name="send" value="Login" class="submit_button">
                        </td>
                    </tr>
                </tbody>
            </table>
        </form>
    </div>
</body>
</html>