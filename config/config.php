<?php
// Start session
session_start();

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Site configuration
define('SITE_NAME', 'BARTEK ICT');
define('SITE_URL', 'http://localhost/bartek-ict');
define('ADMIN_EMAIL', 'admin@bartek.so');

// Timezone
date_default_timezone_set('Africa/Mogadishu');

// Include database connection
require_once __DIR__ . '/database.php';
?>