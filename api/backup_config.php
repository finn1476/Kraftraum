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

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Konfiguration abrufen
    echo json_encode(['success' => true, 'config' => getBackupConfig()]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Konfiguration speichern
    $data = json_decode(file_get_contents('php://input'), true);
    
    $config = [
        'enabled' => isset($data['enabled']) ? (bool)$data['enabled'] : false,
        'interval_hours' => isset($data['interval_hours']) ? max(1, (int)$data['interval_hours']) : 24,
        'max_backups' => isset($data['max_backups']) ? max(1, (int)$data['max_backups']) : 30
    ];
    
    if (saveBackupConfig($config)) {
        echo json_encode(['success' => true, 'config' => $config]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to save config']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);
?>



