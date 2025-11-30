<?php
require_once 'config.php';
$db = getDB();

$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Prüfen ob Datum gültig ist
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

// Trainings für diesen Tag laden
$query = "
    SELECT 
        s.id,
        s.start_time,
        s.end_time,
        s.duration_minutes,
        p.id as person_id,
        p.name as person_name
    FROM sessions s
    INNER JOIN persons p ON s.person_id = p.id
    WHERE date(s.start_time) = ?
    ORDER BY s.start_time ASC
";

$stmt = $db->prepare($query);
$stmt->bindValue(1, $date, SQLITE3_TEXT);
$result = $stmt->execute();

$sessions = [];
$totalMinutes = 0;
$totalSessions = 0;

while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $sessions[] = $row;
    $totalMinutes += $row['duration_minutes'];
    $totalSessions++;
}

// Personen die an diesem Tag trainiert haben
$persons = [];
foreach ($sessions as $session) {
    if (!isset($persons[$session['person_id']])) {
        $persons[$session['person_id']] = [
            'name' => $session['person_name'],
            'sessions' => [],
            'total_minutes' => 0
        ];
    }
    $persons[$session['person_id']]['sessions'][] = $session;
    $persons[$session['person_id']]['total_minutes'] += $session['duration_minutes'];
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title><?php echo date('d.m.Y', strtotime($date)); ?> - Trainings</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header>
            <div class="header-left">
                <h1><?php echo date('d.m.Y', strtotime($date)); ?></h1>
                <p class="subtitle">Trainingsübersicht</p>
            </div>
            <div style="display: flex; gap: 15px;">
                <a href="statistik.php" class="statistik-btn">Zurück</a>
                <a href="admin_login.php" class="statistik-btn" style="background: linear-gradient(135deg, #404040 0%, #2d2d2d 100%);">Admin</a>
            </div>
        </header>
        
        <div class="stats-container">
            <div class="summary-box" style="background: #1a1a1a; padding: 20px; border-radius: 15px; margin: 20px 0; border: 1px solid rgba(255, 255, 255, 0.1);">
                <h3 style="color: #ffffff; margin-bottom: 10px;">Zusammenfassung</h3>
                <p style="color: #a0a0a0; font-size: 1.2em;">
                    <strong style="color: #dc2626;"><?php echo $totalSessions; ?></strong> Training<?php echo $totalSessions !== 1 ? 's' : ''; ?> 
                    | <strong style="color: #dc2626;"><?php echo number_format($totalMinutes / 60, 1); ?></strong> Stunden
                    | <strong style="color: #dc2626;"><?php echo count($persons); ?></strong> Person<?php echo count($persons) !== 1 ? 'en' : ''; ?>
                </p>
            </div>
            
            <?php if (empty($sessions)): ?>
                <div class="empty-state">
                    <p>An diesem Tag wurden keine Trainings durchgeführt.</p>
                </div>
            <?php else: ?>
                <?php foreach ($persons as $personId => $personData): ?>
                    <div style="margin-bottom: 30px;">
                        <h3 style="color: #dc2626; margin-bottom: 15px; font-size: 1.5em; font-weight: 700; cursor: pointer;" onclick="window.location.href='user_detail.php?id=<?php echo $personId; ?>'">
                            <?php echo htmlspecialchars($personData['name']); ?>
                            <span style="color: #a0a0a0; font-size: 0.8em; font-weight: 500;">
                                (<?php echo count($personData['sessions']); ?> Session<?php echo count($personData['sessions']) !== 1 ? 's' : ''; ?>, 
                                <?php echo number_format($personData['total_minutes'] / 60, 1); ?>h)
                            </span>
                        </h3>
                        
                        <div class="sessions-list">
                            <?php foreach ($personData['sessions'] as $session): ?>
                                <div class="session-item" style="background: #1a1a1a; padding: 20px; border-radius: 15px; margin-bottom: 15px; border: 1px solid rgba(255, 255, 255, 0.1);">
                                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                                        <div>
                                            <p style="color: #ffffff; margin-bottom: 5px; font-size: 1.1em; font-weight: 600;">
                                                <?php echo date('H:i', strtotime($session['start_time'])); ?> - 
                                                <?php echo date('H:i', strtotime($session['end_time'])); ?> Uhr
                                            </p>
                                            <p style="color: #a0a0a0; margin: 0; font-size: 0.9em;">
                                                Dauer: <?php echo $session['duration_minutes']; ?> Minuten
                                            </p>
                                        </div>
                                        <div style="text-align: right;">
                                            <p style="color: #dc2626; font-size: 1.3em; font-weight: 700; margin: 0;">
                                                <?php echo $session['duration_minutes']; ?> Min
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>



