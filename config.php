<?php
// Zeitzone setzen
date_default_timezone_set('Europe/Berlin');

// Admin PIN-Code (BITTE ÄNDERN!)
define('ADMIN_PIN', '1234');

// Datenbank-Konfiguration
define('DB_PATH', __DIR__ . '/gym_tracking.db');
define('BACKUP_DIR', __DIR__ . '/backups');
define('BACKUP_CONFIG_FILE', __DIR__ . '/backup_config.json');

// Backup-Konfiguration laden
function getBackupConfig() {
    $defaultConfig = [
        'enabled' => false,
        'interval_hours' => 24,
        'max_backups' => 30
    ];
    
    if (file_exists(BACKUP_CONFIG_FILE)) {
        $config = json_decode(file_get_contents(BACKUP_CONFIG_FILE), true);
        return array_merge($defaultConfig, $config);
    }
    
    return $defaultConfig;
}

// Backup-Konfiguration speichern
function saveBackupConfig($config) {
    // Versuche zu schreiben
    $result = @file_put_contents(BACKUP_CONFIG_FILE, json_encode($config, JSON_PRETTY_PRINT));
    if ($result !== false) {
        @chmod(BACKUP_CONFIG_FILE, 0666);
        return true;
    }
    
    // Falls Schreiben fehlschlägt, versuche mit temporärer Datei
    $tempFile = BACKUP_CONFIG_FILE . '.tmp';
    if (@file_put_contents($tempFile, json_encode($config, JSON_PRETTY_PRINT))) {
        @chmod($tempFile, 0666);
        @rename($tempFile, BACKUP_CONFIG_FILE);
        return true;
    }
    
    error_log('Failed to save backup config to: ' . BACKUP_CONFIG_FILE);
    return false;
}

// Backup-Verzeichnis erstellen falls nicht vorhanden
if (!file_exists(BACKUP_DIR)) {
    @mkdir(BACKUP_DIR, 0755, true);
    @chmod(BACKUP_DIR, 0755);
}

// Backup-Funktion
function createBackup() {
    if (!file_exists(DB_PATH)) {
        error_log('Backup failed: Database file does not exist');
        return false;
    }
    
    $backupConfig = getBackupConfig();
    if (!$backupConfig['enabled']) {
        error_log('Backup skipped: Backups are disabled');
        return false;
    }
    
    // Backup-Verzeichnis erstellen falls nicht vorhanden
    if (!file_exists(BACKUP_DIR)) {
        @mkdir(BACKUP_DIR, 0777, true);
        @chmod(BACKUP_DIR, 0777);
    }
    
    $backupFileName = 'backup_' . date('Y-m-d_H-i-s') . '.db';
    $backupPath = BACKUP_DIR . '/' . $backupFileName;
    
    // Versuche direkt zu schreiben (is_writable kann falsch negativ sein)
    // Datenbank kopieren - verwende file_get_contents/file_put_contents als Fallback
    $backupCreated = false;
    
    // Methode 1: copy()
    if (@copy(DB_PATH, $backupPath)) {
        $backupCreated = true;
    } else {
        // Methode 2: file_get_contents/file_put_contents als Fallback
        $dbContent = @file_get_contents(DB_PATH);
        if ($dbContent !== false) {
            if (@file_put_contents($backupPath, $dbContent) !== false) {
                $backupCreated = true;
            }
        }
    }
    
    if ($backupCreated) {
        @chmod($backupPath, 0644);
        error_log('Backup created successfully: ' . $backupPath);
        
        // Alte Backups löschen (max_backups)
        $backups = glob(BACKUP_DIR . '/backup_*.db');
        if ($backups && count($backups) > $backupConfig['max_backups']) {
            usort($backups, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            
            $toDelete = array_slice($backups, 0, count($backups) - $backupConfig['max_backups']);
            foreach ($toDelete as $file) {
                @unlink($file);
            }
        }
        
        return $backupPath;
    } else {
        $error = error_get_last();
        $errorMsg = $error ? $error['message'] : 'Unknown error';
        error_log('Backup failed: Could not copy database file to ' . $backupPath . ' - ' . $errorMsg);
        return false;
    }
}

// Prüfen ob Backup nötig ist
function shouldCreateBackup() {
    try {
        $backupConfig = getBackupConfig();
        if (!$backupConfig['enabled']) {
            return false;
        }
        
        // Sicherstellen dass Backup-Verzeichnis existiert
        if (!file_exists(BACKUP_DIR)) {
            @mkdir(BACKUP_DIR, 0777, true);
            @chmod(BACKUP_DIR, 0777);
        }
        
        $lastBackupFile = BACKUP_DIR . '/.last_backup';
        if (!file_exists($lastBackupFile)) {
            return true;
        }
        
        $lastBackupTimeContent = @file_get_contents($lastBackupFile);
        if ($lastBackupTimeContent === false) {
            // Datei existiert aber kann nicht gelesen werden - Backup erstellen
            return true;
        }
        
        $lastBackupTime = (int)trim($lastBackupTimeContent);
        if ($lastBackupTime <= 0) {
            // Ungültiger Wert - Backup erstellen
            return true;
        }
        
        $intervalSeconds = $backupConfig['interval_hours'] * 3600;
        $timeSinceLastBackup = time() - $lastBackupTime;
        
        return $timeSinceLastBackup >= $intervalSeconds;
    } catch (Exception $e) {
        error_log('Error in shouldCreateBackup(): ' . $e->getMessage());
        // Bei Fehler lieber kein Backup erstellen
        return false;
    } catch (Error $e) {
        error_log('Fatal error in shouldCreateBackup(): ' . $e->getMessage());
        return false;
    }
}

// Backup-Zeit aktualisieren
function updateLastBackupTime() {
    try {
        // Sicherstellen dass Backup-Verzeichnis existiert
        if (!file_exists(BACKUP_DIR)) {
            @mkdir(BACKUP_DIR, 0777, true);
            @chmod(BACKUP_DIR, 0777);
        }
        
        $file = BACKUP_DIR . '/.last_backup';
        $timestamp = time();
        $result = @file_put_contents($file, $timestamp);
        
        if ($result === false) {
            error_log('Failed to update last backup time in: ' . $file);
            return false;
        }
        
        @chmod($file, 0644);
        return true;
    } catch (Exception $e) {
        error_log('Exception in updateLastBackupTime(): ' . $e->getMessage());
        return false;
    } catch (Error $e) {
        error_log('Fatal error in updateLastBackupTime(): ' . $e->getMessage());
        return false;
    }
}

// Datenbank initialisieren
function initDatabase() {
    $db = new SQLite3(DB_PATH);
    
    // Tabelle für Personen
    $db->exec("CREATE TABLE IF NOT EXISTS persons (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE,
        current_session_start DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Spalte hinzufügen falls sie noch nicht existiert (für bestehende Datenbanken)
    $db->exec("ALTER TABLE persons ADD COLUMN current_session_start DATETIME");
    
    // Tabelle für Trainingseinheiten
    $db->exec("CREATE TABLE IF NOT EXISTS sessions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        person_id INTEGER NOT NULL,
        start_time DATETIME NOT NULL,
        end_time DATETIME,
        duration_minutes INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (person_id) REFERENCES persons(id)
    )");
    
    // Tabelle für Gruppen
    $db->exec("CREATE TABLE IF NOT EXISTS groups (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Tabelle für Gruppen-Mitgliedschaften (many-to-many)
    $db->exec("CREATE TABLE IF NOT EXISTS group_members (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        group_id INTEGER NOT NULL,
        person_id INTEGER NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
        FOREIGN KEY (person_id) REFERENCES persons(id) ON DELETE CASCADE,
        UNIQUE(group_id, person_id)
    )");
    
    // Berechtigungen setzen (falls möglich)
    if (file_exists(DB_PATH)) {
        @chmod(DB_PATH, 0666);
    }
    
    return $db;
}

// Datenbankverbindung
function getDB() {
    // Datenbank initialisieren falls sie nicht existiert
    if (!file_exists(DB_PATH)) {
        initDatabase();
    }
    return new SQLite3(DB_PATH);
}
?>

