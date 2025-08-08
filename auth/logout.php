<?php
require_once __DIR__ . '/../bootstrap.php';

// Destroy session
session_destroy();
header("Location: " . BASE_URL . "/");
exit();
?>