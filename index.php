<?php
require_once 'config/config.php';

if(isset($_SESSION['user_id'])){
    header('Location: ' . BASE_URL . '/app/' . $_SESSION['user_role'] . '/index.php');
    exit;
}

$error='';

if($_SERVER['REQUEST_METHOD'] === 'POST'){

    requireCsrfToken();

    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';

    $error = 'Invalid login credentials';

    if ($login==='' || $password ===''){
        
        // Log incomplete login attempt
    logActivity(
        $pdo,
        null,
        $login,
        'login',
        'failed'
    );

    } else {

        $result = loginUser($pdo, $login, $password);

        if ($result === true) {

            header('Location: ' . BASE_URL . '/app/' . $_SESSION['user_role'] . '/index.php');
            exit;

        } elseif ($result === 'active_session') {

            $error = 'This account is already logged in on another device';

        } elseif ($result === 'unverified') {

            $error = 'Your account is pending admin verification. Please contact an administrator.';

        } else {

            $error = 'Invalid Login Credentials';
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
</head>
<body>

<form method="POST">
    <label>Username or Email</label>
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>" >
    <input type="text" name="login" required>
    <br>
    <br>
    <label>Password</label>
    <input type="password" name="password"required>
    <br>
    <button type="submit">Sign In</button>
</form>

<?php if ($error !== ''): ?>
    <p>
        <?= htmlspecialchars($error) ?>
    </p>
<?php endif; ?>

<?php if (isset($_GET['timeout'])): ?>
    <p>Your session has expired due to inactivity. Please log in again.</p>
<?php endif; ?>

</body>
</html>