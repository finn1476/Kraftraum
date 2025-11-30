<?php
require_once '../config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON: ' . json_last_error_msg()]);
    exit;
}

if (!isset($data['name']) || empty(trim($data['name']))) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Name is required']);
    exit;
}

$db = getDB();

$name = trim($data['name']);

// Prüfen ob Person bereits existiert
$stmt = $db->prepare("SELECT id FROM persons WHERE name = ?");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Prepare failed (check): ' . $db->lastErrorMsg()]);
    $db->close();
    exit;
}

$stmt->bindValue(1, $name, SQLITE3_TEXT);
$result = $stmt->execute();
if (!$result) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Execute failed (check): ' . $db->lastErrorMsg()]);
    $db->close();
    exit;
}

$existing = $result->fetchArray();

if ($existing) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Person existiert bereits']);
    $db->close();
    exit;
}

// Person hinzufügen
$stmt = $db->prepare("INSERT INTO persons (name) VALUES (?)");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Prepare failed: ' . $db->lastErrorMsg()]);
    $db->close();
    exit;
}

$stmt->bindValue(1, $name, SQLITE3_TEXT);

$result = $stmt->execute();
if ($result) {
    echo json_encode(['success' => true, 'id' => $db->lastInsertRowID()]);
} else {
    http_response_code(500);
    $errorMsg = $db->lastErrorMsg();
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $errorMsg]);
}

$db->close();
?>

