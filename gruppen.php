<?php
require_once 'config.php';

$db = getDB();

// Alle Gruppen mit ihren Mitgliedern laden
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
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Gruppen - Kraftraum Tracking</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header>
            <div class="header-left">
                <h1>RV Hoya</h1>
                <p class="subtitle">Gruppen</p>
            </div>
            <a href="index.php" class="statistik-btn">Zurück</a>
        </header>
        
        <div class="cards-grid" id="groupsGrid">
            <?php if (empty($groups)): ?>
                <div class="empty-state">
                    <p>Noch keine Gruppen vorhanden.</p>
                    <p style="color: #a0a0a0; margin-top: 15px;">Bitte im Admin-Menü eine Gruppe anlegen.</p>
                </div>
            <?php else: ?>
                <?php foreach ($groups as $group): ?>
                    <div class="card group-card" 
                         data-group-id="<?php echo htmlspecialchars($group['id']); ?>" 
                         data-group-name="<?php echo htmlspecialchars($group['name']); ?>"
                         onclick="handleGroupClick(<?php echo $group['id']; ?>, '<?php echo htmlspecialchars(addslashes($group['name'])); ?>', event)">
                        <div class="card-content">
                            <h2><?php echo htmlspecialchars($group['name']); ?></h2>
                            <p style="color: #a0a0a0; margin-top: 10px; font-size: 1em;">
                                <?php echo $group['member_count']; ?> Mitglied<?php echo $group['member_count'] != 1 ? 'er' : ''; ?>
                            </p>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal für Gruppenmitglieder -->
    <div id="membersModal" class="modal">
        <div class="modal-content">
            <h2 id="membersModalTitle">Mitglieder</h2>
            <div id="membersList" style="max-height: 400px; overflow-y: auto; margin-bottom: 20px;">
                <p style="color: #a0a0a0; text-align: center;">Lade Mitglieder...</p>
            </div>
            <div class="modal-buttons">
                <button onclick="closeMembersModal()" class="btn btn-secondary">Schließen</button>
            </div>
        </div>
    </div>

    <script>
        let longPressTimer = null;
        let longPressDetected = false;
        const LONG_PRESS_DURATION = 500; // 500ms für Long-Press

        function handleGroupClick(groupId, groupName, event) {
            // Wenn Long-Press erkannt wurde, nicht den normalen Click ausführen
            if (longPressDetected) {
                longPressDetected = false;
                return;
            }
            
            startGroupSession(groupId, groupName);
        }

        // Long-Press Handler für Touch-Geräte
        document.addEventListener('DOMContentLoaded', function() {
            const groupCards = document.querySelectorAll('.group-card');
            
            groupCards.forEach(card => {
                let touchStartTime = 0;
                let touchTimer = null;
                
                // Touch Start
                card.addEventListener('touchstart', function(e) {
                    touchStartTime = Date.now();
                    longPressDetected = false;
                    
                    touchTimer = setTimeout(() => {
                        longPressDetected = true;
                        const groupId = parseInt(card.dataset.groupId);
                        const groupName = card.dataset.groupName;
                        showGroupMembers(groupId, groupName);
                    }, LONG_PRESS_DURATION);
                }, { passive: true });
                
                // Touch End
                card.addEventListener('touchend', function(e) {
                    if (touchTimer) {
                        clearTimeout(touchTimer);
                        touchTimer = null;
                    }
                }, { passive: true });
                
                // Touch Cancel
                card.addEventListener('touchcancel', function(e) {
                    if (touchTimer) {
                        clearTimeout(touchTimer);
                        touchTimer = null;
                    }
                }, { passive: true });
                
                // Mouse Long-Press für Desktop (optional)
                let mouseTimer = null;
                card.addEventListener('mousedown', function(e) {
                    mouseTimer = setTimeout(() => {
                        longPressDetected = true;
                        const groupId = parseInt(card.dataset.groupId);
                        const groupName = card.dataset.groupName;
                        showGroupMembers(groupId, groupName);
                    }, LONG_PRESS_DURATION);
                });
                
                card.addEventListener('mouseup', function(e) {
                    if (mouseTimer) {
                        clearTimeout(mouseTimer);
                        mouseTimer = null;
                    }
                });
                
                card.addEventListener('mouseleave', function(e) {
                    if (mouseTimer) {
                        clearTimeout(mouseTimer);
                        mouseTimer = null;
                    }
                });
            });
        });

        function showGroupMembers(groupId, groupName) {
            document.getElementById('membersModalTitle').textContent = 'Mitglieder: ' + groupName;
            document.getElementById('membersModal').classList.add('show');
            document.getElementById('membersList').innerHTML = '<p style="color: #a0a0a0; text-align: center;">Lade Mitglieder...</p>';
            
            fetch('api/get_group_members.php?group_id=' + groupId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const membersList = document.getElementById('membersList');
                        
                        if (data.members.length === 0) {
                            membersList.innerHTML = '<p style="color: #a0a0a0; text-align: center;">Keine Mitglieder in dieser Gruppe.</p>';
                            return;
                        }
                        
                        let html = '<div style="display: flex; flex-direction: column; gap: 10px;">';
                        data.members.forEach(member => {
                            const isActive = member.current_session_start ? 
                                '<span style="color: #16a34a; font-weight: 600;"> (aktiv)</span>' : '';
                            html += `
                                <div style="background: #1a1a1a; padding: 15px; border-radius: 10px; border: 1px solid rgba(255, 255, 255, 0.1);">
                                    <h3 style="color: #ffffff; margin: 0; font-size: 1.2em;">
                                        ${member.name}${isActive}
                                    </h3>
                                </div>
                            `;
                        });
                        html += '</div>';
                        membersList.innerHTML = html;
                    } else {
                        document.getElementById('membersList').innerHTML = '<p style="color: #ef4444; text-align: center;">Fehler beim Laden der Mitglieder.</p>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('membersList').innerHTML = '<p style="color: #ef4444; text-align: center;">Fehler beim Laden der Mitglieder.</p>';
                });
        }

        function closeMembersModal() {
            document.getElementById('membersModal').classList.remove('show');
        }

        function startGroupSession(groupId, groupName) {
            if (!confirm('Training für alle Mitglieder der Gruppe "' + groupName + '" starten?')) {
                return;
            }
            
            fetch('api/start_group_session.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    group_id: groupId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Training für ' + data.started_count + ' Mitglieder gestartet!');
                    // Zurück zur Hauptseite
                    window.location.href = 'index.php';
                } else {
                    alert('Fehler: ' + (data.error || 'Unbekannter Fehler'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Fehler beim Starten des Gruppentrainings');
            });
        }

        // Modal schließen beim Klick außerhalb
        document.addEventListener('DOMContentLoaded', function() {
            const membersModal = document.getElementById('membersModal');
            if (membersModal) {
                membersModal.addEventListener('click', function(e) {
                    if (e.target === membersModal) {
                        closeMembersModal();
                    }
                });
            }
        });
    </script>
</body>
</html>

