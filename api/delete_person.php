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

// Zuerst alle Sessions des Nutzers löschen
$stmt = $db->prepare("DELETE FROM sessions WHERE person_id = ?");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Prepare failed (sessions): ' . $db->lastErrorMsg()]);
    $db->close();
    exit;
}

$stmt->bindValue(1, $data['person_id'], SQLITE3_INTEGER);
$stmt->execute();

// Dann den Nutzer löschen
$stmt = $db->prepare("DELETE FROM persons WHERE id = ?");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Prepare failed (person): ' . $db->lastErrorMsg()]);
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



