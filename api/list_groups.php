<?php
require_once '../config.php';
header('Content-Type: application/json');

$db = getDB();

$groupsQuery = "
    SELECT 
        g.id,
        g.name,
        COUNT(gm.person_id) as member_count
    FROM groups g
    LEFT JOIN group_members gm ON g.id = gm.group_id
    GROUP BY g.id, g.name
    ORDER BY g.name
";

$result = $db->query($groupsQuery);
$groups = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $groups[] = $row;
}

$db->close();

echo json_encode(['success' => true, 'groups' => $groups]);
?>



