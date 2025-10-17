<?php
require_once __DIR__ . '/includes/config.php';
require_key();

// Set current page for sidebar active state
$current_page = 'settings';

// READ USER DATA
$pins = read_json(PIN_JSON);

// CHECK ADMIN ACCESS
$is_admin = false;
$current_user_pin = $_SESSION['user_pin'] ?? '';

foreach ($pins as $user) {
    if ($user['pin'] === $current_user_pin) {
        if (isset($user['role']) && $user['role'] === 'admin') {
            $is_admin = true;
        }
        break;
    }
}

// Redirect if not admin
if (!$is_admin) {
    header('Location: index.php?error=' . urlencode('Access denied. Admin privileges required.'));
    exit;
}

// SETTINGS FILE PATH
define('SETTINGS_JSON', __DIR__ . '/../data/settings.json');

// LOAD SETTINGS
function load_settings() {
    if (file_exists(SETTINGS_JSON)) {
        $content = file_get_contents(SETTINGS_JSON);
        $data = json_decode($content, true);
        if (is_array($data)) {
            return $data;
        }
    }

    // Default settings
    return [
        'admin_email' => '',
        'site_title' => 'Gate Code',
        'timezone' => 'America/New_York',
        'date_format' => 'Y-m-d H:i:s',
        'require_pin' => true  // Require PIN for public pages by default
    ];
}

// SAVE SETTINGS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $admin_email = trim($_POST['admin_email'] ?? '');
    $site_title = trim($_POST['site_title'] ?? 'Gate Code');
    $timezone = trim($_POST['timezone'] ?? 'America/New_York');
    $date_format = trim($_POST['date_format'] ?? 'Y-m-d H:i:s');
    $require_pin = isset($_POST['require_pin']) && $_POST['require_pin'] === '1';

    // Validate site title
    if (empty($site_title)) {
        $site_title = 'Gate Code';
    }

    // Validate email if provided
    if (!empty($admin_email) && !filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
        header('Location: settings.php?error=' . urlencode('Invalid email address'));
        exit;
    }

    $settings = [
        'admin_email' => $admin_email,
        'site_title' => $site_title,
        'timezone' => $timezone,
        'date_format' => $date_format,
        'require_pin' => $require_pin,
        'updated_at' => date('Y-m-d H:i:s'),
        'updated_by' => $_SESSION['user_name'] ?? 'Admin'
    ];

    if (file_put_contents(SETTINGS_JSON, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))) {
        header('Location: settings.php?msg=' . urlencode('Settings saved successfully'));
        exit;
    } else {
        header('Location: settings.php?error=' . urlencode('Failed to save settings'));
        exit;
    }
}

$settings = load_settings();

// FLASH MESSAGE
$flashMsg = $_GET['msg'] ?? '';
$errorMsg = $_GET['error'] ?? '';

require_once __DIR__ . '/includes/header.php';
?>

<!-- SETTINGS PAGE CONTENT -->
<div class="page-header">
  <div class="page-header-left">
    <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Toggle menu">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <line x1="3" y1="12" x2="21" y2="12"/>
        <line x1="3" y1="6" x2="21" y2="6"/>
        <line x1="3" y1="18" x2="21" y2="18"/>
      </svg>
    </button>
    <div class="page-header-content">
      <h1 class="page-title">
        Settings
        <span class="super-admin-badge">ADMIN</span>
      </h1>
      <p class="page-subtitle">System configuration and preferences</p>
    </div>
  </div>
</div>

<div class="settings-container">
  <div class="settings-scroll-wrapper">
    <form method="POST" action="settings.php" id="settingsForm">
      <input type="hidden" name="save_settings" value="1">

      <!-- SITE TITLE -->
      <div class="card">
        <h2 class="card-title">Site Title</h2>
        <p class="card-subtitle">Customize the name of your application</p>

        <div class="form-group">
          <label for="site_title">Site Title</label>
          <input
            type="text"
            id="site_title"
            name="site_title"
            class="form-input"
            value="<?= htmlspecialchars($settings['site_title'] ?? 'Gate Code') ?>"
            placeholder="Gate Code"
            maxlength="50"
          >
          <small class="form-help">This will appear in the page title and header</small>
        </div>
      </div>

      <!-- ADMINISTRATOR EMAIL -->
      <div class="card">
        <h2 class="card-title">Administrator Email</h2>
        <p class="card-subtitle">Primary contact email for system notifications and alerts</p>

        <div class="form-group">
          <label for="admin_email">Email Address</label>
          <input
            type="email"
            id="admin_email"
            name="admin_email"
            class="form-input"
            value="<?= htmlspecialchars($settings['admin_email']) ?>"
            placeholder="admin@example.com"
          >
          <small class="form-help">Leave empty to disable email notifications</small>
        </div>
      </div>

      <!-- TIMEZONE -->
      <div class="card">
        <h2 class="card-title">Timezone</h2>
        <p class="card-subtitle">Set the timezone for displaying dates and times</p>

        <div class="form-group">
          <label for="timezone">Select Timezone</label>
          <select id="timezone" name="timezone" class="form-select">
            <optgroup label="US Timezones">
              <option value="America/New_York" <?= $settings['timezone'] === 'America/New_York' ? 'selected' : '' ?>>Eastern Time (ET)</option>
              <option value="America/Chicago" <?= $settings['timezone'] === 'America/Chicago' ? 'selected' : '' ?>>Central Time (CT)</option>
              <option value="America/Denver" <?= $settings['timezone'] === 'America/Denver' ? 'selected' : '' ?>>Mountain Time (MT)</option>
              <option value="America/Phoenix" <?= $settings['timezone'] === 'America/Phoenix' ? 'selected' : '' ?>>Arizona Time (MST)</option>
              <option value="America/Los_Angeles" <?= $settings['timezone'] === 'America/Los_Angeles' ? 'selected' : '' ?>>Pacific Time (PT)</option>
              <option value="America/Anchorage" <?= $settings['timezone'] === 'America/Anchorage' ? 'selected' : '' ?>>Alaska Time (AKT)</option>
              <option value="Pacific/Honolulu" <?= $settings['timezone'] === 'Pacific/Honolulu' ? 'selected' : '' ?>>Hawaii Time (HST)</option>
            </optgroup>
            <optgroup label="International">
              <option value="UTC" <?= $settings['timezone'] === 'UTC' ? 'selected' : '' ?>>UTC (Coordinated Universal Time)</option>
              <option value="Europe/London" <?= $settings['timezone'] === 'Europe/London' ? 'selected' : '' ?>>London (GMT/BST)</option>
              <option value="Europe/Paris" <?= $settings['timezone'] === 'Europe/Paris' ? 'selected' : '' ?>>Paris (CET/CEST)</option>
              <option value="Europe/Madrid" <?= $settings['timezone'] === 'Europe/Madrid' ? 'selected' : '' ?>>Madrid (CET/CEST)</option>
              <option value="Asia/Tokyo" <?= $settings['timezone'] === 'Asia/Tokyo' ? 'selected' : '' ?>>Tokyo (JST)</option>
              <option value="Asia/Shanghai" <?= $settings['timezone'] === 'Asia/Shanghai' ? 'selected' : '' ?>>Shanghai (CST)</option>
              <option value="Australia/Sydney" <?= $settings['timezone'] === 'Australia/Sydney' ? 'selected' : '' ?>>Sydney (AEDT/AEST)</option>
            </optgroup>
          </select>
          <small class="form-help">Current server time: <strong id="currentTime"><?= date('Y-m-d H:i:s') ?></strong></small>
        </div>
      </div>

      <!-- DATE FORMAT -->
      <div class="card">
        <h2 class="card-title">Date Format</h2>
        <p class="card-subtitle">Choose how dates are displayed throughout the system</p>

        <div class="form-group">
          <div class="radio-group">
            <?php
            $now = time();
            $formats = [
                'Y-m-d H:i:s' => date('Y-m-d H:i:s', $now),
                'M d, Y g:i A' => date('M d, Y g:i A', $now),
                'd/m/Y H:i' => date('d/m/Y H:i', $now),
                'm/d/Y h:i A' => date('m/d/Y h:i A', $now),
                'F j, Y, g:i a' => date('F j, Y, g:i a', $now)
            ];

            foreach ($formats as $format => $example) {
                $checked = $settings['date_format'] === $format ? 'checked' : '';
                echo '<label class="radio-label">';
                echo '<input type="radio" name="date_format" value="' . htmlspecialchars($format) . '" ' . $checked . '>';
                echo '<div class="radio-content">';
                echo '<div class="radio-title">' . htmlspecialchars($format) . '</div>';
                echo '<div class="radio-example" data-format="' . htmlspecialchars($format) . '">' . htmlspecialchars($example) . '</div>';
                echo '</div>';
                echo '</label>';
            }
            ?>
          </div>
        </div>
      </div>

      <!-- PUBLIC ACCESS -->
      <div class="card">
        <h2 class="card-title">Public Access Control</h2>
        <p class="card-subtitle">Control access to public pages (search and submit)</p>

        <div class="form-group">
          <div class="checkbox-wrapper">
            <input type="checkbox" name="require_pin" value="1" id="require_pin" <?= ($settings['require_pin'] ?? true) ? 'checked' : '' ?> class="checkbox-input">
            <label for="require_pin" class="checkbox-label">
              <span class="checkbox-text">
                <strong>Require PIN for Public Pages</strong>
                <small class="checkbox-description">When enabled, users must enter a PIN to access search and submit pages</small>
              </span>
            </label>
          </div>
        </div>
      </div>

      <!-- SAVE BUTTON -->
      <div class="card">
        <button type="submit" class="btn btn-primary btn-block">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 8px;">
            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
            <polyline points="17 21 17 13 7 13 7 21"></polyline>
            <polyline points="7 3 7 8 15 8"></polyline>
          </svg>
          Save Settings
        </button>

        <?php if (isset($settings['updated_at'])): ?>
        <p class="last-updated">
          Last updated: <?= htmlspecialchars($settings['updated_at']) ?>
          <?php if (isset($settings['updated_by'])): ?>
            by <?= htmlspecialchars($settings['updated_by']) ?>
          <?php endif; ?>
        </p>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<style>
.settings-container {
  display: flex;
  flex-direction: column;
  flex: 1;
  overflow: hidden;
  min-height: 0;
  margin-bottom: 40px;
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

.card-subtitle {
  color: var(--muted);
  font-size: 0.9rem;
  margin-bottom: 20px;
}

.super-admin-badge {
  display: inline-block;
  background: linear-gradient(135deg, #3bdd82, #2bc76a);
  color: white;
  font-size: 0.65rem;
  font-weight: 700;
  padding: 4px 10px;
  border-radius: 6px;
  margin-left: 12px;
  vertical-align: middle;
  letter-spacing: 0.5px;
}

.form-group {
  margin-bottom: 0;
}

.form-group label {
  display: block;
  font-weight: 600;
  color: var(--text);
  margin-bottom: 10px;
  font-size: 0.95rem;
}

.form-input,
.form-select {
  width: 100%;
  padding: 12px 16px;
  background: var(--panel-2);
  border: 1px solid var(--border);
  border-radius: 10px;
  color: var(--text);
  font-size: 0.95rem;
  transition: all 0.2s ease;
  font-family: inherit;
}

.form-input:focus,
.form-select:focus {
  outline: none;
  border-color: var(--brand);
  box-shadow: 0 0 0 3px rgba(59, 221, 130, 0.1);
}

.form-help {
  display: block;
  color: var(--muted);
  font-size: 0.85rem;
  margin-top: 8px;
}

.form-help strong {
  color: var(--brand);
  font-family: 'Courier New', monospace;
}

.radio-group {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.radio-label {
  display: flex;
  align-items: flex-start;
  padding: 16px;
  background: var(--panel-2);
  border: 2px solid var(--border);
  border-radius: 10px;
  cursor: pointer;
  transition: all 0.2s ease;
  gap: 12px;
}

.radio-label:hover {
  border-color: var(--brand);
  background: var(--panel);
}

.radio-label input[type="radio"] {
  margin-top: 3px;
  cursor: pointer;
  accent-color: var(--brand);
  width: 18px;
  height: 18px;
  flex-shrink: 0;
}

.radio-label input[type="radio"]:checked + .radio-content .radio-title {
  color: var(--brand);
}

.radio-content {
  flex: 1;
}

.radio-title {
  font-weight: 600;
  color: var(--text);
  margin-bottom: 4px;
  font-family: 'Courier New', monospace;
  font-size: 0.9rem;
}

.radio-example {
  color: var(--muted);
  font-size: 0.85rem;
}

.btn-block {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 14px 20px;
  font-size: 1rem;
  font-weight: 600;
}

.last-updated {
  text-align: center;
  color: var(--muted);
  font-size: 0.85rem;
  margin-top: 16px;
  margin-bottom: 0;
}

/* Checkbox Styles */
.checkbox-wrapper {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 16px;
  background: var(--panel-2);
  border: 1px solid var(--border);
  border-radius: 10px;
}

.checkbox-input {
  margin-top: 4px;
  cursor: pointer;
  accent-color: var(--brand);
  width: 20px;
  height: 20px;
  flex-shrink: 0;
}

.checkbox-label {
  cursor: pointer;
  flex: 1;
  margin: 0;
}

.checkbox-text {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.checkbox-text strong {
  color: var(--text);
  font-weight: 600;
  font-size: 0.95rem;
  line-height: 1.4;
}

.checkbox-description {
  color: var(--muted);
  font-size: 0.85rem;
  font-weight: normal;
  display: block;
  line-height: 1.5;
}

@media (max-width: 768px) {
  .settings-scroll-wrapper {
    padding-right: 4px;
  }

  .super-admin-badge {
    display: block;
    margin-left: 0;
    margin-top: 8px;
    width: fit-content;
  }

  .card {
    margin-bottom: 20px;
  }

  .checkbox-wrapper {
    padding: 12px;
    gap: 10px;
  }

  .checkbox-input {
    width: 18px;
    height: 18px;
    margin-top: 2px;
  }

  .checkbox-text strong {
    font-size: 0.9rem;
  }

  .checkbox-description {
    font-size: 0.8rem;
  }
}
</style>

<script>
// Show flash messages
<?php if ($flashMsg): ?>
  showAlert({
    type: 'success',
    title: 'Success',
    message: '<?= addslashes($flashMsg) ?>',
    buttons: [{
      text: 'OK',
      className: 'btn-alert-primary'
    }]
  });
<?php endif; ?>

<?php if ($errorMsg): ?>
  showAlert({
    type: 'error',
    title: 'Error',
    message: '<?= addslashes($errorMsg) ?>',
    buttons: [{
      text: 'OK',
      className: 'btn-alert-primary'
    }]
  });
<?php endif; ?>

// Update live time examples when date format changes
document.querySelectorAll('input[name="date_format"]').forEach(radio => {
  radio.addEventListener('change', function() {
    updateTimeExamples();
  });
});

// Update time every second
setInterval(updateCurrentTime, 1000);

function updateCurrentTime() {
  const now = new Date();
  const formatted = now.getFullYear() + '-' +
    String(now.getMonth() + 1).padStart(2, '0') + '-' +
    String(now.getDate()).padStart(2, '0') + ' ' +
    String(now.getHours()).padStart(2, '0') + ':' +
    String(now.getMinutes()).padStart(2, '0') + ':' +
    String(now.getSeconds()).padStart(2, '0');

  const currentTimeEl = document.getElementById('currentTime');
  if (currentTimeEl) {
    currentTimeEl.textContent = formatted;
  }
}

function updateTimeExamples() {
  // This would require server-side update or complex JS date formatting
  // For now, examples are static from PHP
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
