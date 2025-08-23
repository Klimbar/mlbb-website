<?php

// Redirect logged-in users from auth pages
if (is_logged_in() && in_array(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), ['/auth/login', '/auth/register', '/auth/forgot_password'])) {
    header('Location: ' . BASE_URL . '/');
    exit;
}

// Define redirects for base paths that should update the browser URL
$redirects = [
    '/auth' => '/auth/login',
    '/admin' => '/admin/dashboard',
    '/orders' => '/orders/history',
];

// Define routes for specific pages
$routes = [
    '/' => 'pages/games_landing',
    '/api' => 'api',
    '/install' => 'install',
    '/auth/login' => 'auth/login',
    '/auth/logout' => 'auth/logout',
    '/auth/register' => 'auth/register',
    '/auth/forgot_password' => 'auth/forgot_password',
    '/auth/reset_password' => 'auth/reset_password',
    '/auth/clear_session_and_refresh_csrf' => 'auth/clear_session_and_refresh_csrf',
    '/auth/clear_otp_session' => 'auth/clear_otp_session',
    '/auth/refresh_csrf' => 'auth/refresh_csrf',
    '/mobile_legends' => 'pages/mobile_legends_content',
    '/admin/dashboard' => 'admin/dashboard',
    '/admin/manage-products' => 'admin/manage-products',
    '/admin/orders' => 'admin/orders',
    '/admin/sync_order_payment_status' => 'admin/sync_order_payment_status',

    '/admin/update_products' => 'admin/update_products',
    '/orders/history' => 'orders/history',
    '/payments/callback' => 'payments/callback',
    '/payments/process' => 'payments/process',
    '/auth/login_handler' => 'auth/login_handler',
    '/auth/send_otp_ajax' => 'auth/send_otp_ajax',

    // Add static routes for details pages to handle query parameters
    '/admin/order-details' => 'admin/order-details',
    '/orders/details' => 'orders/details',

    // Dynamic routes for "pretty" URLs
    '/admin/order-details/(\d+)' => 'admin/order-details',
    '/orders/details/(\d+)' => 'orders/details',
    '/region-check' => 'region-checker/region-check',
    '/api/region-proxy' => 'region-checker/proxy',
];

// Define page titles, mapping them to the file path from the routes array
$page_titles = [
    'pages/games_landing' => 'Welcome to Serdihin',
    'auth/login' => 'Login',
    'auth/register' => 'Register',
    'auth/forgot_password' => 'Forgot Password',
    'auth/reset_password' => 'Reset Password',
    'pages/mobile_legends_content' => 'Mobile Legends: Bang Bang',
    'admin/dashboard' => 'Admin Dashboard',
    'admin/manage-products' => 'Manage Products',
    'admin/orders' => 'Manage Orders',
    'orders/history' => 'My Order History',
    'admin/order-details' => 'Order Details',
    'orders/details' => 'Order Details',
    'region-checker/region-check' => 'MLBB Region Checker',
];


// Get the current URI path
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$script_name = dirname($_SERVER['SCRIPT_NAME']);

// Normalize script_name to avoid issues with root directory
if ($script_name === '/' || $script_name === '\\') {
    $base_path = '';
} else {
    $base_path = $script_name;
}

// Remove the base path from the request URI
if ($base_path && strpos($request_uri, $base_path) === 0) {
    $request_uri = substr($request_uri, strlen($base_path));
}

// Ensure it starts with a slash
if (substr($request_uri, 0, 1) !== '/') {
    $request_uri = '/' . $request_uri;
}

// Remove trailing slash for consistent routing, unless it's the root
if ($request_uri !== '/' && substr($request_uri, -1) === '/') {
    $request_uri = substr($request_uri, 0, -1);
}

// --- Centralized Admin Route Protection ---
// This is more secure than including a check in each file.
if (strpos($request_uri, '/admin') === 0) {
    // Check if user is logged in AND has the 'admin' role.
    if (!is_logged_in() || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        http_response_code(403); // Forbidden
        header('Location: ' . BASE_URL . '/');
        exit;

    }
}

// --- Centralized Orders Route Protection ---
if (strpos($request_uri, '/orders') === 0) {
    // Check if user is logged in.
    if (!is_logged_in()) {
        header('Location: ' . BASE_URL . '/auth/login.php');
        exit;
    }
}

// Handle redirects first
if (isset($redirects[$request_uri])) {
    // Construct the full redirect URL
    $redirect_url = ($base_path ? $base_path : '') . $redirects[$request_uri];
    // Perform a permanent redirect
    header('Location: ' . $redirect_url, true, 301);
    exit();
}


// Route the request
$route_found = false;
$page_title = ''; // Initialize page title

// Check for dynamic routes first
foreach ($routes as $route => $file) {
    // Check for routes with regex patterns
    if (strpos($route, '(') !== false) {
        $pattern = '#^' . $route . '$#';
        if (preg_match($pattern, $request_uri, $matches)) {
            // Remove the full match from the beginning of the array
            array_shift($matches);

            // The first captured group is the ID
            if (isset($matches[0])) {
                 // The target scripts expect 'id' in $_GET
                $_GET['id'] = $matches[0];
            }

            $route_file = $file;
            if (isset($page_titles[$route_file])) {
                $page_title = $page_titles[$route_file];
            }
            require_once __DIR__ . '/header.php';
            require_once __DIR__ . '/../' . $route_file . '.php';
            require_once __DIR__ . '/footer.php';
            $route_found = true;
            break; // Exit loop once a match is found
        }
    }
}

// If no dynamic route matched, check for static routes
if (!$route_found && array_key_exists($request_uri, $routes)) {
    $route_file = $routes[$request_uri];
    if (isset($page_titles[$route_file])) {
        $page_title = $page_titles[$route_file];
    }
    // Exclude header/footer for API routes
    if ($request_uri === '/api' || strpos($route_file, 'payments/') === 0 || strpos($route_file, 'api_handlers/') === 0 || $request_uri === '/auth/send_otp_ajax' || $request_uri === '/auth/clear_session_and_refresh_csrf' || $request_uri === '/auth/clear_otp_session' || $request_uri === '/auth/refresh_csrf' || $request_uri === '/api/region-proxy') {
        require_once __DIR__ . '/../' . $route_file . '.php';
    } else {
        require_once __DIR__ . '/header.php';
        require_once __DIR__ . '/../' . $route_file . '.php';
        require_once __DIR__ . '/footer.php';
    }
    $route_found = true;
}

if (!$route_found) {
    // Handle 404 Not Found by redirecting to the homepage
    $home_url = ($base_path ? $base_path : '') . '/';
    header('Location: ' . $home_url, true, 302);
    exit();
}

?>