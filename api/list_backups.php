<?php
session_start();
require_once '../config.php';
header('Content-Type: application/json');

// Prüfen ob eingeloggt
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not authorized']);
    exit;
}

$backups = [];
// Alle Backup-Dateien finden (backup_*.db und backup_uploaded_*.db)
$files = array_merge(
    glob(BACKUP_DIR . '/backup_*.db'),
    glob(BACKUP_DIR . '/backup_uploaded_*.db')
);

foreach ($files as $file) {
    $backups[] = [
        'filename' => basename($file),
        'path' => $file,
        'size' => filesize($file),
        'date' => date('Y-m-d H:i:s', filemtime($file)),
        'timestamp' => filemtime($file)
    ];
}

// Nach Datum sortieren (neueste zuerst)
usort($backups, function($a, $b) {
    return $b['timestamp'] - $a['timestamp'];
});

echo json_encode(['success' => true, 'backups' => $backups]);
?>

