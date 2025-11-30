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

// Startzeit aus der persons Tabelle holen
$stmt = $db->prepare("SELECT current_session_start FROM persons WHERE id = ?");
$stmt->bindValue(1, $data['person_id'], SQLITE3_INTEGER);
$result = $stmt->execute();
$person = $result->fetchArray(SQLITE3_ASSOC);

if (!$person || !$person['current_session_start']) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No active session found']);
    $db->close();
    exit;
}

$startTime = new DateTime($person['current_session_start']);
$endTime = new DateTime();
$diff = $startTime->diff($endTime);
$durationMinutes = $diff->days * 24 * 60 + $diff->h * 60 + $diff->i;

// Session in sessions Tabelle speichern
$stmt = $db->prepare("INSERT INTO sessions (person_id, start_time, end_time, duration_minutes) VALUES (?, ?, ?, ?)");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Prepare failed: ' . $db->lastErrorMsg()]);
    $db->close();
    exit;
}

$stmt->bindValue(1, $data['person_id'], SQLITE3_INTEGER);
$stmt->bindValue(2, $person['current_session_start'], SQLITE3_TEXT);
$stmt->bindValue(3, $endTime->format('Y-m-d H:i:s'), SQLITE3_TEXT);
$stmt->bindValue(4, $durationMinutes, SQLITE3_INTEGER);

$result = $stmt->execute();
if ($result) {
    // current_session_start zurücksetzen
    $stmt = $db->prepare("UPDATE persons SET current_session_start = NULL WHERE id = ?");
    $stmt->bindValue(1, $data['person_id'], SQLITE3_INTEGER);
    $stmt->execute();
    
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    $errorMsg = $db->lastErrorMsg();
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $errorMsg]);
}

$db->close();
?>

