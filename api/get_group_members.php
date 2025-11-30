<?php
require_once '../config.php';
header('Content-Type: application/json');

if (!isset($_GET['group_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing group_id']);
    exit;
}

$db = getDB();
$groupId = intval($_GET['group_id']);

$membersQuery = "
    SELECT 
        p.id,
        p.name,
        p.current_session_start
    FROM persons p
    INNER JOIN group_members gm ON p.id = gm.person_id
    WHERE gm.group_id = ?
    ORDER BY p.name
";

$stmt = $db->prepare($membersQuery);
$stmt->bindValue(1, $groupId, SQLITE3_INTEGER);
$result = $stmt->execute();

$members = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $members[] = $row;
}

$db->close();

echo json_encode(['success' => true, 'members' => $members]);
?>



