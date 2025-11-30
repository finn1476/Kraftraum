<?php
session_start();
require_once '../config.php';

// Prüfen ob eingeloggt
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    die('Not authorized');
}

$filename = isset($_GET['file']) ? basename($_GET['file']) : '';
$pin = isset($_GET['pin']) ? $_GET['pin'] : '';

// PIN validieren
if ($pin !== ADMIN_PIN) {
    http_response_code(403);
    die('Falscher PIN-Code');
}

// Sicherheitsprüfung
if (empty($filename) || strpos($filename, 'backup_') !== 0 || substr($filename, -3) !== '.db') {
    http_response_code(400);
    die('Invalid backup file');
}

$backupPath = BACKUP_DIR . '/' . $filename;

if (!file_exists($backupPath)) {
    http_response_code(404);
    die('Backup file not found');
}

// Datei zum Download senden
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($backupPath));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

readfile($backupPath);
exit;
?>

