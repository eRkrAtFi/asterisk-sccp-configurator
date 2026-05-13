#!/usr/bin/php
<?php
/**
 * SCCP Configurator - User Management
 * CLI tool for adding/deleting/listing users
 * --- UPDATED with Role (admin/user) support ---
 *
 * Usage:
 * php manage_users.php add <username> <password> [name] [role]
 * php manage_users.php delete <username>
 * php manage_users.php list
 * php manage_users.php change-password <username> <new_password>
 * php manage_users.php promote <username>
 */

define('USERS_FILE', __DIR__ . '/users.json');

function loadUsers() {
    if (!file_exists(USERS_FILE)) {
        return [];
    }
    $json = file_get_contents(USERS_FILE);
    return json_decode($json, true);
}

function saveUsers($users) {
    $json = json_encode($users, JSON_PRETTY_PRINT);
    file_put_contents(USERS_FILE, $json);
    chmod(USERS_FILE, 0660);
}

function addUser($username, $password, $name = null, $role = null) {
    $users = loadUsers();
    
    if (isset($users[$username])) {
        echo "ERROR: User '$username' already exists!\n";
        return false;
    }
    
    $validRole = ($role === 'admin') ? 'admin' : 'user'; // Default to 'user'
    
    $users[$username] = [
        'password' => password_hash($password, PASSWORD_BCRYPT),
        'name' => $name ?? ucfirst($username),
        'role' => $validRole, // Added role
        'created' => date('Y-m-d H:i:s')
    ];
    
    saveUsers($users);
    echo "✓ User '$username' has been successfully created with role '$validRole'.\n";
    return true;
}

function deleteUser($username) {
    $users = loadUsers();
    
    if (!isset($users[$username])) {
        echo "ERROR: User '$username' does not exist!\n";
        return false;
    }
    
    unset($users[$username]);
    saveUsers($users);
    echo "✓ User '$username' has been removed.\n";
    return true;
}

function listUsers() {
    $users = loadUsers();
    
    if (empty($users)) {
        echo "No users in the database.\n";
        return;
    }
    
    echo "\n";
    echo str_pad("USERNAME", 20) . str_pad("NAME", 25) . str_pad("ROLE", 10) . "CREATED\n";
    echo str_repeat("-", 78) . "\n";
    
    foreach ($users as $username => $data) {
        echo str_pad($username, 20) . 
             str_pad($data['name'], 25) . 
             str_pad($data['role'] ?? 'user', 10) . // Added role column
             ($data['created'] ?? 'N/A') . "\n";
    }
    echo "\n";
    echo "Total users: " . count($users) . "\n\n";
}

function changePassword($username, $newPassword) {
    $users = loadUsers();
    
    if (!isset($users[$username])) {
        echo "ERROR: User '$username' does not exist!\n";
        return false;
    }
    
    $users[$username]['password'] = password_hash($newPassword, PASSWORD_BCRYPT);
    $users[$username]['password_changed'] = date('Y-m-d H:i:s');
    
    saveUsers($users);
    echo "✓ Password for user '$username' has been changed.\n";
    return true;
}

/**
 * NEW FUNCTION: Promote a user to admin
 */
function promoteUser($username) {
    $users = loadUsers();
    
    if (!isset($users[$username])) {
        echo "ERROR: User '$username' does not exist!\n";
        return false;
    }

    if (($users[$username]['role'] ?? 'user') === 'admin') {
        echo "INFO: User '$username' is already an admin.\n";
        return true;
    }
    
    $users[$username]['role'] = 'admin';
    saveUsers($users);
    echo "✓ User '$username' has been promoted to 'admin'.\n";
    return true;
}


function showHelp() {
    echo "\n";
    echo "SCCP Configurator - User Management (with Roles)\n";
    echo str_repeat("=", 50) . "\n\n";
    echo "Usage:\n";
    echo "  php manage_users.php add <username> <password> [name] [role]\n";
    echo "  php manage_users.php delete <username>\n";
    echo "  php manage_users.php list\n";
    echo "  php manage_users.php change-password <username> <new_password>\n";
    echo "  php manage_users.php promote <username>\n";
    echo "\n";
    echo "Examples:\n";
    echo "  php manage_users.php add admin admin123 \"Administrator\" admin\n";
    echo "  php manage_users.php add colleague pass123 \"John Doe\" user\n";
    echo "  php manage_users.php list\n";
    echo "  php manage_users.php promote colleague\n";
    echo "\n";
}

// Main logic
if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.\n");
}

$action = $argv[1] ?? '';

switch ($action) {
    case 'add':
        if (!isset($argv[2]) || !isset($argv[3])) {
            echo "ERROR: Missing parameters!\n";
            echo "Usage: php manage_users.php add <username> <password> [name] [role]\n";
            exit(1);
        }
        $username = $argv[2];
        $password = $argv[3];
        $name = $argv[4] ?? null;
        $role = $argv[5] ?? 'user'; // Default role is 'user'
        addUser($username, $password, $name, $role);
        break;
        
    case 'delete':
        if (!isset($argv[2])) {
            echo "ERROR: Missing username!\n";
            echo "Usage: php manage_users.php delete <username>\n";
            exit(1);
        }
        deleteUser($argv[2]);
        break;
        
    case 'list':
        listUsers();
        break;
        
    case 'change-password':
        if (!isset($argv[2]) || !isset($argv[3])) {
            echo "ERROR: Missing parameters!\n";
            echo "Usage: php manage_users.php change-password <username> <new_password>\n";
            exit(1);
        }
        changePassword($argv[2], $argv[3]);
        break;
    
    case 'promote': // New action
        if (!isset($argv[2])) {
            echo "ERROR: Missing username!\n";
            echo "Usage: php manage_users.php promote <username>\n";
            exit(1);
        }
        promoteUser($argv[2]);
        break;
        
    case 'help':
    case '--help':
    case '-h':
        showHelp();
        break;
        
    default:
        echo "ERROR: Unknown action '$action'\n";
        showHelp();
        exit(1);
}

exit(0);
