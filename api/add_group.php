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

if (!isset($data['name']) || empty(trim($data['name']))) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Name is required']);
    exit;
}

$db = getDB();
$name = trim($data['name']);

// Prüfen ob Gruppe bereits existiert
$stmt = $db->prepare("SELECT id FROM groups WHERE name = ?");
$stmt->bindValue(1, $name, SQLITE3_TEXT);
$result = $stmt->execute();
$existing = $result->fetchArray();

if ($existing) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Gruppe existiert bereits']);
    $db->close();
    exit;
}

// Gruppe erstellen
$stmt = $db->prepare("INSERT INTO groups (name) VALUES (?)");
$stmt->bindValue(1, $name, SQLITE3_TEXT);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'id' => $db->lastInsertRowID()]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to create group: ' . $db->lastErrorMsg()]);
}

$db->close();
?>



