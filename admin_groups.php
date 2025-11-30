<?php
session_start();
require_once 'config.php';

// Prüfen ob eingeloggt
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

$db = getDB();

// Alle Gruppen laden
$groupsResult = $db->query("SELECT id, name FROM groups ORDER BY name");
$groups = [];
while ($row = $groupsResult->fetchArray(SQLITE3_ASSOC)) {
    $groups[] = $row;
}

// Alle Personen laden
$personsResult = $db->query("SELECT id, name FROM persons ORDER BY name");
$persons = [];
while ($row = $personsResult->fetchArray(SQLITE3_ASSOC)) {
    $persons[] = $row;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Gruppen verwalten - Kraftraum Tracking</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header>
            <div class="header-left">
                <h1>RV Hoya</h1>
                <p class="subtitle">Gruppen verwalten</p>
            </div>
            <a href="admin.php" class="statistik-btn">Zurück</a>
        </header>
        
        <div class="stats-container">
            <div class="admin-section">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2 style="color: #ffffff; margin: 0;">Gruppen</h2>
                </div>
                
                <div style="margin-bottom: 30px;">
                    <button onclick="addGroup()" class="add-btn" style="width: 100%; max-width: 400px;">
                        Neue Gruppe erstellen
                    </button>
                </div>
                
                <div id="groupsList">
                    <?php if (empty($groups)): ?>
                        <div class="empty-state">
                            <p>Noch keine Gruppen vorhanden.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($groups as $group): ?>
                            <div class="group-item" data-group-id="<?php echo $group['id']; ?>" 
                                 style="background: #1a1a1a; padding: 20px; border-radius: 15px; margin-bottom: 20px; border: 1px solid rgba(255, 255, 255, 0.1);">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                                    <h3 style="color: #ffffff; margin: 0; font-size: 1.5em;">
                                        <?php echo htmlspecialchars($group['name']); ?>
                                    </h3>
                                    <button onclick="deleteGroup(<?php echo $group['id']; ?>, '<?php echo htmlspecialchars(addslashes($group['name'])); ?>')" 
                                            class="btn btn-danger" style="padding: 10px 20px; font-size: 1em;">
                                        Gruppe löschen
                                    </button>
                                </div>
                                
                                <div style="margin-bottom: 15px;">
                                    <h4 style="color: #a0a0a0; margin-bottom: 10px; font-size: 1.1em;">Mitglieder:</h4>
                                    <div id="members-<?php echo $group['id']; ?>" style="min-height: 50px;">
                                        <p style="color: #666; font-style: italic;">Lade Mitglieder...</p>
                                    </div>
                                </div>
                                
                                <div>
                                    <label style="color: #ffffff; display: block; margin-bottom: 10px; font-weight: 600;">
                                        Person zur Gruppe hinzufügen:
                                    </label>
                                    <select id="person-select-<?php echo $group['id']; ?>" 
                                            style="width: 100%; padding: 15px; font-size: 1.1em; border-radius: 8px; background: #2d2d2d; color: #ffffff; border: 2px solid rgba(255, 255, 255, 0.2); margin-bottom: 10px;">
                                        <option value="">-- Person auswählen --</option>
                                        <?php foreach ($persons as $person): ?>
                                            <option value="<?php echo $person['id']; ?>">
                                                <?php echo htmlspecialchars($person['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button onclick="addPersonToGroup(<?php echo $group['id']; ?>)" 
                                            class="btn btn-primary" style="width: 100%; padding: 12px; font-size: 1em;">
                                        Person hinzufügen
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal für Gruppe hinzufügen -->
    <div id="addGroupModal" class="modal">
        <div class="modal-content">
            <h2>Neue Gruppe erstellen</h2>
            <input type="text" id="newGroupName" placeholder="Gruppenname eingeben" autofocus>
            <div class="modal-buttons">
                <button onclick="confirmAddGroup()" class="btn btn-primary">Erstellen</button>
                <button onclick="closeAddGroupModal()" class="btn btn-secondary">Abbrechen</button>
            </div>
        </div>
    </div>

    <script>
        // Mitglieder für alle Gruppen laden
        document.addEventListener('DOMContentLoaded', function() {
            const groupItems = document.querySelectorAll('.group-item');
            groupItems.forEach(item => {
                const groupId = item.dataset.groupId;
                loadGroupMembers(groupId);
            });
        });
        
        function loadGroupMembers(groupId) {
            fetch('api/get_group_members.php?group_id=' + groupId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const container = document.getElementById('members-' + groupId);
                        if (data.members.length === 0) {
                            container.innerHTML = '<p style="color: #666; font-style: italic;">Keine Mitglieder in dieser Gruppe.</p>';
                            return;
                        }
                        
                        let html = '<div style="display: flex; flex-wrap: wrap; gap: 10px;">';
                        data.members.forEach(member => {
                            const isActive = member.current_session_start ? ' (aktiv)' : '';
                            html += `
                                <div style="background: #2d2d2d; padding: 10px 15px; border-radius: 8px; display: flex; align-items: center; gap: 10px;">
                                    <span style="color: #ffffff;">${member.name}${isActive}</span>
                                    <button onclick="removePersonFromGroup(${groupId}, ${member.id}, '${member.name.replace(/'/g, "\\'")}')" 
                                            class="btn btn-danger" 
                                            style="padding: 5px 10px; font-size: 0.85em;">
                                        Entfernen
                                    </button>
                                </div>
                            `;
                        });
                        html += '</div>';
                        container.innerHTML = html;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('members-' + groupId).innerHTML = '<p style="color: #ef4444;">Fehler beim Laden der Mitglieder.</p>';
                });
        }
        
        function addGroup() {
            document.getElementById('addGroupModal').classList.add('show');
            document.getElementById('newGroupName').value = '';
            document.getElementById('newGroupName').focus();
        }
        
        function closeAddGroupModal() {
            document.getElementById('addGroupModal').classList.remove('show');
        }
        
        function confirmAddGroup() {
            const name = document.getElementById('newGroupName').value.trim();
            if (!name) {
                alert('Bitte geben Sie einen Gruppennamen ein');
                return;
            }
            
            fetch('api/add_group.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ name: name })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Fehler: ' + (data.error || 'Unbekannter Fehler'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Fehler beim Erstellen der Gruppe');
            });
        }
        
        function deleteGroup(groupId, groupName) {
            if (!confirm('Möchten Sie die Gruppe "' + groupName + '" wirklich löschen?\n\nAlle Mitgliedschaften werden ebenfalls entfernt!')) {
                return;
            }
            
            fetch('api/delete_group.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ group_id: groupId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Fehler: ' + (data.error || 'Unbekannter Fehler'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Fehler beim Löschen der Gruppe');
            });
        }
        
        function addPersonToGroup(groupId) {
            const select = document.getElementById('person-select-' + groupId);
            const personId = select.value;
            
            if (!personId) {
                alert('Bitte wählen Sie eine Person aus');
                return;
            }
            
            fetch('api/add_person_to_group.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    group_id: groupId,
                    person_id: personId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    select.value = '';
                    loadGroupMembers(groupId);
                } else {
                    alert('Fehler: ' + (data.error || 'Unbekannter Fehler'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Fehler beim Hinzufügen der Person');
            });
        }
        
        function removePersonFromGroup(groupId, personId, personName) {
            if (!confirm('Möchten Sie "' + personName + '" wirklich aus der Gruppe entfernen?')) {
                return;
            }
            
            fetch('api/remove_person_from_group.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    group_id: groupId,
                    person_id: personId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadGroupMembers(groupId);
                } else {
                    alert('Fehler: ' + (data.error || 'Unbekannter Fehler'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Fehler beim Entfernen der Person');
            });
        }
    </script>
</body>
</html>



