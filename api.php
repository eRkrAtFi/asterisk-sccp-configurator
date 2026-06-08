<?php
// We need auth.php for session and admin checks (auth.php loads config.php internally)
require_once 'auth.php';
require_once 'logger.php';

function lockCurrentUser() { return $_SESSION['sccp_username'] ?? 'unknown'; }
function lockRead() {
    if (!file_exists(LOCK_FILE)) return null;
    $d = json_decode(@file_get_contents(LOCK_FILE), true);
    if (!is_array($d) || empty($d['user']) || empty($d['ts'])) return null;
    if ((time() - (int)$d['ts']) > LOCK_TTL) return null;
    return $d;
}
function lockWrite($user) { @file_put_contents(LOCK_FILE, json_encode(['user'=>$user,'ts'=>time()])); @chmod(LOCK_FILE, 0644); }
function lockClear() { if (file_exists(LOCK_FILE)) @unlink(LOCK_FILE); }
function requestRead() {
    if (!file_exists(REQUEST_FILE)) return null;
    $d = json_decode(@file_get_contents(REQUEST_FILE), true);
    if (!is_array($d) || empty($d['user']) || empty($d['ts'])) return null;
    if ((time() - (int)$d['ts']) > 60) return null;
    return $d;
}
function requestWrite($user) { @file_put_contents(REQUEST_FILE, json_encode(['user'=>$user,'ts'=>time()])); @chmod(REQUEST_FILE, 0644); }
function requestClear() { if (file_exists(REQUEST_FILE)) @unlink(REQUEST_FILE); }


/**
 * API Access Guardian
 * This function checks authentication for API calls.
 *
 * @param string $action The current API action (from $_GET)
 */
function require_admin_or_test($action) {

    // Allow public test endpoint
    if ($action === 'test') {
        return;
    }

    // Allow POST requests (save configuration) if user is authenticated
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        if (!isAuthenticated()) {
            header('HTTP/1.1 401 Unauthorized');
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Unauthorized. Please log in.'
            ]);
            exit;
        }

        return;
    }

    // Standard authentication check
    if (!isAuthenticated()) {
        header('HTTP/1.1 401 Unauthorized');
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Unauthorized. Please log in.'
        ]);
        exit;
    }

    // Admin check for non-POST API calls
    if (!isAdmin()) {
        header('HTTP/1.1 403 Forbidden');
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Access denied. Administrator privileges required.'
        ]);
        exit;
    }
}

// --- Main API Execution Starts Here ---

$requestAction = $_GET['action'] ?? '';

// Run the Access Guardian immediately
require_admin_or_test($requestAction);

/**
 * SCCP Configurator - Backend API
 * SCCP configuration management and Asterisk integration with Dialplan management
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// All configuration constants are loaded via auth.php -> config.php
define('CONTACTS_FILE', __DIR__ . '/contacts.json');
define('FORWARDS_FILE', __DIR__ . '/forwards.json');
define('EXTRABTN_FILE', __DIR__ . '/extra_buttons.json');
define('RINGALSO_FILE', __DIR__ . '/ring_also.json');
define('LOCK_FILE', __DIR__ . '/edit_lock.json');
define('REQUEST_FILE', __DIR__ . '/lock_request.json');
define('LOCK_TTL', 120);

// Function to read SCCP configuration
function readSCCPConfig() {
    if (!file_exists(SCCP_CONF_PATH)) {
        return ['devices' => [], 'lines' => [], 'general' => []];
    }
    
    $content = file_get_contents(SCCP_CONF_PATH);
    $lines = explode("\n", $content);
    
    $devices = [];
    $lineConfigs = [];
    $currentSection = null;
    $currentData = [];
    
    foreach ($lines as $line) {
        $line = trim($line);
        
        if (empty($line) || $line[0] === ';' || $line[0] === '#') {
            continue;
        }
        
        if (preg_match('/^\[([^\]]+)\]$/', $line, $matches)) {
            if ($currentSection && !empty($currentData)) {
                if (preg_match('/^SEP[0-9A-F]{12}$/i', $currentSection)) {
                    $devices[] = array_merge(['mac' => $currentSection], $currentData);
                } elseif (is_numeric($currentSection)) {
                    $lineConfigs[] = array_merge(['id' => $currentSection], $currentData);
                }
            }
            
            $currentSection = $matches[1];
            $currentData = [];
            continue;
        }
        
        if (strpos($line, '=') !== false) {
            list($key, $value) = array_map('trim', explode('=', $line, 2));
            // For 'button' keep only the FIRST (device's own line); extra buttons = shared/extra, ignore
            if ($key === 'button' && isset($currentData['button'])) {
                // skip - keep first button only
            } else {
                $currentData[$key] = $value;
            }
        }
    }
    
    if ($currentSection && !empty($currentData)) {
        if (preg_match('/^SEP[0-9A-F]{12}$/i', $currentSection)) {
            $devices[] = array_merge(['mac' => $currentSection], $currentData);
        } elseif (is_numeric($currentSection)) {
            $lineConfigs[] = array_merge(['id' => $currentSection], $currentData);
        }
    }
    
    return ['devices' => $devices, 'lines' => $lineConfigs];
}

/**
 * Function to write SCCP configuration
 * Optimized for Cisco 79xx series with strict MAC address formatting
 *
 * @param array $devices List of devices from the UI/JSON
 * @param array $lines List of lines/extensions from the UI/JSON
 * @return bool Success status of the write operation
 */
function writeSCCPConfig($devices, $lines) {
    $extraButtons = file_exists(EXTRABTN_FILE) ? (json_decode(file_get_contents(EXTRABTN_FILE), true) ?: []) : [];
    // Create a backup of the current configuration before making changes
    if (file_exists(SCCP_CONF_PATH)) {
        copy(SCCP_CONF_PATH, SCCP_CONF_BACKUP);
    }

    $network = SCCP_NETWORK;

    // Build the [general] section
    $config = "[general]\n";
    $config .= "servername = " . SCCP_SERVERNAME . "\n";
    $config .= "keepalive = 60\n";
    $config .= "debug = 1\n";
    $config .= "context = " . SCCP_CONTEXT_DEFAULT . "\n";
    $config .= "dateformat = D.M.Y\n";
    $config .= "bindaddr = 0.0.0.0\n";
    $config .= "port = 2000\n";
    $config .= "disallow = all\n";
    $config .= "allow = ulaw\n";
    $config .= "allow = alaw\n";
    $config .= "allow = g729\n";
    $config .= "firstdigittimeout = 16\n";
    $config .= "digittimeout = 8\n";
    $config .= "language = " . (defined('SCCP_LANGUAGE') ? SCCP_LANGUAGE : 'en') . "\n";
    $config .= "deny = 0.0.0.0/0.0.0.0
";
    $networks = defined('SCCP_NETWORKS') ? unserialize(SCCP_NETWORKS) : [$network];
    foreach ($networks as $net) { $config .= "permit = $net
"; }
    foreach ($networks as $net) { $config .= "localnet = $net
"; }
    $config .= "earlyrtp = none
";
    $config .= "directrtp = yes\n\n";
    
    // Build the [lines] section
    $config .= "; === LINES (EXTENSIONS) ===\n";
    foreach ($lines as $line) {
        $lineId = trim($line['id']);
        $config .= "\n[" . $lineId . "]\n";
        $config .= "type = line\n";
        $config .= "id = " . $lineId . "\n";
        $config .= "pin = " . ($line['pin'] ?? '1234') . "\n";
        $config .= "label = " . ($line['label'] ?? 'Line ' . $lineId) . "\n";
        $config .= "description = " . ($line['description'] ?? 'Line ' . $lineId) . "\n";
        $config .= "cid_name = " . ($line['label'] ?? 'Line ' . $lineId) . "\n";
        $config .= "cid_num = " . $lineId . "\n";
        $config .= "context = " . ($line['context'] ?? 'from-internal') . "\n";
        $config .= "incominglimit = 2\n";
        $config .= "transfer = on\n";
        $config .= "vmnum = *97\n";
        $config .= "echocancel = on\n";
        $config .= "silencesuppression = off\n";
        $config .= "dndFeature = on\n";
    }

    $config .= "\n; === DEVICES (PHONES) ===\n";

foreach ($devices as $device) {

    // sanitize MAC
    $cleanMac = strtoupper(preg_replace('/[^A-F0-9]/i', '', $device['mac']));
    $cleanMac = substr($cleanMac, -12);

    $deviceName = "SEP" . $cleanMac;

    $config .= "\n[" . $deviceName . "]\n";
    $config .= "type = device\n";
    $config .= "description = " . ($device['description'] ?? 'Cisco Phone') . "\n";
    $model = $device['model'] ?? '7960';
    $is7912 = ($model === '7912');
    $config .= "devicetype = " . $model . "\n";
    $config .= "keepalive = " . ($is7912 ? '20' : '60') . "\n";
    $config .= "tzoffset = 0\n";
    $config .= "transfer = on\n";
    $config .= "park = " . ($is7912 ? 'off' : 'on') . "\n";

    $extension = '';

    if (!empty($device['extension'])) {
        $extension = trim($device['extension']);
    }

    // fallback: try to find extension from lines
    if (empty($extension)) {
        foreach ($lines as $line) {
            if (!empty($line['device']) && stripos($line['device'], $cleanMac) !== false) {
                $extension = $line['id'];
                break;
            }
        }
    }

    if (!empty($extension)) {

        $config .= "button = line, " . $extension . "\n";
        if (!empty($extraButtons[$deviceName])) {
            foreach ($extraButtons[$deviceName] as $xl) {
                $config .= "button = line, " . $xl . "
";
            }
        }

        generateSEPXML(
            $cleanMac,
            $extension,
            $device['description'] ?? "Extension $extension",
            SERVER_IP,
            $device['model'] ?? '7960'
        );
    }
}    
    // Write the built configuration string to the sccp.conf file
    $result = file_put_contents(SCCP_CONF_PATH, $config);
    
    if ($result !== false) {
        // Set permissions so Asterisk can read the file
        chmod(SCCP_CONF_PATH, 0644);
        @chown(SCCP_CONF_PATH, 'asterisk');
        @chgrp(SCCP_CONF_PATH, 'asterisk');
        return true;
    }
    
    return false;
}

function generateSEPXML($mac, $extension, $label, $server_ip, $model = '7960') {

    // Escape values for XML output
    $label_esc     = htmlspecialchars($label,     ENT_XML1, 'UTF-8');
    $extension_esc = htmlspecialchars($extension, ENT_XML1, 'UTF-8');
    $server_esc    = htmlspecialchars($server_ip, ENT_XML1, 'UTF-8');
    $directory_url = htmlspecialchars('http://' . $server_ip . '/sccp-config/directory.php', ENT_XML1, 'UTF-8');

    // Firmware and feature flags per model
    $is7912 = ($model === '7912');
    $loadInfo = $is7912 ? 'CP7912080004SCCP080108A' : 'P0030801SR02';

$xml = '<?xml version="1.0" encoding="UTF-8"?>
<device>
    <deviceProtocol>SCCP</deviceProtocol>

    <loadInformation>'.$loadInfo.'</loadInformation>

    <devicePool>
        <callManagerGroup>
            <members>
                <member priority="0">
                    <callManager>
                        <ports>
                            <ethernetPhonePort>2000</ethernetPhonePort>
                        </ports>
                        <processNodeName>'.$server_esc.'</processNodeName>
                    </callManager>
                </member>
            </members>
        </callManagerGroup>
    </devicePool>
' . (!$is7912 ? '
    <directoryURL>'.$directory_url.'</directoryURL>
' : '') . '
    <userLocale>
        <name>Czech_Czech_Republic</name>
        <uid>1</uid>
        <langCode>cs</langCode>
        <windowSize>1</windowSize>
        <version>1.0.0.0</version>
    </userLocale>
    <networkLocale>Czech_Republic</networkLocale>

    <sipProfile>
        <sipLines>
            <line button="1">
                <featureID>9</featureID>
                <featureLabel>'.$label_esc.'</featureLabel>
                <name>'.$extension_esc.'</name>
                <displayName>'.$label_esc.'</displayName>
                <contact>'.$extension_esc.'</contact>
            </line>
        </sipLines>
    </sipProfile>

</device>';

    $file = TFTP_PATH . "/SEP" . $mac . ".cnf.xml";

    if (file_put_contents($file, $xml) === false) {
        throw new Exception("Failed to write XML file: $file (check that " . TFTP_PATH . " exists and is writable by the web server)");
    }
}

// Function to manage dialplan for SCCP extensions
function readForwards() {
    if (!file_exists(FORWARDS_FILE)) { return []; }
    return json_decode(file_get_contents(FORWARDS_FILE), true) ?: [];
}

function writeForwards($lines) {
    $fwd = [];
    foreach ($lines as $line) {
        $num = trim($line['fwd_number'] ?? '');
        if ($num !== '') {
            $t = (int)($line['fwd_timeout'] ?? 30);
            if ($t <= 0) { $t = 30; }
            $fwd[$line['id']] = ['number' => $num, 'timeout' => $t];
        }
    }
    $r = file_put_contents(FORWARDS_FILE, json_encode($fwd, JSON_PRETTY_PRINT));
    if ($r !== false) { @chmod(FORWARDS_FILE, 0644); @chown(FORWARDS_FILE, 'asterisk'); }
    return $r !== false;
}

function writeDialplan($lines) {
    $ringAlso = file_exists(RINGALSO_FILE) ? (json_decode(file_get_contents(RINGALSO_FILE), true) ?: []) : [];
    if (file_exists(EXTENSIONS_CONF_PATH)) {
        copy(EXTENSIONS_CONF_PATH, EXTENSIONS_CONF_BACKUP);
    }
    
    // Read existing extensions_custom.conf
    $existingContent = '';
    $sccpSectionStart = false;
    $sccpSectionEnd = false;
    
    if (file_exists(EXTENSIONS_CONF_PATH)) {
        $existingLines = file(EXTENSIONS_CONF_PATH, FILE_IGNORE_NEW_LINES);
        $newContent = [];
        
        foreach ($existingLines as $line) {
            // Detect start of SCCP managed section
            if (strpos($line, '; === SCCP MANAGED SECTION - START ===') !== false) {
                $sccpSectionStart = true;
                continue;
            }
            
            // Detect end of SCCP managed section
            if (strpos($line, '; === SCCP MANAGED SECTION - END ===') !== false) {
                $sccpSectionEnd = true;
                continue;
            }
            
            // Skip lines inside SCCP managed section
            if ($sccpSectionStart && !$sccpSectionEnd) {
                continue;
            }
            
            $newContent[] = $line;
        }
        
        $existingContent = implode("\n", $newContent);
    }
    
    // Build SCCP dialplan section
    $dialplan = "\n; === SCCP MANAGED SECTION - START ===\n";
    $dialplan .= "; Auto-generated by SCCP Configurator - DO NOT EDIT MANUALLY\n";
    $dialplan .= "; Last updated: " . date('Y-m-d H:i:s') . "\n\n";
    
    $dialplan .= "[" . SCCP_CONTEXT . "]\n";
    $dialplan .= "; SCCP Extensions\n\n";
    
    foreach ($lines as $line) {
        $ext = $line['id'];
        $label = $line['label'] ?? "Extension {$ext}";
        $fwdNum = trim($line['fwd_number'] ?? '');
        $fwdTimeout = (int)($line['fwd_timeout'] ?? 30);
        if ($fwdTimeout <= 0) { $fwdTimeout = 30; }
        $ringTimeout = ($fwdNum !== '') ? $fwdTimeout : 30;

        $dialplan .= "; {$label}\n";
        $dialplan .= "exten => {$ext},1,NoOp(SCCP Extension {$ext} - {$label})\n";
        $dialTarget = "Local/{$ext}@sccp-direct/n";
        if (!empty($ringAlso[$ext])) {
            foreach ($ringAlso[$ext] as $ra) { $dialTarget .= "&Local/{$ra}@sccp-direct/n"; }
        }
        $dialplan .= " same => n,Dial({$dialTarget},{$ringTimeout},tT)
";
        if ($fwdNum !== '') {
            $dialplan .= " same => n,GotoIf(\$[\"\${DIALSTATUS}\" = \"ANSWER\"]?fwdend)\n";
            $dialplan .= " same => n,GotoIf(\$[\"\${DIALSTATUS}\" = \"BUSY\"]?fwdbusy)\n";
            $dialplan .= " same => n,NoOp(No answer - forwarding {$ext} to {$fwdNum})\n";
            $dialplan .= " same => n,Dial(Local/{$fwdNum}@from-internal/n,30,tT)\n";
            $dialplan .= " same => n,Hangup()\n";
            $dialplan .= " same => n(fwdbusy),VoiceMail({$ext}@default,b)\n";
            $dialplan .= " same => n,Hangup()\n";
            $dialplan .= " same => n(fwdend),Hangup()\n\n";
        } else {
            $dialplan .= " same => n,GotoIf(\$[\"\${DIALSTATUS}\" = \"BUSY\"]?busy:unavail)\n";
            $dialplan .= " same => n(unavail),VoiceMail({$ext}@default,u)\n";
            $dialplan .= " same => n,Hangup()\n";
            $dialplan .= " same => n(busy),VoiceMail({$ext}@default,b)\n";
            $dialplan .= " same => n,Hangup()\n\n";
        }
    }

    // sccp-direct context: proxies calls to SCCP channel
    // This is intentionally a separate context so that bridge_native_rtp
    // cannot upgrade the bridge (Local channel does not support native RTP).
    $dialplan .= "; sccp-direct: do NOT merge this with the main context\n";
    $dialplan .= "[sccp-direct]\n";
    $dialplan .= "exten => _X.,1,NoOp(sccp-direct proxy for \${EXTEN})\n";
    $dialplan .= " same => n,Dial(SCCP/\${EXTEN},30,tT)\n";
    $dialplan .= " same => n,Hangup()\n\n";

    $dialplan .= "; === SCCP MANAGED SECTION - END ===\n";
    
    // Combine existing content with new SCCP section
    $finalContent = trim($existingContent) . "\n" . $dialplan;
    
    $result = file_put_contents(EXTENSIONS_CONF_PATH, $finalContent);
    
    if ($result !== false) {
        chmod(EXTENSIONS_CONF_PATH, 0644);
        chown(EXTENSIONS_CONF_PATH, 'asterisk');
        chgrp(EXTENSIONS_CONF_PATH, 'asterisk');
        return true;
    }
    
    return false;
}
/**
 * Function to reload Asterisk SCCP module
 * * This function uses 'sudo' to allow the web server user (e.g., www-data or asterisk)
 * to execute the Asterisk CLI command without a password prompt.
 * Ensure that the sudoers file is correctly configured.
 *
 * @return array Status of the reload operation
 */
function reloadSCCP() {
    $output = [];
    $return_var = 0;

    // Use 'sudo' to grant necessary permissions for the web server user.
    // Ensure you have: www-data ALL=(ALL) NOPASSWD: /usr/sbin/asterisk in your /etc/sudoers
    $command = "sudo " . ASTERISK_CLI . ' -rx "sccp reload" 2>&1';
    
    exec($command, $output, $return_var);

    $outputText = implode("\n", $output);

    // Analyze the Asterisk output to provide a more descriptive message to the UI
    if (strpos($outputText, 'has not changed') !== false) {
        $message = 'SCCP module reloaded successfully. No configuration changes detected.';
    } elseif ($return_var !== 0) {
        $message = 'Error reloading SCCP module. Check system permissions or Asterisk status.';
    } else {
        $message = 'SCCP module reloaded successfully. Configuration applied.';
    }

    return [
        'success' => $return_var === 0,
        'output' => $message,
        'raw_output' => $outputText, // Useful for debugging if needed
        'command' => 'sccp reload'
    ];
}


// Function to reload Asterisk dialplan
function reloadDialplan() {
    $output = [];
    $return_var = 0;
    
    exec(ASTERISK_CLI . ' -rx "dialplan reload" 2>&1', $output, $return_var);
    
    return [
        'success' => $return_var === 0,
        'output' => implode("\n", $output),
        'command' => 'dialplan reload'
    ];
}

// Function to get device status with extensions
function getSCCPDevices() {
    $output = [];
    exec(ASTERISK_CLI . ' -rx "sccp show devices" 2>&1', $output);
    
    $devices = [];
    $parseData = false;
    
    foreach ($output as $line) {
        if (strpos($line, '+---') !== false || 
            strpos($line, '+ ===') !== false || 
            strpos($line, '| Descr') !== false) {
            $parseData = true;
            continue;
        }
        
        if ($parseData && strpos($line, '|') !== false && strpos($line, 'SEP') !== false) {
            if (preg_match('/(SEP[0-9A-F]{12})\s+(\w+)/', $line, $matches)) {
                $mac = trim($matches[1]);
                $regState = strtolower(trim($matches[2]));
                
                preg_match('/\|\s*([^\|]+?)\s+\d+\.\d+/', $line, $descMatch);
                $description = isset($descMatch[1]) ? trim($descMatch[1]) : '';
                
                preg_match('/(\d+\.\d+\.\d+\.\d+:\d+)/', $line, $ipMatch);
                $address = isset($ipMatch[1]) ? trim($ipMatch[1]) : '';
                
                $devices[] = [
                    'description' => $description,
                    'address' => $address,
                    'mac' => $mac,
                    'status' => $regState === 'ok' ? 'online' : $regState,
                    'extension' => ''
                ];
            }
        }
    }
    
    // Get extensions from sccp show lines and map to devices
    $lines = getSCCPLines();
    $macToExtension = [];
    foreach ($lines as $line) {
        if (!empty($line['device'])) {
            $macToExtension[$line['device']] = $line['extension'];
        }
    }
    
    // Add extension info to devices
    foreach ($devices as $key => $device) {
        if (isset($macToExtension[$device['mac']])) {
            $devices[$key]['extension'] = $macToExtension[$device['mac']];
        }
    }
    
    return $devices;
}

// Function to get line status
function getSCCPLines() {
    $output = [];
    exec(ASTERISK_CLI . ' -rx "sccp show lines" 2>&1', $output);
    
    $lines = [];
    
    foreach ($output as $line) {
        // Skip lines that don't contain extension numbers
        if (!preg_match('/^\|\s*(\d{3,4})\s/', $line)) {
            continue;
        }
        
        // Parse line
        $line = trim($line, '| ');
        
        // Split by multiple spaces (2 or more)
        $parts = preg_split('/\s{2,}/', $line);
        
        if (count($parts) < 4) continue;
        
        $extension = trim($parts[0]);
        $suffix = isset($parts[1]) ? trim($parts[1]) : '';
        $label = isset($parts[2]) ? trim($parts[2]) : '';
        $description = isset($parts[3]) ? trim($parts[3]) : '';
        
        // Find device MAC
        $hasDevice = false;
        $device = null;
        if (preg_match('/(SEP[0-9A-F]{12})/', $line, $macMatch)) {
            $hasDevice = true;
            $device = trim($macMatch[1]);
        }
        
        $lines[] = [
            'id' => $extension,
            'extension' => $extension,
            'label' => $label ?: "Line {$extension}",
            'description' => $description ?: $label,
            'device' => $device,
            'status' => $hasDevice ? 'online' : 'offline'
        ];
    }
    
    return $lines;
}

// Main API logic
$method = $_SERVER['REQUEST_METHOD'];
// We already got $requestAction at the top

try {
    switch ($method) {
        case 'GET':
            if ($requestAction === 'config') {
                $config = readSCCPConfig();
                $fwd = readForwards();
                foreach ($config['lines'] as &$__ln) {
                    if (isset($fwd[$__ln['id']])) {
                        $__ln['fwd_number'] = $fwd[$__ln['id']]['number'];
                        $__ln['fwd_timeout'] = (string)$fwd[$__ln['id']]['timeout'];
                    }
                }
                unset($__ln);
                echo json_encode(['success' => true, 'data' => $config, 'version' => (string)@filemtime(SCCP_CONF_PATH)]);
                
            } elseif ($requestAction === 'status') {
                $devices = getSCCPDevices();
                $lines = getSCCPLines();
                
                // Load config to get model info
                $config = readSCCPConfig();
                $configDevices = $config['devices'];
                
                // Merge config data (model) with status data
                foreach ($devices as $key => $device) {
                    foreach ($configDevices as $configDevice) {
                        if ($configDevice['mac'] === $device['mac']) {
                            $devices[$key]['model'] = $configDevice['model'] ?? '7960';
                            break;
                        }
                    }
                }
                
                echo json_encode([
                    'success' => true, 
                    'devices' => $devices,
                    'lines' => $lines
                ]);
                
            } elseif ($requestAction === 'lock_acquire' || $requestAction === 'lock_heartbeat') {
                $me = lockCurrentUser(); $lock = lockRead(); $req = requestRead();
                $active = (($_GET['active'] ?? '') === '1'); // proof of activity from current code; old tabs omit it
                if (!$active) {
                    echo json_encode(['granted'=>($lock && $lock['user']===$me), 'holder'=>($lock['user'] ?? null), 'request'=>($req['user'] ?? null)]);
                } elseif ($lock === null || $lock['user'] === $me) {
                    lockWrite($me);
                    if ($req && $req['user'] === $me) { requestClear(); $req = null; }
                    echo json_encode(['granted'=>true,'holder'=>$me, 'request'=>($req['user'] ?? null)]);
                } else {
                    echo json_encode(['granted'=>false,'holder'=>$lock['user'], 'request'=>($req['user'] ?? null)]);
                }
            } elseif ($requestAction === 'lock_status') {
                $me = lockCurrentUser(); $lock = lockRead(); $req = requestRead();
                echo json_encode(['locked'=>$lock!==null,'holder'=>$lock['user']??null,'mine'=>($lock && $lock['user']===$me),'request'=>($req['user']??null)]);
            } elseif ($requestAction === 'lock_release') {
                $me = lockCurrentUser(); $lock = lockRead();
                if ($lock === null || $lock['user'] === $me) { lockClear(); }
                echo json_encode(['released'=>true]);
            } elseif ($requestAction === 'lock_request') {
                $me = lockCurrentUser(); $lock = lockRead();
                if ($lock && $lock['user'] !== $me) { requestWrite($me); }
                echo json_encode(['requested'=>true]);
            } elseif ($requestAction === 'lock_handoff') {
                $me = lockCurrentUser(); $lock = lockRead();
                if ($lock === null || $lock['user'] === $me) { lockClear(); requestClear(); }
                echo json_encode(['handoff'=>true]);
            } elseif ($requestAction === 'test') {
                echo json_encode([
                    'success' => true,
                    'message' => 'SCCP API works!',
                    'timestamp' => date('Y-m-d H:i:s'),
                    'sccp_conf_exists' => file_exists(SCCP_CONF_PATH),
                    'sccp_conf_readable' => is_readable(SCCP_CONF_PATH),
                    'sccp_conf_writable' => is_writable(SCCP_CONF_PATH),
                    'extensions_conf_exists' => file_exists(EXTENSIONS_CONF_PATH),
                    'extensions_conf_writable' => is_writable(EXTENSIONS_CONF_PATH)
                ]);

            } elseif ($requestAction === 'contacts') {
                $contacts = [];
                if (file_exists(CONTACTS_FILE)) {
                    $contacts = json_decode(file_get_contents(CONTACTS_FILE), true) ?: [];
                }
                echo json_encode(['success' => true, 'contacts' => $contacts]);

            } elseif ($requestAction === 'audit_log') {
                if (!isAdmin()) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Forbidden']);
                    break;
                }
                $entries = getAuditLog(10000);
                echo json_encode(['success' => true, 'log' => $entries, 'total' => count($entries)]);

            } elseif ($requestAction === 'export_log') {
                if (!isAdmin()) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Forbidden']);
                    break;
                }
                $entries = getAuditLog(0); // 0 = unlimited
                $filename = 'sccp_audit_' . date('Y-m-d_H-i-s') . '.csv';
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Cache-Control: no-cache');
                $out = fopen('php://output', 'w');
                fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM for Excel
                fputcsv($out, ['ID', 'Timestamp', 'User', 'Action', 'Entity Type', 'Entity ID', 'Description', 'IP Address']);
                foreach ($entries as $row) {
                    fputcsv($out, [$row['id'], $row['created_at'], $row['username'], $row['action'], $row['entity_type'], $row['entity_id'] ?? '', $row['description'] ?? '', $row['ip_address'] ?? '']);
                }
                fclose($out);
                logAudit('log_export', 'system', null, 'Audit log exported to CSV (' . count($entries) . ' entries)');
                exit;
            } else {
                echo json_encode(['success' => false, 'error' => 'Unknown action']);
            }
            break;
            
        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);

            // --- Save contacts (phone directory) ---
            if ($requestAction === 'contacts') {
                $contacts = $input['contacts'] ?? [];
                // Basic sanitize
                $contacts = array_values(array_map(function ($c) {
                    return [
                        'name'       => trim($c['name']       ?? ''),
                        'number'     => trim($c['number']     ?? ''),
                        'department' => trim($c['department'] ?? ''),
                    ];
                }, $contacts));
                // Remove entries without a name or number
                $contacts = array_values(array_filter($contacts, fn($c) => $c['name'] !== '' && $c['number'] !== ''));

                $result = file_put_contents(CONTACTS_FILE, json_encode($contacts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                if ($result === false) {
                    throw new Exception('Failed to write contacts file');
                }
                chmod(CONTACTS_FILE, 0644);
                logAudit('contacts_save', 'contact', null, 'Phone directory saved (' . count($contacts) . ' contacts)');
                echo json_encode(['success' => true, 'message' => count($contacts) . ' contacts saved']);
                break;
            }

            if (!isset($input['devices']) || !isset($input['lines'])) {
                throw new Exception('Missing required data (devices, lines)');
            }

            // --- Generate XML files only (no config save, no Asterisk reload) ---
            if ($requestAction === 'generate-xml') {
                $results  = [];
                $warnings = [];

                foreach ($input['devices'] as $device) {
                    $cleanMac = strtoupper(preg_replace('/[^A-F0-9]/i', '', $device['mac']));
                    $cleanMac = substr($cleanMac, -12);
                    $sepName  = "SEP{$cleanMac}";
                    $extension = trim($device['extension'] ?? '');

                    if (empty($extension)) {
                        $warnings[] = "{$sepName}: No extension assigned, skipping XML generation";
                        $results[$sepName] = 'skipped';
                        continue;
                    }

                    $label = $device['description'] ?? "Extension {$extension}";

                    try {
                        generateSEPXML($cleanMac, $extension, $label, SERVER_IP, $device['model'] ?? '7960');
                        $results[$sepName] = 'success';
                    } catch (Exception $e) {
                        $results[$sepName] = 'error';
                        $warnings[] = "{$sepName}: " . $e->getMessage();
                    }
                }

                echo json_encode([
                    'success'  => true,
                    'message'  => count($results) . ' XML file(s) processed',
                    'results'  => $results,
                    'warnings' => $warnings,
                ]);
                break;
            }

            // --- Concurrency lock: only the holder can save ---
            $me = lockCurrentUser(); $lock = lockRead();
            if ($lock !== null && $lock['user'] !== $me) {
                http_response_code(409);
                echo json_encode(['success'=>false,'error'=>'Configuration is currently being edited by '.$lock['user'].'. Please wait and refresh (Ctrl+F5).']);
                break;
            }
            lockWrite($me);

            // --- Version check: reject saves from a stale tab (config changed in the meantime) ---
            $clientVer = isset($input['version']) ? (string)$input['version'] : null;
            $curVer = (string)@filemtime(SCCP_CONF_PATH);
            if ($clientVer === null || $clientVer === '' || $clientVer !== $curVer) {
                http_response_code(409);
                echo json_encode(['success'=>false,'error'=>'Configuration changed in the meantime (someone saved, or you have an old tab). Refresh (Ctrl+F5) and redo your change.']);
                break;
            }

            // --- Default POST: save full config and reload Asterisk ---

            // Snapshot old config for audit diff
            $oldConfig = readSCCPConfig();

            // Write SCCP configuration
            $sccpResult = writeSCCPConfig($input['devices'], $input['lines']);
            if (!$sccpResult) {
                throw new Exception('Failed to write SCCP configuration');
            }

            // Write dialplan configuration
            $dialplanResult = writeDialplan($input['lines']);
            if (!$dialplanResult) {
                throw new Exception('Failed to write dialplan configuration');
            }
            writeForwards($input['lines']);

            // Log configuration changes
            diffAndLog($oldConfig, $input['lines'], $input['devices']);

            // Reload SCCP
            $sccpReload = reloadSCCP();

            // Reload dialplan
            $dialplanReload = reloadDialplan();

            echo json_encode([
                'success' => true,
                'message' => 'Configuration saved, SCCP and dialplan reloaded',
                'sccp_reload' => $sccpReload,
                'dialplan_reload' => $dialplanReload,
                'version' => (string)@filemtime(SCCP_CONF_PATH)
            ]);
            break;
            
        case 'PUT':
            if ($requestAction === 'reload') {
                $sccpResult = reloadSCCP();
                $dialplanResult = reloadDialplan();
                
                echo json_encode([
                    'success' => $sccpResult['success'] && $dialplanResult['success'],
                    'message' => 'SCCP and dialplan reloaded',
                    'sccp_output' => $sccpResult['output'],
                    'dialplan_output' => $dialplanResult['output']
                ]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Unknown action']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Unsupported method']);
            break;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
