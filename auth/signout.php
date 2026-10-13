<?php
require_once '../config/config.php';

logoutUser($pdo);

header('Location: ' . BASE_URL . '/index.php');
exit;

?>