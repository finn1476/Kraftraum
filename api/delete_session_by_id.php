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

if (!isset($data['session_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing session_id']);
    exit;
}

$sessionId = intval($data['session_id']);
if ($sessionId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Ungültige Session-ID']);
    exit;
}

$db = getDB();

$stmt = $db->prepare("DELETE FROM sessions WHERE id = ?");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Prepare failed: ' . $db->lastErrorMsg()]);
    $db->close();
    exit;
}

$stmt->bindValue(1, $sessionId, SQLITE3_INTEGER);
$result = $stmt->execute();

if ($result) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $db->lastErrorMsg()]);
}

$db->close();
?>
