<?php
session_start();
require_once 'config.php';

// Prüfen ob eingeloggt
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

$db = getDB();

// Alle Personen laden
$result = $db->query("SELECT id, name, created_at FROM persons ORDER BY name");
$persons = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $persons[] = $row;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Admin - Kraftraum Tracking</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header>
            <div class="header-left">
                <h1>RV Hoya</h1>
                <p class="subtitle">Admin</p>
            </div>
            <a href="index.php" class="statistik-btn">Zurück</a>
        </header>
        
        <div class="stats-container">
            <div class="admin-section">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2 style="color: #ffffff; margin: 0;">Nutzer verwalten</h2>
                    <button onclick="logout()" class="btn btn-secondary" style="padding: 10px 20px; font-size: 0.9em;">
                        Abmelden
                    </button>
                </div>
                
                <div style="margin-bottom: 30px;">
                    <a href="admin_backup.php" class="add-btn" style="display: inline-block; width: 100%; max-width: 400px; text-align: center; text-decoration: none;">
                        Backup-Verwaltung
                    </a>
                </div>
                
                <div style="margin-bottom: 30px;">
                    <a href="admin_groups.php" class="add-btn" style="display: inline-block; width: 100%; max-width: 400px; text-align: center; text-decoration: none; background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);">
                        Gruppen verwalten
                    </a>
                </div>
                
                <div style="margin-bottom: 30px;">
                    <button onclick="addPerson()" class="add-btn" style="width: 100%; max-width: 400px;">
                        Neuen Nutzer hinzufügen
                    </button>
                </div>
                
                <div class="users-list">
                    <?php if (empty($persons)): ?>
                        <div class="empty-state">
                            <p>Noch keine Nutzer vorhanden.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($persons as $person): ?>
                            <div class="user-item" style="background: #1a1a1a; padding: 20px; border-radius: 15px; margin-bottom: 15px; border: 1px solid rgba(255, 255, 255, 0.1); display: flex; justify-content: space-between; align-items: center; gap: 15px; flex-wrap: wrap;">
                                <div>
                                    <h3 style="color: #ffffff; margin-bottom: 5px; font-size: 1.3em;">
                                        <?php echo htmlspecialchars($person['name']); ?>
                                    </h3>
                                    <p style="color: #a0a0a0; margin: 0; font-size: 0.9em;">
                                        Erstellt: <?php echo date('d.m.Y', strtotime($person['created_at'])); ?>
                                    </p>
                                </div>
                                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                                    <button onclick="openRenamePersonModal(<?php echo $person['id']; ?>, '<?php echo htmlspecialchars(addslashes($person['name'])); ?>')"
                                            class="btn btn-secondary" style="padding: 10px 20px; font-size: 1em;">
                                        Umbenennen
                                    </button>
                                    <button onclick="openSessionsModal(<?php echo $person['id']; ?>, '<?php echo htmlspecialchars(addslashes($person['name'])); ?>')"
                                            class="btn btn-primary" style="padding: 10px 20px; font-size: 1em;">
                                        Trainings
                                    </button>
                                    <button onclick="deletePerson(<?php echo $person['id']; ?>, '<?php echo htmlspecialchars(addslashes($person['name'])); ?>')" 
                                            class="btn btn-danger" style="padding: 10px 20px; font-size: 1em;">
                                        Löschen
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal für Person hinzufügen -->
    <div id="addPersonModal" class="modal">
        <div class="modal-content">
            <h2>Neuen Nutzer hinzufügen</h2>
            <input type="text" id="newPersonName" placeholder="Name eingeben" autofocus>
            <div class="modal-buttons">
                <button onclick="confirmAddPerson()" class="btn btn-primary">Hinzufügen</button>
                <button onclick="closeAddPersonModal()" class="btn btn-secondary">Abbrechen</button>
            </div>
        </div>
    </div>
    
    <!-- Modal für Nutzer umbenennen -->
    <div id="renamePersonModal" class="modal">
        <div class="modal-content">
            <h2>Nutzer umbenennen</h2>
            <input type="text" id="renamePersonName" placeholder="Neuer Name" autofocus>
            <div class="modal-buttons">
                <button onclick="confirmRenamePerson()" class="btn btn-primary">Speichern</button>
                <button onclick="closeRenamePersonModal()" class="btn btn-secondary">Abbrechen</button>
            </div>
        </div>
    </div>
    
    <!-- Modal für einzelne Trainings -->
    <div id="sessionsModal" class="modal">
        <div class="modal-content" style="max-width: 700px;">
            <h2 id="sessionsModalTitle">Trainings verwalten</h2>
            <div id="sessionsModalContent" style="max-height: 420px; overflow-y: auto; margin-bottom: 15px;">
                <p style="color: #a0a0a0; margin: 0;">Lade Trainings...</p>
            </div>
            <div class="modal-buttons">
                <button onclick="closeSessionsModal()" class="btn btn-secondary">Schließen</button>
            </div>
        </div>
    </div>

    <script src="script.js"></script>
    <script>
        let renamePersonId = null;
        let sessionsPersonId = null;
        
        function logout() {
            if (confirm('Möchten Sie sich wirklich abmelden?')) {
                window.location.href = 'admin_logout.php';
            }
        }
        
        function deletePerson(personId, personName) {
            if (!confirm('Möchten Sie den Nutzer "' + personName + '" wirklich löschen?\n\nAlle Trainingsdaten werden ebenfalls gelöscht!')) {
                return;
            }
            
            fetch('api/delete_person.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ person_id: personId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Fehler beim Löschen: ' + (data.error || 'Unbekannter Fehler'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Fehler beim Löschen des Nutzers');
            });
        }
        
        function openRenamePersonModal(personId, currentName) {
            renamePersonId = personId;
            const input = document.getElementById('renamePersonName');
            input.value = currentName;
            document.getElementById('renamePersonModal').classList.add('show');
            setTimeout(() => {
                input.focus();
                input.select();
            }, 50);
        }
        
        function closeRenamePersonModal() {
            renamePersonId = null;
            document.getElementById('renamePersonModal').classList.remove('show');
            document.getElementById('renamePersonName').value = '';
        }
        
        function confirmRenamePerson() {
            const newName = document.getElementById('renamePersonName').value.trim();
            
            if (!renamePersonId) {
                alert('Kein Nutzer ausgewählt.');
                return;
            }
            
            if (!newName) {
                alert('Bitte geben Sie einen Namen ein.');
                return;
            }
            
            fetch('api/rename_person.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ person_id: renamePersonId, name: newName })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeRenamePersonModal();
                    location.reload();
                } else {
                    alert('Fehler beim Umbenennen: ' + (data.error || 'Unbekannter Fehler'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Fehler beim Umbenennen des Nutzers');
            });
        }
        
        function openSessionsModal(personId, personName) {
            sessionsPersonId = personId;
            document.getElementById('sessionsModalTitle').textContent = 'Trainings: ' + personName;
            document.getElementById('sessionsModal').classList.add('show');
            loadSessionsForPerson();
        }
        
        function closeSessionsModal() {
            sessionsPersonId = null;
            document.getElementById('sessionsModal').classList.remove('show');
            document.getElementById('sessionsModalContent').innerHTML = '<p style="color: #a0a0a0; margin: 0;">Lade Trainings...</p>';
        }
        
        function loadSessionsForPerson() {
            const container = document.getElementById('sessionsModalContent');
            if (!sessionsPersonId) {
                container.innerHTML = '<p style="color: #ef4444; margin: 0;">Keine Person ausgewählt.</p>';
                return;
            }
            
            container.innerHTML = '<p style="color: #a0a0a0; margin: 0;">Lade Trainings...</p>';
            
            fetch('api/get_person_sessions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ person_id: sessionsPersonId })
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    container.innerHTML = '<p style="color: #ef4444; margin: 0;">Fehler beim Laden: ' + (data.error || 'Unbekannter Fehler') + '</p>';
                    return;
                }
                
                if (!data.sessions || data.sessions.length === 0) {
                    container.innerHTML = '<p style="color: #a0a0a0; margin: 0;">Keine Trainings vorhanden.</p>';
                    return;
                }
                
                const html = data.sessions.map(session => {
                    return `
                        <div style="background: #1a1a1a; padding: 15px; border-radius: 12px; margin-bottom: 12px; border: 1px solid rgba(255, 255, 255, 0.1);">
                            <div style="display: flex; justify-content: space-between; gap: 15px; align-items: center; flex-wrap: wrap;">
                                <div>
                                    <div style="color: #ffffff; font-weight: 700; margin-bottom: 4px;">
                                        ${session.start_display} - ${session.end_display}
                                    </div>
                                    <div style="color: #a0a0a0; font-size: 0.95em;">
                                        Dauer: ${session.duration_minutes} Minuten
                                    </div>
                                </div>
                                <button onclick="deleteSingleSession(${session.id})" class="btn btn-danger" style="padding: 8px 14px; font-size: 0.95em;">
                                    Training löschen
                                </button>
                            </div>
                        </div>
                    `;
                }).join('');
                
                container.innerHTML = html;
            })
            .catch(error => {
                console.error('Error:', error);
                container.innerHTML = '<p style="color: #ef4444; margin: 0;">Fehler beim Laden der Trainings.</p>';
            });
        }
        
        function deleteSingleSession(sessionId) {
            if (!confirm('Dieses Training wirklich löschen?')) {
                return;
            }
            
            fetch('api/delete_session_by_id.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ session_id: sessionId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadSessionsForPerson();
                } else {
                    alert('Fehler beim Löschen: ' + (data.error || 'Unbekannter Fehler'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Fehler beim Löschen des Trainings');
            });
        }
        
        document.getElementById('renamePersonName').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                confirmRenamePerson();
            }
        });
    </script>
</body>
</html>

