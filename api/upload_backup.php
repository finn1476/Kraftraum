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

$pin = isset($_POST['pin']) ? $_POST['pin'] : '';

// PIN validieren
if ($pin !== ADMIN_PIN) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Falscher PIN-Code']);
    exit;
}

if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No file uploaded or upload error']);
    exit;
}

$uploadedFile = $_FILES['backup_file'];
$fileName = $uploadedFile['name'];
$tmpName = $uploadedFile['tmp_name'];

// Sicherheitsprüfung: Nur .db Dateien erlauben
if (pathinfo($fileName, PATHINFO_EXTENSION) !== 'db') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Only .db files are allowed']);
    exit;
}

// Prüfen ob es eine gültige SQLite-Datei ist
$fileInfo = @getimagesize($tmpName);
$fileHeader = @file_get_contents($tmpName, false, null, 0, 16);
if (substr($fileHeader, 0, 16) !== 'SQLite format 3') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid SQLite database file']);
    exit;
}

// Backup-Verzeichnis erstellen falls nicht vorhanden
if (!file_exists(BACKUP_DIR)) {
    @mkdir(BACKUP_DIR, 0777, true);
}

// Dateiname generieren (mit Timestamp)
$backupFileName = 'backup_uploaded_' . date('Y-m-d_H-i-s') . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($fileName));
$backupPath = BACKUP_DIR . '/' . $backupFileName;

// Datei hochladen
if (@move_uploaded_file($tmpName, $backupPath)) {
    @chmod($backupPath, 0644);
    echo json_encode([
        'success' => true, 
        'filename' => $backupFileName,
        'message' => 'Backup erfolgreich hochgeladen'
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to save uploaded file']);
}
?>

