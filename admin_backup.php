<?php
session_start();
require_once 'config.php';

// Prüfen ob eingeloggt
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

$backupConfig = getBackupConfig();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Backup-Verwaltung - Kraftraum Tracking</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header>
            <div class="header-left">
                <h1>RV Hoya</h1>
                <p class="subtitle">Backup-Verwaltung</p>
            </div>
            <a href="admin.php" class="statistik-btn">Zurück</a>
        </header>
        
        <div class="stats-container">
            <div class="admin-section">
                <h2 style="color: #ffffff; margin-bottom: 20px;">Backup-Konfiguration</h2>
                
                <div style="background: #1a1a1a; padding: 20px; border-radius: 15px; margin-bottom: 30px; border: 1px solid rgba(255, 255, 255, 0.1);">
                    <div style="margin-bottom: 20px;">
                        <label style="display: flex; align-items: center; color: #ffffff; font-size: 1.1em; cursor: pointer;">
                            <input type="checkbox" id="backupEnabled" <?php echo $backupConfig['enabled'] ? 'checked' : ''; ?> 
                                   style="width: 25px; height: 25px; margin-right: 15px; cursor: pointer;">
                            Automatische Backups aktivieren
                        </label>
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <label style="color: #ffffff; display: block; margin-bottom: 10px; font-weight: 600;">
                            Backup-Intervall (Stunden):
                        </label>
                        <input type="number" id="backupInterval" value="<?php echo $backupConfig['interval_hours']; ?>" 
                               min="1" max="168" 
                               style="width: 100%; padding: 15px; font-size: 1.1em; border-radius: 8px; background: #2d2d2d; color: #ffffff; border: 2px solid rgba(255, 255, 255, 0.2);">
                        <p style="color: #a0a0a0; font-size: 0.9em; margin-top: 5px;">
                            Wie oft soll ein Backup erstellt werden? (1-168 Stunden)
                        </p>
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <label style="color: #ffffff; display: block; margin-bottom: 10px; font-weight: 600;">
                            Maximale Anzahl Backups:
                        </label>
                        <input type="number" id="maxBackups" value="<?php echo $backupConfig['max_backups']; ?>" 
                               min="1" max="100" 
                               style="width: 100%; padding: 15px; font-size: 1.1em; border-radius: 8px; background: #2d2d2d; color: #ffffff; border: 2px solid rgba(255, 255, 255, 0.2);">
                        <p style="color: #a0a0a0; font-size: 0.9em; margin-top: 5px;">
                            Alte Backups werden automatisch gelöscht, wenn diese Anzahl überschritten wird.
                        </p>
                    </div>
                    
                    <button onclick="saveBackupConfig()" class="btn btn-primary" style="width: 100%; padding: 18px; font-size: 1.2em;">
                        Konfiguration speichern
                    </button>
                </div>
                
                <h2 style="color: #ffffff; margin-bottom: 20px;">Manuelles Backup</h2>
                <div style="background: #1a1a1a; padding: 20px; border-radius: 15px; margin-bottom: 30px; border: 1px solid rgba(255, 255, 255, 0.1);">
                    <button onclick="createManualBackup()" class="btn btn-primary" style="width: 100%; padding: 18px; font-size: 1.2em;">
                        Jetzt Backup erstellen
                    </button>
                </div>
                
                <h2 style="color: #ffffff; margin-bottom: 20px;">Backup hochladen</h2>
                <div style="background: #1a1a1a; padding: 20px; border-radius: 15px; margin-bottom: 30px; border: 1px solid rgba(255, 255, 255, 0.1);">
                    <form id="uploadBackupForm" enctype="multipart/form-data" style="display: flex; flex-direction: column; gap: 15px;">
                        <input type="file" id="backupFileInput" accept=".db" required
                               style="padding: 15px; font-size: 1.1em; border-radius: 8px; background: #2d2d2d; color: #ffffff; border: 2px solid rgba(255, 255, 255, 0.2); cursor: pointer;">
                        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 18px; font-size: 1.2em;">
                            Backup hochladen
                        </button>
                    </form>
                    <p style="color: #a0a0a0; font-size: 0.9em; margin-top: 10px;">
                        Wählen Sie eine .db Datei aus, die Sie wiederherstellen möchten.
                    </p>
                </div>
                
                <h2 style="color: #ffffff; margin-bottom: 20px;">Backups wiederherstellen</h2>
                <div id="backupsList" style="background: #1a1a1a; padding: 20px; border-radius: 15px; border: 1px solid rgba(255, 255, 255, 0.1);">
                    <p style="color: #a0a0a0;">Lade Backups...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal für Backup löschen mit PIN -->
    <div id="deletePinModal" class="modal">
        <div class="modal-content">
            <h2>PIN-Code eingeben</h2>
            <p style="color: #a0a0a0; margin-bottom: 20px;">Bitte geben Sie den PIN-Code ein, um das Backup zu löschen.</p>
            
            <input type="hidden" id="deleteBackupFilename" value="">
            
            <div style="margin-bottom: 20px;">
                <input type="password" 
                       id="deletePinInput" 
                       placeholder="PIN-Code eingeben" 
                       autofocus
                       maxlength="10"
                       style="width: 100%; padding: 20px; font-size: 1.5em; text-align: center; letter-spacing: 8px; background: #1a1a1a; color: #ffffff; border: 2px solid rgba(255, 255, 255, 0.2); border-radius: 10px; outline: none; transition: border-color 0.3s ease;"
                       onfocus="this.style.borderColor='#dc2626';"
                       onblur="this.style.borderColor='rgba(255, 255, 255, 0.2)';">
            </div>
            
            <!-- Touch-Nummernfeld -->
            <div id="deleteTouchPad" style="display: none; margin-bottom: 20px;">
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; max-width: 400px; margin: 0 auto;">
                    <button type="button" class="number-btn" onclick="addDeleteDigit('1')">1</button>
                    <button type="button" class="number-btn" onclick="addDeleteDigit('2')">2</button>
                    <button type="button" class="number-btn" onclick="addDeleteDigit('3')">3</button>
                    <button type="button" class="number-btn" onclick="addDeleteDigit('4')">4</button>
                    <button type="button" class="number-btn" onclick="addDeleteDigit('5')">5</button>
                    <button type="button" class="number-btn" onclick="addDeleteDigit('6')">6</button>
                    <button type="button" class="number-btn" onclick="addDeleteDigit('7')">7</button>
                    <button type="button" class="number-btn" onclick="addDeleteDigit('8')">8</button>
                    <button type="button" class="number-btn" onclick="addDeleteDigit('9')">9</button>
                    <button type="button" class="number-btn" onclick="addDeleteDigit('0')" style="grid-column: 2;">0</button>
                    <button type="button" class="number-btn delete-btn" onclick="deleteDeleteDigit()" style="grid-column: 3;">
                        ⌫
                    </button>
                </div>
            </div>
            
            <div class="modal-buttons">
                <button onclick="confirmDeleteBackup()" class="btn btn-danger">Löschen</button>
                <button onclick="closeDeletePinModal()" class="btn btn-secondary">Abbrechen</button>
            </div>
        </div>
    </div>

    <!-- Modal für Backup wiederherstellen mit PIN -->
    <div id="restorePinModal" class="modal">
        <div class="modal-content">
            <h2>PIN-Code eingeben</h2>
            <p style="color: #a0a0a0; margin-bottom: 20px;">Bitte geben Sie den PIN-Code ein, um das Backup wiederherzustellen.</p>
            
            <input type="hidden" id="restoreBackupFilename" value="">
            
            <div style="margin-bottom: 20px;">
                <input type="password" 
                       id="restorePinInput" 
                       placeholder="PIN-Code eingeben" 
                       autofocus
                       maxlength="10"
                       style="width: 100%; padding: 20px; font-size: 1.5em; text-align: center; letter-spacing: 8px; background: #1a1a1a; color: #ffffff; border: 2px solid rgba(255, 255, 255, 0.2); border-radius: 10px; outline: none; transition: border-color 0.3s ease;"
                       onfocus="this.style.borderColor='#dc2626';"
                       onblur="this.style.borderColor='rgba(255, 255, 255, 0.2)';">
            </div>
            
            <!-- Touch-Nummernfeld -->
            <div id="restoreTouchPad" style="display: none; margin-bottom: 20px;">
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; max-width: 400px; margin: 0 auto;">
                    <button type="button" class="number-btn" onclick="addRestoreDigit('1')">1</button>
                    <button type="button" class="number-btn" onclick="addRestoreDigit('2')">2</button>
                    <button type="button" class="number-btn" onclick="addRestoreDigit('3')">3</button>
                    <button type="button" class="number-btn" onclick="addRestoreDigit('4')">4</button>
                    <button type="button" class="number-btn" onclick="addRestoreDigit('5')">5</button>
                    <button type="button" class="number-btn" onclick="addRestoreDigit('6')">6</button>
                    <button type="button" class="number-btn" onclick="addRestoreDigit('7')">7</button>
                    <button type="button" class="number-btn" onclick="addRestoreDigit('8')">8</button>
                    <button type="button" class="number-btn" onclick="addRestoreDigit('9')">9</button>
                    <button type="button" class="number-btn" onclick="addRestoreDigit('0')" style="grid-column: 2;">0</button>
                    <button type="button" class="number-btn delete-btn" onclick="deleteRestoreDigit()" style="grid-column: 3;">
                        ⌫
                    </button>
                </div>
            </div>
            
            <div class="modal-buttons">
                <button onclick="confirmRestoreBackup()" class="btn btn-primary">Wiederherstellen</button>
                <button onclick="closeRestorePinModal()" class="btn btn-secondary">Abbrechen</button>
            </div>
        </div>
    </div>

    <!-- Modal für Backup herunterladen mit PIN -->
    <div id="downloadPinModal" class="modal">
        <div class="modal-content">
            <h2>PIN-Code eingeben</h2>
            <p style="color: #a0a0a0; margin-bottom: 20px;">Bitte geben Sie den PIN-Code ein, um das Backup herunterzuladen.</p>
            
            <input type="hidden" id="downloadBackupFilename" value="">
            
            <div style="margin-bottom: 20px;">
                <input type="password" 
                       id="downloadPinInput" 
                       placeholder="PIN-Code eingeben" 
                       autofocus
                       maxlength="10"
                       style="width: 100%; padding: 20px; font-size: 1.5em; text-align: center; letter-spacing: 8px; background: #1a1a1a; color: #ffffff; border: 2px solid rgba(255, 255, 255, 0.2); border-radius: 10px; outline: none; transition: border-color 0.3s ease;"
                       onfocus="this.style.borderColor='#dc2626';"
                       onblur="this.style.borderColor='rgba(255, 255, 255, 0.2)';">
            </div>
            
            <!-- Touch-Nummernfeld -->
            <div id="downloadTouchPad" style="display: none; margin-bottom: 20px;">
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; max-width: 400px; margin: 0 auto;">
                    <button type="button" class="number-btn" onclick="addDownloadDigit('1')">1</button>
                    <button type="button" class="number-btn" onclick="addDownloadDigit('2')">2</button>
                    <button type="button" class="number-btn" onclick="addDownloadDigit('3')">3</button>
                    <button type="button" class="number-btn" onclick="addDownloadDigit('4')">4</button>
                    <button type="button" class="number-btn" onclick="addDownloadDigit('5')">5</button>
                    <button type="button" class="number-btn" onclick="addDownloadDigit('6')">6</button>
                    <button type="button" class="number-btn" onclick="addDownloadDigit('7')">7</button>
                    <button type="button" class="number-btn" onclick="addDownloadDigit('8')">8</button>
                    <button type="button" class="number-btn" onclick="addDownloadDigit('9')">9</button>
                    <button type="button" class="number-btn" onclick="addDownloadDigit('0')" style="grid-column: 2;">0</button>
                    <button type="button" class="number-btn delete-btn" onclick="deleteDownloadDigit()" style="grid-column: 3;">
                        ⌫
                    </button>
                </div>
            </div>
            
            <div class="modal-buttons">
                <button onclick="confirmDownloadBackup()" class="btn btn-primary">Herunterladen</button>
                <button onclick="closeDownloadPinModal()" class="btn btn-secondary">Abbrechen</button>
            </div>
        </div>
    </div>

    <!-- Modal für Backup hochladen mit PIN -->
    <div id="uploadPinModal" class="modal">
        <div class="modal-content">
            <h2>PIN-Code eingeben</h2>
            <p style="color: #a0a0a0; margin-bottom: 20px;">Bitte geben Sie den PIN-Code ein, um das Backup hochzuladen.</p>
            
            <div style="margin-bottom: 20px;">
                <input type="password" 
                       id="uploadPinInput" 
                       placeholder="PIN-Code eingeben" 
                       autofocus
                       maxlength="10"
                       style="width: 100%; padding: 20px; font-size: 1.5em; text-align: center; letter-spacing: 8px; background: #1a1a1a; color: #ffffff; border: 2px solid rgba(255, 255, 255, 0.2); border-radius: 10px; outline: none; transition: border-color 0.3s ease;"
                       onfocus="this.style.borderColor='#dc2626';"
                       onblur="this.style.borderColor='rgba(255, 255, 255, 0.2)';">
            </div>
            
            <!-- Touch-Nummernfeld -->
            <div id="uploadTouchPad" style="display: none; margin-bottom: 20px;">
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; max-width: 400px; margin: 0 auto;">
                    <button type="button" class="number-btn" onclick="addUploadDigit('1')">1</button>
                    <button type="button" class="number-btn" onclick="addUploadDigit('2')">2</button>
                    <button type="button" class="number-btn" onclick="addUploadDigit('3')">3</button>
                    <button type="button" class="number-btn" onclick="addUploadDigit('4')">4</button>
                    <button type="button" class="number-btn" onclick="addUploadDigit('5')">5</button>
                    <button type="button" class="number-btn" onclick="addUploadDigit('6')">6</button>
                    <button type="button" class="number-btn" onclick="addUploadDigit('7')">7</button>
                    <button type="button" class="number-btn" onclick="addUploadDigit('8')">8</button>
                    <button type="button" class="number-btn" onclick="addUploadDigit('9')">9</button>
                    <button type="button" class="number-btn" onclick="addUploadDigit('0')" style="grid-column: 2;">0</button>
                    <button type="button" class="number-btn delete-btn" onclick="deleteUploadDigit()" style="grid-column: 3;">
                        ⌫
                    </button>
                </div>
            </div>
            
            <div class="modal-buttons">
                <button onclick="confirmUploadBackup()" class="btn btn-primary">Hochladen</button>
                <button onclick="closeUploadPinModal()" class="btn btn-secondary">Abbrechen</button>
            </div>
        </div>
    </div>
    
    <script>
        function saveBackupConfig() {
            const config = {
                enabled: document.getElementById('backupEnabled').checked,
                interval_hours: parseInt(document.getElementById('backupInterval').value),
                max_backups: parseInt(document.getElementById('maxBackups').value)
            };
            
            fetch('api/backup_config.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(config)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Konfiguration gespeichert!');
                } else {
                    alert('Fehler: ' + (data.error || 'Unbekannter Fehler'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Fehler beim Speichern der Konfiguration');
            });
        }
        
        function createManualBackup() {
            if (!confirm('Möchten Sie jetzt ein Backup erstellen?')) {
                return;
            }
            
            fetch('api/create_backup.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Backup erfolgreich erstellt!');
                    loadBackups();
                } else {
                    alert('Fehler: ' + (data.error || 'Unbekannter Fehler'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Fehler beim Erstellen des Backups');
            });
        }
        
        function loadBackups() {
            fetch('api/list_backups.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const container = document.getElementById('backupsList');
                    if (data.backups.length === 0) {
                        container.innerHTML = '<p style="color: #a0a0a0;">Keine Backups vorhanden.</p>';
                        return;
                    }
                    
                    let html = '';
                    data.backups.forEach(backup => {
                        const sizeMB = (backup.size / 1024 / 1024).toFixed(2);
                        html += `
                            <div style="background: #2d2d2d; padding: 15px; border-radius: 10px; margin-bottom: 15px; border: 1px solid rgba(255, 255, 255, 0.1);">
                                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                                    <div>
                                        <h3 style="color: #ffffff; margin-bottom: 5px; font-size: 1.1em;">
                                            ${backup.date}
                                        </h3>
                                        <p style="color: #a0a0a0; margin: 0; font-size: 0.9em;">
                                            ${sizeMB} MB | ${backup.filename}
                                        </p>
                                    </div>
                                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                                        <button onclick="downloadBackup('${backup.filename}')" 
                                                class="btn btn-secondary" 
                                                style="padding: 10px 20px; font-size: 1em;">
                                            Herunterladen
                                        </button>
                                        <button onclick="restoreBackup('${backup.filename}')" 
                                                class="btn btn-primary" 
                                                style="padding: 10px 20px; font-size: 1em;">
                                            Wiederherstellen
                                        </button>
                                        <button onclick="deleteBackup('${backup.filename}')" 
                                                class="btn btn-danger" 
                                                style="padding: 10px 20px; font-size: 1em;">
                                            Löschen
                                        </button>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                    container.innerHTML = html;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('backupsList').innerHTML = '<p style="color: #ef4444;">Fehler beim Laden der Backups.</p>';
            });
        }
        
        function restoreBackup(filename) {
            if (!confirm('ACHTUNG: Möchten Sie dieses Backup wirklich wiederherstellen?\n\nDie aktuelle Datenbank wird überschrieben!')) {
                return;
            }
            
            if (!confirm('Sind Sie sich wirklich sicher? Diese Aktion kann nicht rückgängig gemacht werden!')) {
                return;
            }
            
            // PIN-Eingabe Modal öffnen
            openRestorePinModal(filename);
        }
        
        function downloadBackup(filename) {
            // PIN-Eingabe Modal öffnen
            openDownloadPinModal(filename);
        }
        
        // Backup-Upload Formular
        document.getElementById('uploadBackupForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const fileInput = document.getElementById('backupFileInput');
            if (!fileInput.files || fileInput.files.length === 0) {
                alert('Bitte wählen Sie eine Datei aus');
                return;
            }
            
            // PIN-Eingabe Modal öffnen
            openUploadPinModal(fileInput.files[0]);
        });
        
        function deleteBackup(filename) {
            // PIN-Eingabe Modal öffnen
            openDeletePinModal(filename);
        }
        
        function openDeletePinModal(filename) {
            document.getElementById('deleteBackupFilename').value = filename;
            document.getElementById('deletePinModal').classList.add('show');
            document.getElementById('deletePinInput').value = '';
            if (!isTouchDevice()) {
                document.getElementById('deletePinInput').focus();
            }
        }
        
        function closeDeletePinModal() {
            document.getElementById('deletePinModal').classList.remove('show');
            document.getElementById('deletePinInput').value = '';
            document.getElementById('deleteBackupFilename').value = '';
        }
        
        function confirmDeleteBackup() {
            const filename = document.getElementById('deleteBackupFilename').value;
            const pin = document.getElementById('deletePinInput').value;
            
            if (!pin) {
                alert('Bitte geben Sie den PIN-Code ein');
                return;
            }
            
            fetch('api/delete_backup.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    filename: filename,
                    pin: pin
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Backup erfolgreich gelöscht!');
                    closeDeletePinModal();
                    loadBackups();
                } else {
                    alert('Fehler: ' + (data.error || 'Unbekannter Fehler'));
                    document.getElementById('deletePinInput').value = '';
                    if (!isTouchDevice()) {
                        document.getElementById('deletePinInput').focus();
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Fehler beim Löschen des Backups');
            });
        }
        
        // Wiederherstellen PIN-Modal
        function openRestorePinModal(filename) {
            document.getElementById('restoreBackupFilename').value = filename;
            document.getElementById('restorePinModal').classList.add('show');
            document.getElementById('restorePinInput').value = '';
            if (!isTouchDevice()) {
                document.getElementById('restorePinInput').focus();
            }
        }
        
        function closeRestorePinModal() {
            document.getElementById('restorePinModal').classList.remove('show');
            document.getElementById('restorePinInput').value = '';
            document.getElementById('restoreBackupFilename').value = '';
        }
        
        function confirmRestoreBackup() {
            const filename = document.getElementById('restoreBackupFilename').value;
            const pin = document.getElementById('restorePinInput').value;
            
            if (!pin) {
                alert('Bitte geben Sie den PIN-Code ein');
                return;
            }
            
            fetch('api/restore_backup.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    filename: filename,
                    pin: pin
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Backup erfolgreich wiederhergestellt! Die Seite wird neu geladen.');
                    window.location.href = 'admin.php';
                } else {
                    alert('Fehler: ' + (data.error || 'Unbekannter Fehler'));
                    document.getElementById('restorePinInput').value = '';
                    if (!isTouchDevice()) {
                        document.getElementById('restorePinInput').focus();
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Fehler beim Wiederherstellen des Backups');
            });
        }
        
        // Download PIN-Modal
        function openDownloadPinModal(filename) {
            document.getElementById('downloadBackupFilename').value = filename;
            document.getElementById('downloadPinModal').classList.add('show');
            document.getElementById('downloadPinInput').value = '';
            if (!isTouchDevice()) {
                document.getElementById('downloadPinInput').focus();
            }
        }
        
        function closeDownloadPinModal() {
            document.getElementById('downloadPinModal').classList.remove('show');
            document.getElementById('downloadPinInput').value = '';
            document.getElementById('downloadBackupFilename').value = '';
        }
        
        function confirmDownloadBackup() {
            const filename = document.getElementById('downloadBackupFilename').value;
            const pin = document.getElementById('downloadPinInput').value;
            
            if (!pin) {
                alert('Bitte geben Sie den PIN-Code ein');
                return;
            }
            
            window.location.href = 'api/download_backup.php?file=' + encodeURIComponent(filename) + '&pin=' + encodeURIComponent(pin);
            closeDownloadPinModal();
        }
        
        // Upload PIN-Modal
        let pendingUploadFile = null;
        
        function openUploadPinModal(file) {
            pendingUploadFile = file;
            document.getElementById('uploadPinModal').classList.add('show');
            document.getElementById('uploadPinInput').value = '';
            if (!isTouchDevice()) {
                document.getElementById('uploadPinInput').focus();
            }
        }
        
        function closeUploadPinModal() {
            document.getElementById('uploadPinModal').classList.remove('show');
            document.getElementById('uploadPinInput').value = '';
            pendingUploadFile = null;
        }
        
        function confirmUploadBackup() {
            const pin = document.getElementById('uploadPinInput').value;
            
            if (!pin) {
                alert('Bitte geben Sie den PIN-Code ein');
                return;
            }
            
            if (!pendingUploadFile) {
                alert('Keine Datei ausgewählt');
                return;
            }
            
            const formData = new FormData();
            formData.append('backup_file', pendingUploadFile);
            formData.append('pin', pin);
            
            fetch('api/upload_backup.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Backup erfolgreich hochgeladen!');
                    document.getElementById('backupFileInput').value = '';
                    closeUploadPinModal();
                    loadBackups();
                } else {
                    alert('Fehler: ' + (data.error || 'Unbekannter Fehler'));
                    document.getElementById('uploadPinInput').value = '';
                    if (!isTouchDevice()) {
                        document.getElementById('uploadPinInput').focus();
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Fehler beim Hochladen des Backups');
            });
        }
        
        // Touchscreen-Erkennung für PIN-Eingabe
        function isTouchDevice() {
            return (('ontouchstart' in window) ||
                    (navigator.maxTouchPoints > 0) ||
                    (navigator.msMaxTouchPoints > 0));
        }
        
        // Nummernfelder für alle PIN-Eingaben aktivieren
        if (isTouchDevice()) {
            document.addEventListener('DOMContentLoaded', function() {
                const touchPads = [
                    { pad: 'deleteTouchPad', input: 'deletePinInput' },
                    { pad: 'restoreTouchPad', input: 'restorePinInput' },
                    { pad: 'downloadTouchPad', input: 'downloadPinInput' },
                    { pad: 'uploadTouchPad', input: 'uploadPinInput' }
                ];
                
                touchPads.forEach(({ pad, input }) => {
                    const touchPad = document.getElementById(pad);
                    const pinInput = document.getElementById(input);
                    if (touchPad && pinInput) {
                        touchPad.style.display = 'block';
                        pinInput.readOnly = true;
                        pinInput.style.cursor = 'default';
                    }
                });
            });
        }
        
        function addDeleteDigit(digit) {
            const input = document.getElementById('deletePinInput');
            if (input.value.length < 10) {
                input.value += digit;
            }
        }
        
        function deleteDeleteDigit() {
            const input = document.getElementById('deletePinInput');
            input.value = input.value.slice(0, -1);
        }
        
        // Wiederherstellen PIN-Funktionen
        function addRestoreDigit(digit) {
            const input = document.getElementById('restorePinInput');
            if (input.value.length < 10) {
                input.value += digit;
            }
        }
        
        function deleteRestoreDigit() {
            const input = document.getElementById('restorePinInput');
            input.value = input.value.slice(0, -1);
        }
        
        // Download PIN-Funktionen
        function addDownloadDigit(digit) {
            const input = document.getElementById('downloadPinInput');
            if (input.value.length < 10) {
                input.value += digit;
            }
        }
        
        function deleteDownloadDigit() {
            const input = document.getElementById('downloadPinInput');
            input.value = input.value.slice(0, -1);
        }
        
        // Upload PIN-Funktionen
        function addUploadDigit(digit) {
            const input = document.getElementById('uploadPinInput');
            if (input.value.length < 10) {
                input.value += digit;
            }
        }
        
        function deleteUploadDigit() {
            const input = document.getElementById('uploadPinInput');
            input.value = input.value.slice(0, -1);
        }
        
        // Enter-Taste für alle PIN-Eingaben
        document.addEventListener('DOMContentLoaded', function() {
            // Delete PIN
            const deletePinInput = document.getElementById('deletePinInput');
            if (deletePinInput) {
                deletePinInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        confirmDeleteBackup();
                    }
                });
            }
            
            // Restore PIN
            const restorePinInput = document.getElementById('restorePinInput');
            if (restorePinInput) {
                restorePinInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        confirmRestoreBackup();
                    }
                });
            }
            
            // Download PIN
            const downloadPinInput = document.getElementById('downloadPinInput');
            if (downloadPinInput) {
                downloadPinInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        confirmDownloadBackup();
                    }
                });
            }
            
            // Upload PIN
            const uploadPinInput = document.getElementById('uploadPinInput');
            if (uploadPinInput) {
                uploadPinInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        confirmUploadBackup();
                    }
                });
            }
            
            // Modal schließen beim Klick außerhalb
            const modals = ['deletePinModal', 'restorePinModal', 'downloadPinModal', 'uploadPinModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (modal) {
                    modal.addEventListener('click', function(e) {
                        if (e.target === modal) {
                            if (modalId === 'deletePinModal') closeDeletePinModal();
                            else if (modalId === 'restorePinModal') closeRestorePinModal();
                            else if (modalId === 'downloadPinModal') closeDownloadPinModal();
                            else if (modalId === 'uploadPinModal') closeUploadPinModal();
                        }
                    });
                }
            });
        });
        
        // Backups beim Laden anzeigen
        loadBackups();
    </script>
</body>
</html>

