<?php
session_start();
require_once 'config.php';

// Wenn bereits eingeloggt, weiterleiten
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: admin.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pin = isset($_POST['pin']) ? trim($_POST['pin']) : '';
    
    if ($pin === ADMIN_PIN) {
        $_SESSION['admin_logged_in'] = true;
        header('Location: admin.php');
        exit;
    } else {
        $error = 'Falscher PIN-Code!';
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Admin Login - Kraftraum Tracking</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header>
            <div class="header-left">
                <h1>RV Hoya</h1>
                <p class="subtitle">Admin Login</p>
            </div>
            <a href="index.php" class="statistik-btn">Zurück</a>
        </header>
        
        <div class="stats-container" style="max-width: 500px; margin: 50px auto;">
            <h2 style="color: #ffffff; margin-bottom: 30px; text-align: center;">PIN-Code eingeben</h2>
            
            <?php if ($error): ?>
                <div style="background: rgba(239, 68, 68, 0.2); border: 2px solid #ef4444; color: #ef4444; padding: 15px; border-radius: 10px; margin-bottom: 20px; text-align: center; font-weight: 600;">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div style="margin-bottom: 20px;">
                    <input type="password" 
                           id="pinInput" 
                           name="pin" 
                           placeholder="PIN-Code eingeben" 
                           autofocus
                           maxlength="10"
                           style="width: 100%; padding: 20px; font-size: 1.5em; text-align: center; letter-spacing: 8px; background: #1a1a1a; color: #ffffff; border: 2px solid rgba(255, 255, 255, 0.2); border-radius: 10px; outline: none; transition: border-color 0.3s ease;"
                           onfocus="this.style.borderColor='#dc2626';"
                           onblur="this.style.borderColor='rgba(255, 255, 255, 0.2)';">
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 18px; font-size: 1.2em;">
                    Anmelden
                </button>
            </form>
            
            <!-- Touch-Nummernfeld -->
            <div id="touchPad" style="display: none; margin-top: 30px;">
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; max-width: 400px; margin: 0 auto;">
                    <button type="button" class="number-btn" onclick="addDigit('1')">1</button>
                    <button type="button" class="number-btn" onclick="addDigit('2')">2</button>
                    <button type="button" class="number-btn" onclick="addDigit('3')">3</button>
                    <button type="button" class="number-btn" onclick="addDigit('4')">4</button>
                    <button type="button" class="number-btn" onclick="addDigit('5')">5</button>
                    <button type="button" class="number-btn" onclick="addDigit('6')">6</button>
                    <button type="button" class="number-btn" onclick="addDigit('7')">7</button>
                    <button type="button" class="number-btn" onclick="addDigit('8')">8</button>
                    <button type="button" class="number-btn" onclick="addDigit('9')">9</button>
                    <button type="button" class="number-btn" onclick="addDigit('0')" style="grid-column: 2;">0</button>
                    <button type="button" class="number-btn delete-btn" onclick="deleteDigit()" style="grid-column: 3;">
                        ⌫
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Touchscreen-Erkennung
        function isTouchDevice() {
            return (('ontouchstart' in window) ||
                    (navigator.maxTouchPoints > 0) ||
                    (navigator.msMaxTouchPoints > 0));
        }
        
        // Nummernfeld anzeigen wenn Touchscreen erkannt
        if (isTouchDevice()) {
            document.getElementById('touchPad').style.display = 'block';
            // Eingabefeld auf readonly setzen für Touch-Geräte
            document.getElementById('pinInput').readOnly = true;
            document.getElementById('pinInput').style.cursor = 'default';
        } else {
            // Fokus auf PIN-Eingabefeld für Desktop
            document.getElementById('pinInput').focus();
        }
        
        // Ziffer hinzufügen
        function addDigit(digit) {
            const input = document.getElementById('pinInput');
            if (input.value.length < 10) {
                input.value += digit;
            }
        }
        
        // Letzte Ziffer löschen
        function deleteDigit() {
            const input = document.getElementById('pinInput');
            input.value = input.value.slice(0, -1);
        }
        
        // Enter-Taste zum Absenden
        document.getElementById('pinInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                this.form.submit();
            }
        });
    </script>
</body>
</html>

