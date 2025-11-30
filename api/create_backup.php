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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$backupPath = createBackup();
if ($backupPath) {
    updateLastBackupTime();
    echo json_encode(['success' => true, 'backup_path' => $backupPath]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to create backup']);
}
?>



