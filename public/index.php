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

// Check for PIN in URL parameter
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
          break;
        }
      }
    }
  }

  // Redirect to clean URL
  header('Location: index.php');
  exit;
}

// Check if user is logged in OR if PIN is not required
$isLoggedIn = !$requirePin || (isset($_SESSION['user_authenticated']) && $_SESSION['user_authenticated'] === true);
$userName = (isset($_SESSION['user_authenticated']) && $_SESSION['user_authenticated'] === true) ? ($_SESSION['user_name'] ?? 'User') : ($requirePin ? null : 'Guest');
$userPin = (isset($_SESSION['user_authenticated']) && $_SESSION['user_authenticated'] === true) ? ($_SESSION['user_pin'] ?? null) : null;

// Handle logout
if (isset($_GET['logout'])) {
  session_destroy();
  header('Location: index.php');
  exit;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($siteTitle) ?></title>
<script src="https://cdn.jsdelivr.net/npm/exif-js"></script>
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
    justify-content:center;
    padding:20px;
    text-align:center;
  }

  h1{margin:0 0 10px 0;font-size:2rem}
  .sub{color:var(--muted);margin-bottom:20px}

  /* Title style + underline animation */
  .title {
    font-size: 2.5rem; font-weight: 800; text-transform: uppercase;
    letter-spacing: 2px; margin: 0 0 10px 0;
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
  .title::after {
    content:""; position: absolute; left: 0; bottom: -6px; width: 100%; height: 3px;
    background: linear-gradient(90deg, #3bdd82, #1bbf67);
    border-radius: 2px; transform: scaleX(0); transform-origin: left; transition: transform .4s ease;
  }
  .title.animate::after { transform: scaleX(1); }

  /* Search Form - Horizontal Layout with GPS Button */
  .search{
    width:100%;
    max-width:420px;
    margin-bottom:16px;
    display:flex;
    flex-direction:column;
    gap:12px;
  }
  .search input{
    padding:14px 16px;
    width:100%;
    box-sizing:border-box;
    border-radius:12px;
    border:1px solid var(--border);
    background:linear-gradient(180deg,var(--input-bg-1),var(--input-bg-2));
    color:var(--text);
    font-size:15px;
    outline:none;
    transition:border-color .15s ease, box-shadow .15s ease;
  }
  .search input::placeholder{color:var(--muted)}
  .search input:focus{
    border-color:var(--brand);
    box-shadow:0 0 0 3px rgba(59,221,130,.15);
  }

  .button-group{
    display:flex;
    gap:12px;
    width:100%;
  }

  .btn-primary{
    padding:14px 20px;
    flex:1;
    border-radius:12px;
    background:linear-gradient(135deg,var(--brand),var(--brand-2));
    border:0;
    font-weight:700;
    font-size:16px;
    cursor:pointer;
    color:#07140c;
    box-shadow:0 4px 14px rgba(59,221,130,.4);
    transition:transform .1s ease, box-shadow .2s ease;
    display:flex;
    align-items:center;
    justify-content:center;
    height:48px;
  }
  .btn-primary:hover{box-shadow:0 6px 18px rgba(59,221,130,.55)}
  .btn-primary:active{transform:translateY(1px)}

  /* Results Container with Scroll */
  .results-container{
    width:100%;
    max-width:420px;
    max-height:400px;
    overflow-y:auto;
    overflow-x:hidden;
    padding-right:8px;
  }
  
  /* Custom Scrollbar */
  .results-container::-webkit-scrollbar {
    width: 8px;
  }
  .results-container::-webkit-scrollbar-track {
    background: var(--scrollbar-track);
    border-radius: 10px;
  }
  .results-container::-webkit-scrollbar-thumb {
    background: var(--scrollbar-thumb);
    border-radius: 10px;
  }
  .results-container::-webkit-scrollbar-thumb:hover {
    background: var(--scrollbar-thumb-hover);
  }

  .grid{display:grid;gap:14px;}
  .item{
    background:var(--panel);
    padding:16px;
    border-radius:var(--radius);
    text-align:left;
    border:1px solid var(--border-2);
  }
  .item-head{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:10px;
  }
  .community{font-weight:700;font-size:18px}
  .codes{display:grid;gap:8px}
  .code-row{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:10px;
    background:var(--panel-2);
    border:1px solid var(--border-2);
    border-radius:10px;
    padding:10px;
    text-align:left;
  }
  .code-row > div:first-child{
    flex:1;
    display:flex;
    flex-direction:column;
    align-items:flex-start;
  }
  .actions{display:flex; gap:8px; margin-left:auto;}

  .code{font-family:monospace;font-size:17px;font-weight:600; display:flex; align-items:center; gap:8px;}
  .note{color:var(--muted);font-size:13px;text-align:left;margin-top:2px}

  .report-badge{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    width:20px;
    height:20px;
    min-width:20px;
    min-height:20px;
    max-width:20px;
    max-height:20px;
    background:#ff3b3b;
    color:#fff;
    font-size:14px;
    font-weight:800;
    border-radius:50%;
    font-family:system-ui,Segoe UI,Roboto,Arial;
    flex-shrink:0;
    line-height:1;
    padding-bottom:2px;
    box-sizing:border-box;
  }

  .btn-secondary{
    background:var(--btn-secondary-bg); color:var(--btn-secondary-text); border:1px solid var(--btn-secondary-border);
    padding:8px 12px; border-radius:10px; cursor:pointer; font-weight:600;
    transition:background .15s ease, transform .1s ease;
    font-size:14px;
  }
  .btn-secondary:hover{ background:var(--btn-secondary-hover) }
  .btn-secondary:active{ transform:translateY(1px) }

  .btn-report{
    background:linear-gradient(135deg,var(--danger),var(--danger-2));
    color:#fff;font-weight:600;border:0;
    padding:8px 12px;border-radius:10px;cursor:pointer;
    box-shadow:0 4px 12px rgba(255,92,92,.35);
    transition:transform .1s ease, box-shadow .2s ease;
    font-size:14px;
  }
  .btn-report:hover{box-shadow:0 6px 16px rgba(255,92,92,.5)}
  .btn-report:active{transform:translateY(1px)}

  .btn-open-gate{
    background:linear-gradient(135deg,var(--brand),var(--brand-2));
    color:#07140c;font-weight:600;border:0;
    padding:8px 12px;border-radius:10px;cursor:pointer;
    box-shadow:0 4px 12px rgba(59,221,130,.35);
    transition:transform .1s ease, box-shadow .2s ease;
    font-size:14px;
    display:inline-flex;align-items:center;gap:4px;
  }
  .btn-open-gate:hover{box-shadow:0 6px 16px rgba(59,221,130,.5)}
  .btn-open-gate:active{transform:translateY(1px)}
  .btn-open-gate svg{width:14px;height:14px;}

  .empty,.hint{
    color:var(--muted);
    margin-top:10px;
    font-size:14px;
  }

  /* GPS Loading Animation */
  .gps-loading {
    display: none;
    margin-top: 12px;
    text-align: center;
  }

  .gps-loading.show {
    display: block;
  }

  .gps-loading-dots {
    display: inline-flex;
    align-items: center;
    gap: 6px;
  }

  .gps-loading-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--brand);
    animation: gps-dot-pulse 1.4s ease-in-out infinite;
  }

  .gps-loading-dot:nth-child(2) {
    animation-delay: 0.2s;
  }

  .gps-loading-dot:nth-child(3) {
    animation-delay: 0.4s;
  }

  @keyframes gps-dot-pulse {
    0%, 60%, 100% {
      opacity: 0.3;
      transform: scale(0.8);
    }
    30% {
      opacity: 1;
      transform: scale(1.2);
    }
  }

  /* GPS Button */
  .gps-btn {
    padding: 14px 20px;
    flex: 0 0 auto;
    width: 68px;
    border-radius: 12px;
    background: linear-gradient(135deg, var(--brand), var(--brand-2));
    border: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 4px 14px rgba(59, 221, 130, .4);
    transition: transform .1s ease, box-shadow .2s ease;
    height: 48px;
  }

  .gps-btn:hover {
    box-shadow: 0 6px 18px rgba(59, 221, 130, .55);
  }

  .gps-btn:active {
    transform: translateY(1px);
  }

  .gps-btn.searching {
    animation: gps-pulse 1.5s ease-in-out infinite;
  }

  .gps-btn .gps-icon {
    color: #07140c;
    width: 24px;
    height: 24px;
  }

  .gps-btn.searching .gps-icon {
    animation: gps-bounce 1s ease-in-out infinite;
  }

  @keyframes gps-pulse {
    0%, 100% {
      box-shadow: 0 4px 14px rgba(59, 221, 130, .4);
    }
    50% {
      box-shadow: 0 8px 20px rgba(59, 221, 130, .6);
    }
  }

  @keyframes gps-bounce {
    0%, 100% {
      transform: translateY(0);
    }
    50% {
      transform: translateY(-3px);
    }
  }

  @keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
  }

  /* Footer */
  footer{
    padding:12px;
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

  /* Modal */
  .modal-backdrop{
    position:fixed; inset:0; background:rgba(0,0,0,.55);
    display:none; align-items:center; justify-content:center; padding:16px; z-index:50;
  }
  .modal-backdrop.open{ display:flex; }
  .modal{
    width:min(92vw, 700px);
    max-height:90vh;
    display:flex; flex-direction:column;
    background:linear-gradient(180deg, var(--modal-bg-1), var(--modal-bg-2));
    border:1px solid var(--modal-border); border-radius:12px; box-shadow:0 20px 60px rgba(0,0,0,.5);
    overflow:hidden; text-align:left;
  }
  .modal-header{
    display:flex; justify-content:space-between; align-items:center;
    padding:14px 16px; border-bottom:1px solid var(--border)
  }
  .modal-title{font-weight:700}
  .modal-close{
    background:var(--btn-secondary-bg); color:var(--btn-secondary-text); border:1px solid var(--btn-secondary-border);
    padding:6px 10px; border-radius:8px; cursor:pointer; font-weight:600
  }
  .modal-close-x {
    width: 40px;
    height: 40px;
    font-size: 32px;
    line-height: 1;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
  }
  .modal-close-x:hover {
    background: var(--btn-secondary-hover);
    transform: scale(1.05);
  }
  .modal-body{
    padding:0; display:flex; flex-direction:column; overflow:auto;
  }
  .modal-img{
    width:100%; height:auto; max-height:50vh;
    object-fit:contain; background:#000;
    border:none; border-radius:0; display:block;
  }
  .modal-note{ color:var(--text); font-size:14px; line-height:1.45; padding:12px 16px 6px 16px }
  .modal-meta{ color:var(--muted); font-size:13px; padding:0 16px 14px 16px }

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

  /* Report Modal */
  .report-modal {
    background: linear-gradient(180deg, var(--modal-bg-1), var(--modal-bg-2));
    border: 1px solid var(--modal-border);
    border-radius: 12px;
    width: min(90vw, 500px);
    box-shadow: 0 20px 60px rgba(0,0,0,.5);
    overflow: hidden;
  }

  .report-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    border-bottom: 1px solid var(--border);
  }

  .report-modal-header .modal-close {
    width: 40px;
    height: 40px;
    font-size: 32px;
    line-height: 1;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--btn-secondary-bg);
    color: var(--btn-secondary-text);
    border: 1px solid var(--btn-secondary-border);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
  }

  .report-modal-header .modal-close:hover {
    background: var(--btn-secondary-hover);
    transform: scale(1.05);
  }

  .report-modal-title {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text);
  }

  .report-modal-body {
    padding: 24px;
  }

  .report-info {
    display: grid;
    grid-template-columns: auto 1fr;
    gap: 8px 16px;
    padding: 16px;
    background: var(--panel-2);
    border: 1px solid var(--border-2);
    border-radius: 10px;
    margin-bottom: 16px;
  }

  .report-code-label,
  .report-community-label {
    font-weight: 600;
    color: var(--muted);
    font-size: 14px;
  }

  .report-code-value {
    font-family: monospace;
    font-size: 16px;
    font-weight: 600;
    color: var(--brand);
  }

  .report-community-value {
    font-size: 15px;
    font-weight: 600;
    color: var(--text);
  }

  .form-label {
    display: block;
    margin-bottom: 8px;
    color: var(--text);
    font-weight: 600;
    font-size: 15px;
  }

  .field-select {
    width: 100%;
    padding: 12px 14px;
    border-radius: 10px;
    border: 1px solid var(--border);
    background: linear-gradient(180deg, var(--input-bg-1), var(--input-bg-2));
    color: var(--text);
    font-size: 15px;
    outline: none;
    transition: border-color .15s ease, box-shadow .15s ease;
    cursor: pointer;
  }

  .field-select:focus {
    border-color: var(--brand);
    box-shadow: 0 0 0 3px rgba(59, 221, 130, .15);
  }

  .field-select option {
    background: var(--panel);
    color: var(--text);
  }

  .report-modal-footer {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    padding: 16px 24px;
    border-top: 1px solid var(--border);
    background: var(--panel-2);
  }

  .report-modal-footer .btn-secondary,
  .report-modal-footer .btn-report {
    min-width: 100px;
  }

  /* Theme Toggle Button */
  .theme-toggle {
    position: fixed;
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
    z-index: 10;
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

  /* Login Modal */
  .login-modal {
    background: linear-gradient(180deg, var(--modal-bg-1), var(--modal-bg-2));
    border: 1px solid var(--modal-border);
    border-radius: 12px;
    width: min(90vw, 400px);
    box-shadow: 0 20px 60px rgba(0,0,0,.5);
    overflow: hidden;
  }

  .login-modal-header {
    padding: 20px 24px;
    border-bottom: 1px solid var(--border);
  }

  .login-modal-title {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text);
  }

  .login-modal-body {
    padding: 24px;
  }

  .login-form-group {
    margin-bottom: 20px;
  }

  .login-form-label {
    display: block;
    margin-bottom: 8px;
    color: var(--text);
    font-weight: 600;
    font-size: 15px;
  }

  .login-form-input {
    width: 100%;
    padding: 12px 14px;
    border-radius: 10px;
    border: 1px solid var(--border);
    background: linear-gradient(180deg, var(--input-bg-1), var(--input-bg-2));
    color: var(--text);
    font-size: 15px;
    outline: none;
    transition: border-color .15s ease, box-shadow .15s ease;
    box-sizing: border-box;
    font-family: monospace;
    letter-spacing: 2px;
    font-weight: 600;
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
  }

  .login-error-message {
    color: var(--danger);
    font-size: 13px;
    margin-top: 8px;
    display: none;
  }

  .login-error-message.show {
    display: block;
  }

  .login-modal-footer {
    display: flex;
    gap: 12px;
    padding: 16px 24px;
    border-top: 1px solid var(--border);
    background: var(--panel-2);
  }

  .login-modal-footer .btn-primary,
  .login-modal-footer .btn-secondary {
    min-width: 100px;
    flex: 1;
  }

  /* User Info Display */
  .user-info {
    position: fixed;
    top: 20px;
    left: 20px;
    background: var(--panel);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 12px 16px;
    display: flex;
    align-items: center;
    gap: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,.2);
    z-index: 10;
  }

  .user-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--brand), var(--brand-2));
    display: flex;
    align-items: center;
    justify-content: center;
    color: #07140c;
    font-weight: 700;
    font-size: 14px;
  }

  .user-details {
    display: flex;
    flex-direction: column;
    gap: 2px;
  }

  .user-name {
    font-weight: 600;
    font-size: 14px;
    color: var(--text);
  }

  .user-pin-display {
    font-family: monospace;
    font-size: 12px;
    color: var(--muted);
  }

  .btn-logout {
    background: var(--btn-secondary-bg);
    color: var(--btn-secondary-text);
    border: 1px solid var(--btn-secondary-border);
    padding: 6px 12px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    font-size: 13px;
    transition: all 0.2s ease;
  }

  .btn-logout:hover {
    background: var(--btn-secondary-hover);
  }

  /* Login Page Styles */
  .login-container {
    width: 100%;
    max-width: 450px;
    background: linear-gradient(180deg, var(--panel), var(--panel-2));
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 30px 32px;
    margin: 0 auto 16px auto;
    text-align: center;
    box-sizing: border-box;
  }

  .login-icon {
    width: 70px;
    height: 70px;
    margin: 0 auto 20px;
    border-radius: 50%;
    background: linear-gradient(135deg, rgba(59, 221, 130, 0.2), rgba(27, 191, 103, 0.15));
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .login-icon svg {
    width: 35px;
    height: 35px;
    stroke: var(--brand);
  }

  .login-title {
    font-size: 1.5rem;
    font-weight: 700;
    margin: 0 0 10px 0;
    color: var(--text);
  }

  .login-subtitle {
    color: var(--muted);
    margin-bottom: 24px;
    font-size: 0.9rem;
    line-height: 1.4;
  }

  .login-form-group {
    margin-bottom: 20px;
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

  /* File Upload Styles for Update Photo Modal */
  .file-upload-wrapper-update {
    position: relative;
  }

  .file-upload-label-update {
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
    width: 100%;
    box-sizing: border-box;
    justify-content: center;
  }

  .file-upload-label-update:hover {
    background: var(--panel-2);
    transform: translateY(-1px);
    border-color: var(--brand);
  }

  .file-upload-label-update svg {
    flex-shrink: 0;
  }

  .file-upload-input-update {
    position: absolute;
    width: 0.1px;
    height: 0.1px;
    opacity: 0;
    overflow: hidden;
    z-index: -1;
  }

  @media (max-width: 480px) {
    .title{font-size:2.2rem}
    .search{max-width:100%}
    .results-container{max-width:100%}
    .theme-toggle {
      position: absolute;
      width: 44px;
      height: 44px;
      top: 15px;
      right: 15px;
    }
    .theme-toggle svg {
      width: 20px;
      height: 20px;
    }
    .user-info {
      position: static;
      margin: 30px auto 20px auto;
      width: fit-content;
    }

    /* Login Container Responsive */
    .login-container {
      max-width: 100%;
      padding: 32px 20px;
      margin: 0 auto 20px auto;
      border-radius: 12px;
    }

    .login-icon {
      width: 70px;
      height: 70px;
      margin-bottom: 20px;
    }

    .login-icon svg {
      width: 35px;
      height: 35px;
    }

    .login-title {
      font-size: 1.5rem;
      margin-bottom: 10px;
    }

    .login-subtitle {
      font-size: 0.9rem;
      margin-bottom: 24px;
    }

    .login-form-group {
      margin-bottom: 20px;
    }

    .login-form-label {
      font-size: 0.9rem;
    }

    .login-form-input {
      padding: 12px 14px;
      font-size: 15px;
    }

    .login-btn {
      padding: 12px 18px;
      font-size: 15px;
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

  <!-- User Info (only shown when logged in with a real PIN, hidden for Guest users) -->
  <?php if ($isLoggedIn && $userPin): ?>
    <div class="user-info">
      <div class="user-avatar"><?= strtoupper(substr($userName, 0, 1)) ?></div>
      <div class="user-details">
        <div class="user-name"><?= htmlspecialchars($userName) ?></div>
        <div class="user-pin-display">PIN: <?php
          $maskedPin = str_repeat('*', strlen($userPin) - 2) . substr($userPin, -2);
          echo htmlspecialchars($maskedPin);
        ?></div>
      </div>
      <a href="?logout" class="btn-logout">Logout</a>
    </div>
  <?php endif; ?>

  <main>
    <a href="/" id="title" class="title"><?= htmlspecialchars($siteTitle) ?></a>

    <?php if (!$isLoggedIn): ?>
      <!-- LOGIN PAGE -->
      <div class="sub">Enter your PIN to access</div>

      <div class="login-container">
        <div class="login-icon">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
          </svg>
        </div>

        <h2 class="login-title">PIN Authentication</h2>
        <p class="login-subtitle">Please enter your PIN to access community gate codes</p>

        <form id="loginForm" method="POST" action="auth_pin.php">
          <div class="login-form-group">
            <label class="login-form-label" for="loginPin">Your PIN:</label>
            <input type="text" id="loginPin" name="pin" class="login-form-input" placeholder="Enter PIN" maxlength="6" autocomplete="off" required autofocus />
            <div id="loginErrorMessage" class="login-error-message"></div>
          </div>
          <button type="submit" class="login-btn" id="loginBtn">
            <span id="loginBtnText">Verify PIN</span>
          </button>
        </form>
      </div>
    <?php else: ?>
      <!-- SEARCH PAGE -->
      <div class="sub">Search community gate codes</div>

      <form class="search" id="searchForm" role="search" aria-label="Community search">
        <input id="q" placeholder="e.g. Water Oaks" aria-label="Community name" required>
        <div class="button-group">
          <button class="btn-primary" type="submit">Search</button>
          <button id="gpsBtn" class="gps-btn" type="button" title="Find communities near me" aria-label="Find communities near me">
            <svg class="gps-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
              <circle cx="12" cy="10" r="3"/>
            </svg>
          </button>
        </div>
      </form>

      <div id="msg" class="hint">Type a community name and press Search.</div>

      <!-- GPS Loading Animation -->
      <div id="gpsLoading" class="gps-loading">
        <div class="gps-loading-dots">
          <div class="gps-loading-dot"></div>
          <div class="gps-loading-dot"></div>
          <div class="gps-loading-dot"></div>
        </div>
      </div>

      <div class="results-container">
        <div id="results" class="grid"></div>
      </div>
    <?php endif; ?>
  </main>

  <footer>
      <span>© <?=date('Y')?> Built by <a href="mailto:blancuniverse@gmail.com" class="footer-by">Alejandro</a> | <a href="submit.php">Submit Community</a></span>
      <span style="margin: 0 8px; opacity: 0.5;">•</span>
      <span style="font-family: 'Courier New', monospace; color: var(--brand); font-weight: 600;">Build v<?= APP_VERSION ?></span>
  </footer>

  <!-- Modal -->
  <div id="backdrop" class="modal-backdrop" aria-hidden="true">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
      <div class="modal-header">
        <div id="modalTitle" class="modal-title">Details</div>
        <div style="display: flex; gap: 12px; align-items: center;">
          <button id="updatePhotoBtn" class="btn-secondary" type="button" style="display: none;">Update Photo</button>
          <button class="modal-close modal-close-x" id="modalClose" type="button">&times;</button>
        </div>
      </div>
      <div class="modal-body">
        <img id="modalImg" class="modal-img" alt="Location photo" />
        <div id="modalText" class="modal-note"></div>
        <div id="modalMeta" class="modal-meta"></div>
      </div>
    </div>
  </div>

  <!-- Alert Modal -->
  <div id="alertBackdrop" class="modal-backdrop" aria-hidden="true">
    <div class="alert-modal" role="alertdialog" aria-modal="true">
      <div id="alertIcon" class="alert-icon"></div>
      <div id="alertTitle" class="alert-title"></div>
      <div id="alertMessage" class="alert-message"></div>
      <div id="alertActions" class="alert-actions"></div>
    </div>
  </div>

  <!-- Report Modal -->
  <div id="reportBackdrop" class="modal-backdrop" aria-hidden="true">
    <div class="report-modal" role="dialog" aria-modal="true">
      <div class="report-modal-header">
        <h3 class="report-modal-title">Report Gate Code</h3>
        <button class="modal-close" id="reportModalClose" type="button">&times;</button>
      </div>
      <div class="report-modal-body">
        <div class="report-info">
          <div class="report-code-label">Code:</div>
          <div id="reportCodeDisplay" class="report-code-value"></div>
          <div class="report-community-label">Community:</div>
          <div id="reportCommunityDisplay" class="report-community-value"></div>
        </div>
        <div class="form-group" style="margin-top: 20px;">
          <label class="form-label" for="reportReason">Reason for reporting:</label>
          <select id="reportReason" class="field-select">
            <option value="">Select a reason...</option>
            <option value="incorrect">Code is incorrect</option>
            <option value="outdated">Code is outdated/changed</option>
            <option value="not_working">Code not working anymore</option>
            <option value="wrong_community">Wrong community assigned</option>
            <option value="duplicate">Duplicate entry</option>
            <option value="other">Other reason</option>
          </select>
        </div>
      </div>
      <div class="report-modal-footer">
        <button class="btn-secondary" id="reportCancelBtn" type="button">Cancel</button>
        <button class="btn-report" id="reportSubmitBtn" type="button">Report Code</button>
      </div>
    </div>
  </div>

  <!-- Update Photo Modal -->
  <div id="updatePhotoBackdrop" class="modal-backdrop" aria-hidden="true">
    <div class="report-modal" role="dialog" aria-modal="true">
      <div class="report-modal-header">
        <h3 class="report-modal-title">Update Photo</h3>
        <button class="modal-close" id="updatePhotoModalClose" type="button">&times;</button>
      </div>
      <div class="report-modal-body">
        <div class="report-info">
          <div class="report-code-label">Code:</div>
          <div id="updatePhotoCodeDisplay" class="report-code-value"></div>
          <div class="report-community-label">Community:</div>
          <div id="updatePhotoCommunityDisplay" class="report-community-value"></div>
        </div>
        <div class="form-group" style="margin-top: 20px;">
          <label class="form-label">Choose Photo:</label>
          <div class="file-upload-wrapper-update">
            <label class="file-upload-label-update">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
                <circle cx="12" cy="13" r="4"/>
              </svg>
              <span>Take Photo / Choose Image</span>
              <input type="file" id="updatePhotoInput" accept="image/*" capture="environment" class="file-upload-input-update">
            </label>
            <div id="updatePhotoPreview" style="margin-top: 12px; display: none;">
              <img id="updatePhotoPreviewImg" style="max-width: 100%; max-height: 200px; border-radius: 10px; border: 1px solid var(--border);" alt="Preview">
            </div>
            <div id="updatePhotoStatus" style="margin-top: 8px; font-size: 13px; color: var(--muted);"></div>
          </div>
        </div>
      </div>
      <div class="report-modal-footer">
        <button class="btn-secondary" id="updatePhotoCancelBtn" type="button">Cancel</button>
        <button class="btn-primary" id="updatePhotoSubmitBtn" type="button" disabled>Submit Photo</button>
      </div>
    </div>
  </div>

<script>
const IS_LOGGED_IN = <?= json_encode($isLoggedIn) ?>;

// Login Form Handler (only if not logged in)
if (!IS_LOGGED_IN) {
  document.addEventListener('DOMContentLoaded', () => {
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
            // Login successful - redirect to index
            loginBtnText.textContent = 'Success!';
            window.location.href = 'index.php';
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
  });
}
</script>
<script>
// Only run search functionality if logged in
if (IS_LOGGED_IN) {
const JSON_URL   = 'data/gates.json';
const SUGGEST_URL = 'data/suggest.json';
const ASSETS_URL = 'assets/';
const DEFAULT_PHOTO = 'thumbnailnone.png';
let DATA = [];
let SUGGESTIONS = [];

function norm(s){
  return (s||'').toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'');
}

async function loadData(){
  try{
    const r = await fetch(JSON_URL, { cache: 'no-store' });
    if(!r.ok) throw new Error('Failed to load JSON');
    DATA = await r.json();
  }catch(e){
    DATA = [];
    document.getElementById('msg').textContent = 'Error loading data file.';
  }
}

async function loadSuggestions(){
  try{
    const r = await fetch(SUGGEST_URL, { cache: 'no-store' });
    if(!r.ok) throw new Error('Failed to load suggestions');
    SUGGESTIONS = await r.json();
  }catch(e){
    SUGGESTIONS = [];
  }
}

function hasPendingPhotoUpdate(community, code) {
  return SUGGESTIONS.some(suggestion =>
    suggestion.community === community &&
    suggestion.type === 'photo_update' &&
    suggestion.codes &&
    suggestion.codes.some(c => c.code === code)
  );
}

function renderNone(q){
  const res = document.getElementById('results');
  res.innerHTML = '';
  const msg = document.getElementById('msg');
  msg.style.color = 'var(--muted)';
  if(!q){
    msg.textContent = 'Type a community name and press Search.';
  } else {
    msg.textContent = `No results for "${q}".`;
  }
  msg.style.display = 'block';
}

function renderResults(items){
  const res = document.getElementById('results');
  const msg = document.getElementById('msg');
  msg.style.display = 'none';
  res.innerHTML = items.map(it => `
    <section class="item">
      <div class="item-head">
        <div class="community">
          ${escapeHtml(it.community)}
          ${it.city ? `<span style="color: var(--muted); font-weight: 500;"> - ${escapeHtml(it.city)}</span>` : ''}
        </div>
      </div>
      <div class="codes">
        ${it.codes.map(c => `
          <div class="code-row">
            <div>
              <div class="code">
                <span>${escapeHtml(c.code)}</span>
                ${c.report_count > 0 ? `<span class="report-badge" title="${c.report_count} report${c.report_count > 1 ? 's' : ''}">!</span>` : ''}
              </div>
              ${c.notes ? `<div class="note">${escapeHtml(c.notes)}</div>` : ``}
            </div>
            <div class="actions">
              ${it.http_url ? `<button class="btn-open-gate" data-url="${escapeHtml(it.http_url)}" title="Open gate via HTTP">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                  <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                </svg>
                Open
              </button>` : ''}
              <button class="btn-secondary btn-details"
                data-community="${escapeHtml(it.community)}"
                data-code="${escapeHtml(c.code)}"
                data-photo="${escapeHtml(c.photo||'')}"
                data-notes="${escapeHtml(c.notes||'')}">Details</button>
              <button class="btn-report" data-community="${escapeHtml(it.community)}" data-code="${escapeHtml(c.code)}">Report</button>
            </div>
          </div>
        `).join('')}
      </div>
    </section>
  `).join('');

  res.querySelectorAll('.btn-details').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      openModal({
        community: btn.dataset.community,
        code: btn.dataset.code,
        photo: btn.dataset.photo,
        notes: btn.dataset.notes
      });
    });
  });

  res.querySelectorAll('.btn-open-gate').forEach(btn=>{
    btn.addEventListener('click', async ()=>{
      const url = btn.getAttribute('data-url');
      if (!url) return;

      // Disable button and show loading state
      const originalHtml = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="animation: spin 1s linear infinite;">
        <circle cx="12" cy="12" r="10"></circle>
        <path d="M12 6v6l4 2"></path>
      </svg>
      Opening...`;

      try {
        // Make HTTP request to the URL
        const response = await fetch(url, {
          method: 'GET',
          mode: 'no-cors' // Allow requests to devices without CORS
        });

        // Show success alert
        showAlert({
          type: 'success',
          title: 'Gate Command Sent',
          message: 'The gate opening command has been sent successfully.',
          buttons: [
            {
              text: 'OK',
              className: 'btn-alert-primary'
            }
          ]
        });

        // Reset button after success
        btn.disabled = false;
        btn.innerHTML = originalHtml;

      } catch (error) {
        console.error('Error opening gate:', error);

        // Show error alert
        showAlert({
          type: 'error',
          title: 'Failed to Open Gate',
          message: 'Could not send the opening command. Please check your connection or try again.',
          buttons: [
            {
              text: 'OK',
              className: 'btn-alert-secondary'
            }
          ]
        });

        // Reset button after error
        btn.disabled = false;
        btn.innerHTML = originalHtml;
      }
    });
  });

  res.querySelectorAll('.btn-report').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      const comm = btn.getAttribute('data-community');
      const code = btn.getAttribute('data-code');
      openReportModal(comm, code, btn);
    });
  });
}

function search(q){
  const qn = norm(q);
  const hits = DATA.filter(x => norm(x.community).includes(qn));
  if(hits.length === 0){ renderNone(q); return; }
  renderResults(hits);
}

function escapeHtml(s){ return String(s||'').replace(/[&<>"']/g,c=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' })[c]); }

function resolvePhoto(p){
  const v = (p||'').trim();
  if (/^(https?:)?\/\//.test(v)) return v;
  if (/^\.{1,2}\//.test(v)) return v;
  if (/^\//.test(v)) return v;
  if (/^assets\//i.test(v)) return v;
  return ASSETS_URL + (v || DEFAULT_PHOTO);
}

document.getElementById('searchForm').addEventListener('submit', async (e)=>{
  e.preventDefault();
  const q = document.getElementById('q').value.trim();
  if(!DATA.length) await loadData();

  const title = document.getElementById('title');
  title.classList.add('animate');
  setTimeout(()=> title.classList.remove('animate'), 600);

  if(!q){ renderNone(''); return; }

  // Track search usage
  try {
    await fetch('track_search.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: `query=${encodeURIComponent(q)}`
    });
  } catch (error) {
    console.error('Failed to track search:', error);
  }

  search(q);
});

const backdrop = document.getElementById('backdrop');
const modalImg = document.getElementById('modalImg');
const modalText = document.getElementById('modalText');
const modalMeta = document.getElementById('modalMeta');
const modalClose = document.getElementById('modalClose');

let currentModalData = null;

function openModal({community, code, photo, notes}){
  currentModalData = {community, code, photo, notes};
  document.getElementById('modalTitle').textContent = `Details — ${community}`;

  const src = resolvePhoto(photo);
  modalImg.onerror = () => {
    modalImg.onerror = null;
    modalImg.src = resolvePhoto(DEFAULT_PHOTO);
  };
  modalImg.src = src;
  modalImg.alt = photo ? `Photo for ${community} (${code})` : 'No photo available';

  modalText.textContent = notes || 'No extra information.';
  modalMeta.textContent = `${community} • Code: ${code}`;

  // Show "Update Photo" button only if photo is default AND no pending suggestion exists
  const updatePhotoBtn = document.getElementById('updatePhotoBtn');
  const isDefaultPhoto = !photo || photo === '' || photo === DEFAULT_PHOTO || photo.includes('thumbnailnone');
  const hasPendingSuggestion = hasPendingPhotoUpdate(community, code);
  updatePhotoBtn.style.display = (isDefaultPhoto && !hasPendingSuggestion) ? 'block' : 'none';

  backdrop.classList.add('open');
  backdrop.setAttribute('aria-hidden','false');
  modalClose.focus();
}

function closeModal(){
  backdrop.classList.remove('open');
  backdrop.setAttribute('aria-hidden','true');
}
modalClose.addEventListener('click', closeModal);
backdrop.addEventListener('click', (e)=> { if(e.target === backdrop) closeModal(); });
window.addEventListener('keydown', (e)=> { if(e.key === 'Escape' && backdrop.classList.contains('open')) closeModal(); });

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

// GPS Location functionality
const gpsBtn = document.getElementById('gpsBtn');
const msgElement = document.getElementById('msg');
const gpsLoadingElement = document.getElementById('gpsLoading');

// Function to calculate distance between two coordinates (Haversine formula)
function calculateDistance(lat1, lon1, lat2, lon2) {
  const R = 6371; // Earth's radius in kilometers
  const dLat = (lat2 - lat1) * Math.PI / 180;
  const dLon = (lon2 - lon1) * Math.PI / 180;
  const a =
    Math.sin(dLat/2) * Math.sin(dLat/2) +
    Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
    Math.sin(dLon/2) * Math.sin(dLon/2);
  const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
  const distance = R * c;
  return distance; // Distance in kilometers
}

// GPS button click handler
gpsBtn.addEventListener('click', async () => {
  // Check if geolocation is supported
  if (!navigator.geolocation) {
    msgElement.textContent = 'Geolocation is not supported by your browser.';
    msgElement.style.color = 'var(--danger)';
    return;
  }

  // Load data if not already loaded
  if (!DATA.length) await loadData();

  // Clear results container
  const resultsElement = document.getElementById('results');
  resultsElement.innerHTML = '';

  // Add searching animation
  gpsBtn.classList.add('searching');
  gpsBtn.disabled = true;
  msgElement.textContent = 'Searching for communities nearby...';
  msgElement.style.color = 'var(--brand)';
  gpsLoadingElement.classList.add('show');

  // Get user's location
  navigator.geolocation.getCurrentPosition(
    // Success callback
    (position) => {
      const userLat = position.coords.latitude;
      const userLon = position.coords.longitude;
      const searchRadius = 0.04; // Search radius in kilometers (40 meters)

      // Find communities within radius
      const nearbyCommunities = DATA.filter(community => {
        if (!community.coordinates ||
            community.coordinates.latitude === null ||
            community.coordinates.longitude === null) {
          return false;
        }

        const distance = calculateDistance(
          userLat,
          userLon,
          community.coordinates.latitude,
          community.coordinates.longitude
        );

        return distance <= searchRadius;
      });

      // Remove searching animation
      gpsBtn.classList.remove('searching');
      gpsBtn.disabled = false;
      gpsLoadingElement.classList.remove('show');

      if (nearbyCommunities.length > 0) {
        // Sort by distance (closest first)
        nearbyCommunities.sort((a, b) => {
          const distA = calculateDistance(userLat, userLon, a.coordinates.latitude, a.coordinates.longitude);
          const distB = calculateDistance(userLat, userLon, b.coordinates.latitude, b.coordinates.longitude);
          return distA - distB;
        });

        // Show results
        renderResults(nearbyCommunities);

        // Update message
        const count = nearbyCommunities.length;
        const closest = nearbyCommunities[0];
        const closestDistanceKm = calculateDistance(
          userLat, userLon,
          closest.coordinates.latitude,
          closest.coordinates.longitude
        );

        // Show distance in meters
        const closestDistanceMeters = Math.round(closestDistanceKm * 1000);
        const distanceText = `${closestDistanceMeters} meters away`;

        msgElement.textContent = `Found ${count} ${count === 1 ? 'community' : 'communities'} near you. Closest: ${closest.community} (${distanceText})`;
        msgElement.style.color = 'var(--brand)';
      } else {
        // No communities found
        renderNone('');
        msgElement.textContent = 'No communities found within 40 meters of your location.';
        msgElement.style.color = 'var(--muted)';
      }
    },
    // Error callback
    (error) => {
      gpsBtn.classList.remove('searching');
      gpsBtn.disabled = false;
      gpsLoadingElement.classList.remove('show');

      let errorMessage = 'Unable to get your location. ';

      switch(error.code) {
        case error.PERMISSION_DENIED:
          errorMessage += 'Please allow location access to find nearby communities.';
          break;
        case error.POSITION_UNAVAILABLE:
          errorMessage += 'Location information is unavailable.';
          break;
        case error.TIMEOUT:
          errorMessage += 'Location request timed out.';
          break;
        default:
          errorMessage += 'An unknown error occurred.';
      }

      msgElement.textContent = errorMessage;
      msgElement.style.color = 'var(--danger)';
    },
    // Options
    {
      enableHighAccuracy: true,
      timeout: 10000,
      maximumAge: 0
    }
  );
});

// Report Modal Functions
const reportBackdrop = document.getElementById('reportBackdrop');
const reportModalClose = document.getElementById('reportModalClose');
const reportCodeDisplay = document.getElementById('reportCodeDisplay');
const reportCommunityDisplay = document.getElementById('reportCommunityDisplay');
const reportReason = document.getElementById('reportReason');
const reportCancelBtn = document.getElementById('reportCancelBtn');
const reportSubmitBtn = document.getElementById('reportSubmitBtn');

let currentReportData = null;

function openReportModal(community, code, btnElement) {
  currentReportData = { community, code, btnElement };

  reportCodeDisplay.textContent = code;
  reportCommunityDisplay.textContent = community;
  reportReason.value = '';

  reportBackdrop.classList.add('open');
  reportBackdrop.setAttribute('aria-hidden', 'false');
  reportReason.focus();
}

function closeReportModal() {
  reportBackdrop.classList.remove('open');
  reportBackdrop.setAttribute('aria-hidden', 'true');
  currentReportData = null;
}

reportModalClose.addEventListener('click', closeReportModal);
reportCancelBtn.addEventListener('click', closeReportModal);

reportBackdrop.addEventListener('click', (e) => {
  if (e.target === reportBackdrop) closeReportModal();
});

window.addEventListener('keydown', (e) => {
  if (e.key === 'Escape' && reportBackdrop.classList.contains('open')) {
    closeReportModal();
  }
});

reportSubmitBtn.addEventListener('click', async () => {
  if (!currentReportData) return;

  const reason = reportReason.value.trim();
  if (!reason) {
    reportReason.style.borderColor = 'var(--danger)';
    reportReason.focus();
    return;
  }

  const { community, code, btnElement } = currentReportData;

  // Disable submit button
  const originalText = reportSubmitBtn.textContent;
  reportSubmitBtn.disabled = true;
  reportSubmitBtn.textContent = 'Reporting...';

  try {
    const response = await fetch('report_gate.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        community: community,
        code: code,
        reason: reason
      })
    });

    const result = await response.json();

    if (result.success) {
      // Update local DATA
      const communityObj = DATA.find(x => x.community === community);
      if (communityObj) {
        const codeObj = communityObj.codes.find(c => c.code === code);
        if (codeObj) {
          codeObj.report_count = (codeObj.report_count || 0) + 1;
        }
      }

      // Close report modal
      closeReportModal();

      // Re-render results to show updated badge
      const q = document.getElementById('q').value.trim();
      search(q);

      // Show success message
      showAlert({
        type: 'success',
        title: 'Report Submitted',
        message: `Thank you for reporting the code "${code}" from ${community}. Your feedback helps keep our database accurate and up-to-date.`,
        buttons: [
          {
            text: 'Close',
            className: 'btn-alert-primary'
          }
        ]
      });
    } else {
      // Show error message
      closeReportModal();
      showAlert({
        type: 'error',
        title: 'Report Failed',
        message: result.message || 'Failed to submit report. Please try again.',
        buttons: [
          {
            text: 'Close',
            className: 'btn-alert-secondary'
          }
        ]
      });
    }
  } catch (error) {
    console.error('Error reporting gate:', error);
    closeReportModal();
    showAlert({
      type: 'error',
      title: 'Connection Error',
      message: 'Failed to submit report. Please check your connection and try again.',
      buttons: [
        {
          text: 'Close',
          className: 'btn-alert-secondary'
        }
      ]
    });
  } finally {
    reportSubmitBtn.disabled = false;
    reportSubmitBtn.textContent = originalText;
  }
});

// Reset border color on input change
reportReason.addEventListener('change', () => {
  reportReason.style.borderColor = 'var(--border)';
});

// Update Photo Modal Functions
const updatePhotoBackdrop = document.getElementById('updatePhotoBackdrop');
const updatePhotoBtn = document.getElementById('updatePhotoBtn');
const updatePhotoModalClose = document.getElementById('updatePhotoModalClose');
const updatePhotoCodeDisplay = document.getElementById('updatePhotoCodeDisplay');
const updatePhotoCommunityDisplay = document.getElementById('updatePhotoCommunityDisplay');
const updatePhotoInput = document.getElementById('updatePhotoInput');
const updatePhotoPreview = document.getElementById('updatePhotoPreview');
const updatePhotoPreviewImg = document.getElementById('updatePhotoPreviewImg');
const updatePhotoStatus = document.getElementById('updatePhotoStatus');
const updatePhotoCancelBtn = document.getElementById('updatePhotoCancelBtn');
const updatePhotoSubmitBtn = document.getElementById('updatePhotoSubmitBtn');

let selectedPhotoFile = null;
let photoCoordinates = null;

function openUpdatePhotoModal() {
  if (!currentModalData) return;

  updatePhotoCodeDisplay.textContent = currentModalData.code;
  updatePhotoCommunityDisplay.textContent = currentModalData.community;
  updatePhotoInput.value = '';
  updatePhotoPreview.style.display = 'none';
  updatePhotoStatus.textContent = '';
  updatePhotoSubmitBtn.disabled = true;
  selectedPhotoFile = null;
  photoCoordinates = null;

  updatePhotoBackdrop.classList.add('open');
  updatePhotoBackdrop.setAttribute('aria-hidden', 'false');
}

function closeUpdatePhotoModal() {
  updatePhotoBackdrop.classList.remove('open');
  updatePhotoBackdrop.setAttribute('aria-hidden', 'true');
}

updatePhotoBtn.addEventListener('click', openUpdatePhotoModal);
updatePhotoModalClose.addEventListener('click', closeUpdatePhotoModal);
updatePhotoCancelBtn.addEventListener('click', closeUpdatePhotoModal);

updatePhotoBackdrop.addEventListener('click', (e) => {
  if (e.target === updatePhotoBackdrop) closeUpdatePhotoModal();
});

// Image compression and GPS extraction function (copied from communities.php)
async function compressImage(file) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = async (e) => {
      try {
        // Extract EXIF data for GPS coordinates
        const EXIF = window.EXIF;
        let coordinates = null;

        if (EXIF) {
          EXIF.getData(file, function() {
            const lat = EXIF.getTag(this, 'GPSLatitude');
            const latRef = EXIF.getTag(this, 'GPSLatitudeRef');
            const lon = EXIF.getTag(this, 'GPSLongitude');
            const lonRef = EXIF.getTag(this, 'GPSLongitudeRef');

            if (lat && lon) {
              const latitude = convertDMSToDD(lat, latRef);
              const longitude = convertDMSToDD(lon, lonRef);
              coordinates = { latitude, longitude };
            }
          });
        }

        // Compress image
        const img = new Image();
        img.onload = () => {
          const canvas = document.createElement('canvas');
          let width = img.width;
          let height = img.height;
          const maxSize = 1200;

          if (width > height && width > maxSize) {
            height *= maxSize / width;
            width = maxSize;
          } else if (height > maxSize) {
            width *= maxSize / height;
            height = maxSize;
          }

          canvas.width = width;
          canvas.height = height;
          const ctx = canvas.getContext('2d');
          ctx.drawImage(img, 0, 0, width, height);

          canvas.toBlob((blob) => {
            resolve({ file: new File([blob], file.name, { type: 'image/jpeg' }), coordinates });
          }, 'image/jpeg', 0.8);
        };
        img.src = e.target.result;
      } catch (error) {
        reject(error);
      }
    };
    reader.readAsDataURL(file);
  });
}

function convertDMSToDD(dms, ref) {
  const degrees = dms[0];
  const minutes = dms[1];
  const seconds = dms[2];
  let dd = degrees + minutes / 60 + seconds / 3600;
  if (ref === 'S' || ref === 'W') {
    dd = dd * -1;
  }
  return dd;
}

updatePhotoInput.addEventListener('change', async (e) => {
  const file = e.target.files[0];
  if (!file) return;

  updatePhotoStatus.textContent = 'Compressing image...';
  updatePhotoStatus.style.color = 'var(--muted)';

  try {
    const { file: compressedFile, coordinates: gpsCoords } = await compressImage(file);
    selectedPhotoFile = compressedFile;
    photoCoordinates = gpsCoords;

    // Show preview
    const reader = new FileReader();
    reader.onload = (e) => {
      updatePhotoPreviewImg.src = e.target.result;
      updatePhotoPreview.style.display = 'block';
    };
    reader.readAsDataURL(compressedFile);

    const originalSize = (file.size / 1024 / 1024).toFixed(2);
    const compressedSize = (compressedFile.size / 1024 / 1024).toFixed(2);

    if (gpsCoords) {
      updatePhotoStatus.textContent = `Ready! (${originalSize}MB → ${compressedSize}MB) • GPS: ${gpsCoords.latitude.toFixed(6)}, ${gpsCoords.longitude.toFixed(6)}`;
      updatePhotoStatus.style.color = 'var(--brand)';
    } else {
      updatePhotoStatus.textContent = `Ready! (${originalSize}MB → ${compressedSize}MB) • No GPS data found`;
      updatePhotoStatus.style.color = 'var(--muted)';
    }

    updatePhotoSubmitBtn.disabled = false;
  } catch (error) {
    console.error('Error processing image:', error);
    updatePhotoStatus.textContent = 'Error processing image';
    updatePhotoStatus.style.color = 'var(--danger)';
    updatePhotoSubmitBtn.disabled = true;
  }
});

updatePhotoSubmitBtn.addEventListener('click', async () => {
  if (!selectedPhotoFile || !currentModalData) return;

  const originalText = updatePhotoSubmitBtn.textContent;
  updatePhotoSubmitBtn.disabled = true;
  updatePhotoSubmitBtn.textContent = 'Uploading...';
  updatePhotoStatus.textContent = 'Uploading photo...';
  updatePhotoStatus.style.color = 'var(--muted)';

  try {
    // First upload the photo to temp_assets
    const formData = new FormData();
    formData.append('photo', selectedPhotoFile);

    const uploadResponse = await fetch('upload_temp.php', {
      method: 'POST',
      body: formData
    });

    const uploadResult = await uploadResponse.json();

    if (!uploadResult.success) {
      throw new Error(uploadResult.message || 'Failed to upload photo');
    }

    // Use coordinates from upload if available, otherwise from EXIF extraction
    const finalCoordinates = uploadResult.coordinates || photoCoordinates;

    // Then save suggestion to suggest.json
    const suggestionData = {
      community: currentModalData.community,
      code: currentModalData.code,
      photo: uploadResult.path,  // Use 'path' instead of 'url' (temp_assets/filename)
      coordinates: finalCoordinates,
      type: 'photo_update',
      timestamp: new Date().toISOString()
    };

    const suggestResponse = await fetch('suggest_photo_update.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(suggestionData)
    });

    const suggestResult = await suggestResponse.json();

    if (suggestResult.success) {
      closeUpdatePhotoModal();
      showAlert({
        type: 'success',
        title: 'Photo Submitted!',
        message: `Your photo for ${currentModalData.community} (${currentModalData.code}) has been submitted for review. Thank you for your contribution!`,
        buttons: [{
          text: 'OK',
          className: 'btn-alert-primary',
          onClick: () => {
            // Close all modals
            closeModal();
            closeUpdatePhotoModal();
            closeReportModal();
            // Reload suggestions to update button visibility
            loadSuggestions();
          }
        }]
      });
    } else {
      throw new Error(suggestResult.message || 'Failed to save suggestion');
    }
  } catch (error) {
    console.error('Error submitting photo:', error);
    updatePhotoStatus.textContent = error.message;
    updatePhotoStatus.style.color = 'var(--danger)';
    updatePhotoSubmitBtn.disabled = false;
    updatePhotoSubmitBtn.textContent = originalText;

    showAlert({
      type: 'error',
      title: 'Upload Failed',
      message: error.message || 'Failed to upload photo. Please try again.',
      buttons: [{
        text: 'OK',
        className: 'btn-alert-secondary'
      }]
    });
  }
});

loadData();
} // End of IS_LOGGED_IN check

// Theme Toggle Functionality (always available)
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
</script>
</body>
</html>