<?php
session_start();
require_once __DIR__ . '/includes/email_config.php';
require_once __DIR__ . '/includes/config.php'; // Get version

$message = '';
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['recover'])) {
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        $error = 'Please enter your email address';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } else {
        // Load users
        $pins = json_decode(file_get_contents(__DIR__ . '/../data/pin.json'), true);
        $found = false;

        foreach ($pins as $user) {
            if (isset($user['email']) && strtolower($user['email']) === strtolower($email)) {
                $found = true;

                // Send recovery email
                $subject = 'PIN Recovery - Gate Codes Admin';
                $body = get_pin_recovery_template($user['name'], $user['pin']);

                if (send_email($email, $subject, $body)) {
                    $success = true;
                    $message = 'Recovery email sent successfully! Please check your inbox.';
                } else {
                    $error = 'Failed to send recovery email. Please make sure PHPMailer is installed (run: composer require phpmailer/phpmailer) and SMTP settings are configured in email_config.php.';
                }
                break;
            }
        }

        if (!$found) {
            // Security: Don't reveal if email exists or not
            $success = true;
            $message = 'If this email is registered, you will receive a PIN recovery email shortly.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot PIN - Gate Codes Admin</title>
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

        .recovery-container {
            background: linear-gradient(180deg, var(--panel), var(--panel-2));
            border: 1px solid var(--line);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 480px;
            overflow: hidden;
        }

        .recovery-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            padding: 40px 30px;
            text-align: center;
            color: white;
        }

        .recovery-header h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .recovery-header p {
            font-size: 14px;
            opacity: 0.95;
        }

        .recovery-icon {
            font-size: 48px;
            margin-bottom: 16px;
        }

        .recovery-body {
            padding: 40px 30px;
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

        input[type="email"] {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid var(--border);
            border-radius: 12px;
            font-size: 15px;
            background: linear-gradient(180deg, var(--input-bg-1), var(--input-bg-2));
            color: var(--text);
            transition: all 0.2s ease;
        }

        input[type="email"]:focus {
            outline: none;
            border-color: var(--brand);
            box-shadow: 0 0 0 3px rgba(59, 221, 130, 0.15);
        }

        .btn {
            width: 100%;
            padding: 14px 20px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 14px rgba(102, 126, 234, .4);
        }

        .btn:hover {
            background: linear-gradient(135deg, #5a6fd6, #6a3f94);
            box-shadow: 0 6px 18px rgba(102, 126, 234, .55);
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

        .back-login {
            text-align: center;
            margin-top: 20px;
        }

        .back-login a {
            color: var(--brand);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: opacity 0.2s ease;
        }

        .back-login a:hover {
            opacity: 0.8;
        }

        .info-box {
            background: rgba(102, 126, 234, 0.1);
            border-left: 4px solid #667eea;
            padding: 16px;
            border-radius: 6px;
            margin-top: 20px;
            font-size: 13px;
            color: #667eea;
            line-height: 1.5;
        }

        [data-theme="light"] .info-box {
            background: #e7f3ff;
            color: #004085;
            border-left-color: #0066cc;
        }

        .info-box strong {
            display: block;
            margin-bottom: 6px;
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
            .recovery-header {
                padding: 30px 20px;
            }

            .recovery-body {
                padding: 30px 20px;
            }

            .recovery-header h1 {
                font-size: 24px;
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

    <div class="recovery-container">
        <div class="recovery-header">
            <div class="recovery-icon">📧</div>
            <h1>Forgot PIN?</h1>
            <p>Enter your email to recover your PIN</p>
        </div>

        <div class="recovery-body">
            <?php if ($error): ?>
                <div class="error-message">
                    <strong>⚠️ Error:</strong> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="success-message">
                    <strong>✓ Success!</strong> <?= htmlspecialchars($message) ?>
                </div>
                <div class="info-box">
                    <strong>📬 Check your email</strong>
                    Please check your inbox and spam folder. The recovery email should arrive within a few minutes.
                </div>
                <div class="back-login">
                    <a href="login.php">
                        <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd"/>
                        </svg>
                        Back to Login
                    </a>
                </div>
            <?php else: ?>
                <form method="POST" action="forgot-pin.php">
                    <input type="hidden" name="recover" value="1">

                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            placeholder="your.email@example.com"
                            required
                            autofocus
                        >
                    </div>

                    <button type="submit" class="btn">
                        Send Recovery Email
                    </button>
                </form>

                <div class="info-box">
                    <strong>ℹ️ How it works</strong>
                    Enter the email address associated with your account. If the email is registered, you'll receive a recovery email with your PIN.
                </div>

                <div class="back-login">
                    <a href="login.php">
                        <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd"/>
                        </svg>
                        Back to Login
                    </a>
                </div>
            <?php endif; ?>
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
