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
                            <div class="user-item" style="background: #1a1a1a; padding: 20px; border-radius: 15px; margin-bottom: 15px; border: 1px solid rgba(255, 255, 255, 0.1); display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <h3 style="color: #ffffff; margin-bottom: 5px; font-size: 1.3em;">
                                        <?php echo htmlspecialchars($person['name']); ?>
                                    </h3>
                                    <p style="color: #a0a0a0; margin: 0; font-size: 0.9em;">
                                        Erstellt: <?php echo date('d.m.Y', strtotime($person['created_at'])); ?>
                                    </p>
                                </div>
                                <button onclick="deletePerson(<?php echo $person['id']; ?>, '<?php echo htmlspecialchars(addslashes($person['name'])); ?>')" 
                                        class="btn btn-danger" style="padding: 10px 20px; font-size: 1em;">
                                    Löschen
                                </button>
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

    <script src="script.js"></script>
    <script>
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
    </script>
</body>
</html>

