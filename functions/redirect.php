<?php

// ------------------------------------------------------
// Redirect
// ------------------------------------------------------

function redirect($path)
{
    header("Location: " . BASE_URL . $path);
    exit;
}
?>