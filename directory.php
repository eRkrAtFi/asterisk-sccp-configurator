<?php
/**
 * SCCP Configurator - Cisco IP Phone Directory Service
 *
 * Returns XML in CiscoIPPhoneDirectory format.
 * Accessed directly by Cisco phones via the "Directories" button.
 * No session authentication required (phones don't carry cookies).
 */

require_once __DIR__ . '/config.php';

define('CONTACTS_FILE', __DIR__ . '/contacts.json');

header('Content-Type: text/xml; charset=UTF-8');
header('Cache-Control: no-cache, no-store');

$search = strtolower(trim($_GET['search'] ?? ''));

$contacts = [];
if (file_exists(CONTACTS_FILE)) {
    $contacts = json_decode(file_get_contents(CONTACTS_FILE), true) ?: [];
}

// Filter by search term if provided
if ($search !== '') {
    $contacts = array_values(array_filter($contacts, function ($c) use ($search) {
        return strpos(strtolower($c['name']       ?? ''), $search) !== false
            || strpos(        ($c['number']       ?? ''), $search) !== false
            || strpos(strtolower($c['department'] ?? ''), $search) !== false;
    }));
}

// Sort alphabetically by name
usort($contacts, fn($a, $b) => strcmp($a['name'] ?? '', $b['name'] ?? ''));

$title = $search !== ''
    ? 'Results: ' . htmlspecialchars($search, ENT_XML1, 'UTF-8')
    : 'Company Directory';

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<CiscoIPPhoneDirectory>' . "\n";
echo '  <Title>' . $title . '</Title>' . "\n";
echo '  <Prompt>Select entry to dial</Prompt>' . "\n";

if (empty($contacts)) {
    echo "  <DirectoryEntry><Name>-- No entries --</Name><Telephone></Telephone></DirectoryEntry>\n";
} else {
    foreach ($contacts as $c) {
        $displayName = trim(($c['department'] ? $c['department'] . ' / ' : '') . ($c['name'] ?? ''));
        $name   = htmlspecialchars($displayName,      ENT_XML1, 'UTF-8');
        $number = htmlspecialchars($c['number'] ?? '', ENT_XML1, 'UTF-8');
        echo "  <DirectoryEntry>\n";
        echo "    <Name>{$name}</Name>\n";
        echo "    <Telephone>{$number}</Telephone>\n";
        echo "  </DirectoryEntry>\n";
    }
}

echo '</CiscoIPPhoneDirectory>';
