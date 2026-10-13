<?php

require '../../config/config.php';

requireRole('admin');

requireCsrfToken();

// Determine current section
$section = $_GET['section'] ?? 'users';

// CRUD Operations
$action = $_GET['action'] ?? '';

//-----------------------------------------------------------
// Users
//-----------------------------------------------------------

// Fetch users
if ($section === 'users') {
    $stmt = $pdo->query("
        SELECT *
        FROM users
        ORDER BY user_id DESC
    ");

    $users = $stmt->fetchAll();
}

// Fetch pending password reset requests
if ($section === 'reset_requests') {

    $stmt = $pdo->query("
        SELECT
            password_reset_requests.reset_request_id,
            password_reset_requests.user_id,
            password_reset_requests.reset_request_status,
            password_reset_requests.reset_request_created_at,
            password_reset_requests.reset_request_resolved_at,

            users.user_email,
            users.user_username

        FROM password_reset_requests

        INNER JOIN users
            ON password_reset_requests.user_id = users.user_id

        ORDER BY
            CASE password_reset_requests.reset_request_status
                WHEN 'pending' THEN 0
                ELSE 1
            END,
            password_reset_requests.reset_request_id DESC
    ");

    $resetRequests = $stmt->fetchAll();
}

// Create User
if ($section === 'users' && $action === 'create') {

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        $email    = trim($_POST['user_email'] ?? '');
        $username = trim($_POST['user_username'] ?? '');
        $password = $_POST['user_password'] ?? '';
        $role     = $_POST['user_role'] ?? 'user';

        // Validate role whitelist
        $allowedRoles = ['admin', 'manager', 'user'];
        if (!in_array($role, $allowedRoles, true)) {
            $role = 'user';
        }

        if ($email !== '' && $username !== '' && $password !== '') {

            // Check for duplicate email/username
            $stmt = $pdo->prepare("
                SELECT user_id
                FROM users
                WHERE user_email = ? OR user_username = ?
                LIMIT 1
            ");
            $stmt->execute([$email, $username]);

            if ($stmt->fetch()) {

                $_SESSION['alert'] = 'Email or username already exists.';

            } else {

                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $pdo->prepare("
                    INSERT INTO users (
                        user_email,
                        user_username,
                        user_password,
                        user_role,
                        user_verified
                    )
                    VALUES (?, ?, ?, ?, 0)
                ");

                $stmt->execute([
                    $email,
                    $username,
                    $hashedPassword,
                    $role
                ]);

                if (isset($_SESSION['user_id'])) {
                    logActivity(
                        $pdo,
                        $_SESSION['user_id'],
                        $_SESSION['user_email'] ?? null,
                        'create-user',
                        'success'
                    );
                }

                $_SESSION['alert'] = 'User created successfully. Account is pending verification.';

                header("Location: users.php?section=users");
                exit;
            }
        } else {
            $_SESSION['alert'] = 'Please fill in all required fields.';
        }
    }
}

// Update User
if ($section === 'users' && $action === 'update') {

    $userId = (int) ($_GET['id'] ?? 0);

    // Retrieve User Information
    $stmt = $pdo->prepare("
        SELECT *
        FROM users
        WHERE user_id = ?
    ");

    $stmt->execute([$userId]);

    $user = $stmt->fetch();

    if (!$user) {
        die("User not found.");
    }

    // Update User Info
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        $email    = trim($_POST['user_email'] ?? '');
        $username = trim($_POST['user_username'] ?? '');
        $password = $_POST['user_password'] ?? '';
        $role     = $_POST['user_role'] ?? 'user';

        $allowedRoles = ['admin', 'manager', 'user'];
        if (!in_array($role, $allowedRoles, true)) {
            $role = 'user';
        }

        if ($email !== '' && $username !== '') {

            // Check for duplicates excluding current user
            $stmt = $pdo->prepare("
                SELECT user_id
                FROM users
                WHERE (user_email = ? OR user_username = ?)
                    AND user_id != ?
                LIMIT 1
            ");
            $stmt->execute([$email, $username, $userId]);

            if ($stmt->fetch()) {

                $_SESSION['alert'] = 'Email or username already in use.';

            } else {

                if ($password !== '') {
                    // Update with new password
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                    $stmt = $pdo->prepare("
                        UPDATE users
                        SET
                            user_email = ?,
                            user_username = ?,
                            user_password = ?,
                            user_role = ?
                        WHERE user_id = ?
                    ");

                    $stmt->execute([
                        $email,
                        $username,
                        $hashedPassword,
                        $role,
                        $userId
                    ]);
                } else {
                    // Update without changing password
                    $stmt = $pdo->prepare("
                        UPDATE users
                        SET
                            user_email = ?,
                            user_username = ?,
                            user_role = ?
                        WHERE user_id = ?
                    ");

                    $stmt->execute([
                        $email,
                        $username,
                        $role,
                        $userId
                    ]);
                }

                if (isset($_SESSION['user_id'])) {
                    logActivity(
                        $pdo,
                        $_SESSION['user_id'],
                        $_SESSION['user_email'] ?? null,
                        'update-user',
                        'success'
                    );
                }

                $_SESSION['alert'] = 'User updated successfully.';

                header("Location: users.php?section=users");
                exit;
            }
        }
    }
}

// Verify User
if ($section === 'users' && $action === 'verify') {

    $userId = (int) ($_GET['id'] ?? 0);

    $stmt = $pdo->prepare("
        UPDATE users
        SET user_verified = 1
        WHERE user_id = ?
    ");

    $stmt->execute([$userId]);

    if (isset($_SESSION['user_id'])) {
        logActivity(
            $pdo,
            $_SESSION['user_id'],
            $_SESSION['user_email'] ?? null,
            'verify-user',
            'success'
        );
    }

    $_SESSION['alert'] = 'User verified successfully.';

    header("Location: users.php?section=users");
    exit;
}

// Unverify User
if ($section === 'users' && $action === 'unverify') {

    $userId = (int) ($_GET['id'] ?? 0);

    $stmt = $pdo->prepare("
        UPDATE users
        SET user_verified = 0
        WHERE user_id = ?
    ");

    $stmt->execute([$userId]);

    if (isset($_SESSION['user_id'])) {
        logActivity(
            $pdo,
            $_SESSION['user_id'],
            $_SESSION['user_email'] ?? null,
            'unverify-user',
            'success'
        );
    }

    $_SESSION['alert'] = 'User unverified successfully.';

    header("Location: users.php?section=users");
    exit;
}

// Delete User
if ($section === 'users' && $action === 'delete') {

    $userId = (int) ($_GET['id'] ?? 0);

    // Prevent admin from deleting themselves
    if (isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] === $userId) {

        $_SESSION['alert'] = 'You cannot delete your own account.';

        header("Location: users.php?section=users");
        exit;
    }

    // Retrieve the target user to show in confirmation
    $stmt = $pdo->prepare("
        SELECT user_id, user_email, user_username, user_role
        FROM users
        WHERE user_id = ?
    ");

    $stmt->execute([$userId]);

    $userToDelete = $stmt->fetch();

    if (!$userToDelete) {

        $_SESSION['alert'] = 'User not found.';

        header("Location: users.php?section=users");
        exit;
    }

    // ------------------------------------------------------------
    // CONSTRAINT: Cannot delete the last user of a given role
    // ------------------------------------------------------------
    $roleCounts = [
        'admin'   => 0,
        'manager' => 0,
        'user'    => 0,
    ];

    $stmt = $pdo->query("
        SELECT user_role, COUNT(*) AS total
        FROM users
        GROUP BY user_role
    ");

    foreach ($stmt->fetchAll() as $row) {
        if (isset($roleCounts[$row['user_role']])) {
            $roleCounts[$row['user_role']] = (int) $row['total'];
        }
    }

    $targetRole = $userToDelete['user_role'];

    if (isset($roleCounts[$targetRole]) && $roleCounts[$targetRole] <= 1) {

        $_SESSION['alert'] =
            'Cannot delete this user: they are the only remaining "' .
            $targetRole .
            '" in the system.';

        header("Location: users.php?section=users");
        exit;
    }

    // Handle password confirmation submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        $adminPassword = $_POST['admin_password'] ?? '';

        if ($adminPassword === '') {

            $_SESSION['alert'] = 'Please enter your password to confirm deletion.';

        } else {

            // Fetch the currently logged-in admin's details
            $stmt = $pdo->prepare("
                SELECT user_id, user_email, user_password, user_role
                FROM users
                WHERE user_id = ?
                LIMIT 1
            ");

            $stmt->execute([$_SESSION['user_id'] ?? 0]);

            $admin = $stmt->fetch();

            // Verify the admin exists and has admin role
            if (!$admin || $admin['user_role'] !== 'admin') {

                $_SESSION['alert'] = 'Only administrators can delete users.';

                header("Location: users.php?section=users");
                exit;
            }

            // Verify the supplied password against the stored hash
            if (!password_verify($adminPassword, $admin['user_password'])) {

                // Log failed attempt
                logActivity(
                    $pdo,
                    $_SESSION['user_id'],
                    $_SESSION['user_email'] ?? null,
                    'delete-user-failed-password',
                    'failure'
                );

                $_SESSION['alert'] = 'Incorrect password. User was not deleted.';

            } else {

                // Re-check the constraint here too, in case the DB changed
                // between page load and form submission.
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) AS total
                    FROM users
                    WHERE user_role = ?
                ");

                $stmt->execute([$targetRole]);

                $freshCount = (int) $stmt->fetchColumn();

                if ($freshCount <= 1) {

                    $_SESSION['alert'] =
                        'Cannot delete this user: they are the only remaining "' .
                        $targetRole .
                        '" in the system.';

                    header("Location: users.php?section=users");
                    exit;
                }

                // Constraint passed — proceed with deletion
                $stmt = $pdo->prepare("
                    DELETE FROM users
                    WHERE user_id = ?
                ");

                $stmt->execute([$userId]);

                // Audit trail
                logActivity(
                    $pdo,
                    $_SESSION['user_id'],
                    $_SESSION['user_email'] ?? null,
                    'delete-user',
                    'success'
                );

                $_SESSION['alert'] = 'User deleted successfully.';

                header("Location: users.php?section=users");
                exit;
            }
        }
    }
}


// Reset User Password
if ($section === 'users' && $action === 'reset_password') {

    $userId = (int) ($_GET['id'] ?? 0);

    // Retrieve user
    $stmt = $pdo->prepare("
        SELECT user_id, user_email
        FROM users
        WHERE user_id = ?
    ");

    $stmt->execute([$userId]);

    $user = $stmt->fetch();

    if (!$user) {
        die("User not found.");
    }

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        $newPassword     = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($newPassword === '' || $confirmPassword === '') {

            $_SESSION['alert'] = 'Please fill in both password fields.';

        } elseif ($newPassword !== $confirmPassword) {

            $_SESSION['alert'] = 'Passwords do not match.';

        } elseif (strlen($newPassword) < 8) {

            $_SESSION['alert'] = 'Password must be at least 8 characters.';

        } else {

            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

            // 1. Update the user's password
            $stmt = $pdo->prepare("
                UPDATE users
                SET user_password = ?
                WHERE user_id = ?
            ");

            $stmt->execute([$hashedPassword, $userId]);

            // 2. Mark any pending reset request for this user as done
            $stmt = $pdo->prepare("
                UPDATE password_reset_requests
                SET
                    reset_request_status = 'done',
                    reset_request_resolved_at = CURRENT_TIMESTAMP,
                    reset_request_resolved_by = ?
                WHERE user_id = ?
                    AND reset_request_status = 'pending'
            ");

            $stmt->execute([
                $_SESSION['user_id'] ?? null,
                $userId
            ]);

            // 3. Audit trail
            if (isset($_SESSION['user_id'])) {
                logActivity(
                    $pdo,
                    $_SESSION['user_id'],
                    $_SESSION['user_email'] ?? null,
                    'reset-user-password',
                    'success'
                );
            }

            $_SESSION['alert'] = 'User password reset successfully.';

            header("Location: users.php?section=users");
            exit;
        }
    }
}

// Dismiss a reset request (mark done without resetting password)
if ($section === 'reset_requests' && $action === 'dismiss') {

    $requestId = (int) ($_GET['id'] ?? 0);

    $stmt = $pdo->prepare("
        UPDATE password_reset_requests
        SET
            reset_request_status = 'done',
            reset_request_resolved_at = CURRENT_TIMESTAMP,
            reset_request_resolved_by = ?
        WHERE reset_request_id = ?
            AND reset_request_status = 'pending'
    ");

    $stmt->execute([
        $_SESSION['user_id'] ?? null,
        $requestId
    ]);

    if (isset($_SESSION['user_id'])) {
        logActivity(
            $pdo,
            $_SESSION['user_id'],
            $_SESSION['user_email'] ?? null,
            'dismiss-password-reset',
            'success'
        );
    }

    $_SESSION['alert'] = 'Reset request dismissed.';

    header("Location: users.php?section=reset_requests");
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management</title>
</head>
<body>
    <form method="POST" action="../../auth/signout.php">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
        <button type="submit">Sign Out</button>
    </form>

    <h1>User Management</h1>

    <nav>
        <a href="index.php">Dashboard</a>
        |
        <a href="users.php?section=users">Users</a>
        |
        <a href="users.php?section=reset_requests">Password Reset Requests</a>
    </nav>

    <hr>

    <!--USERS SECTION-->
    <?php if ($section === 'users'): ?>

        <h1>Users</h1>

        <p>
            <a href="users.php?section=users&action=create">
                Add User
            </a>
        </p>

        <?php if ($action === 'create'): ?>

            <h2>Add User</h2>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">

                <p>
                    <label>Email</label>
                    <br>
                    <input type="email" name="user_email" required>
                </p>

                <p>
                    <label>Username</label>
                    <br>
                    <input type="text" name="user_username" required>
                </p>

                <p>
                    <label>Password</label>
                    <br>
                    <input type="password" name="user_password" required>
                </p>

                <p>
                    <label>Role</label>
                    <br>
                    <select name="user_role" required>
                        <option value="user">User</option>
                        <option value="manager">Manager</option>
                        <option value="admin">Admin</option>
                    </select>
                </p>

                <p>
                    <em>Note: Newly created accounts are unverified and must be verified by an admin.</em>
                </p>

                <button type="submit">Save</button>

                <a href="users.php?section=users">Cancel</a>
            </form>

        <?php elseif ($action === 'update'): ?>

            <h2>Update User</h2>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">

                <p>
                    <label>Email</label>
                    <br>
                    <input
                        type="email"
                        name="user_email"
                        value="<?= htmlspecialchars($user['user_email']) ?>"
                        required
                    >
                </p>

                <p>
                    <label>Username</label>
                    <br>
                    <input
                        type="text"
                        name="user_username"
                        value="<?= htmlspecialchars($user['user_username']) ?>"
                        required
                    >
                </p>

                <p>
                    <label>Password</label>
                    <br>
                    <input type="password" name="user_password" placeholder="Leave blank to keep current password">
                </p>

                <p>
                    <label>Role</label>
                    <br>
                    <select name="user_role" required>
                        <option value="user"    <?= $user['user_role'] === 'user'    ? 'selected' : '' ?>>User</option>
                        <option value="manager" <?= $user['user_role'] === 'manager' ? 'selected' : '' ?>>Manager</option>
                        <option value="admin"   <?= $user['user_role'] === 'admin'   ? 'selected' : '' ?>>Admin</option>
                    </select>
                </p>

                <button type="submit">Update</button>

                <a href="users.php?section=users">Cancel</a>
            </form>
        <?php elseif ($action === 'delete'): ?>

            <h2>Confirm User Deletion</h2>

            <p>
                You are about to permanently delete the following user:
            </p>

            <table border="1" cellpadding="8">
                <tr>
                    <th>ID</th>
                    <td><?= htmlspecialchars($userToDelete['user_id']) ?></td>
                </tr>
                <tr>
                    <th>Email</th>
                    <td><?= htmlspecialchars($userToDelete['user_email']) ?></td>
                </tr>
                <tr>
                    <th>Username</th>
                    <td><?= htmlspecialchars($userToDelete['user_username']) ?></td>
                </tr>
                <tr>
                    <th>Role</th>
                    <td><?= htmlspecialchars($userToDelete['user_role']) ?></td>
                </tr>
            </table>

            <p style="color: red;">
                <strong>Warning:</strong> This action cannot be undone.
            </p>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">

                <p>
                    <label>Enter your admin password to confirm</label>
                    <br>
                    <input type="password" name="admin_password" required autofocus>
                </p>

                <button type="submit" onclick="return confirm('Permanently delete this user?');">
                    Delete User
                </button>

                <a href="users.php?section=users">Cancel</a>
            </form>

        <?php elseif ($action === 'reset_password'): ?>

            <h2>Reset Password</h2>

            <p>
                Resetting password for
                <strong><?= htmlspecialchars($user['user_email']) ?></strong>
            </p>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">

                <p>
                    <label>New Password</label>
                    <br>
                    <input type="password" name="new_password" minlength="8" required>
                </p>

                <p>
                    <label>Confirm New Password</label>
                    <br>
                    <input type="password" name="confirm_password" minlength="8" required>
                </p>

                <button type="submit">Reset Password</button>

                <a href="users.php?section=users">Cancel</a>
            </form>

        <?php else: ?>

            <table border="1" cellpadding="8">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Email</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Verified</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td>
                                <?= htmlspecialchars($user['user_id']) ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($user['user_email']) ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($user['user_username']) ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($user['user_role']) ?>
                            </td>
                            <td>
                                <?php if ($user['user_verified']): ?>
                                    <strong style="color: green;">Verified</strong>
                                <?php else: ?>
                                    <strong style="color: red;">Unverified</strong>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($user['user_created_at']) ?>
                            </td>
                            <td>
                                <a href="users.php?section=users&action=update&id=<?= $user['user_id'] ?>">Edit</a>
                                |
                                <a href="users.php?section=users&action=reset_password&id=<?= $user['user_id'] ?>">Reset Password</a>
                                |
                                <?php if ($user['user_verified']): ?>
                                    <a
                                        href="users.php?section=users&action=unverify&id=<?= $user['user_id'] ?>"
                                        onclick="return confirm('Unverify this user?');"
                                    >
                                        Unverify
                                    </a>
                                <?php else: ?>
                                    <a
                                        href="users.php?section=users&action=verify&id=<?= $user['user_id'] ?>"
                                        onclick="return confirm('Verify this user?');"
                                    >
                                        Verify
                                    </a>
                                <?php endif; ?>
                                |
                                <a href="users.php?section=users&action=delete&id=<?= $user['user_id'] ?>">
                                    Delete
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

        <?php endif; ?>

    <?php endif; ?>

    <!--RESET REQUESTS SECTION-->
    <?php if ($section === 'reset_requests'): ?>

        <h1>Password Reset Requests</h1>

        <?php if (empty($resetRequests)): ?>

            <p>No password reset requests.</p>

        <?php else: ?>

            <table border="1" cellpadding="8">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Requested At</th>
                        <th>Resolved At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($resetRequests as $request): ?>
                        <tr>
                            <td>
                                <?= htmlspecialchars($request['reset_request_id']) ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($request['user_username'] ?? '-') ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($request['user_email'] ?? '-') ?>
                            </td>
                            <td>
                                <?php if ($request['reset_request_status'] === 'pending'): ?>
                                    <strong style="color: orange;">Pending</strong>
                                <?php else: ?>
                                    <strong style="color: green;">Done</strong>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($request['reset_request_created_at']) ?>
                            </td>
                            <td>
                                <?= $request['reset_request_resolved_at']
                                    ? htmlspecialchars($request['reset_request_resolved_at'])
                                    : '—' ?>
                            </td>
                            <td>
                                <?php if ($request['reset_request_status'] === 'pending'): ?>

                                    <a href="users.php?section=users&action=reset_password&id=<?= (int) $request['user_id'] ?>">
                                        Reset Password
                                    </a>

                                    |

                                    <a
                                        href="users.php?section=reset_requests&action=dismiss&id=<?= (int) $request['reset_request_id'] ?>"
                                        onclick="return confirm('Dismiss this reset request without changing the password?');"
                                    >
                                        Dismiss
                                    </a>

                                <?php else: ?>

                                    <em>—</em>

                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

        <?php endif; ?>

    <?php endif; ?>

</body>

<?php if (isset($_SESSION['alert'])): ?>

    <script>
        alert(<?= json_encode($_SESSION['alert']) ?>);
    </script>

    <?php unset($_SESSION['alert']); ?>

<?php endif; ?>

</html>