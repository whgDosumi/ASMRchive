<?php
// auth.php - Handles user authentication and management

$USER_DB_PATH = '/var/ASMRchive/.appdata/users.json';

// Initialize the user database if it doesn't exist
function init_user_db() {
    global $USER_DB_PATH;
    if (!file_exists($USER_DB_PATH)) {
        write_file($USER_DB_PATH, json_encode([]), 0660);
    }
}

// Check if there are any users in the database
function has_users() {
    global $USER_DB_PATH;
    if (!file_exists($USER_DB_PATH)) {
        return false;
    }
    $users = json_decode(file_get_contents($USER_DB_PATH), true);
    return is_array($users) && count($users) > 0;
}

// Get all users
function get_users() {
    global $USER_DB_PATH;
    init_user_db();
    return json_decode(file_get_contents($USER_DB_PATH), true);
}

// Save users to database
function save_users($users) {
    global $USER_DB_PATH;
    return write_file($USER_DB_PATH, json_encode($users, JSON_PRETTY_PRINT), 0660);
}

// Create a new user
function create_user($username, $password) {
    $users = get_users();
    if (isset($users[$username])) {
        return false; // User already exists
    }
    
    $is_first_user = count($users) === 0;
    
    $users[$username] = [
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'created_at' => time(),
        'is_owner' => $is_first_user
    ];
    return save_users($users) !== false;
}

// Verify a user's password
function verify_user($username, $password) {
    $users = get_users();
    if (!isset($users[$username])) {
        return false;
    }
    return password_verify($password, $users[$username]['password_hash']);
}

// Check if a specific user is owner
function is_user_owner($username) {
    $users = get_users();
    return isset($users[$username]['is_owner']) && $users[$username]['is_owner'] === true;
}

// Delete a user
function delete_user($username) {
    $users = get_users();
    if (!isset($users[$username])) {
        return false;
    }
    unset($users[$username]);
    return save_users($users) !== false;
}

// Change a user's password
function change_password($username, $new_password) {
    $users = get_users();
    if (!isset($users[$username])) {
        return false;
    }
    $users[$username]['password_hash'] = password_hash($new_password, PASSWORD_DEFAULT);
    return save_users($users) !== false;
}

// Enforce login on a page
function require_login() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check if system needs setup
    if (!has_users()) {
        header("Location: setup.php");
        exit();
    }
    
    // Check if logged in
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header("Location: login.php");
        exit();
    }
}

// Check if current user is owner
function is_owner() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (isset($_SESSION['is_owner'])) {
        return $_SESSION['is_owner'] === true;
    }
    if (isset($_SESSION['username'])) {
        $_SESSION['is_owner'] = is_user_owner($_SESSION['username']);
        return $_SESSION['is_owner'];
    }
    return false;
}
?>
