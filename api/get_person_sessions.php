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

if (!isset($data['person_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing person_id']);
    exit;
}

$personId = intval($data['person_id']);
$db = getDB();

$stmt = $db->prepare("
    SELECT id, start_time, end_time, duration_minutes
    FROM sessions
    WHERE person_id = ?
    ORDER BY start_time DESC
    LIMIT 200
");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Prepare failed: ' . $db->lastErrorMsg()]);
    $db->close();
    exit;
}

$stmt->bindValue(1, $personId, SQLITE3_INTEGER);
$result = $stmt->execute();
if (!$result) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Execute failed: ' . $db->lastErrorMsg()]);
    $db->close();
    exit;
}

$sessions = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $startTs = strtotime($row['start_time']);
    $endTs = $row['end_time'] ? strtotime($row['end_time']) : null;
    $sessions[] = [
        'id' => intval($row['id']),
        'start_time' => $row['start_time'],
        'end_time' => $row['end_time'],
        'duration_minutes' => intval($row['duration_minutes']),
        'start_display' => $startTs ? date('d.m.Y H:i', $startTs) : $row['start_time'],
        'end_display' => $endTs ? date('d.m.Y H:i', $endTs) : '-'
    ];
}

echo json_encode([
    'success' => true,
    'sessions' => $sessions
]);

$db->close();
?>
