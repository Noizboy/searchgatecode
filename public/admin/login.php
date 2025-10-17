<?php
session_start();

// If already logged in as admin or supervisor, redirect to dashboard
if (isset($_SESSION['user_pin']) && isset($_SESSION['user_role'])) {
    $pins = json_decode(file_get_contents(__DIR__ . '/../data/pin.json'), true);
    foreach ($pins as $user) {
        if ($user['pin'] === $_SESSION['user_pin'] && isset($user['role']) && in_array($user['role'], ['admin', 'supervisor'])) {
            header('Location: index.php');
            exit;
        }
    }
}

// Handle login
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $pin = trim($_POST['pin'] ?? '');

    if (empty($pin)) {
        $error = 'Please enter your PIN';
    } else {
        $pins = json_decode(file_get_contents(__DIR__ . '/../data/pin.json'), true);
        $found = false;

        foreach ($pins as $user) {
            if ($user['pin'] === $pin) {
                if (isset($user['role']) && in_array($user['role'], ['admin', 'supervisor'])) {
                    // Valid admin or supervisor PIN
                    $_SESSION['user_pin'] = $user['pin'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['user_email'] = $user['email'] ?? '';
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['admin_authenticated'] = true;

                    header('Location: index.php');
                    exit;
                } else {
                    $error = 'Access denied. Dashboard privileges required.';
                    $found = true;
                    break;
                }
            }
        }

        if (!$found && empty($error)) {
            $error = 'Invalid PIN. Please try again.';
        }
    }
}

// Get error/message from URL
$success_msg = '';
if (isset($_GET['error'])) {
    $error = $_GET['error'];
}
if (isset($_GET['msg'])) {
    $success_msg = $_GET['msg'];
}

// Get version from config
require_once __DIR__ . '/includes/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Gate Codes</title>
    <script>
    // Apply theme immediately before any rendering to prevent flash
    (function() {
      try {
        let theme = localStorage.getItem('theme');
        if (!theme) {
          theme = 'dark';
          localStorage.setItem('theme', 'dark');
        }
        document.documentElement.setAttribute('data-theme', theme);
      } catch (e) {
        console.error('Error loading theme:', e);
        document.documentElement.setAttribute('data-theme', 'dark');
      }
    })();
    </script>
    <style>
        /* Prevent flash of unstyled content */
        html:not([data-theme]) {
            visibility: hidden;
        }

        html[data-theme] {
            visibility: visible;
        }

        :root{
            --bg:#0b0d10; --panel:#151a20; --panel-2:#0f1318;
            --text:#e8eef4; --muted:#93a0ad; --brand:#3bdd82; --brand-2:#1bbf67;
            --danger:#ff5c5c; --danger-2:#e53935; --radius:14px;
            --gradient-1:#1a2330; --gradient-2:#11202a;
            --border:#2a3340; --border-2:#1e2a34; --line:#22303b;
            --input-bg-1:#0f141a; --input-bg-2:#0c1116;
            --footer-bg:rgba(15,19,24,0.5);
        }

        [data-theme="light"]{
            --bg:#f5f7fa; --panel:#ffffff; --panel-2:#f8f9fa;
            --text:#1a1f26; --muted:#5a6c7d; --brand:#3bdd82; --brand-2:#1bbf67;
            --danger:#ff5c5c; --danger-2:#e53935; --radius:14px;
            --gradient-1:#e0f5ee; --gradient-2:#d4ede2;
            --border:#d1dce5; --border-2:#e1e8ed; --line:#d1dce5;
            --input-bg-1:#ffffff; --input-bg-2:#f9fafb;
            --footer-bg:rgba(255,255,255,0.5);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: system-ui, Segoe UI, Roboto, Arial;
            color: var(--text);
            background: var(--bg);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            padding-bottom: 80px;
            position: relative;
        }

        body::before {
            content: "";
            position: fixed;
            inset: 0;
            z-index: -1;
            background:
                radial-gradient(1000px 500px at 80% -10%, var(--gradient-1) 0%, transparent 60%),
                radial-gradient(900px 400px at -10% 90%, var(--gradient-2) 0%, transparent 55%),
                var(--bg);
            background-repeat: no-repeat;
            transition: background 0.3s ease;
        }

        .login-container {
            background: linear-gradient(180deg, var(--panel), var(--panel-2));
            border: 1px solid var(--line);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 440px;
            overflow: hidden;
        }

        .login-body {
            padding: 50px 40px;
        }

        .login-title {
            font-size: 32px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 30px;
            text-align: center;
        }

        .form-group {
            margin-bottom: 24px;
        }

        label {
            display: block;
            font-weight: 600;
            margin-bottom: 10px;
            font-size: 14px;
            color: var(--text);
        }

        .pin-input-wrapper {
            position: relative;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid var(--border);
            border-radius: 12px;
            font-size: 16px;
            font-family: 'Courier New', monospace;
            letter-spacing: 4px;
            text-align: center;
            background: linear-gradient(180deg, var(--input-bg-1), var(--input-bg-2));
            color: var(--text);
            transition: all 0.2s ease;
        }

        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: var(--brand);
            box-shadow: 0 0 0 3px rgba(59, 221, 130, 0.15);
        }

        .btn {
            width: 100%;
            padding: 14px 20px;
            background: linear-gradient(135deg, #2FD874, #12B767);
            color: #fff;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 14px rgba(59, 221, 130, .4);
        }

        .btn:hover {
            background: linear-gradient(135deg, #12B767, #0e9a52);
            box-shadow: 0 6px 18px rgba(59, 221, 130, .55);
            transform: translateY(-1px);
        }

        .btn:active {
            transform: translateY(0);
        }

        .error-message {
            background: #fff3cd;
            border-left: 4px solid var(--danger);
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            color: #856404;
            font-size: 14px;
        }

        [data-theme="dark"] .error-message {
            background: rgba(255, 193, 7, 0.1);
            color: #ffca28;
        }

        .success-message {
            background: #d4edda;
            border-left: 4px solid #28a745;
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            color: #155724;
            font-size: 14px;
        }

        [data-theme="dark"] .success-message {
            background: rgba(59, 221, 130, 0.1);
            color: #3bdd82;
        }

        .forgot-pin {
            text-align: center;
            margin-top: 20px;
        }

        .forgot-pin a {
            color: var(--brand);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: opacity 0.2s ease;
        }

        .forgot-pin a:hover {
            opacity: 0.8;
        }


        .theme-toggle {
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--input-bg-1);
            border: 1px solid var(--border);
            border-radius: 12px;
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            z-index: 1000;
        }

        .theme-toggle:hover {
            transform: scale(1.1);
            background: var(--panel-2);
        }

        .theme-toggle svg {
            width: 20px;
            height: 20px;
            fill: var(--brand);
            transition: transform 0.3s ease;
        }

        .theme-toggle:hover svg {
            transform: rotate(20deg);
        }

        footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 12px 32px;
            background: var(--footer-bg);
            backdrop-filter: blur(10px);
            border-top: 1px solid var(--line);
            text-align: center;
            font-size: 0.85rem;
            color: var(--muted);
            z-index: 100;
        }

        footer a {
            color: var(--brand);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s ease;
        }

        footer a:hover {
            opacity: 0.8;
        }

        .version {
            font-family: 'Courier New', monospace;
            color: var(--brand);
            font-weight: 600;
        }

        @media (max-width: 480px) {
            .login-body {
                padding: 40px 25px;
            }

            .login-title {
                font-size: 26px;
            }

            .theme-toggle {
                top: 10px;
                right: 10px;
                width: 40px;
                height: 40px;
            }

            footer {
                font-size: 0.75rem;
                padding: 10px 16px;
            }
        }
    </style>
</head>
<body>
    <!-- Theme Toggle -->
    <button class="theme-toggle" id="themeToggle" aria-label="Toggle theme">
        <svg id="moonIcon" viewBox="0 0 20 20">
            <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"/>
        </svg>
        <svg id="sunIcon" viewBox="0 0 20 20" style="display: none;">
            <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd"/>
        </svg>
    </button>

    <div class="login-container">
        <div class="login-body">
            <h1 class="login-title">Admin Login</h1>

            <?php if ($success_msg): ?>
                <div class="success-message">
                    <strong>✓ Success:</strong> <?= htmlspecialchars($success_msg) ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="error-message">
                    <strong>⚠️ Error:</strong> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <input type="hidden" name="login" value="1">

                <div class="form-group">
                    <label for="pin">PIN Code</label>
                    <div class="pin-input-wrapper">
                        <input
                            type="password"
                            id="pin"
                            name="pin"
                            placeholder="• • • • •"
                            maxlength="5"
                            pattern="[0-9]{5}"
                            required
                            autofocus
                        >
                    </div>
                </div>

                <button type="submit" class="btn">
                    Login to Dashboard
                </button>
            </form>

            <div class="forgot-pin">
                <a href="forgot-pin.php">Forgot your PIN?</a>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer>
        © <span id="currentYear"></span> Built by <a href="mailto:blancuniverse@gmail.com">Alejandro</a>
        <span style="margin: 0 8px; opacity: 0.5;">•</span>
        <span class="version">Build v<?= APP_VERSION ?></span>
    </footer>

    <script>
        // Set current year
        document.getElementById('currentYear').textContent = new Date().getFullYear();

        // Auto-format PIN input
        const pinInput = document.getElementById('pin');
        pinInput.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '').slice(0, 5);
        });

        // Theme toggle functionality
        const htmlElement = document.documentElement;
        const themeToggle = document.getElementById('themeToggle');
        const moonIcon = document.getElementById('moonIcon');
        const sunIcon = document.getElementById('sunIcon');

        function updateThemeIcon(theme) {
            if (theme === 'light') {
                moonIcon.style.display = 'block';
                sunIcon.style.display = 'none';
            } else {
                moonIcon.style.display = 'none';
                sunIcon.style.display = 'block';
            }
        }

        // Set initial icon state
        const currentTheme = htmlElement.getAttribute('data-theme') || 'dark';
        updateThemeIcon(currentTheme);

        // Toggle theme on click
        themeToggle.addEventListener('click', () => {
            const currentTheme = htmlElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';

            htmlElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateThemeIcon(newTheme);
        });
    </script>
</body>
</html>
