let activeSessions = {}; // {personId: {startTime, intervalId, sessionId, card}}
let inactivityTimer = null;
let hasActiveSession = false;
let manualAddModeEnabled = false;
const INACTIVITY_TIMEOUT = 15000; // 15 Sekunden
const RELOAD_FLAG_KEY = 'gym_tracking_reload_triggered';

// URL-Leiste verstecken (für mobile Browser)
function hideURLBar() {
    if (window.innerHeight < window.outerHeight) {
        window.scrollTo(0, 1);
    }
    setTimeout(function() {
        window.scrollTo(0, 1);
    }, 0);
}

// Prüfen ob bereits neu geladen wurde für aktive Sessions
function wasReloadTriggered() {
    const activeSessionIds = Object.keys(activeSessions).join(',');
    const storedIds = localStorage.getItem(RELOAD_FLAG_KEY);
    return storedIds === activeSessionIds;
}

// Flag setzen dass bereits neu geladen wurde (mit aktuellen Session-IDs)
function setReloadTriggered() {
    const activeSessionIds = Object.keys(activeSessions).join(',');
    localStorage.setItem(RELOAD_FLAG_KEY, activeSessionIds);
    // Flag nach 5 Minuten wieder zurücksetzen (Session sollte dann beendet sein)
    setTimeout(function() {
        localStorage.removeItem(RELOAD_FLAG_KEY);
    }, 300000); // 5 Minuten
}

// Prüfen ob neue Sessions hinzugekommen sind (die noch nicht neu geladen wurden)
function hasNewSessions() {
    const activeSessionIds = Object.keys(activeSessions).join(',');
    const storedIds = localStorage.getItem(RELOAD_FLAG_KEY);
    
    // Wenn keine gespeicherten IDs existieren, sind alle Sessions neu
    if (!storedIds) {
        return activeSessionIds.length > 0;
    }
    
    // Prüfen ob neue Session-IDs hinzugekommen sind
    const storedIdsArray = storedIds.split(',');
    const activeIdsArray = activeSessionIds.split(',');
    
    // Wenn neue IDs vorhanden sind, die nicht in storedIds sind
    return activeIdsArray.some(id => !storedIdsArray.includes(id));
}

// Inaktivitäts-Timer starten
function startInactivityTimer() {
    // Timer nur starten wenn neue Sessions vorhanden sind (die noch nicht neu geladen wurden)
    if (!hasNewSessions()) {
        return;
    }
    
    if (inactivityTimer) {
        clearTimeout(inactivityTimer);
    }
    
    // Nur neu laden wenn eine Session aktiv ist
    if (hasActiveSession) {
        inactivityTimer = setTimeout(function() {
            // Nochmal prüfen ob neue Sessions vorhanden sind
            if (hasNewSessions()) {
                setReloadTriggered();
                window.location.reload();
            }
        }, INACTIVITY_TIMEOUT);
    }
}

// Inaktivitäts-Timer zurücksetzen
function resetInactivityTimer() {
    // Timer nicht zurücksetzen wenn bereits für alle aktiven Sessions neu geladen wurde
    if (!hasNewSessions()) {
        return;
    }
    
    if (inactivityTimer) {
        clearTimeout(inactivityTimer);
    }
    
    // Timer neu starten
    startInactivityTimer();
}

// Aktivität erkennen
function trackActivity() {
    resetInactivityTimer();
}

// Beim Laden der Seite aktive Sessions wiederherstellen
document.addEventListener('DOMContentLoaded', function() {
    // URL-Leiste verstecken
    hideURLBar();
    window.addEventListener('resize', hideURLBar);
    window.addEventListener('orientationchange', function() {
        setTimeout(hideURLBar, 500);
    });
    
    // Aktivität-Tracking einrichten
    document.addEventListener('mousemove', trackActivity);
    document.addEventListener('mousedown', trackActivity);
    document.addEventListener('keypress', trackActivity);
    document.addEventListener('touchstart', trackActivity);
    document.addEventListener('touchmove', trackActivity);
    document.addEventListener('scroll', trackActivity);
    window.addEventListener('focus', trackActivity);
    
    // Prüfen ob aktive Sessions vorhanden sind
    if (typeof activeSessionsData !== 'undefined' && Object.keys(activeSessionsData).length > 0) {
        hasActiveSession = true;
        // Timer nur einmal starten (nicht zurücksetzen bei Aktivität)
        startInactivityTimer();
    }
    if (typeof activeSessionsData !== 'undefined') {
        for (let personId in activeSessionsData) {
            const sessionData = activeSessionsData[personId];
            const card = document.querySelector(`[data-person-id="${personId}"]`);
            if (card) {
                restoreSession(card, personId, sessionData.start_time);
            }
        }
    }
    
    // Klick auf Namen für Nachtragen-Modus
    const personNames = document.querySelectorAll('.person-name');
    personNames.forEach(nameElement => {
        nameElement.addEventListener('click', handlePersonNameClick);
    });

    updateManualAddModeUI();
});

function restoreSession(card, personId, startTimeStr) {
    card.classList.add('active');
    
    // Startzeit sicher parsen - SQLite Format: "YYYY-MM-DD HH:MM:SS"
    // Als lokale Zeit interpretieren (nicht UTC)
    let startTime;
    if (startTimeStr.includes('T')) {
        startTime = new Date(startTimeStr);
    } else {
        // SQLite Format: "YYYY-MM-DD HH:MM:SS" 
        // Als lokale Zeit interpretieren
        const timeStr = String(startTimeStr);
        const [datePart, timePart] = timeStr.split(' ');
        const [year, month, day] = datePart.split('-');
        const [hour, minute, second] = timePart.split(':');
        // Erstelle Date-Objekt in lokaler Zeitzone
        startTime = new Date(parseInt(year), parseInt(month) - 1, parseInt(day), 
                            parseInt(hour), parseInt(minute), parseInt(second || 0));
    }
    
    // Prüfen ob Startzeit gültig ist
    if (isNaN(startTime.getTime())) {
        console.error('Invalid start time:', startTimeStr);
        return;
    }
    
    activeSessions[personId] = {
        startTime: startTime,
        card: card
    };
    
    // Inaktivitäts-Timer starten wenn Session wiederhergestellt wird (nur einmal)
    hasActiveSession = true;
    startInactivityTimer();
}

function handleCardClick(card, event) {
    event.stopPropagation();
    if (manualAddModeEnabled) return;

    const personId = card.dataset.personId;
    const personName = card.dataset.personName;
    
    if (!personId) return; // Add card clicked
    
    if (card.classList.contains('active')) {
        // Zweiter Klick - Modal öffnen
        openSessionModal(personId, personName);
    } else {
        // Erster Klick - Karte aktivieren
        startSession(card, personId);
    }
}

function toggleManualAddMode() {
    manualAddModeEnabled = !manualAddModeEnabled;
    updateManualAddModeUI();
}

function updateManualAddModeUI() {
    const modeButton = document.getElementById('manualAddModeBtn');
    if (!modeButton) {
        return;
    }

    modeButton.textContent = `Nachtragen-Modus: ${manualAddModeEnabled ? 'EIN' : 'AUS'}`;
    modeButton.classList.toggle('manual-mode-active', manualAddModeEnabled);
    document.body.classList.toggle('manual-add-mode', manualAddModeEnabled);
}

function handlePersonNameClick(event) {
    if (!manualAddModeEnabled) {
        return;
    }

    event.preventDefault();
    event.stopPropagation();

    const card = event.currentTarget.closest('.person-card');
    if (!card) {
        return;
    }

    const personId = card.dataset.personId;
    const personName = card.dataset.personName;
    openManualAddModal(personId, personName);
}

function startSession(card, personId) {
    // Session in der Datenbank starten
    // Mehrere Personen können gleichzeitig aktiv sein
    fetch('api/start_session.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            person_id: personId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Session lokal aktivieren
            card.classList.add('active');
            
            // Startzeit sicher parsen - SQLite Format: "YYYY-MM-DD HH:MM:SS"
            // Wichtig: Die Zeit kommt als lokale Zeit ohne Zeitzone, daher als lokale Zeit interpretieren
            let startTime;
            if (data.start_time.includes('T')) {
                startTime = new Date(data.start_time);
            } else {
                // SQLite Format: "YYYY-MM-DD HH:MM:SS" 
                // Als lokale Zeit interpretieren (nicht UTC)
                const timeStr = data.start_time;
                const [datePart, timePart] = timeStr.split(' ');
                const [year, month, day] = datePart.split('-');
                const [hour, minute, second] = timePart.split(':');
                // Erstelle Date-Objekt in lokaler Zeitzone
                startTime = new Date(parseInt(year), parseInt(month) - 1, parseInt(day), 
                                    parseInt(hour), parseInt(minute), parseInt(second || 0));
            }
            
            // Prüfen ob Startzeit gültig ist
            if (isNaN(startTime.getTime())) {
                console.error('Invalid start time from server:', data.start_time);
                alert('Fehler: Ungültige Startzeit vom Server');
                return;
            }
            
            activeSessions[personId] = {
                startTime: startTime,
                card: card
            };
            
            // Inaktivitäts-Timer starten wenn Session gestartet wird (nur einmal)
            hasActiveSession = true;
            startInactivityTimer();
        } else {
            alert('Fehler beim Starten der Session: ' + (data.error || 'Unbekannter Fehler'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Fehler beim Starten der Session');
    });
}

function stopAllSessions() {
    for (let personId in activeSessions) {
        const card = activeSessions[personId].card;
        if (card) {
            card.classList.remove('active');
        }
    }
    activeSessions = {};
}

function openSessionModal(personId, personName) {
    const session = activeSessions[personId];
    if (!session) return;
    
    // Startzeit sicher parsen (kann Date-Objekt oder String sein)
    let startTime;
    if (session.startTime instanceof Date) {
        startTime = session.startTime;
    } else {
        // String parsen - SQLite Format: "YYYY-MM-DD HH:MM:SS"
        // Als lokale Zeit interpretieren
        const timeStr = String(session.startTime);
        if (timeStr.includes('T')) {
            startTime = new Date(timeStr);
        } else {
            // SQLite Format: "YYYY-MM-DD HH:MM:SS" 
            // Als lokale Zeit interpretieren
            const [datePart, timePart] = timeStr.split(' ');
            const [year, month, day] = datePart.split('-');
            const [hour, minute, second] = timePart.split(':');
            // Erstelle Date-Objekt in lokaler Zeitzone
            startTime = new Date(parseInt(year), parseInt(month) - 1, parseInt(day), 
                                parseInt(hour), parseInt(minute), parseInt(second || 0));
        }
    }
    
    // Prüfen ob Startzeit gültig ist
    if (isNaN(startTime.getTime())) {
        console.error('Invalid start time:', session.startTime);
        alert('Fehler: Ungültige Startzeit');
        return;
    }
    
    const now = new Date();
    const diffMs = now.getTime() - startTime.getTime();
    const diffMinutes = Math.max(0, Math.floor(diffMs / 60000)); // Mindestens 0
    
    // Debug-Log (kann später entfernt werden)
    console.log('Raw start time string:', session.startTime);
    console.log('Parsed start time:', startTime);
    console.log('Now:', now);
    console.log('Diff ms:', diffMs);
    console.log('Diff minutes:', diffMinutes);
    
    document.getElementById('modalPersonName').textContent = personName;
    document.getElementById('minutesDisplay').textContent = diffMinutes;
    document.getElementById('sessionModal').dataset.personId = personId;
    document.getElementById('sessionModal').dataset.startTime = startTime.toISOString();
    
    const modal = document.getElementById('sessionModal');
    modal.classList.add('show');
    
    // Minuten live aktualisieren (jede Sekunde für bessere UX)
    const intervalId = setInterval(() => {
        const now = new Date();
        const diffMs = now.getTime() - startTime.getTime();
        const diffMinutes = Math.max(0, Math.floor(diffMs / 60000));
        document.getElementById('minutesDisplay').textContent = diffMinutes;
    }, 1000);
    
    session.intervalId = intervalId;
}

function saveSession() {
    const modal = document.getElementById('sessionModal');
    const personId = modal.dataset.personId;
    
    fetch('api/save_session.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            person_id: personId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeSessionModal();
            stopAllSessions();
            hasActiveSession = false;
            if (inactivityTimer) {
                clearTimeout(inactivityTimer);
                inactivityTimer = null;
            }
            // Reload-Flag zurücksetzen da Session beendet wurde
            localStorage.removeItem(RELOAD_FLAG_KEY);
            // Seite neu laden um aktive Sessions oben anzuzeigen
            window.location.reload();
        } else {
            alert('Fehler beim Speichern: ' + (data.error || 'Unbekannter Fehler'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Fehler beim Speichern der Session');
    });
}

function cancelSession() {
    closeSessionModal();
}

// Nachträgliches Eintragen
function openManualAddModal(personId, personName) {
    document.getElementById('manualAddPersonName').textContent = 'Nachträgliches Eintragen: ' + personName;
    document.getElementById('manualAddModal').dataset.personId = personId;
    
    // Aktuelles Datum/Zeit als Standard setzen
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    document.getElementById('manualStartTime').value = `${year}-${month}-${day}T${hours}:${minutes}`;
    document.getElementById('manualDuration').value = 60;
    
    document.getElementById('manualAddModal').classList.add('show');
}

function closeManualAddModal() {
    document.getElementById('manualAddModal').classList.remove('show');
}

function confirmManualAdd() {
    const modal = document.getElementById('manualAddModal');
    const personId = modal.dataset.personId;
    const startTime = document.getElementById('manualStartTime').value;
    const duration = parseInt(document.getElementById('manualDuration').value);
    
    if (!startTime || !duration || duration < 1) {
        alert('Bitte füllen Sie alle Felder korrekt aus');
        return;
    }
    
    // Datum/Zeit Format konvertieren (YYYY-MM-DDTHH:MM -> YYYY-MM-DD HH:MM:SS)
    const startDateTime = startTime.replace('T', ' ') + ':00';
    
    fetch('api/manual_add_session.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            person_id: personId,
            start_time: startDateTime,
            duration_minutes: duration
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Training erfolgreich nachgetragen!');
            closeManualAddModal();
            window.location.reload();
        } else {
            alert('Fehler: ' + (data.error || 'Unbekannter Fehler'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Fehler beim Nachtragen des Trainings');
    });
}

// Modal schließen beim Klick außerhalb
document.addEventListener('DOMContentLoaded', function() {
    const manualAddModal = document.getElementById('manualAddModal');
    if (manualAddModal) {
        manualAddModal.addEventListener('click', function(e) {
            if (e.target === manualAddModal) {
                closeManualAddModal();
            }
        });
    }
});

function deleteSession() {
    if (!confirm('Möchten Sie diese Session wirklich löschen?')) {
        return;
    }
    
    const modal = document.getElementById('sessionModal');
    const personId = modal.dataset.personId;
    
    // Session in der Datenbank löschen
    fetch('api/delete_session.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            person_id: personId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Session lokal stoppen
            if (activeSessions[personId]) {
                const session = activeSessions[personId];
                if (session.intervalId) {
                    clearInterval(session.intervalId);
                }
                if (session.card) {
                    session.card.classList.remove('active');
                }
                delete activeSessions[personId];
            }
            closeSessionModal();
            hasActiveSession = false;
            if (inactivityTimer) {
                clearTimeout(inactivityTimer);
                inactivityTimer = null;
            }
            // Reload-Flag zurücksetzen da Session beendet wurde
            localStorage.removeItem(RELOAD_FLAG_KEY);
            // Seite neu laden um aktive Sessions oben anzuzeigen
            window.location.reload();
        } else {
            alert('Fehler beim Löschen: ' + (data.error || 'Unbekannter Fehler'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        // Trotzdem lokal stoppen
        if (activeSessions[personId]) {
            const session = activeSessions[personId];
            if (session.intervalId) {
                clearInterval(session.intervalId);
            }
            if (session.card) {
                session.card.classList.remove('active');
            }
            delete activeSessions[personId];
        }
        closeSessionModal();
    });
}

function closeSessionModal() {
    const modal = document.getElementById('sessionModal');
    modal.classList.remove('show');
    
    // Interval stoppen falls vorhanden
    const personId = modal.dataset.personId;
    if (personId && activeSessions[personId] && activeSessions[personId].intervalId) {
        clearInterval(activeSessions[personId].intervalId);
    }
}

function addPerson() {
    document.getElementById('addPersonModal').classList.add('show');
    document.getElementById('newPersonName').focus();
}

function closeAddPersonModal() {
    document.getElementById('addPersonModal').classList.remove('show');
    document.getElementById('newPersonName').value = '';
}

function confirmAddPerson() {
    const name = document.getElementById('newPersonName').value.trim();
    
    if (!name) {
        alert('Bitte geben Sie einen Namen ein');
        return;
    }
    
    fetch('api/add_person.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ name: name })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeAddPersonModal();
            location.reload();
        } else {
            alert('Fehler beim Hinzufügen: ' + (data.error || 'Unbekannter Fehler'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Fehler beim Hinzufügen der Person');
    });
}

// Modal schließen bei Klick außerhalb
document.addEventListener('click', function(event) {
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        if (event.target === modal) {
            if (modal.id === 'sessionModal') {
                closeSessionModal();
            } else if (modal.id === 'addPersonModal') {
                closeAddPersonModal();
            } else if (modal.id === 'renamePersonModal' && typeof closeRenamePersonModal === 'function') {
                closeRenamePersonModal();
            } else if (modal.id === 'sessionsModal' && typeof closeSessionsModal === 'function') {
                closeSessionsModal();
            }
        }
    });
});

// Enter-Taste für Person hinzufügen
document.getElementById('newPersonName').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        confirmAddPerson();
    }
});

