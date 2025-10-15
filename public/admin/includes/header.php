<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $page_title ?? 'Admin Dashboard' ?> · Gate Code</title>
<script>
// Apply theme immediately before any rendering to prevent flash
(function() {
  try {
    const savedTheme = localStorage.getItem('theme');
    const theme = savedTheme ? savedTheme : 'dark';
    document.documentElement.setAttribute('data-theme', theme);
    console.log('Theme loaded from localStorage:', savedTheme, 'Applied theme:', theme);
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
    --scrollbar-track:#0f141a; --scrollbar-thumb:#2a3340; --scrollbar-thumb-hover:#364456;
    --modal-bg-1:#1a1f26; --modal-bg-2:#12161c; --modal-border:#233041;
    --btn-secondary-bg:#22272f; --btn-secondary-text:#d0d7de; --btn-secondary-border:#2e3947;
    --btn-secondary-hover:#2a3240;
    --footer-bg:rgba(15,19,24,0.5);
    --sidebar-width:260px;
  }

  [data-theme="light"]{
    --bg:#f5f7fa; --panel:#ffffff; --panel-2:#f8f9fa;
    --text:#1a1f26; --muted:#5a6c7d; --brand:#3bdd82; --brand-2:#1bbf67;
    --danger:#ff5c5c; --danger-2:#e53935; --radius:14px;
    --gradient-1:#e0f5ee; --gradient-2:#d4ede2;
    --border:#d1dce5; --border-2:#e1e8ed; --line:#d1dce5;
    --input-bg-1:#ffffff; --input-bg-2:#f9fafb;
    --scrollbar-track:#e8eef4; --scrollbar-thumb:#c1ccd7; --scrollbar-thumb-hover:#a8b5c2;
    --modal-bg-1:#ffffff; --modal-bg-2:#f8f9fa; --modal-border:#d1dce5;
    --btn-secondary-bg:#f0f3f6; --btn-secondary-text:#2c3845; --btn-secondary-border:#d1dce5;
    --btn-secondary-hover:#e4e9ed;
    --footer-bg:rgba(255,255,255,0.5);
  }

  * { box-sizing: border-box; }

  html, body {
    height: 100%;
    margin: 0;
    font-family: system-ui, Segoe UI, Roboto, Arial;
    color: var(--text);
    background: var(--bg);
    transition: background 0.3s ease, color 0.3s ease;
    overflow: hidden;
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

  /* SIDEBAR */
  .sidebar {
    position: fixed;
    left: 0;
    top: 0;
    bottom: 0;
    width: var(--sidebar-width);
    background: linear-gradient(180deg, var(--panel), var(--panel-2));
    border-right: 1px solid var(--line);
    display: flex;
    flex-direction: column;
    z-index: 1000;
    transition: transform 0.3s ease;
  }

  .sidebar-header {
    padding: 24px 20px;
    border-bottom: 1px solid var(--line);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    flex-shrink: 0;
  }

  .sidebar-logo {
    font-size: 1.4rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 1px;
    background: linear-gradient(90deg, #3bdd82, #1bbf67);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    text-decoration: none;
    cursor: pointer;
    transition: opacity 0.2s ease, transform 0.2s ease;
    display: inline-block;
  }

  .sidebar-logo:hover {
    opacity: 0.8;
    transform: translateY(-1px);
  }

  .sidebar-logo:active {
    transform: translateY(0);
  }

  .theme-toggle-sidebar {
    background: var(--input-bg-1);
    border: 1px solid var(--border);
    border-radius: 8px;
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    flex-shrink: 0;
  }

  .theme-toggle-sidebar:hover {
    transform: scale(1.1);
    background: var(--panel-2);
  }

  .theme-toggle-sidebar svg {
    width: 18px;
    height: 18px;
    fill: var(--brand);
    transition: transform 0.3s ease;
  }

  .theme-toggle-sidebar:hover svg {
    transform: rotate(20deg);
  }

  .sidebar-nav {
    flex: 1;
    padding: 20px 0;
    overflow-y: auto;
  }

  .nav-item {
    padding: 12px 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    color: var(--muted);
    text-decoration: none;
    transition: all 0.2s ease;
    cursor: pointer;
    border-left: 3px solid transparent;
    position: relative;
  }

  .nav-item:hover {
    background: var(--panel-2);
    color: var(--text);
  }

  .nav-item.active {
    background: var(--panel-2);
    color: var(--brand);
    border-left-color: var(--brand);
    font-weight: 600;
  }

  .nav-icon {
    width: 20px;
    height: 20px;
    flex-shrink: 0;
  }

  .nav-badge {
    margin-left: auto;
    background: var(--danger);
    color: #fff;
    font-size: 11px;
    font-weight: 700;
    padding: 2px 6px;
    border-radius: 10px;
    min-width: 20px;
    text-align: center;
  }

  .sidebar-footer {
    padding: 16px 20px;
    border-top: 1px solid var(--line);
    flex-shrink: 0;
  }

  .version-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 6px;
  }

  .version-label {
    font-size: 0.75rem;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
  }

  .version-number {
    font-size: 0.85rem;
    color: var(--brand);
    font-weight: 700;
    font-family: 'Courier New', monospace;
  }

  .version-date {
    font-size: 0.7rem;
    color: var(--muted);
    text-align: center;
    opacity: 0.7;
  }

  /* MAIN CONTENT */
  .main-content {
    margin-left: var(--sidebar-width);
    height: 100vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
  }

  .content-body {
    flex: 1;
    padding: 32px 32px 60px 32px;
    overflow-y: auto;
    overflow-x: hidden;
    min-height: 0;
  }

  /* PAGE HEADER */
  .page-header {
    flex-shrink: 0;
    margin-bottom: 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    background: var(--panel);
    border: 1px solid var(--line);
    border-radius: 12px;
    padding: 20px 24px;
  }

  .page-header-left {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 16px;
  }

  .page-header-content {
    flex: 1;
  }

  .page-header-right {
    flex-shrink: 0;
  }

  .page-title {
    font-size: 2rem;
    font-weight: 700;
    color: var(--text);
    margin: 0 0 8px 0;
  }

  .page-subtitle {
    font-size: 1rem;
    color: var(--muted);
    margin: 0;
  }

  /* CARDS */
  .card {
    background: linear-gradient(180deg, var(--panel), var(--panel-2));
    border: 1px solid var(--line);
    border-radius: var(--radius);
    padding: 24px;
    margin-bottom: 24px;
    height: 100%;
  }

  .card-title {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0 0 16px 0;
    color: var(--text);
  }

  /* FORMS */
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

  .form-group {
    margin-bottom: 20px;
  }

  .form-label {
    display: block;
    margin-bottom: 8px;
    color: var(--text);
    font-weight: 600;
    font-size: 0.95rem;
  }

  /* BUTTONS */
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

  /* SCROLLBAR */
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

  /* RESPONSIVE */
  .mobile-menu-toggle {
    display: none;
    background: transparent;
    border: none;
    width: 40px;
    height: 40px;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    padding: 0;
    color: var(--text);
  }

  .mobile-menu-toggle svg {
    transition: transform 0.3s ease;
  }

  .mobile-menu-toggle:hover svg {
    transform: scale(1.1);
  }

  @media (max-width: 768px) {
    .sidebar {
      transform: translateX(-100%);
    }

    .sidebar.open {
      transform: translateX(0);
    }

    .main-content {
      margin-left: 0;
    }

    .mobile-menu-toggle {
      display: flex;
    }

    .content-body {
      padding: 20px;
    }

    .page-header {
      flex-direction: column;
      align-items: stretch;
      padding: 16px;
      gap: 16px;
    }

    .page-header-left {
      gap: 12px;
    }

    .page-header-content {
      flex: 1;
      min-width: 0;
    }

    .page-title {
      font-size: 1.5rem;
      margin: 0 0 4px 0;
    }

    .page-subtitle {
      font-size: 0.875rem;
    }

    .page-header-right {
      width: 100%;
    }

    .page-header-right .btn {
      width: 100%;
    }
  }

  /* ALERT MODAL */
  .alert-modal {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, .7);
    backdrop-filter: blur(4px);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 10000;
    padding: 20px;
    opacity: 0;
    transition: opacity 0.2s ease;
  }

  .alert-modal.open {
    display: flex;
    opacity: 1;
  }

  .alert-modal-content {
    width: min(90vw, 400px);
    background: linear-gradient(180deg, var(--modal-bg-1), var(--modal-bg-2));
    border: 1px solid var(--modal-border);
    border-radius: 12px;
    padding: 24px;
    text-align: center;
    box-shadow: 0 20px 60px rgba(0, 0, 0, .5);
  }

  .alert-modal-icon {
    width: 64px;
    height: 64px;
    margin: 0 auto 16px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .alert-modal-icon.success {
    background: linear-gradient(135deg, rgba(59, 221, 130, 0.2), rgba(27, 191, 103, 0.15));
  }

  .alert-modal-icon.error {
    background: linear-gradient(135deg, rgba(255, 92, 92, 0.2), rgba(229, 57, 53, 0.15));
  }

  .alert-modal-icon.warning {
    background: linear-gradient(135deg, rgba(255, 152, 0, 0.2), rgba(245, 124, 0, 0.15));
  }

  .alert-icon-svg {
    width: 32px;
    height: 32px;
  }

  .alert-modal-title {
    font-size: 1.25rem;
    font-weight: 700;
    margin-bottom: 8px;
    color: var(--text);
  }

  .alert-modal-message {
    font-size: 0.95rem;
    color: var(--muted);
    margin-bottom: 24px;
    line-height: 1.5;
  }

  .alert-modal-actions {
    display: flex;
    gap: 12px;
    justify-content: center;
  }

  .btn-alert {
    padding: 12px 24px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 15px;
    cursor: pointer;
    transition: all 0.2s ease;
    border: 0;
  }

  .btn-alert-primary {
    background: linear-gradient(135deg, var(--brand), var(--brand-2));
    color: #07140c;
    box-shadow: 0 4px 14px rgba(59, 221, 130, .4);
  }

  .btn-alert-primary:hover {
    box-shadow: 0 6px 18px rgba(59, 221, 130, .55);
    transform: translateY(-1px);
  }

  .btn-alert-danger {
    background: linear-gradient(135deg, var(--danger), var(--danger-2));
    color: #fff;
    box-shadow: 0 4px 14px rgba(255, 92, 92, .4);
  }

  .btn-alert-danger:hover {
    box-shadow: 0 6px 18px rgba(255, 92, 92, .55);
    transform: translateY(-1px);
  }

  .btn-alert-secondary {
    background: var(--btn-secondary-bg);
    color: var(--btn-secondary-text);
    border: 1px solid var(--btn-secondary-border);
  }

  .btn-alert-secondary:hover {
    background: var(--btn-secondary-hover);
  }
</style>
</head>
<body>

<?php require_once __DIR__ . '/sidebar.php'; ?>

<!-- MAIN CONTENT -->
<main class="main-content">
  <div class="content-body">
