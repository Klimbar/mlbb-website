<?php
require_once __DIR__ . '/../config.php';

// Destroy session
session_destroy();
header("Location: " . BASE_URL . "/");
exit();
?>