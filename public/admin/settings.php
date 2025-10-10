<?php
require_once __DIR__ . '/includes/config.php';
require_key();

// Set current page for sidebar active state
$current_page = 'settings';

// READ DATA
$data = read_json(GATES_JSON);
$suggestions = read_json(SUGGEST_JSON);
$pins = read_json(PIN_JSON);

// FLASH MESSAGE
$flashMsg = $_GET['msg'] ?? '';
$errorMsg = $_GET['error'] ?? '';

require_once __DIR__ . '/includes/header.php';
?>

<!-- SETTINGS PAGE CONTENT -->
<div class="page-header">
  <h1 class="page-title">Settings</h1>
  <p class="page-subtitle">Configure your dashboard preferences</p>
</div>

<div class="settings-container">
  <div class="settings-scroll-wrapper">
    <div class="card">
      <h2 class="card-title">General Settings</h2>
      <p style="color: var(--muted); margin-bottom: 20px;">Dashboard configuration options will be available here.</p>

      <div class="settings-info">
        <div class="info-item">
          <div class="info-label">Theme</div>
          <div class="info-value">Use the toggle button in the sidebar to switch between dark and light mode</div>
        </div>

        <div class="info-item">
          <div class="info-label">Total Communities</div>
          <div class="info-value"><?= count($data) ?></div>
        </div>

        <div class="info-item">
          <div class="info-label">Pending Contributions</div>
          <div class="info-value"><?= count($suggestions) ?></div>
        </div>

        <div class="info-item">
          <div class="info-label">Registered Users</div>
          <div class="info-value"><?= count($pins) ?></div>
        </div>
      </div>
    </div>

    <div class="card">
      <h2 class="card-title">About</h2>
      <div class="about-content">
        <p style="color: var(--text); margin-bottom: 12px;">
          <strong>Gate Codes Admin Dashboard</strong>
        </p>
        <p style="color: var(--muted); line-height: 1.6;">
          This dashboard allows you to manage community gate codes, review user contributions,
          manage user access via PINs, and backup your data.
        </p>
      </div>
    </div>
  </div>
</div>

<style>
.page-header {
  flex-shrink: 0;
  margin-bottom: 24px;
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

.settings-container {
  display: flex;
  flex-direction: column;
  flex: 1;
  overflow: hidden;
  min-height: 0;
}

.settings-scroll-wrapper {
  flex: 1;
  overflow-y: auto;
  overflow-x: hidden;
  padding-right: 8px;
  min-height: 0;
}

.card {
  margin-bottom: 24px;
}

.card:last-child {
  margin-bottom: 0;
}

.settings-info {
  display: grid;
  gap: 20px;
}

.info-item {
  padding: 16px 20px;
  background: var(--panel-2);
  border: 1px solid var(--border);
  border-radius: 12px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
}

.info-label {
  font-weight: 600;
  color: var(--text);
  font-size: 0.95rem;
}

.info-value {
  color: var(--muted);
  font-size: 0.95rem;
  text-align: right;
}

.about-content {
  padding: 12px 0;
}

@media (max-width: 768px) {
  .info-item {
    flex-direction: column;
    align-items: flex-start;
  }

  .info-value {
    text-align: left;
  }

  .page-header {
    margin-bottom: 16px;
  }

  .settings-scroll-wrapper {
    padding-right: 4px;
  }
}
</style>

<script>
// Show flash messages
<?php if ($flashMsg): ?>
  showAlert('<?= addslashes($flashMsg) ?>', 'Success');
<?php endif; ?>

<?php if ($errorMsg): ?>
  showAlert('<?= addslashes($errorMsg) ?>', 'Error');
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
