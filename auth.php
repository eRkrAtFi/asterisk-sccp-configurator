<?php
/**
 * SCCP Configurator - Authentication System
 * FreePBX 17 userman_users Database Integration with Role-Based Access Control (RBAC)
 * and Debug Logging.
 */

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load server configuration (DB credentials, paths, timeouts)
require_once __DIR__ . '/config.php';

// Fallback JSON file for special admin accounts
define('USERS_FILE', __DIR__ . '/users.json');

// --- DATABASE & CORE FUNCTIONS ---

// Connect to MySQL
function getDBConnection() {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        error_log("SCCP Auth DB Connection Error: " . $e->getMessage());
        return null;
    }
}

/**
 * Function to check if a user is in the 'Administrators' group
 *
 * @param PDO $db The PDO database connection
 * @param int $userId The user's ID from userman_users
 * @return bool True if user is admin, false otherwise
 */
function isUserAdmin($db, $userId) {
    try {

        error_log("SCCP DEBUG: Checking admin for userId=" . $userId);

        // Debug: show all groups from FreePBX
        $debugStmt = $db->query("
            SELECT id, groupname, auth, users
            FROM userman_groups
        ");
        $groupsDebug = $debugStmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("SCCP DEBUG GROUPS: " . print_r($groupsDebug, true));

        // Get Administrators group
        $stmt = $db->prepare("
            SELECT users
            FROM userman_groups
            WHERE groupname = 'Administrators'
        ");
        $stmt->execute();

        $users = $stmt->fetchColumn();

        error_log("SCCP DEBUG: Raw users JSON=" . $users);

        if (!$users) {
            error_log("SCCP DEBUG: Administrators group empty or not found");
            return false;
        }

        $users = json_decode($users, true);

        error_log("SCCP DEBUG: Decoded users=" . print_r($users, true));

        if (!is_array($users)) {
            error_log("SCCP DEBUG: users is not an array");
            return false;
        }

        // Normalize IDs to integers
        $users = array_map('intval', $users);

        error_log("SCCP DEBUG: Normalized users=" . print_r($users, true));

        if (in_array((int)$userId, $users)) {
            error_log("SCCP DEBUG: User IS admin");
            return true;
        }

        error_log("SCCP DEBUG: User is NOT admin");
        return false;

    } catch (PDOException $e) {
        error_log("SCCP: Admin check error - " . $e->getMessage());
        return false;
    }
}

// Function to authenticate via FreePBX userman_users (FreePBX 17)
function authenticateUserMySQL($username, $password) {
    error_log("SCCP: authenticateUserMySQL called for username='$username'");
    
    $db = getDBConnection();
    if (!$db) {
        error_log("SCCP: DB connection FAILED");
        return false;
    }
    
    error_log("SCCP: DB connection OK");
    
    try {
        $stmt = $db->prepare("
            SELECT id, username, password, displayname, email, auth 
            FROM userman_users 
            WHERE username = ? AND (auth = 'freepbx' OR auth = '1')
        ");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if (!$user) {
            error_log("SCCP: User NOT found or auth != 'freepbx' for username='$username'");
            return false;
        }
        
        error_log("SCCP: User found - username='{$user['username']}', auth='{$user['auth']}'");
        
        // FreePBX 17 uses bcrypt
        if (password_verify($password, $user['password'])) {
            error_log("SCCP: password_verify SUCCESS for username='$username'");
            
            // --- NEW: Check Admin Status ---
            $isAdmin = isUserAdmin($db, $user['id']);
            error_log("SCCP: Admin status check for '{$user['username']}': " . ($isAdmin ? 'ADMIN' : 'USER'));
            
            // Successful login
            $_SESSION['sccp_authenticated'] = true;
            $_SESSION['sccp_user_id'] = $user['id']; // Store user ID
            $_SESSION['sccp_username'] = $user['username'];
            $_SESSION['sccp_name'] = $user['displayname'] ?: $user['username'];
            $_SESSION['sccp_email'] = $user['email'] ?? '';
            $_SESSION['sccp_login_time'] = time();
            $_SESSION['sccp_auth_source'] = 'freepbx';
            $_SESSION['sccp_is_admin'] = $isAdmin; // Store admin status
            $_SESSION['sccp_role'] = $isAdmin ? 'admin' : 'user'; // Store role name
            $_SESSION['last_activity'] = time(); // SET TIMEOUT START
            
            error_log("SCCP: Session created for username='$username'");
            return true;
        } else {
            error_log("SCCP: password_verify FAILED for username='$username'");
        }
    } catch (PDOException $e) {
        error_log("SCCP: SQL Error - " . $e->getMessage());
    }
    
    return false;
}

// Function to load users from JSON (fallback)
function loadUsersJSON() {
    if (!file_exists(USERS_FILE)) {
        return [];
    }
    
    $json = file_get_contents(USERS_FILE);
    return json_decode($json, true) ?: [];
}

// Function to authenticate via JSON fallback
function authenticateUserJSON($username, $password) {
    error_log("SCCP: authenticateUserJSON called for username='$username'");
    
    $users = loadUsersJSON();
    
    if (!isset($users[$username])) {
        error_log("SCCP: User NOT found in JSON for username='$username'");
        return false;
    }
    
    if (password_verify($password, $users[$username]['password'])) {
        error_log("SCCP: JSON auth SUCCESS for username='$username'");
        
        // --- NEW: Check Admin Status from JSON ---
        $isAdmin = (isset($users[$username]['role']) && $users[$username]['role'] === 'admin');
        error_log("SCCP: JSON Admin status for '{$username}': " . ($isAdmin ? 'ADMIN' : 'USER'));

        // Successful login
        $_SESSION['sccp_authenticated'] = true;
        $_SESSION['sccp_username'] = $username;
        $_SESSION['sccp_name'] = $users[$username]['name'];
        $_SESSION['sccp_login_time'] = time();
        $_SESSION['sccp_auth_source'] = 'local';
        $_SESSION['sccp_is_admin'] = $isAdmin; // Store admin status
        $_SESSION['sccp_role'] = $isAdmin ? 'admin' : 'user'; // Store role name
        $_SESSION['last_activity'] = time(); // SET TIMEOUT START
        
        return true;
    }
    
    error_log("SCCP: JSON auth FAILED for username='$username'");
    return false;
}

// Main authentication function (MySQL + JSON fallback)
function authenticateUser($username, $password) {
    error_log("SCCP: authenticateUser called for username='$username'");
    
    // 1. First try FreePBX MySQL (userman_users)
    if (authenticateUserMySQL($username, $password)) {
        error_log("SCCP: Authentication SUCCESS via MySQL for username='$username'");
        return true;
    }
    
    // 2. If MySQL fails, try local JSON (for emergency admin accounts)
    if (authenticateUserJSON($username, $password)) {
        error_log("SCCP: Authentication SUCCESS via JSON for username='$username'");
        return true;
    }
    
    error_log("SCCP: Authentication FAILED for username='$username'");
    return false;
}

// Function to check if user is authenticated
function isAuthenticated() {
    return isset($_SESSION['sccp_authenticated']) && $_SESSION['sccp_authenticated'] === true;
}

/**
 * Function to check if user is an admin
 * @return bool
 */
function isAdmin() {
    return isAuthenticated() && isset($_SESSION['sccp_is_admin']) && $_SESSION['sccp_is_admin'] === true;
}

// Function to logout
function logout() {
    $username = $_SESSION['sccp_username'] ?? 'unknown';
    error_log("SCCP: Logout for username='$username'");
    session_unset();
    session_destroy();
}

// Function to get current user
function getCurrentUser() {
    if (!isAuthenticated()) {
        return null;
    }
    
    return [
        'username' => $_SESSION['sccp_username'] ?? 'unknown',
        'name' => $_SESSION['sccp_name'] ?? 'Unknown User',
        'email' => $_SESSION['sccp_email'] ?? '',
        'login_time' => $_SESSION['sccp_login_time'] ?? time(),
        'auth_source' => $_SESSION['sccp_auth_source'] ?? 'unknown',
        'isAdmin' => $_SESSION['sccp_is_admin'] ?? false, // Return admin status
        'role' => $_SESSION['sccp_role'] ?? 'user'      // Return role name
    ];
}

// Function to save users to JSON (for manage_users.php)
function saveUsers($users) {
    $json = json_encode($users, JSON_PRETTY_PRINT);
    file_put_contents(USERS_FILE, $json);
    chmod(USERS_FILE, 0600);
}

// --- AJAX/POST HANDLER ---
// Handles login, logout, and check requests from client-side JavaScript
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'login') {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        error_log("SCCP: Login attempt - username='$username', password_length=" . strlen($password));
        
        if (authenticateUser($username, $password)) {
            $user = getCurrentUser();
            $authSource = $user['auth_source'] === 'freepbx' ? 'FreePBX' : 'Local';
            
            error_log("SCCP: Login SUCCESS - username='$username' via $authSource (Admin: " . ($user['isAdmin'] ? 'Yes' : 'No') . ")");
            
            echo json_encode([
                'success' => true, 
                'message' => "Login successful ($authSource)",
                'isAdmin' => $user['isAdmin'] // Send admin status to client
            ]);
        } else {
            error_log("SCCP: Login FAILED - username='$username'");
            
            echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
        }
        exit;
    }
    
    if ($_POST['action'] === 'logout') {
        logout();
        echo json_encode(['success' => true, 'message' => 'Logout successful']);
        exit;
    }
    
    if ($_POST['action'] === 'check') {
        if (isAuthenticated()) {
            echo json_encode(['authenticated' => true, 'user' => getCurrentUser()]);
        } else {
            echo json_encode(['authenticated' => false]);
        }
        exit;
    }
}

// --- 403 PAGE LOGOUT HANDLER (FIX) ---
// This MUST come before the Page Guardian
// Handles the logout link from the 403 "Access Denied" page
if (isset($_GET['action']) && $_GET['action'] === 'logout_redirect') {
    logout();
    header('Location: login.php');
    exit;
}

// --- PAGE ACCESS & SECURITY CHECKS ---

// Define helper variables
$isLoginPage = (basename($_SERVER['PHP_SELF']) === 'login.php');
$isApiRequest = (strpos($_SERVER['REQUEST_URI'], 'api.php') !== false);
$isCli = (php_sapi_name() === 'cli');

// --- SESSION TIMEOUT HANDLER ---
// Runs on every authenticated page load (like index.php or api.php)
if (!$isLoginPage && isAuthenticated()) {

    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT_SECONDS) {
        // Session has expired
        error_log("SCCP: Session timeout for user '" . ($_SESSION['sccp_username'] ?? 'unknown') . "'");
        
        logout(); // Destroy the session data

        if ($isApiRequest) {
            // If it's an API call, return JSON error
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Session expired. Please log in again.']);
            exit;
        } else {
            // If it's a normal page, redirect to login
            header('Location: login.php?reason=session_expired');
            exit;
        }
    }
    
    // If session is still valid, update the activity time
    $_SESSION['last_activity'] = time();
}


// --- PAGE GUARDIAN (Protection) ---
// This runs on normal page loads (e.g., index.php)
// It protects pages from unauthorized access.
if (!$isLoginPage && !$isApiRequest && !$isCli) {
    
    // 1. Check if authenticated
    if (!isAuthenticated()) {
        error_log("SCCP: Page access DENIED (Not Authenticated) for " . $_SERVER['PHP_SELF']);
        // Not authenticated, redirect to login
        header('Location: login.php');
        exit;
    }
    
    // 2. Is authenticated, but NOT admin
    if (!isAdmin()) {
        error_log("SCCP: Page access DENIED (Not Admin) for user '" . ($_SESSION['sccp_username'] ?? 'unknown') . "' on " . $_SERVER['PHP_SELF']);
        // Show 403 Forbidden error
        http_response_code(403);
        echo "<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'>";
        echo "<title>403 - Access Denied</title>";
        echo "<style>body { font-family: system-ui, sans-serif; display: grid; place-items: center; min-height: 90vh; background: #f9f9f9; color: #333; }";
        echo ".container { text-align: center; border: 1px solid #ddd; background: #fff; padding: 40px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }";
        echo "h1 { color: #d9534f; margin: 0 0 10px 0; } p { margin: 5px 0; } a { color: #0275d8; text-decoration: none; } a:hover { text-decoration: underline; }</style>";
        echo "</head><body><div class='container'>";
        echo "<h1>403 - Access Denied</h1>";
        echo "<p>You are logged in, but you do not have permission to access this page.</p>";
        echo "<p>Please contact an administrator if you believe this is an error.</p>";
        echo "<br/><p><a href='auth.php?action=logout_redirect'>Log Out</a></p>";
        echo "</div></body></html>";
        exit;
    }
    
    // If we are here, user is authenticated AND is an admin.
}

?>
