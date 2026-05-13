# SCCP Configurator

Web-based management interface for Cisco SCCP phones on Asterisk with chan_sccp.

## Stack

- **Frontend** — React 18 (Babel standalone, Tailwind CSS)
- **Backend** — PHP 8.x REST API
- **Auth** — FreePBX `userman_users` database + local JSON fallback
- **Phones** — Cisco 7900 series via chan_sccp

## Features

- Provision SCCP phones (sccp.conf + SEP*.cnf.xml via TFTP)
- Auto-generate Asterisk dialplan for SCCP extensions
- Phone directory (served to phones via XML)
- Audit log — append-only, CSV export

## Setup

### Requirements

- Asterisk with chan_sccp compiled from source
- FreePBX (uses its database for auth)
- nginx + PHP 8.x
- MariaDB (`asterisk` database)

### Installation

```bash
cp config.example.php config.php
# Edit config.php with your values
```

Create the audit log table and grant permissions:

```sql
CREATE TABLE IF NOT EXISTS sccp_audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    username VARCHAR(100) NOT NULL,
    action VARCHAR(50) NOT NULL,
    entity_type VARCHAR(30) NOT NULL,
    entity_id VARCHAR(50) DEFAULT NULL,
    description TEXT,
    ip_address VARCHAR(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

GRANT SELECT, INSERT ON asterisk.sccp_audit_log TO 'sccp_auth'@'localhost';
FLUSH PRIVILEGES;
```

Allow the web server to reload Asterisk:

```bash
# Add to /etc/sudoers
www-data ALL=(ALL) NOPASSWD: /usr/sbin/asterisk
```

### Local user management

```bash
php manage_users.php add admin mypassword "Admin Name" admin
php manage_users.php list
```

## File structure

```
├── api.php             REST API
├── auth.php            Authentication + session guard
├── logger.php          Audit log
├── index.php           React frontend
├── login.php           Login page
├── directory.php       XML phone directory endpoint
├── manage_users.php    CLI user management
├── config.example.php  Configuration template
└── whoami.php          Debug endpoint
```
