<?php
// Copy this file to config.php and fill in your values.

// === DATABASE ===
define('DB_HOST', 'localhost');
define('DB_NAME', 'asterisk');
define('DB_USER', 'sccp_auth');
define('DB_PASS', 'your_password_here');

// === SERVER ===
define('SERVER_IP',     '10.0.x.x');          // Asterisk server IP (sent to phones via TFTP)
define('SCCP_NETWORK',  '10.0.x.x/255.255.x.x'); // Permitted phone subnet

// === PATHS ===
define('SCCP_CONF_PATH',         '/etc/asterisk/sccp.conf');
define('SCCP_CONF_BACKUP',       '/etc/asterisk/sccp.conf.backup');
define('EXTENSIONS_CONF_PATH',   '/etc/asterisk/extensions_custom.conf');
define('EXTENSIONS_CONF_BACKUP', '/etc/asterisk/extensions_custom.conf.backup');
define('ASTERISK_CLI',           '/usr/sbin/asterisk');
define('TFTP_PATH',              '/var/lib/tftpboot');

// === SCCP DEFAULTS ===
define('SCCP_LANGUAGE',        'en');   // Asterisk sound-prompt language (e.g. en, cs, de)
define('SCCP_SERVERNAME',      'FreePBX');
define('SCCP_CONTEXT_DEFAULT', 'from-internal');
define('SCCP_CONTEXT',         'from-internal-custom');

// === SESSION ===
define('SESSION_TIMEOUT_SECONDS', 1800); // 30 minutes
