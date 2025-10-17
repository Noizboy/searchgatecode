<?php
// Include config to get version
require_once __DIR__ . '/admin/includes/config.php';

// Start session for user authentication (if not already started)
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// LOAD SETTINGS to check if PIN is required
$settingsFile = __DIR__ . '/data/settings.json';
$requirePin = true; // Default to requiring PIN
$siteTitle = 'Gate Code'; // Default site title

if (file_exists($settingsFile)) {
  $settingsData = json_decode(file_get_contents($settingsFile), true);
  if (is_array($settingsData)) {
    if (isset($settingsData['require_pin'])) {
      $requirePin = $settingsData['require_pin'];
    }
    if (isset($settingsData['site_title'])) {
      $siteTitle = $settingsData['site_title'];
    }
  }
}

// Check for PIN in URL parameter (from index.php link)
if (isset($_GET['key'])) {
  $pinFromUrl = trim($_GET['key']);

  // Load pins from data/pin.json
  $pinsFile = __DIR__ . '/data/pin.json';

  if (file_exists($pinsFile)) {
    $pinsData = json_decode(file_get_contents($pinsFile), true);

    if (is_array($pinsData)) {
      // Find user with matching PIN
      foreach ($pinsData as $user) {
        if (isset($user['pin']) && $user['pin'] === $pinFromUrl) {
          // Authenticate user
          $_SESSION['user_authenticated'] = true;
          $_SESSION['user_name'] = $user['name'] ?? 'User';
          $_SESSION['user_pin'] = $user['pin'];
          $_SESSION['user_id'] = $user['id'] ?? null;
          break;
        }
      }
    }
  }

  // Redirect to clean URL
  header('Location: submit.php');
  exit;
}

// Check if user is logged in OR if PIN is not required
$isLoggedIn = !$requirePin || (isset($_SESSION['user_authenticated']) && $_SESSION['user_authenticated'] === true);
$userName = (isset($_SESSION['user_authenticated']) && $_SESSION['user_authenticated'] === true) ? ($_SESSION['user_name'] ?? 'User') : ($requirePin ? null : 'Guest');

// Set Florida timezone
date_default_timezone_set('America/New_York');

// PROCESS FORM SUBMISSION
$flashMsg = '';
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isLoggedIn) {
  $suggestFile = __DIR__ . '/data/suggest.json';

  // Read existing suggestions
  $suggestions = [];
  if (file_exists($suggestFile)) {
    $content = file_get_contents($suggestFile);
    $suggestions = json_decode($content, true) ?: [];
  }

  // Get form data
  $community = trim($_POST['community'] ?? '');
  $city = trim($_POST['city'] ?? '');

  // Validate required fields
  if ($community === '') {
    $errorMsg = 'Community name is required';
  } else {
    // Build codes array
    $codes = [];
    $codeInputs = $_POST['code'] ?? [];
    $notesInputs = $_POST['notes'] ?? [];
    $photoInputs = $_POST['photo'] ?? [];
    $coordinatesInputs = $_POST['coordinates'] ?? [];

    foreach ($codeInputs as $idx => $codeVal) {
      $codeVal = trim($codeVal);
      if ($codeVal !== '') {
        $codeData = [
          'code' => $codeVal,
          'notes' => trim($notesInputs[$idx] ?? ''),
          'photo' => trim($photoInputs[$idx] ?? '')
        ];

        // Add coordinates if available
        $coordsJson = trim($coordinatesInputs[$idx] ?? '');
        if ($coordsJson !== '') {
          $coords = json_decode($coordsJson, true);
          if ($coords && isset($coords['latitude']) && isset($coords['longitude'])) {
            $codeData['coordinates'] = $coords;
          }
        }

        $codes[] = $codeData;
      }
    }

    if (empty($codes)) {
      $errorMsg = 'At least one code is required';
    } else {
      // Create suggestion entry
      $suggestion = [
        'community' => $community,
        'codes' => $codes,
        'submitted_date' => date('Y-m-d H:i:s'),
        'submitted_by' => $userName
      ];

      if ($city !== '') {
        $suggestion['city'] = $city;
      }

      // Add to suggestions array
      $suggestions[] = $suggestion;

      // Save to file
      $dir = dirname($suggestFile);
      if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
      }

      if (file_put_contents($suggestFile, json_encode($suggestions, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))) {
        $flashMsg = 'Your community submission has been received! Thank you for contributing.';
      } else {
        $errorMsg = 'Failed to save submission. Please try again.';
      }
    }
  }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Submit Community · <?= htmlspecialchars($siteTitle) ?></title>
<script>
// Apply theme immediately before any rendering to prevent flash
(function() {
  try {
    let theme = localStorage.getItem('theme');
    // If no theme is saved, default to 'dark' and save it
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
  :root{
    --bg:#0b0d10; --panel:#151a20; --panel-2:#0f1318;
    --text:#e8eef4; --muted:#93a0ad; --brand:#3bdd82; --brand-2:#1bbf67;
    --danger:#ff5c5c; --danger-2:#e53935; --radius:14px;
    --gradient-1:#1a2330; --gradient-2:#11202a;
    --border:#2a3340; --border-2:#1e2a34;
    --input-bg-1:#0f141a; --input-bg-2:#0c1116;
    --scrollbar-track:#0f141a; --scrollbar-thumb:#2a3340; --scrollbar-thumb-hover:#364456;
    --modal-bg-1:#1a1f26; --modal-bg-2:#12161c; --modal-border:#233041;
    --btn-secondary-bg:#22272f; --btn-secondary-text:#d0d7de; --btn-secondary-border:#2e3947;
    --btn-secondary-hover:#2a3240;
    --footer-bg:rgba(15,19,24,0.5);
  }

  [data-theme="light"]{
    --bg:#f5f7fa; --panel:#ffffff; --panel-2:#f8f9fa;
    --text:#1a1f26; --muted:#5a6c7d; --brand:#3bdd82; --brand-2:#1bbf67;
    --danger:#ff5c5c; --danger-2:#e53935; --radius:14px;
    --gradient-1:#e0f5ee; --gradient-2:#d4ede2;
    --border:#d1dce5; --border-2:#e1e8ed;
    --input-bg-1:#ffffff; --input-bg-2:#f9fafb;
    --scrollbar-track:#e8eef4; --scrollbar-thumb:#c1ccd7; --scrollbar-thumb-hover:#a8b5c2;
    --modal-bg-1:#ffffff; --modal-bg-2:#f8f9fa; --modal-border:#d1dce5;
    --btn-secondary-bg:#f0f3f6; --btn-secondary-text:#2c3845; --btn-secondary-border:#d1dce5;
    --btn-secondary-hover:#e4e9ed;
    --footer-bg:rgba(255,255,255,0.5);
  }

  html,body{
    height:100%; margin:0; padding:0; font-family:system-ui,Segoe UI,Roboto,Arial; color:var(--text);
    background:
      radial-gradient(1000px 500px at 80% -10%, var(--gradient-1) 0%, transparent 60%),
      radial-gradient(900px 400px at -10% 90%, var(--gradient-2) 0%, transparent 55%),
      var(--bg);
    background-attachment: fixed;
    background-repeat: no-repeat;
    transition: background 0.3s ease, color 0.3s ease;
  }
  body{display:flex;flex-direction:column;min-height:100vh}

  main{
    flex:1;
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:flex-start;
    padding:40px 20px 60px 20px;
    text-align:center;
  }

  h1{margin:0 0 10px 0;font-size:2rem}
  .sub{color:var(--muted);margin-bottom:30px}

  /* Title style */
  .title {
    font-size: 3rem; font-weight: 800; text-transform: uppercase;
    letter-spacing: 2px; margin: 0 0 15px 0;
    background: linear-gradient(90deg, #3bdd82, #1bbf67);
    -webkit-background-clip: text; -webkit-text-fill-color: transparent;
    background-clip: text;
    position: relative; display: inline-block; text-shadow: 0 2px 6px rgba(0,0,0,.3);
    text-decoration: none;
    cursor: pointer;
    transition: opacity .2s ease;
  }
  .title:hover {
    opacity: 0.85;
  }

  /* Form Container */
  .form-container {
    width: 100%;
    max-width: 700px;
    background: linear-gradient(180deg, var(--panel), var(--panel-2));
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 32px;
    margin-bottom: 24px;
    text-align: left;
  }

  .form-title {
    font-size: 1.5rem;
    font-weight: 700;
    margin: 0 0 24px 0;
    color: var(--text);
  }

  .form-row {
    display: grid;
    gap: 16px;
    margin-bottom: 20px;
  }

  .form-group {
   /*margin-bottom: 20px;*/
  }

  .form-label {
    display: block;
    margin-bottom: 8px;
    color: var(--text);
    font-weight: 600;
    font-size: 0.95rem;
  }

  .field {
    width: 100%;
    padding: 12px 14px;
    border-radius: 12px;
    border: 1px solid var(--border);
    background: linear-gradient(180deg, var(--input-bg-1), var(--input-bg-2));
    color: var(--text);
    outline: none;
    transition: border-color .15s ease, box-shadow .15s ease;
    font-size: 15px;
    box-sizing: border-box;
  }

  .field::placeholder {
    color: var(--muted);
  }

  .field:focus {
    border-color: var(--brand);
    box-shadow: 0 0 0 3px rgba(59, 221, 130, .15);
  }

  textarea.field {
    resize: vertical;
    min-height: 90px;
  }

  /* Codes Section */
  .codes-section {
    margin-top: 32px;
    padding-top: 24px;
    border-top: 1px solid var(--border);
  }

  .codes-list {
    display: flex;
    flex-direction: column;
    gap: 20px;
    margin-bottom: 20px;
    max-height: 500px;
    overflow-y: auto;
    overflow-x: hidden;
    scroll-snap-type: y mandatory;
    padding-right: 8px;
  }

  .code-item {
    background: var(--panel-2);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 20px;
    scroll-snap-align: start;
    scroll-snap-stop: always;
  }

  .code-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
    gap: 12px;
  }

  .code-header .btn-danger {
    flex-shrink: 0;
  }

  .code-number {
    font-weight: 700;
    color: var(--text);
    font-size: 1.1rem;
  }

  .code-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-bottom: 12px;
  }

  .code-full {
    grid-column: 1 / -1;
  }

  .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 12px 20px;
    border-radius: 12px;
    border: 1px solid var(--border);
    background: linear-gradient(180deg, var(--input-bg-1), var(--input-bg-2));
    color: var(--text);
    font-size: 15px;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.2s ease;
    gap: 8px;
  }

  .btn:hover {
    background: var(--panel-2);
    transform: translateY(-1px);
  }

  .btn-primary {
    background: linear-gradient(135deg, #2FD874, #12B767);
    border: 0;
    color: #fff;
    box-shadow: 0 4px 14px rgba(59, 221, 130, .4);
  }

  .btn-primary:hover {
    background: linear-gradient(135deg, #12B767, #0e9a52);
    box-shadow: 0 6px 18px rgba(59, 221, 130, .55);
  }

  .btn-danger {
    background: linear-gradient(135deg, #FF5A5F, #E23D3D);
    border: 0;
    color: #fff;
    box-shadow: 0 4px 14px rgba(255, 92, 92, .4);
  }

  .btn-danger:hover {
    background: linear-gradient(135deg, #E23D3D, #c73030);
    box-shadow: 0 6px 18px rgba(255, 92, 92, .55);
  }

  .btn-group {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
  }

  .form-actions {
    margin-top: 32px;
    padding-top: 24px;
    border-top: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
  }

  /* Custom Scrollbar */
  ::-webkit-scrollbar {
    width: 8px;
    height: 8px;
  }
  ::-webkit-scrollbar-track {
    background: var(--scrollbar-track);
    border-radius: 10px;
  }
  ::-webkit-scrollbar-thumb {
    background: var(--scrollbar-thumb);
    border-radius: 10px;
  }
  ::-webkit-scrollbar-thumb:hover {
    background: var(--scrollbar-thumb-hover);
  }

  /* Footer */
  footer{
    padding:16px;
    text-align:center;
    font-size:13px;
    color:var(--muted);
    border-top:1px solid var(--border-2);
    background:var(--footer-bg);
  }
  footer a{
    color:var(--brand);
    text-decoration:none;
    font-weight:600;
    transition:color .15s ease;
  }
  footer a:hover{
    color:var(--brand-2);
    text-decoration:underline;
  }

  /* Theme Toggle Button */
  .theme-toggle {
    position: absolute;
    top: 20px;
    right: 20px;
    background: var(--panel);
    border: 1px solid var(--border);
    border-radius: 50%;
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 12px rgba(0,0,0,.2);
    z-index: 100;
  }
  .theme-toggle:hover {
    transform: scale(1.1);
    box-shadow: 0 6px 16px rgba(59,221,130,.3);
  }
  .theme-toggle svg {
    width: 24px;
    height: 24px;
    fill: var(--brand);
    transition: transform 0.3s ease;
  }
  .theme-toggle:hover svg {
    transform: rotate(20deg);
  }

  main {
    position: relative;
  }

  /* Modal Backdrop */
  .modal-backdrop {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(4px);
    z-index: 1000;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.2s ease;
  }

  .modal-backdrop.open {
    display: flex;
    opacity: 1;
  }

  /* Alert Modal */
  .alert-modal {
    background: linear-gradient(180deg, var(--modal-bg-1), var(--modal-bg-2));
    border: 1px solid var(--modal-border);
    border-radius: 12px;
    padding: 24px;
    width: min(90vw, 400px);
    text-align: center;
    box-shadow: 0 20px 60px rgba(0,0,0,.5);
  }

  .alert-modal .alert-icon {
    width: 64px;
    height: 64px;
    margin: 0 auto 16px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .alert-modal .alert-icon.success {
    background: linear-gradient(135deg, rgba(59, 221, 130, 0.2), rgba(27, 191, 103, 0.15));
  }

  .alert-modal .alert-icon.error {
    background: linear-gradient(135deg, rgba(255, 92, 92, 0.2), rgba(229, 57, 53, 0.15));
  }

  .alert-modal .alert-icon.warning {
    background: linear-gradient(135deg, rgba(255, 152, 0, 0.2), rgba(245, 124, 0, 0.15));
  }

  .alert-modal .alert-icon svg {
    width: 32px;
    height: 32px;
  }

  .alert-modal .alert-title {
    font-size: 1.25rem;
    font-weight: 700;
    margin-bottom: 8px;
    color: var(--text);
  }

  .alert-modal .alert-message {
    font-size: 0.95rem;
    color: var(--muted);
    margin-bottom: 24px;
    line-height: 1.5;
  }

  .alert-modal .alert-actions {
    display: flex;
    gap: 12px;
    justify-content: center;
  }

  .alert-modal .btn-alert {
    padding: 12px 24px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 15px;
    cursor: pointer;
    transition: all 0.2s ease;
    border: 0;
  }

  .alert-modal .btn-alert-primary {
    background: linear-gradient(135deg, var(--brand), var(--brand-2));
    color: #07140c;
    box-shadow: 0 4px 14px rgba(59, 221, 130, .4);
  }

  .alert-modal .btn-alert-primary:hover {
    box-shadow: 0 6px 18px rgba(59, 221, 130, .55);
    transform: translateY(-1px);
  }

  .alert-modal .btn-alert-danger {
    background: linear-gradient(135deg, var(--danger), var(--danger-2));
    color: #fff;
    box-shadow: 0 4px 14px rgba(255, 92, 92, .4);
  }

  .alert-modal .btn-alert-danger:hover {
    box-shadow: 0 6px 18px rgba(255, 92, 92, .55);
    transform: translateY(-1px);
  }

  .alert-modal .btn-alert-secondary {
    background: var(--btn-secondary-bg);
    color: var(--btn-secondary-text);
    border: 1px solid var(--btn-secondary-border);
  }

  .alert-modal .btn-alert-secondary:hover {
    background: var(--btn-secondary-hover);
  }

  /* Alert */
  .alert {
    width: 100%;
    max-width: 700px;
    padding: 16px 20px;
    border-radius: 12px;
    margin-bottom: 24px;
    font-weight: 600;
  }

  .alert-success {
    background: linear-gradient(135deg, rgba(59, 221, 130, 0.2), rgba(27, 191, 103, 0.15));
    border: 1px solid var(--brand);
    color: var(--brand);
  }

  .alert-error {
    background: linear-gradient(135deg, rgba(255, 92, 92, 0.2), rgba(229, 57, 53, 0.15));
    border: 1px solid var(--danger);
    color: var(--danger);
  }

  /* Login Page Styles */
  .login-container {
    width: 100%;
    max-width: 450px;
    background: linear-gradient(180deg, var(--panel), var(--panel-2));
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 40px 32px;
    margin-bottom: 24px;
    text-align: center;
  }

  .login-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 24px;
    border-radius: 50%;
    background: linear-gradient(135deg, rgba(59, 221, 130, 0.2), rgba(27, 191, 103, 0.15));
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .login-icon svg {
    width: 40px;
    height: 40px;
    stroke: var(--brand);
  }

  .login-title {
    font-size: 1.75rem;
    font-weight: 700;
    margin: 0 0 12px 0;
    color: var(--text);
  }

  .login-subtitle {
    color: var(--muted);
    margin-bottom: 32px;
    font-size: 0.95rem;
    line-height: 1.5;
  }

  .login-form-group {
    margin-bottom: 24px;
    text-align: left;
  }

  .login-form-label {
    display: block;
    margin-bottom: 8px;
    color: var(--text);
    font-weight: 600;
    font-size: 0.95rem;
  }

  .login-form-input {
    width: 100%;
    padding: 14px 16px;
    border-radius: 12px;
    border: 1px solid var(--border);
    background: linear-gradient(180deg, var(--input-bg-1), var(--input-bg-2));
    color: var(--text);
    outline: none;
    transition: border-color .15s ease, box-shadow .15s ease;
    font-size: 16px;
    box-sizing: border-box;
    font-family: monospace;
    letter-spacing: 3px;
    font-weight: 600;
    text-align: center;
  }

  .login-form-input::placeholder {
    color: var(--muted);
    letter-spacing: normal;
    font-family: system-ui,Segoe UI,Roboto,Arial;
    font-weight: 400;
  }

  .login-form-input:focus {
    border-color: var(--brand);
    box-shadow: 0 0 0 3px rgba(59, 221, 130, .15);
  }

  .login-form-input.error {
    border-color: var(--danger);
    animation: shake 0.3s ease;
  }

  @keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-10px); }
    75% { transform: translateX(10px); }
  }

  .login-error-message {
    color: var(--danger);
    font-size: 0.85rem;
    margin-top: 8px;
    display: none;
    text-align: center;
  }

  .login-error-message.show {
    display: block;
  }

  .login-btn {
    width: 100%;
    padding: 14px 20px;
    border-radius: 12px;
    background: linear-gradient(135deg, #2FD874, #12B767);
    border: 0;
    color: #fff;
    font-size: 16px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s ease;
    box-shadow: 0 4px 14px rgba(59, 221, 130, .4);
  }

  .login-btn:hover {
    background: linear-gradient(135deg, #12B767, #0e9a52);
    box-shadow: 0 6px 18px rgba(59, 221, 130, .55);
    transform: translateY(-1px);
  }

  .login-btn:active {
    transform: translateY(0);
  }

  .login-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none !important;
  }

  .login-footer {
    margin-top: 24px;
    padding-top: 24px;
    border-top: 1px solid var(--border);
    color: var(--muted);
    font-size: 0.9rem;
  }

  .login-footer a {
    color: var(--brand);
    text-decoration: none;
    font-weight: 600;
    transition: color .15s ease;
  }

  .login-footer a:hover {
    color: var(--brand-2);
    text-decoration: underline;
  }

  .file-upload-wrapper {
    position: relative;
  }

  .file-upload-label {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 20px;
    border-radius: 12px;
    border: 1px solid var(--border);
    background: linear-gradient(180deg, var(--input-bg-1), var(--input-bg-2));
    color: var(--text);
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
  }

  .file-upload-label:hover {
    background: var(--panel-2);
    transform: translateY(-1px);
  }

  .file-upload-input {
    position: absolute;
    width: 0.1px;
    height: 0.1px;
    opacity: 0;
    overflow: hidden;
    z-index: -1;
  }

  .file-name {
    color: var(--muted);
    font-size: 0.9rem;
    margin-top: 8px;
  }

  @media (max-width: 768px) {
    main {
      padding: 24px 40px 40px 40px;
    }

    .title {
      font-size: 2.2rem;
      letter-spacing: 1px;
    }

    .sub {
      font-size: 0.9rem;
      margin-bottom: 20px;
    }

    .form-container {
      padding: 20px 16px;
      border-radius: 12px;
      margin: 0 8px;
    }

    .form-title {
      font-size: 1.25rem;
      margin-bottom: 16px;
    }

    .form-row {
      grid-template-columns: 1fr 1fr;
      gap: 12px;
    }

    .code-row {
      grid-template-columns: 1fr;
      gap: 0;
    }

    .codes-section {
      margin-top: 24px;
      padding-top: 20px;
    }

    .codes-list {
      gap: 16px;
      max-height: 400px;
    }

    .code-item {
      padding: 16px;
    }

    .code-header {
      flex-direction: row;
      align-items: center;
      margin-bottom: 12px;
    }

    .code-number {
      font-size: 1rem;
    }

    .code-header .btn-danger {
      width: auto;
      padding: 8px 14px;
      white-space: nowrap;
      font-size: 0.85rem;
    }

    .form-actions {
      flex-direction: column;
      align-items: stretch;
      gap: 12px;
      margin-top: 24px;
      padding-top: 20px;
    }

    .btn-group {
      width: 100%;
    }

    .btn {
      width: 100%;
      padding: 14px 20px;
      font-size: 15px;
    }

    .alert {
      padding: 14px 16px;
      font-size: 0.9rem;
    }

    .file-upload-label {
      padding: 12px 16px;
      font-size: 14px;
    }

    .file-upload-label svg {
      width: 18px;
      height: 18px;
    }

    .file-preview img {
      max-width: 100% !important;
      max-height: 180px !important;
    }

    .theme-toggle {
      width: 44px;
      height: 44px;
      top: 15px;
      right: 15px;
    }

    .theme-toggle svg {
      width: 20px;
      height: 20px;
    }

    footer {
      padding: 16px 12px;
      font-size: 12px;
    }
  }

  @media (max-width: 480px) {
    .title {
      font-size: 1.8rem;
    }

    .form-container {
      padding: 16px 12px;
      margin: 0 6px;
    }

    .code-item {
      padding: 12px;
    }

    .btn {
      padding: 12px 16px;
      font-size: 14px;
    }

    .code-header .btn-danger {
      padding: 6px 12px;
      font-size: 0.8rem;
    }
  }
</style>
</head>
<body>
  <!-- Theme Toggle Button -->
  <button id="themeToggle" class="theme-toggle" aria-label="Toggle theme">
    <svg id="moonIcon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
      <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
    </svg>
    <svg id="sunIcon" style="display:none;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
      <circle cx="12" cy="12" r="5"/>
      <path d="M12 1L13 5L11 5Z"/>
      <path d="M12 23L13 19L11 19Z"/>
      <path d="M23 12L19 13L19 11Z"/>
      <path d="M1 12L5 13L5 11Z"/>
      <path d="M19.07 4.93L16 7.5L15 6.5Z"/>
      <path d="M4.93 19.07L8 16.5L9 17.5Z"/>
      <path d="M19.07 19.07L16.5 16L17.5 15Z"/>
      <path d="M4.93 4.93L7.5 8L6.5 9Z"/>
    </svg>
  </button>

  <main>
    <a href="index.php" class="title"><?= htmlspecialchars($siteTitle) ?></a>

    <?php if (!$isLoggedIn): ?>
      <!-- LOGIN PAGE -->
      <div class="sub">Enter your PIN to contribute</div>

      <div class="login-container">
        <div class="login-icon">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
          </svg>
        </div>

        <h2 class="login-title">PIN Authentication</h2>
        <p class="login-subtitle">Please enter your PIN to submit community gate codes</p>

        <form id="loginForm" method="POST" action="auth_pin.php">
          <div class="login-form-group">
            <label class="login-form-label" for="loginPin">Your PIN:</label>
            <input
              type="text"
              id="loginPin"
              name="pin"
              class="login-form-input"
              placeholder="Enter PIN"
              maxlength="6"
              autocomplete="off"
              required
              autofocus
            />
            <div id="loginErrorMessage" class="login-error-message"></div>
          </div>

          <button type="submit" class="login-btn" id="loginBtn">
            <span id="loginBtnText">Verify PIN</span>
          </button>
        </form>

        <div class="login-footer">
          <a href="index.php">← Back to Search</a>
        </div>
      </div>
    <?php else: ?>
      <!-- SUBMIT FORM PAGE -->
      <div class="sub">Submit a new community gate code</div>

      <?php if ($errorMsg): ?>
        <div class="alert alert-error"><?= htmlspecialchars($errorMsg) ?></div>
      <?php endif; ?>

      <!-- Hidden flash message data for modal -->
      <?php if ($flashMsg): ?>
        <div id="flashMessage" data-message="<?= htmlspecialchars($flashMsg) ?>" style="display: none;"></div>
      <?php endif; ?>

      <form class="form-container" method="POST" id="submitForm">
      <h2 class="form-title">Community Information</h2>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Community Name *</label>
          <input type="text" class="field" name="community" placeholder="e.g., Water Oaks" required>
        </div>

        <div class="form-group">
          <label class="form-label">City Name *</label>
          <input type="text" class="field" name="city" placeholder="e.g., Orlando">
        </div>
      </div>

      <div class="codes-section">
        <h2 class="form-title">Gate Codes</h2>

        <div id="codesList" class="codes-list">
          <!-- Codes will be added here dynamically -->
        </div>

        <div class="form-actions">
          <button type="button" class="btn" id="addCodeBtn">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <line x1="12" y1="5" x2="12" y2="19"></line>
              <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            Add Code
          </button>
          <div class="btn-group">
            <a href="index.php" class="btn">Cancel</a>
            <button type="submit" class="btn btn-primary">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                <polyline points="22 4 12 14.01 9 11.01"></polyline>
              </svg>
              Submit Community
            </button>
          </div>
        </div>
      </div>
    </form>
    <?php endif; ?>
  </main>

  <footer>
    <span>© <?=date('Y')?> Built by <a href="mailto:blancuniverse@gmail.com" class="footer-by">Alejandro</a> | <a href="index.php">Back to Search</a></span>
    <span style="margin: 0 8px; opacity: 0.5;">•</span>
    <span style="font-family: 'Courier New', monospace; color: var(--brand); font-weight: 600;">Build v<?= APP_VERSION ?></span>
  </footer>

  <!-- Alert Modal -->
  <div id="alertBackdrop" class="modal-backdrop" aria-hidden="true">
    <div class="alert-modal" role="alertdialog" aria-modal="true">
      <div id="alertIcon" class="alert-icon"></div>
      <div id="alertTitle" class="alert-title"></div>
      <div id="alertMessage" class="alert-message"></div>
      <div id="alertActions" class="alert-actions"></div>
    </div>
  </div>

<script>
const IS_LOGGED_IN = <?= json_encode($isLoggedIn) ?>;

// Alert Modal Functions
const alertBackdrop = document.getElementById('alertBackdrop');
const alertIcon = document.getElementById('alertIcon');
const alertTitle = document.getElementById('alertTitle');
const alertMessage = document.getElementById('alertMessage');
const alertActions = document.getElementById('alertActions');

function showAlert({ type = 'warning', title, message, buttons = [] }) {
  // Set icon based on type
  const icons = {
    success: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#3bdd82" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
      <polyline points="22 4 12 14.01 9 11.01"></polyline>
    </svg>`,
    error: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#ff5c5c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <circle cx="12" cy="12" r="10"></circle>
      <line x1="15" y1="9" x2="9" y2="15"></line>
      <line x1="9" y1="9" x2="15" y2="15"></line>
    </svg>`,
    warning: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#ff9800" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
      <line x1="12" y1="9" x2="12" y2="13"></line>
      <line x1="12" y1="17" x2="12.01" y2="17"></line>
    </svg>`
  };

  alertIcon.innerHTML = icons[type] || icons.warning;
  alertIcon.className = `alert-icon ${type}`;
  alertTitle.textContent = title;
  alertMessage.textContent = message;

  // Clear and add buttons
  alertActions.innerHTML = '';
  buttons.forEach(btn => {
    const button = document.createElement('button');
    button.className = `btn-alert ${btn.className || 'btn-alert-secondary'}`;
    button.textContent = btn.text;
    button.onclick = () => {
      closeAlert();
      if (btn.onClick) btn.onClick();
    };
    alertActions.appendChild(button);
  });

  alertBackdrop.classList.add('open');
  alertBackdrop.setAttribute('aria-hidden', 'false');
}

function closeAlert() {
  alertBackdrop.classList.remove('open');
  alertBackdrop.setAttribute('aria-hidden', 'true');
}

alertBackdrop.addEventListener('click', (e) => {
  if (e.target === alertBackdrop) closeAlert();
});

window.addEventListener('keydown', (e) => {
  if (e.key === 'Escape' && alertBackdrop.classList.contains('open')) closeAlert();
});

// Show flash message on load
const flashMessage = document.getElementById('flashMessage');
if (flashMessage) {
  const message = flashMessage.getAttribute('data-message');
  if (message) {
    setTimeout(() => {
      showAlert({
        type: 'success',
        title: 'Submission Successful',
        message: message,
        buttons: [{
          text: 'OK',
          className: 'btn-alert-primary',
          onClick: () => {
            // Redirect to clean URL after successful submission
            window.location.href = 'submit.php';
          }
        }]
      });
    }, 100);
  }
}

// Theme Toggle Functionality
const themeToggle = document.getElementById('themeToggle');
const moonIcon = document.getElementById('moonIcon');
const sunIcon = document.getElementById('sunIcon');
const htmlElement = document.documentElement;

// Function to update theme icon based on current theme
function updateThemeIcon(theme) {
  if (moonIcon && sunIcon) {
    // Dark mode = show sun icon (to switch to light)
    // Light mode = show moon icon (to switch to dark)
    if (theme === 'light') {
      moonIcon.style.display = 'block';
      sunIcon.style.display = 'none';
    } else {
      moonIcon.style.display = 'none';
      sunIcon.style.display = 'block';
    }
  }
}

// Set initial icon state based on current theme
const currentTheme = htmlElement.getAttribute('data-theme') || 'dark';
updateThemeIcon(currentTheme);

// Toggle theme on click
themeToggle.addEventListener('click', () => {
  const currentTheme = htmlElement.getAttribute('data-theme');
  const newTheme = currentTheme === 'light' ? 'dark' : 'light';

  // Update DOM
  htmlElement.setAttribute('data-theme', newTheme);

  // Save to localStorage
  localStorage.setItem('theme', newTheme);

  // Update icon
  updateThemeIcon(newTheme);
});

// Login Form Handler (only if not logged in)
if (!IS_LOGGED_IN) {
  const loginForm = document.getElementById('loginForm');
  const loginPin = document.getElementById('loginPin');
  const loginBtn = document.getElementById('loginBtn');
  const loginBtnText = document.getElementById('loginBtnText');
  const loginErrorMessage = document.getElementById('loginErrorMessage');

  if (loginForm) {
    loginForm.addEventListener('submit', async (e) => {
      e.preventDefault();

      const pin = loginPin.value.trim();

      if (!pin) {
        loginPin.classList.add('error');
        loginErrorMessage.textContent = 'Please enter your PIN';
        loginErrorMessage.classList.add('show');
        return;
      }

      // Disable form
      loginBtn.disabled = true;
      loginBtnText.textContent = 'Verifying...';
      loginPin.disabled = true;

      try {
        const response = await fetch('auth_pin.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({ pin: pin })
        });

        const result = await response.json();

        if (result.success) {
          // Login successful - redirect to submit page
          loginBtnText.textContent = 'Success!';
          window.location.href = 'submit.php';
        } else {
          // Login failed
          loginPin.classList.add('error');
          loginErrorMessage.textContent = result.message || 'Invalid PIN. Please try again.';
          loginErrorMessage.classList.add('show');

          // Re-enable form
          loginBtn.disabled = false;
          loginBtnText.textContent = 'Verify PIN';
          loginPin.disabled = false;
          loginPin.focus();
          loginPin.select();
        }
      } catch (error) {
        console.error('Login error:', error);

        loginPin.classList.add('error');
        loginErrorMessage.textContent = 'Connection error. Please try again.';
        loginErrorMessage.classList.add('show');

        // Re-enable form
        loginBtn.disabled = false;
        loginBtnText.textContent = 'Verify PIN';
        loginPin.disabled = false;
      }
    });

    // Clear error on input
    loginPin.addEventListener('input', () => {
      loginPin.classList.remove('error');
      loginErrorMessage.classList.remove('show');
    });
  }
}

// Code Management (only if logged in)
if (IS_LOGGED_IN) {
let codeCounter = 0;

function createCodeItem(index) {
  const div = document.createElement('div');
  div.className = 'code-item';
  div.dataset.index = index;

  div.innerHTML = `
    <div class="code-header">
      <span class="code-number">Code #${index + 1}</span>
      <button type="button" class="btn btn-danger btn-remove-code" data-index="${index}">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="3 6 5 6 21 6"></polyline>
          <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
          <line x1="10" y1="11" x2="10" y2="17"></line>
          <line x1="14" y1="11" x2="14" y2="17"></line>
        </svg>
        Remove
      </button>
    </div>

    <div class="code-row">
      <div class="form-group">
        <label class="form-label">Code *</label>
        <input type="text" class="field" name="code[]" placeholder="e.g., #1234" required>
      </div>

      <div class="form-group">
        <label class="form-label">Notes</label>
        <input type="text" class="field" name="notes[]" placeholder="e.g., Main entrance">
      </div>
    </div>

    <div class="form-group code-full">
      <label class="form-label">Photo (optional)</label>
      <div class="file-upload-wrapper">
        <label class="file-upload-label">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
            <circle cx="12" cy="13" r="4"/>
          </svg>
          <span>Take Photo / Choose Image</span>
          <input type="file" class="file-upload-input photo-input" accept="image/*" capture="environment" data-index="${index}">
        </label>
        <input type="hidden" name="photo[]" class="photo-path">
        <input type="hidden" name="coordinates[]" class="photo-coordinates">
        <div class="file-preview" style="margin-top: 12px; display: none;">
          <img style="max-width: 200px; max-height: 200px; border-radius: 8px; border: 1px solid var(--border);" alt="Preview">
        </div>
        <div class="file-name" style="margin-top: 8px; color: var(--muted); font-size: 0.85rem;"></div>
      </div>
    </div>
  `;

  return div;
}

function addCode() {
  const codesList = document.getElementById('codesList');
  const currentCount = codesList.children.length;
  const codeItem = createCodeItem(currentCount);
  codesList.appendChild(codeItem);
}

function updateCodeNumbers() {
  const codeItems = document.querySelectorAll('.code-item');
  codeItems.forEach((item, index) => {
    item.dataset.index = index;
    const codeNumber = item.querySelector('.code-number');
    if (codeNumber) {
      codeNumber.textContent = `Code #${index + 1}`;
    }
    // Update remove button data-index
    const removeBtn = item.querySelector('.btn-remove-code');
    if (removeBtn) {
      removeBtn.dataset.index = index;
    }
  });
}

// Event Listeners
document.getElementById('addCodeBtn').addEventListener('click', addCode);

// Handle remove code button
document.getElementById('codesList').addEventListener('click', (e) => {
  if (e.target.classList.contains('btn-remove-code') || e.target.closest('.btn-remove-code')) {
    const btn = e.target.classList.contains('btn-remove-code') ? e.target : e.target.closest('.btn-remove-code');
    const codeItem = btn.closest('.code-item');
    const codesList = document.getElementById('codesList');

    // Only remove if there's more than one code
    if (codesList.children.length > 1) {
      codeItem.remove();
      updateCodeNumbers();
    } else {
      showAlert({
        type: 'warning',
        title: 'Cannot Remove',
        message: 'You must have at least one code in your submission.',
        buttons: [
          {
            text: 'OK',
            className: 'btn-alert-primary'
          }
        ]
      });
    }
  }
});

// Handle photo uploads
document.getElementById('codesList').addEventListener('change', async (e) => {
  if (e.target.classList.contains('photo-input')) {
    const fileInput = e.target;
    const file = fileInput.files[0];

    if (!file) return;

    const wrapper = fileInput.closest('.file-upload-wrapper');
    const fileNameDiv = wrapper.querySelector('.file-name');
    const photoPath = wrapper.querySelector('.photo-path');
    const preview = wrapper.querySelector('.file-preview');
    const previewImg = preview.querySelector('img');

    // Show preview immediately
    const reader = new FileReader();
    reader.onload = (e) => {
      previewImg.src = e.target.result;
      preview.style.display = 'block';
    };
    reader.readAsDataURL(file);

    // Upload to server
    fileNameDiv.textContent = 'Uploading...';
    fileNameDiv.style.color = 'var(--brand)';

    const formData = new FormData();
    formData.append('photo', file);

    try {
      const response = await fetch('upload_temp.php', {
        method: 'POST',
        body: formData
      });

      const result = await response.json();

      if (result.success) {
        photoPath.value = result.path;

        // Store coordinates if available
        const coordinatesInput = wrapper.querySelector('.photo-coordinates');
        if (result.coordinates) {
          coordinatesInput.value = JSON.stringify(result.coordinates);
          fileNameDiv.textContent = `✓ Photo uploaded with GPS: ${result.filename}`;
        } else {
          coordinatesInput.value = '';
          fileNameDiv.textContent = `✓ Photo uploaded: ${result.filename}`;
        }
        fileNameDiv.style.color = 'var(--brand)';
      } else {
        fileNameDiv.textContent = `✗ Upload failed: ${result.message}`;
        fileNameDiv.style.color = 'var(--danger)';
        preview.style.display = 'none';
      }
    } catch (error) {
      fileNameDiv.textContent = '✗ Upload error. Please try again.';
      fileNameDiv.style.color = 'var(--danger)';
      preview.style.display = 'none';
    }
  }
});

// Add initial code
addCode();
}
</script>
</body>
</html>
