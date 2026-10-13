<?php

require '../../config/config.php';

requireRole('user');

requireCsrfToken();

$section = $_GET['section'] ?? 'dashboard';
$action  = $_GET['action'] ?? '';

//-----------------------------------------------------------
// Password Reset Request
//-----------------------------------------------------------

if ($action === 'request_reset') {

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        $userId = $_SESSION['user_id'] ?? null;

        if ($userId) {

            // Check if user already has a pending request
            $stmt = $pdo->prepare("
                SELECT reset_request_id
                FROM password_reset_requests
                WHERE user_id = ?
                    AND reset_request_status = 'pending'
                LIMIT 1
            ");

            $stmt->execute([$userId]);

            if ($stmt->fetch()) {

                $_SESSION['alert'] =
                    'You already have a pending password reset request. '
                    . 'Please wait for an administrator to handle it.';

            } else {

                // Insert workflow row
                $stmt = $pdo->prepare("
                    INSERT INTO password_reset_requests (
                        user_id,
                        reset_request_status
                    )
                    VALUES (?, 'pending')
                ");

                $stmt->execute([$userId]);

                // Audit trail
                logActivity(
                    $pdo,
                    $userId,
                    $_SESSION['user_email'] ?? null,
                    'request-password-reset',
                    'success'
                );

                $_SESSION['alert'] =
                    'Your password reset request has been submitted. '
                    . 'Please wait for an administrator to reset your password.';
            }
        }

        header("Location: index.php?section=reset");
        exit;
    }
}

//-----------------------------------------------------------
// Fetch user's own reset request history
//-----------------------------------------------------------

$myResetRequests = [];

if (isset($_SESSION['user_id'])) {

    $stmt = $pdo->prepare("
        SELECT
            reset_request_id,
            reset_request_status,
            reset_request_created_at,
            reset_request_resolved_at
        FROM password_reset_requests
        WHERE user_id = ?
        ORDER BY reset_request_id DESC
        LIMIT 10
    ");

    $stmt->execute([$_SESSION['user_id']]);

    $myResetRequests = $stmt->fetchAll();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard</title>
</head>
<body>

    <form method="POST" action="../../auth/signout.php">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
        <button type="submit">Sign Out</button>
    </form>

    <h1>User Dashboard</h1>

    <nav>
        <a href="index.php">Dashboard</a>
        |
        <a href="index.php?section=reset">Password Reset</a>
    </nav>

    <hr>

    <?php if ($section === 'reset'): ?>

        <h2>Password Reset</h2>

        <p>
            If you've forgotten your password or would like it reset,
            submit a request below. An administrator will reset it for you.
        </p>

        <?php
        // Determine if there's a pending request
        $hasPending = false;
        foreach ($myResetRequests as $r) {
            if ($r['reset_request_status'] === 'pending') {
                $hasPending = true;
                break;
            }
        }
        ?>

        <?php if ($hasPending): ?>

            <p>
                <strong style="color: orange;">
                    You already have a pending password reset request.
                </strong>
            </p>

        <?php else: ?>

            <form method="POST" action="index.php?section=reset&action=request_reset">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">

                <button type="submit">Request Password Reset</button>

                <a href="index.php">Cancel</a>
            </form>

        <?php endif; ?>

        <h3>Your Recent Requests</h3>

        <?php if (empty($myResetRequests)): ?>

            <p>No reset requests yet.</p>

        <?php else: ?>

            <table border="1" cellpadding="8">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Status</th>
                        <th>Requested At</th>
                        <th>Resolved At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($myResetRequests as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars($r['reset_request_id']) ?></td>
                            <td>
                                <?php if ($r['reset_request_status'] === 'pending'): ?>
                                    <strong style="color: orange;">Pending</strong>
                                <?php else: ?>
                                    <strong style="color: green;">Done</strong>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($r['reset_request_created_at']) ?></td>
                            <td>
                                <?= $r['reset_request_resolved_at']
                                    ? htmlspecialchars($r['reset_request_resolved_at'])
                                    : '—' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

        <?php endif; ?>

    <?php else: ?>

        <h2>Welcome, <?= htmlspecialchars($_SESSION['user_username'] ?? 'User') ?></h2>

        <p>
            You are signed in as
            <strong><?= htmlspecialchars($_SESSION['user_email'] ?? '') ?></strong>.
        </p>

        <p>
            Need a new password?
            <a href="index.php?section=reset">Request a password reset</a>.
        </p>

    <?php endif; ?>

</body>

<?php if (isset($_SESSION['alert'])): ?>

    <script>
        alert(<?= json_encode($_SESSION['alert']) ?>);
    </script>

    <?php unset($_SESSION['alert']); ?>

<?php endif; ?>

</html>