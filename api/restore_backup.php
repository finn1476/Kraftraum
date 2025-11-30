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

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['filename']) || !isset($data['pin'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing filename or PIN']);
    exit;
}

// PIN validieren
if ($data['pin'] !== ADMIN_PIN) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Falscher PIN-Code']);
    exit;
}

$backupFile = BACKUP_DIR . '/' . basename($data['filename']);

// Sicherheitsprüfung
if (!file_exists($backupFile) || strpos(basename($backupFile), 'backup_') !== 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid backup file']);
    exit;
}

// Datenbank schließen falls offen
if (isset($GLOBALS['db'])) {
    $GLOBALS['db']->close();
}

// Backup der aktuellen Datenbank erstellen (Sicherheit)
$safetyBackup = BACKUP_DIR . '/safety_backup_' . date('Y-m-d_H-i-s') . '.db';
if (file_exists(DB_PATH)) {
    copy(DB_PATH, $safetyBackup);
}

// Backup wiederherstellen
if (copy($backupFile, DB_PATH)) {
    @chmod(DB_PATH, 0666);
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to restore backup']);
}
?>

