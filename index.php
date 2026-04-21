<?php
require_once 'config.php';

// Datenbank initialisieren (muss vor Backup-Check passieren)
$db = getDB();

// Automatisches Backup prüfen und erstellen (im Hintergrund, keine Fehler werfen)
// Wird nur ausgeführt wenn Backups aktiviert sind
try {
    // Prüfen ob alle benötigten Funktionen existieren
    if (function_exists('shouldCreateBackup') && function_exists('createBackup') && function_exists('updateLastBackupTime')) {
        // Prüfen ob Backup nötig ist
        if (shouldCreateBackup()) {
            // Backup erstellen
            $backupResult = createBackup();
            if ($backupResult !== false) {
                // Backup-Zeit aktualisieren
                updateLastBackupTime();
                // Erfolg wird nicht geloggt um Logs nicht zu überfüllen
            } else {
                // Fehler wird in createBackup() bereits geloggt
                error_log('Backup creation returned false in index.php');
            }
        }
    } else {
        // Funktionen fehlen - sollte nicht passieren, aber für Debugging
        error_log('Backup functions not available: shouldCreateBackup=' . (function_exists('shouldCreateBackup') ? 'yes' : 'no') . 
                  ', createBackup=' . (function_exists('createBackup') ? 'yes' : 'no') . 
                  ', updateLastBackupTime=' . (function_exists('updateLastBackupTime') ? 'yes' : 'no'));
    }
} catch (Exception $e) {
    // Backup-Fehler nicht anzeigen, nur loggen
    error_log('Backup exception in index.php: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
} catch (Error $e) {
    error_log('Backup fatal error in index.php: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
}

// Automatisches Beenden von Sessions >24h
$currentTime = new DateTime();
$stmt = $db->prepare("SELECT id, name, current_session_start FROM persons WHERE current_session_start IS NOT NULL");
$result = $stmt->execute();

while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    if ($row['current_session_start']) {
        $startTime = new DateTime($row['current_session_start']);
        $diff = $currentTime->diff($startTime);
        $hours = $diff->days * 24 + $diff->h;
        
        // Wenn Session länger als 24 Stunden läuft, automatisch beenden (nicht speichern)
        if ($hours >= 24) {
            $updateStmt = $db->prepare("UPDATE persons SET current_session_start = NULL WHERE id = ?");
            $updateStmt->bindValue(1, $row['id'], SQLITE3_INTEGER);
            $updateStmt->execute();
        }
    }
}

// Alle Personen mit aktiven Sessions laden
// Sortierung: Aktive zuerst, dann alphabetisch
$result = $db->query("SELECT id, name, current_session_start FROM persons ORDER BY 
    CASE WHEN current_session_start IS NOT NULL THEN 0 ELSE 1 END,
    name");
$persons = [];
$activeSessions = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $persons[] = $row;
    // Wenn current_session_start gesetzt ist, ist die Person aktiv
    if ($row['current_session_start']) {
        $activeSessions[$row['id']] = [
            'start_time' => $row['current_session_start']
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="#1a1a1a">

    <title>Kraftraum Tracking</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header>
            <div class="header-left">
                <h1>RV Hoya</h1>
                <p class="subtitle">Kraftraum</p>
            </div>
            <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                <button id="manualAddModeBtn" class="statistik-btn manual-mode-btn" type="button" onclick="toggleManualAddMode()">
                    Nachtragen-Modus: AUS
                </button>
                <a href="gruppen.php" class="statistik-btn" style="background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);">Gruppen</a>
                <a href="statistik.php" class="statistik-btn">Statistik</a>
                <a href="admin_login.php" class="statistik-btn" style="background: linear-gradient(135deg, #404040 0%, #2d2d2d 100%);">Admin</a>
            </div>
        </header>
        
        <div class="cards-grid" id="cardsGrid">
            <?php if (empty($persons)): ?>
                <div class="empty-state">
                    <p>Noch keine Personen vorhanden.</p>
                    <p style="color: #a0a0a0; margin-top: 15px;">Bitte im Admin-Menü eine Person hinzufügen.</p>
                </div>
            <?php else: ?>
                <?php foreach ($persons as $person): ?>
                    <div class="card person-card" data-person-id="<?php echo htmlspecialchars($person['id']); ?>" 
                         data-person-name="<?php echo htmlspecialchars($person['name']); ?>"
                         onclick="handleCardClick(this, event)">
                        <div class="card-content">
                            <h2 class="person-name"><?php echo htmlspecialchars($person['name']); ?></h2>
                            <div class="status-indicator"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal für Training beenden -->
    <div id="sessionModal" class="modal">
        <div class="modal-content">
            <h2 id="modalPersonName"></h2>
            <p id="modalDuration">Sie waren <span id="minutesDisplay">0</span> Minuten im Kraftraum</p>
            <div class="modal-buttons">
                <button onclick="saveSession()" class="btn btn-primary">Eintragen</button>
                <button onclick="cancelSession()" class="btn btn-secondary">Abbrechen</button>
                <button onclick="deleteSession()" class="btn btn-danger">Löschen</button>
            </div>
        </div>
    </div>

    <!-- Modal für nachträgliches Eintragen -->
    <div id="manualAddModal" class="modal">
        <div class="modal-content">
            <h2 id="manualAddPersonName">Nachträgliches Eintragen</h2>
            <div style="margin-bottom: 20px;">
                <label style="color: #ffffff; display: block; margin-bottom: 10px; font-weight: 600;">
                    Datum und Uhrzeit (Start):
                </label>
                <input type="datetime-local" id="manualStartTime" 
                       style="width: 100%; padding: 15px; font-size: 1.1em; border-radius: 8px; background: #1a1a1a; color: #ffffff; border: 2px solid rgba(255, 255, 255, 0.2);">
            </div>
            <div style="margin-bottom: 20px;">
                <label style="color: #ffffff; display: block; margin-bottom: 10px; font-weight: 600;">
                    Dauer (Minuten):
                </label>
                <input type="number" id="manualDuration" min="1" max="1440" value="60"
                       style="width: 100%; padding: 15px; font-size: 1.1em; border-radius: 8px; background: #1a1a1a; color: #ffffff; border: 2px solid rgba(255, 255, 255, 0.2);">
            </div>
            <div class="modal-buttons">
                <button onclick="confirmManualAdd()" class="btn btn-primary">Eintragen</button>
                <button onclick="closeManualAddModal()" class="btn btn-secondary">Abbrechen</button>
            </div>
        </div>
    </div>

    <script>
        // Aktive Sessions an JavaScript übergeben
        const activeSessionsData = <?php echo json_encode($activeSessions); ?>;
    </script>
    <script src="script.js"></script>
</body>
</html>

