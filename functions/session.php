<?php 

// Start User Session
function startUserSession($pdo)
{
    if (!isset($_SESSION['user_id'])) {
        return false;
    }

    $user_id = $_SESSION['user_id'];

    $stmt = $pdo->prepare("
        INSERT INTO user_sessions (
            user_id,
            session_start
        )
        VALUES (
            :user_id,
            NOW()
        )
    ");

    $stmt->execute([
        'user_id' => $user_id
    ]);

    return $pdo->lastInsertId();
}


// End User Session
function endUserSession($pdo)
{
    if (!isset($_SESSION['session_id'])) {
        return false;
    }

    $session_id = $_SESSION['session_id'];

    $stmt = $pdo->prepare("
        UPDATE user_sessions
        SET
            session_end = NOW(),
            session_duration = TIMESTAMPDIFF(
                SECOND,
                session_start,
                NOW()
            )
        WHERE session_id = :session_id
    ");

    return $stmt->execute([
        'session_id' => $session_id
    ]);
}


// Get User Session Duration
function getSessionDuration($pdo)
{
    if (!isset($_SESSION['session_id'])) {
        return 0;
    }

    $session_id = $_SESSION['session_id'];

    $stmt = $pdo->prepare("
        SELECT TIMESTAMPDIFF(
            SECOND,
            session_start,
            COALESCE(session_end, NOW())
        )
        FROM user_sessions
        WHERE session_id = :session_id
    ");

    $stmt->execute([
        'session_id' => $session_id
    ]);

    return (int) $stmt->fetchColumn();
}


// Function check user for active session
function hasActiveUserSession($pdo, $user_id)
{
    $stmt = $pdo->prepare("
        SELECT session_id
        FROM user_sessions
        WHERE user_id = :user_id
        AND session_end IS NULL
        LIMIT 1
    ");

    $stmt->execute([
        'user_id' => $user_id
    ]);

    return (bool) $stmt->fetchColumn();
}


// Session Timeout
function checkSessionTimeout()
{
    global $pdo;

    // 10 seconds for testing
    // 1800 seconds = 30 minutes
    $timeout = 1800;

    // Check last activity
    if (isset($_SESSION['last_activity'])) {

        $inactive = time() - $_SESSION['last_activity'];

        if ($inactive >= $timeout) {

            // Log logout activity
            if (isset($_SESSION['user_id'])) {

                logActivity(
                    $pdo,
                    $_SESSION['user_id'],
                    $_SESSION['user_email'] ?? null,
                    'logout-session-timeout',
                    'success'
                );
            }

            // End database session
            endUserSession($pdo);

            // Destroy PHP session
            session_unset();
            session_destroy();

            // Redirect to login
            header(
                'Location: ' .
                BASE_URL .
                '/index.php?timeout=1'
            );

            exit;
        }
    }

    // Update last activity
    $_SESSION['last_activity'] = time();
}

?>