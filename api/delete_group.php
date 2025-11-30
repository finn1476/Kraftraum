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

if (!isset($data['group_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing group_id']);
    exit;
}

$db = getDB();
$groupId = intval($data['group_id']);

// Gruppe löschen (CASCADE löscht automatisch alle Mitgliedschaften)
$stmt = $db->prepare("DELETE FROM groups WHERE id = ?");
$stmt->bindValue(1, $groupId, SQLITE3_INTEGER);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to delete group: ' . $db->lastErrorMsg()]);
}

$db->close();
?>



