<?php
require_once '../config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['person_id']) || !isset($data['start_time']) || !isset($data['duration_minutes'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

$db = getDB();
$personId = intval($data['person_id']);
$startTime = $data['start_time'];
$durationMinutes = intval($data['duration_minutes']);

// Endzeit berechnen
$startDateTime = new DateTime($startTime);
$endDateTime = clone $startDateTime;
$endDateTime->modify('+' . $durationMinutes . ' minutes');
$endTime = $endDateTime->format('Y-m-d H:i:s');

// Session manuell hinzufügen
$stmt = $db->prepare("INSERT INTO sessions (person_id, start_time, end_time, duration_minutes) VALUES (?, ?, ?, ?)");
$stmt->bindValue(1, $personId, SQLITE3_INTEGER);
$stmt->bindValue(2, $startTime, SQLITE3_TEXT);
$stmt->bindValue(3, $endTime, SQLITE3_TEXT);
$stmt->bindValue(4, $durationMinutes, SQLITE3_INTEGER);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'id' => $db->lastInsertRowID()]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to add session: ' . $db->lastErrorMsg()]);
}

$db->close();
?>



