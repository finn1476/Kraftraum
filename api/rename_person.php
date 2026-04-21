<?php
session_start();
require_once '../config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['person_id']) || !isset($data['name'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

$personId = intval($data['person_id']);
$name = trim($data['name']);

if ($personId <= 0 || $name === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}

$db = getDB();

// Prüfen, ob Zielname bereits von jemand anderem verwendet wird
$checkStmt = $db->prepare("SELECT id FROM persons WHERE name = ? AND id != ?");
if (!$checkStmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Prepare failed (check): ' . $db->lastErrorMsg()]);
    $db->close();
    exit;
}

$checkStmt->bindValue(1, $name, SQLITE3_TEXT);
$checkStmt->bindValue(2, $personId, SQLITE3_INTEGER);
$checkResult = $checkStmt->execute();

if (!$checkResult) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Execute failed (check): ' . $db->lastErrorMsg()]);
    $db->close();
    exit;
}

if ($checkResult->fetchArray(SQLITE3_ASSOC)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Name wird bereits verwendet']);
    $db->close();
    exit;
}

$stmt = $db->prepare("UPDATE persons SET name = ? WHERE id = ?");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Prepare failed: ' . $db->lastErrorMsg()]);
    $db->close();
    exit;
}

$stmt->bindValue(1, $name, SQLITE3_TEXT);
$stmt->bindValue(2, $personId, SQLITE3_INTEGER);
$result = $stmt->execute();

if ($result && $db->changes() > 0) {
    echo json_encode(['success' => true]);
} elseif ($result) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Nutzer nicht gefunden']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $db->lastErrorMsg()]);
}

$db->close();
?>
