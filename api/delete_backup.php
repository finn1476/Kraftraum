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

$filename = basename($data['filename']);

// Sicherheitsprüfung
if (strpos($filename, 'backup_') !== 0 || substr($filename, -3) !== '.db') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid backup file']);
    exit;
}

$backupPath = BACKUP_DIR . '/' . $filename;

if (!file_exists($backupPath)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Backup file not found']);
    exit;
}

// Prüfen ob Datei beschreibbar ist
if (!is_writable($backupPath) && !is_writable(BACKUP_DIR)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Backup file or directory is not writable']);
    exit;
}

// Backup löschen
$deleted = @unlink($backupPath);

if ($deleted) {
    // Prüfen ob Datei wirklich gelöscht wurde
    if (file_exists($backupPath)) {
        // Versuche nochmal zu löschen
        @chmod($backupPath, 0666);
        $deleted = @unlink($backupPath);
    }
    
    if ($deleted && !file_exists($backupPath)) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to delete backup file']);
    }
} else {
    $error = error_get_last();
    $errorMsg = $error ? $error['message'] : 'Unknown error';
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to delete backup: ' . $errorMsg]);
}
?>

