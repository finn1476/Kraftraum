<?php
require_once 'config.php';
$db = getDB();

$personId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$filterDate = isset($_GET['date']) ? $_GET['date'] : '';
$selectedYear = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$currentYear = date('Y');

// Person laden
$stmt = $db->prepare("SELECT id, name FROM persons WHERE id = ?");
$stmt->bindValue(1, $personId, SQLITE3_INTEGER);
$result = $stmt->execute();
$person = $result->fetchArray(SQLITE3_ASSOC);

if (!$person) {
    header('Location: statistik.php');
    exit;
}

// Verfügbare Jahre ermitteln
$yearsQuery = "SELECT DISTINCT strftime('%Y', start_time) as year FROM sessions WHERE person_id = ? ORDER BY year DESC";
$stmt = $db->prepare($yearsQuery);
$stmt->bindValue(1, $personId, SQLITE3_INTEGER);
$result = $stmt->execute();
$availableYears = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $availableYears[] = intval($row['year']);
}
// Aktuelles Jahr hinzufügen falls noch keine Sessions existieren
if (!in_array($currentYear, $availableYears)) {
    $availableYears[] = $currentYear;
}
sort($availableYears);
$availableYears = array_reverse($availableYears);

// Trainings laden
$query = "SELECT id, start_time, end_time, duration_minutes 
          FROM sessions 
          WHERE person_id = ? 
          AND strftime('%Y', start_time) = ?";
$params = [$personId, strval($selectedYear)];

if ($filterDate) {
    $query .= " AND date(start_time) = ?";
    $params[] = $filterDate;
}

$query .= " ORDER BY start_time DESC";

$stmt = $db->prepare($query);
foreach ($params as $index => $param) {
    $stmt->bindValue($index + 1, $param, is_int($param) ? SQLITE3_INTEGER : SQLITE3_TEXT);
}
$result = $stmt->execute();

$sessions = [];
$totalMinutes = 0;
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $sessions[] = $row;
    $totalMinutes += $row['duration_minutes'];
}

// Verfügbare Daten für Filter
$dateQuery = "SELECT DISTINCT date(start_time) as session_date 
              FROM sessions 
              WHERE person_id = ? 
              ORDER BY session_date DESC";
$stmt = $db->prepare($dateQuery);
$stmt->bindValue(1, $personId, SQLITE3_INTEGER);
$result = $stmt->execute();

$availableDates = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $availableDates[] = $row['session_date'];
}

// Monatliche Statistiken für diesen Nutzer (ausgewähltes Jahr)
$monthlyStats = [];
$monthlyQuery = "
    SELECT 
        strftime('%m', start_time) as month,
        SUM(duration_minutes) as total_minutes,
        COUNT(id) as session_count
    FROM sessions
    WHERE person_id = ? 
        AND strftime('%Y', start_time) = ?
    GROUP BY month
    ORDER BY month
";

$stmt = $db->prepare($monthlyQuery);
$stmt->bindValue(1, $personId, SQLITE3_INTEGER);
$stmt->bindValue(2, strval($selectedYear), SQLITE3_TEXT);
$result = $stmt->execute();

while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $monthlyStats[$row['month']] = [
        'total_minutes' => $row['total_minutes'],
        'session_count' => $row['session_count']
    ];
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title><?php echo htmlspecialchars($person['name']); ?> - Trainings</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header>
            <div class="header-left">
                <h1><?php echo htmlspecialchars($person['name']); ?></h1>
                <p class="subtitle">Trainingsübersicht</p>
            </div>
            <div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
                <select id="yearSelect" onchange="changeYear()" 
                        style="padding: 12px 20px; font-size: 1.1em; border-radius: 10px; background: #2d2d2d; color: #ffffff; border: 2px solid rgba(255, 255, 255, 0.2); cursor: pointer;">
                    <?php foreach ($availableYears as $year): ?>
                        <option value="<?php echo $year; ?>" <?php echo $year == $selectedYear ? 'selected' : ''; ?>>
                            <?php echo $year; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <a href="statistik.php" class="statistik-btn">Zurück</a>
                <a href="admin_login.php" class="statistik-btn" style="background: linear-gradient(135deg, #404040 0%, #2d2d2d 100%);">Admin</a>
            </div>
        </header>
        
        <div class="stats-container">
            <div class="filter-section">
                <label for="dateFilter" style="color: #ffffff; margin-right: 15px; font-weight: 600;">Nach Tag filtern:</label>
                <select id="dateFilter" onchange="filterByDate()" style="padding: 10px 15px; font-size: 1.1em; border-radius: 8px; background: #1a1a1a; color: #ffffff; border: 2px solid rgba(255, 255, 255, 0.2); cursor: pointer;">
                    <option value="">Alle Tage</option>
                    <?php foreach ($availableDates as $date): ?>
                        <option value="<?php echo htmlspecialchars($date); ?>" <?php echo $filterDate === $date ? 'selected' : ''; ?>>
                            <?php echo date('d.m.Y', strtotime($date)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="summary-box" style="background: #1a1a1a; padding: 20px; border-radius: 15px; margin: 20px 0; border: 1px solid rgba(255, 255, 255, 0.1);">
                <h3 style="color: #ffffff; margin-bottom: 10px;">Zusammenfassung</h3>
                <p style="color: #a0a0a0; font-size: 1.2em;">
                    <strong style="color: #dc2626;"><?php echo count($sessions); ?></strong> Training<?php echo count($sessions) !== 1 ? 's' : ''; ?> 
                    | <strong style="color: #dc2626;"><?php echo number_format($totalMinutes / 60, 1); ?></strong> Stunden
                </p>
            </div>
            
            <?php if (!empty($monthlyStats)): ?>
            <div class="stats-section" style="margin-bottom: 30px;">
                <h2 style="color: #ffffff; margin-bottom: 20px; font-size: 2em; font-weight: 700;">Monatliche Übersicht <?php echo $selectedYear; ?></h2>
                <div class="chart-container">
                    <div class="bar-chart">
                        <?php 
                        $months = ['01' => 'Jan', '02' => 'Feb', '03' => 'Mär', '04' => 'Apr', 
                                  '05' => 'Mai', '06' => 'Jun', '07' => 'Jul', '08' => 'Aug',
                                  '09' => 'Sep', '10' => 'Okt', '11' => 'Nov', '12' => 'Dez'];
                        
                        $maxMonthly = 0;
                        foreach ($monthlyStats as $monthData) {
                            if ($monthData['total_minutes'] > $maxMonthly) {
                                $maxMonthly = $monthData['total_minutes'];
                            }
                        }
                        
                        foreach ($months as $monthNum => $monthName): 
                            $monthData = $monthlyStats[$monthNum] ?? ['total_minutes' => 0, 'session_count' => 0];
                            $minutes = $monthData['total_minutes'];
                            $hours = $minutes / 60.0;
                        ?>
                            <div class="bar-item">
                                <div class="bar-label" style="min-width: 80px;">
                                    <?php echo $monthName; ?>
                                </div>
                                <div class="bar-wrapper">
                                    <div class="bar-fill" style="width: <?php echo $maxMonthly > 0 ? ($minutes / $maxMonthly * 100) : 0; ?>%">
                                        <?php if ($minutes > 0 && ($minutes / $maxMonthly * 100) > 20): ?>
                                            <?php echo number_format($hours, 1); ?>h
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="bar-value" style="min-width: 100px;">
                                    <?php echo number_format($hours, 1); ?>h
                                    <br>
                                    <small style="font-size: 0.8em; color: #a0a0a0;">
                                        (<?php echo $monthData['session_count']; ?> Sessions)
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (empty($sessions)): ?>
                <div class="empty-state">
                    <p>Noch keine Trainings vorhanden<?php echo $filterDate ? ' für diesen Tag' : ''; ?>.</p>
                </div>
            <?php else: ?>
                <div class="sessions-list">
                    <?php foreach ($sessions as $session): ?>
                        <div class="session-item" style="background: #1a1a1a; padding: 20px; border-radius: 15px; margin-bottom: 15px; border: 1px solid rgba(255, 255, 255, 0.1);">
                            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                                <div>
                                    <h3 style="color: #ffffff; margin-bottom: 5px; font-size: 1.3em;">
                                        <?php echo date('d.m.Y', strtotime($session['start_time'])); ?>
                                    </h3>
                                    <p style="color: #a0a0a0; margin: 0;">
                                        <?php echo date('H:i', strtotime($session['start_time'])); ?> - 
                                        <?php echo date('H:i', strtotime($session['end_time'])); ?> Uhr
                                    </p>
                                </div>
                                <div style="text-align: right;">
                                    <p style="color: #dc2626; font-size: 1.5em; font-weight: 700; margin: 0;">
                                        <?php echo $session['duration_minutes']; ?> Min
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function filterByDate() {
            const dateFilter = document.getElementById('dateFilter').value;
            const year = document.getElementById('yearSelect').value;
            const url = new URL(window.location);
            url.searchParams.set('year', year);
            if (dateFilter) {
                url.searchParams.set('date', dateFilter);
            } else {
                url.searchParams.delete('date');
            }
            window.location.href = url.toString();
        }
        
        function changeYear() {
            const year = document.getElementById('yearSelect').value;
            const dateFilter = document.getElementById('dateFilter').value;
            const url = new URL(window.location);
            url.searchParams.set('year', year);
            if (dateFilter) {
                url.searchParams.set('date', dateFilter);
            } else {
                url.searchParams.delete('date');
            }
            window.location.href = url.toString();
        }
    </script>
</body>
</html>

