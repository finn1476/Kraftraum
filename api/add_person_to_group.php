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

if (!isset($data['group_id']) || !isset($data['person_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing group_id or person_id']);
    exit;
}

$db = getDB();
$groupId = intval($data['group_id']);
$personId = intval($data['person_id']);

// Prüfen ob Mitgliedschaft bereits existiert
$stmt = $db->prepare("SELECT id FROM group_members WHERE group_id = ? AND person_id = ?");
$stmt->bindValue(1, $groupId, SQLITE3_INTEGER);
$stmt->bindValue(2, $personId, SQLITE3_INTEGER);
$result = $stmt->execute();
$existing = $result->fetchArray();

if ($existing) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Person ist bereits Mitglied dieser Gruppe']);
    $db->close();
    exit;
}

// Person zur Gruppe hinzufügen
$stmt = $db->prepare("INSERT INTO group_members (group_id, person_id) VALUES (?, ?)");
$stmt->bindValue(1, $groupId, SQLITE3_INTEGER);
$stmt->bindValue(2, $personId, SQLITE3_INTEGER);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to add person to group: ' . $db->lastErrorMsg()]);
}

$db->close();
?>



