<?php
require_once '../config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['person_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing person_id']);
    exit;
}

$db = getDB();

// current_session_start zurücksetzen (Session löschen)
$stmt = $db->prepare("UPDATE persons SET current_session_start = NULL WHERE id = ?");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Prepare failed: ' . $db->lastErrorMsg()]);
    $db->close();
    exit;
}

$stmt->bindValue(1, $data['person_id'], SQLITE3_INTEGER);

$result = $stmt->execute();
if ($result) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    $errorMsg = $db->lastErrorMsg();
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $errorMsg]);
}

$db->close();
?>
