<?php
require_once '../config.php';
header('Content-Type: application/json');

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

// Alle Mitglieder der Gruppe abrufen
$stmt = $db->prepare("SELECT person_id FROM group_members WHERE group_id = ?");
$stmt->bindValue(1, $groupId, SQLITE3_INTEGER);
$result = $stmt->execute();

$memberIds = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $memberIds[] = $row['person_id'];
}

if (empty($memberIds)) {
    echo json_encode(['success' => false, 'error' => 'Gruppe hat keine Mitglieder']);
    $db->close();
    exit;
}

// Aktuelle Zeit für alle Sessions
$currentTime = date('Y-m-d H:i:s');

// Training für alle Mitglieder starten (nur wenn sie nicht bereits aktiv sind)
$startedCount = 0;
$stmt = $db->prepare("UPDATE persons SET current_session_start = ? WHERE id = ? AND (current_session_start IS NULL OR current_session_start = '')");
$stmt->bindValue(1, $currentTime, SQLITE3_TEXT);

foreach ($memberIds as $personId) {
    $stmt->bindValue(2, $personId, SQLITE3_INTEGER);
    if ($stmt->execute()) {
        $changes = $db->changes();
        if ($changes > 0) {
            $startedCount++;
        }
    }
}

$db->close();

echo json_encode([
    'success' => true,
    'started_count' => $startedCount,
    'total_members' => count($memberIds)
]);
?>



