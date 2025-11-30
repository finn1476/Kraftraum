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

// Prüfen ob bereits eine aktive Session für diese Person existiert
$stmt = $db->prepare("SELECT current_session_start FROM persons WHERE id = ?");
$stmt->bindValue(1, $data['person_id'], SQLITE3_INTEGER);
$result = $stmt->execute();
$person = $result->fetchArray(SQLITE3_ASSOC);

if ($person && $person['current_session_start']) {
    // Session existiert bereits, Startzeit zurückgeben
    echo json_encode(['success' => true, 'start_time' => $person['current_session_start']]);
    $db->close();
    exit;
}

// Neue Session starten - PHP Zeit verwenden für korrekte Zeitzone
// Mehrere Personen können gleichzeitig aktiv sein
// Stelle sicher, dass Zeitzone gesetzt ist
if (!date_default_timezone_get()) {
    date_default_timezone_set('Europe/Berlin');
}
$currentTime = date('Y-m-d H:i:s');
// Debug: Prüfe was gespeichert wird
error_log("Setting session start time: " . $currentTime . " (timezone: " . date_default_timezone_get() . ")");

$stmt = $db->prepare("UPDATE persons SET current_session_start = ? WHERE id = ?");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Prepare failed: ' . $db->lastErrorMsg()]);
    $db->close();
    exit;
}

$stmt->bindValue(1, $currentTime, SQLITE3_TEXT);
$stmt->bindValue(2, $data['person_id'], SQLITE3_INTEGER);

$result = $stmt->execute();
if ($result) {
    // Startzeit abrufen
    $stmt = $db->prepare("SELECT current_session_start FROM persons WHERE id = ?");
    $stmt->bindValue(1, $data['person_id'], SQLITE3_INTEGER);
    $result = $stmt->execute();
    $person = $result->fetchArray(SQLITE3_ASSOC);
    
    // Debug: Prüfe was aus der DB kommt
    error_log("Retrieved from DB: " . $person['current_session_start']);
    
    echo json_encode(['success' => true, 'start_time' => $person['current_session_start']]);
} else {
    http_response_code(500);
    $errorMsg = $db->lastErrorMsg();
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $errorMsg]);
}

$db->close();
?>
