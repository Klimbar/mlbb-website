<?php
// test_db.php
// Place this file in your website's root directory and access it via your browser.
// IMPORTANT: DELETE THIS FILE AFTER YOU ARE DONE TESTING.

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>Database Connection Test</h1>";

// --- Step 0: Check for Pre-existing Environment Variables ---
echo "<strong>Step 0: Checking for pre-existing server environment variables...</strong><br>";
$pre_existing_vars = [];
$vars_to_check = ['DB_HOST', 'DB_USER', 'DB_PASS', 'DB_NAME', 'BASE_URL'];
foreach ($vars_to_check as $var) {
    $value = getenv($var);
    if ($value !== false) { // getenv returns false if not set
        $pre_existing_vars[$var] = $value;
    }
}

if (!empty($pre_existing_vars)) {
    echo "<strong><font color='orange'>⚠️ WARNING:</font></strong> The following environment variables already exist on the server. The .env file will NOT override them.<br>";
    echo "<pre>";
    print_r($pre_existing_vars);
    echo "</pre>";
    echo "If these values are empty or incorrect, you must find where they are set in your server configuration (e.g., Apache's httpd.conf, a .htaccess file with `SetEnv`, or a PHP-FPM pool configuration) and remove them.<br><br>";
} else {
    echo "✅ No conflicting server-level environment variables found. This is good.<br><br>";
}


// --- Step 1: Check for .env file ---
$env_file_path = __DIR__ . '/.env';
echo "<strong>Step 1: Checking for .env file...</strong><br>";
echo "Looking for file at: " . htmlspecialchars($env_file_path) . "<br>";

if (!file_exists($env_file_path)) {
    die("❌ <strong>CRITICAL ERROR:</strong> The .env file does not exist at the expected location. Please ensure it is in the root directory and named correctly.");
}
echo "✅ File exists.<br>";

if (!is_readable($env_file_path)) {
    die("❌ <strong>CRITICAL ERROR:</strong> The .env file exists but is not readable by the web server. Please run `sudo chmod 640 .env` and `sudo chown www-data:www-data .env`.");
}
echo "✅ File is readable.<br><br>";

// --- Step 2: Check for open_basedir restrictions ---
$open_basedir = ini_get('open_basedir');
echo "<strong>Step 2: Checking for PHP open_basedir restrictions...</strong><br>";
if ($open_basedir) {
    echo "Warning: `open_basedir` is enabled on your server: " . htmlspecialchars($open_basedir) . "<br>";
    // Simple check to see if our path is within the allowed paths
    $allowed = false;
    foreach (explode(PATH_SEPARATOR, $open_basedir) as $path) {
        if (strpos(__DIR__, $path) === 0) {
            $allowed = true;
            break;
        }
    }
    if (!$allowed) {
        die("❌ <strong>CRITICAL ERROR:</strong> Your `open_basedir` setting prevents PHP from accessing the directory where your .env file is located.");
    }
    echo "✅ Your application directory appears to be within the allowed path.<br><br>";
} else {
    echo "✅ `open_basedir` is not set. This is good.<br><br>";
}

// --- Step 3: Load .env and check variables ---
echo "<strong>Step 3: Loading .env file and checking variables...</strong><br>";
try {
    require_once __DIR__ . '/vendor/autoload.php';
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
    echo "✅ .env file loaded by Dotenv library.<br>";
} catch (Exception $e) {
    die("❌ <strong>CRITICAL ERROR:</strong> Could not load .env file. The file might have a syntax error. Error: " . $e->getMessage());
}

$db_host = getenv('DB_HOST');
$db_user = getenv('DB_USER');
$db_pass = getenv('DB_PASS');
$db_name = getenv('DB_NAME');

if (empty($db_host) || empty($db_user) || empty($db_name)) {
    die("❌ <strong>CRITICAL ERROR:</strong> Dotenv loaded the file, but the database variables (DB_HOST, DB_USER, DB_NAME) are empty. Please check for typos in your .env file.");
}
echo "✅ Database variables are not empty.<br><br>";

// --- Step 4: Attempt Database Connection ---
echo "<strong>Step 4: Attempting to connect to the database...</strong><br>";
echo "Host: " . htmlspecialchars($db_host) . "<br>";
echo "User: " . htmlspecialchars($db_user) . "<br>";
echo "Pass: [hidden for security]<br>";
echo "DB Name: " . htmlspecialchars($db_name) . "<br><br>";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    echo "<h2>✅ SUCCESS!</h2>";
    echo "Database connection was established successfully. Your website should now be working.<br>";
    echo "MySQL Server Version: " . $conn->server_info . "<br>";
    $conn->close();
} catch (mysqli_sql_exception $e) {
    echo "<h2>❌ CONNECTION FAILED!</h2>";
    echo "<strong>Error Code:</strong> " . $e->getCode() . "<br>";
    echo "<strong>Error Message:</strong> " . htmlspecialchars($e->getMessage()) . "<br>";
}