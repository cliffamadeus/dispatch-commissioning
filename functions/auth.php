<?php

// ------------------------------------------------------
// Login
// ------------------------------------------------------

function loginUser($pdo, $login, $password)
{
    $sql = "
        SELECT
            user_id,
            user_email,
            user_username,
            user_password,
            user_role,
            user_verified
        FROM users
        WHERE user_email = :login
        OR user_username = :login
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        'login' => $login
    ]);

    $user = $stmt->fetch();

    // User not found
    if (!$user) {
        return false;
    }

    // Invalid password
    if (!password_verify($password, $user['user_password'])) {
        return false;
    }

    // Account not verified by admin
    if (!$user['user_verified']) {
        return 'unverified';
    }

    // Check if user already has an active session
    if (hasActiveUserSession($pdo, $user['user_id'])) {
        return 'active_session';
    }

    // Store user information in PHP session
    $_SESSION['user_id']       = $user['user_id'];
    $_SESSION['user_email']    = $user['user_email'];
    $_SESSION['user_username'] = $user['user_username'];
    $_SESSION['user_role']     = $user['user_role'];

    // Create database session record
    $_SESSION['session_id'] = startUserSession($pdo);

    // Log complete login attempt
    logActivity(
        $pdo,$_SESSION['user_id'],
        $_SESSION['user_email'],
        $_SESSION['user_role'] . '-login',
        'success'
    );

    return true;
}

// ------------------------------------------------------
// Authentication
// ------------------------------------------------------

function requireLogin()
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }

    checkSessionTimeout();
}


function requireRole($role)
{
    requireLogin();

    if ($_SESSION['user_role'] !== $role) {
        http_response_code(403);
        die('Access denied.');
    }
}


// ------------------------------------------------------
// Logout
// ------------------------------------------------------

function logoutUser($pdo)
{
    // Log logout activity
    if (isset($_SESSION['user_id'])) {

        logActivity(
            $pdo,
            $_SESSION['user_id'],
            $_SESSION['user_email'] ?? null,
            $_SESSION['user_role'] . '-logout',
            'success'
        );

    }

    // End database session
    endUserSession($pdo);

    // Clear PHP session data
    $_SESSION = [];

    // Destroy PHP session
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}
?>
