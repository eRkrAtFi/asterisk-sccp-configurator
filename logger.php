<?php
require_once __DIR__ . '/config.php';

function getLogDB() {
    try {
        return new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    } catch (PDOException $e) {
        error_log("SCCP Logger DB error: " . $e->getMessage());
        return null;
    }
}

// Table sccp_audit_log is pre-created; sccp_auth has SELECT+INSERT only

function logAudit($action, $entityType, $entityId, $description) {
    $db = getLogDB();
    if (!$db) return;
    try {
        $username = $_SESSION['sccp_username'] ?? 'system';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $stmt = $db->prepare(
            "INSERT INTO sccp_audit_log (username, action, entity_type, entity_id, description, ip_address) VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$username, $action, $entityType, $entityId, $description, $ip]);
    } catch (PDOException $e) {
        error_log("SCCP Logger write error: " . $e->getMessage());
    }
}

function getAuditLog($limit = 500) {
    $db = getLogDB();
    if (!$db) return [];
    try {
        $limit = (int)$limit;
        $sql = $limit > 0
            ? "SELECT * FROM sccp_audit_log ORDER BY created_at DESC LIMIT {$limit}"
            : "SELECT * FROM sccp_audit_log ORDER BY created_at DESC";
        $stmt = $db->query($sql);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("SCCP Logger read error: " . $e->getMessage());
        return [];
    }
}

function diffAndLog($oldConfig, $newLines, $newDevices) {
    // Lines diff
    $oldLines = [];
    foreach (($oldConfig['lines'] ?? []) as $l) {
        $oldLines[$l['id']] = $l;
    }
    $newLinesMap = [];
    foreach ($newLines as $l) {
        $newLinesMap[$l['id']] = $l;
    }

    foreach ($newLinesMap as $id => $line) {
        if (!isset($oldLines[$id])) {
            $label = $line['label'] ?? $id;
            logAudit('line_add', 'line', $id, "Line {$id} added ({$label})");
        } else {
            $old = $oldLines[$id];
            $changes = [];
            if (($old['label'] ?? '') !== ($line['label'] ?? '')) {
                $changes[] = "label: '{$old['label']}' → '{$line['label']}'";
            }
            if (($old['description'] ?? '') !== ($line['description'] ?? '')) {
                $changes[] = "popis: '{$old['description']}' → '{$line['description']}'";
            }
            if (!empty($changes)) {
                logAudit('line_modify', 'line', $id, "Line {$id} modified: " . implode(', ', $changes));
            }
        }
    }
    foreach ($oldLines as $id => $line) {
        if (!isset($newLinesMap[$id])) {
            $label = $line['label'] ?? $id;
            logAudit('line_remove', 'line', $id, "Line {$id} removed ({$label})");
        }
    }

    // Devices diff
    $oldDevices = [];
    foreach (($oldConfig['devices'] ?? []) as $d) {
        $mac = strtoupper(preg_replace('/[^A-F0-9]/i', '', $d['mac']));
        $mac = substr($mac, -12);
        $oldDevices[$mac] = $d;
    }
    $newDevicesMap = [];
    foreach ($newDevices as $d) {
        $mac = strtoupper(preg_replace('/[^A-F0-9]/i', '', $d['mac']));
        $mac = substr($mac, -12);
        $newDevicesMap[$mac] = $d;
    }

    foreach ($newDevicesMap as $mac => $device) {
        $sepName = "SEP{$mac}";
        if (!isset($oldDevices[$mac])) {
            $ext = $device['extension'] ?? '?';
            $desc = $device['description'] ?? '';
            logAudit('device_add', 'device', $sepName, "Device {$sepName} added (ext: {$ext}, {$desc})");
        } else {
            $old = $oldDevices[$mac];
            $changes = [];
            if (($old['description'] ?? '') !== ($device['description'] ?? '')) {
                $changes[] = "popis: '{$old['description']}' → '{$device['description']}'";
            }
            $oldModel = $old['devicetype'] ?? $old['model'] ?? '';
            $newModel = $device['model'] ?? '';
            if ($oldModel !== $newModel) {
                $changes[] = "model: '{$oldModel}' → '{$newModel}'";
            }
            if (!empty($changes)) {
                logAudit('device_modify', 'device', $sepName, "Device {$sepName} modified: " . implode(', ', $changes));
            }
        }
    }
    foreach ($oldDevices as $mac => $device) {
        if (!isset($newDevicesMap[$mac])) {
            $sepName = "SEP{$mac}";
            $desc = $device['description'] ?? '';
            logAudit('device_remove', 'device', $sepName, "Device {$sepName} removed ({$desc})");
        }
    }
}
