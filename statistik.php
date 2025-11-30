<?php
require_once 'config.php';
$db = getDB();

// Jahr aus GET-Parameter oder aktuelles Jahr
$selectedYear = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$currentYear = date('Y');

// Verfügbare Jahre ermitteln (aus Sessions)
$yearsQuery = "SELECT DISTINCT strftime('%Y', start_time) as year FROM sessions ORDER BY year DESC";
$yearsResult = $db->query($yearsQuery);
$availableYears = [];
while ($row = $yearsResult->fetchArray(SQLITE3_ASSOC)) {
    $availableYears[] = intval($row['year']);
}
// Aktuelles Jahr hinzufügen falls noch keine Sessions existieren
if (!in_array($currentYear, $availableYears)) {
    $availableYears[] = $currentYear;
}
sort($availableYears);
$availableYears = array_reverse($availableYears);

// Statistiken für das aktuelle Jahr
$statsQuery = "
    SELECT 
        p.id,
        p.name,
        COUNT(s.id) as session_count,
        SUM(s.duration_minutes) as total_minutes,
        SUM(s.duration_minutes) / 60.0 as total_hours
    FROM persons p
    LEFT JOIN sessions s ON p.id = s.person_id 
        AND strftime('%Y', s.start_time) = ?
    GROUP BY p.id, p.name
    HAVING session_count > 0
    ORDER BY total_minutes DESC
";

$stmt = $db->prepare($statsQuery);
$stmt->bindValue(1, strval($selectedYear), SQLITE3_TEXT);
$result = $stmt->execute();

$stats = [];
$maxMinutes = 0;

while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $stats[] = $row;
    if ($row['total_minutes'] > $maxMinutes) {
        $maxMinutes = $row['total_minutes'];
    }
}

// Monatliche Übersicht für alle Mitglieder zusammen
$monthlyStatsAll = [];
$monthlyQuery = "
    SELECT 
        strftime('%m', s.start_time) as month,
        SUM(s.duration_minutes) as total_minutes,
        COUNT(s.id) as session_count
    FROM sessions s
    WHERE strftime('%Y', s.start_time) = ?
    GROUP BY month
    ORDER BY month
";

$stmt = $db->prepare($monthlyQuery);
$stmt->bindValue(1, strval($selectedYear), SQLITE3_TEXT);
$result = $stmt->execute();

while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $monthlyStatsAll[$row['month']] = [
        'total_minutes' => $row['total_minutes'],
        'session_count' => $row['session_count']
    ];
}

// Letzte 7 Tage
$last7Days = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $dayQuery = "
        SELECT 
            COUNT(s.id) as session_count,
            SUM(s.duration_minutes) as total_minutes,
            COUNT(DISTINCT s.person_id) as person_count
        FROM sessions s
        WHERE date(s.start_time) = ?
    ";
    
    $stmt = $db->prepare($dayQuery);
    $stmt->bindValue(1, $date, SQLITE3_TEXT);
    $result = $stmt->execute();
    $dayData = $result->fetchArray(SQLITE3_ASSOC);
    
    $last7Days[$date] = [
        'date' => $date,
        'display_date' => date('d.m.Y', strtotime($date)),
        'day_name' => date('D', strtotime($date)),
        'session_count' => $dayData['session_count'] ?? 0,
        'total_minutes' => $dayData['total_minutes'] ?? 0,
        'person_count' => $dayData['person_count'] ?? 0
    ];
}

$dayNames = ['Mon' => 'Mo', 'Tue' => 'Di', 'Wed' => 'Mi', 'Thu' => 'Do', 'Fri' => 'Fr', 'Sat' => 'Sa', 'Sun' => 'So'];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Statistik - Kraftraum Tracking</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header>
            <div class="header-left">
                <h1>RV Hoya</h1>
                <p class="subtitle">Statistik <?php echo $selectedYear; ?></p>
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
                <a href="index.php" class="statistik-btn">Zurück</a>
                <a href="admin_login.php" class="statistik-btn" style="background: linear-gradient(135deg, #404040 0%, #2d2d2d 100%);">Admin</a>
            </div>
        </header>
        
        <div class="stats-container">
            <div class="stats-section">
                <h2>Letzte 7 Tage</h2>
                    <div class="chart-container">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 15px;">
                            <?php foreach ($last7Days as $dayData): ?>
                                <div class="day-card" 
                                     onclick="window.location.href='day_detail.php?date=<?php echo $dayData['date']; ?>'"
                                     style="background: <?php echo $dayData['session_count'] > 0 ? 'linear-gradient(135deg, #2d2d2d 0%, #252525 100%)' : '#1a1a1a'; ?>; 
                                            padding: 20px; 
                                            border-radius: 15px; 
                                            border: 2px solid <?php echo $dayData['session_count'] > 0 ? 'rgba(220, 38, 38, 0.3)' : 'rgba(255, 255, 255, 0.1)'; ?>; 
                                            cursor: pointer; 
                                            transition: all 0.3s ease;
                                            text-align: center;">
                                    <h3 style="color: #ffffff; margin-bottom: 10px; font-size: 1.1em; font-weight: 700;">
                                        <?php 
                                        $dayName = $dayNames[$dayData['day_name']] ?? $dayData['day_name'];
                                        echo $dayName . ', ' . $dayData['display_date']; 
                                        ?>
                                    </h3>
                                    <?php if ($dayData['session_count'] > 0): ?>
                                        <p style="color: #dc2626; font-size: 1.5em; font-weight: 700; margin: 5px 0;">
                                            <?php echo number_format($dayData['total_minutes'] / 60, 1); ?>h
                                        </p>
                                        <p style="color: #a0a0a0; font-size: 0.9em; margin: 5px 0;">
                                            <?php echo $dayData['session_count']; ?> Session<?php echo $dayData['session_count'] !== 1 ? 's' : ''; ?>
                                        </p>
                                        <p style="color: #a0a0a0; font-size: 0.9em; margin: 5px 0;">
                                            <?php echo $dayData['person_count']; ?> Person<?php echo $dayData['person_count'] !== 1 ? 'en' : ''; ?>
                                        </p>
                                    <?php else: ?>
                                        <p style="color: #666; font-size: 1em; margin: 5px 0;">
                                            Keine Trainings
                                        </p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
            <?php if (!empty($stats)): ?>
                <div class="stats-section">
                    <h2>Top Trainer des Jahres</h2>
                    <div class="chart-container">
                        <div class="bar-chart">
                            <?php foreach ($stats as $index => $stat): ?>
                                <div class="bar-item clickable-bar" onclick="window.location.href='user_detail.php?id=<?php echo $stat['id']; ?>'" style="cursor: pointer; transition: all 0.3s ease;">
                                    <div class="bar-label">
                                        <?php echo htmlspecialchars($stat['name']); ?>
                                    </div>
                                    <div class="bar-wrapper">
                                        <div class="bar-fill" style="width: <?php echo $maxMinutes > 0 ? ($stat['total_minutes'] / $maxMinutes * 100) : 0; ?>%">
                                            <?php if (($stat['total_minutes'] / $maxMinutes * 100) > 15): ?>
                                                <?php echo number_format($stat['total_hours'], 1); ?>h
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="bar-value">
                                        <?php echo number_format($stat['total_hours'], 1); ?>h
                                        <br>
                                        <small style="font-size: 0.8em; color: #a0a0a0;">
                                            (<?php echo $stat['session_count']; ?> Sessions)
                                        </small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <p>Noch keine Statistiken für das Jahr <?php echo $selectedYear; ?> verfügbar.</p>
                </div>
            <?php endif; ?>
                
                <?php if (!empty($monthlyStatsAll)): ?>
                <div class="stats-section">
                    <h2>Jahresübersicht <?php echo $selectedYear; ?> (Alle Mitglieder)</h2>
                    <div class="chart-container">
                        <div class="bar-chart">
                            <?php 
                            $months = ['01' => 'Jan', '02' => 'Feb', '03' => 'Mär', '04' => 'Apr', 
                                      '05' => 'Mai', '06' => 'Jun', '07' => 'Jul', '08' => 'Aug',
                                      '09' => 'Sep', '10' => 'Okt', '11' => 'Nov', '12' => 'Dez'];
                            
                            $maxMonthlyAll = 0;
                            foreach ($monthlyStatsAll as $monthData) {
                                if ($monthData['total_minutes'] > $maxMonthlyAll) {
                                    $maxMonthlyAll = $monthData['total_minutes'];
                                }
                            }
                            
                            foreach ($months as $monthNum => $monthName): 
                                $monthData = $monthlyStatsAll[$monthNum] ?? ['total_minutes' => 0, 'session_count' => 0];
                                $minutes = $monthData['total_minutes'];
                                $hours = $minutes / 60.0;
                            ?>
                                <div class="bar-item">
                                    <div class="bar-label" style="min-width: 80px;">
                                        <?php echo $monthName; ?>
                                    </div>
                                    <div class="bar-wrapper">
                                        <div class="bar-fill" style="width: <?php echo $maxMonthlyAll > 0 ? ($minutes / $maxMonthlyAll * 100) : 0; ?>%">
                                            <?php if ($minutes > 0 && ($minutes / $maxMonthlyAll * 100) > 20): ?>
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
        </div>
    </div>
    
    <script>
        function changeYear() {
            const year = document.getElementById('yearSelect').value;
            window.location.href = 'statistik.php?year=' + year;
        }
    </script>
</body>
</html>

