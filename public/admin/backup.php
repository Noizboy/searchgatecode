<?php
require_once __DIR__ . '/includes/config.php';
require_key();

// Set current page for sidebar active state
$current_page = 'backup';

// READ DATA
$data = read_json(GATES_JSON);
$suggestions = read_json(SUGGEST_JSON);
$pins = read_json(PIN_JSON);

/******************** ACTIONS ********************/

// DOWNLOAD JSON
if (isset($_GET['action']) && $_GET['action'] === 'download_json') {
  $data = read_json(GATES_JSON);
  header('Content-Type: application/json');
  header('Content-Disposition: attachment; filename="gates_backup_' . date('Ymd_His') . '.json"');
  echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  // UPLOAD JSON
  if ($action === 'upload_json') {
    if (!isset($_FILES['json_file']) || $_FILES['json_file']['error'] !== UPLOAD_ERR_OK) {
      header('Location: backup.php?key=' . urlencode(ADMIN_KEY) . '&error=' . urlencode('No file uploaded or upload error'));
      exit;
    }

    $file = $_FILES['json_file'];
    $content = file_get_contents($file['tmp_name']);
    $newData = json_decode($content, true);

    if (!is_array($newData)) {
      header('Location: backup.php?key=' . urlencode(ADMIN_KEY) . '&error=' . urlencode('Invalid JSON file'));
      exit;
    }

    // Create backup of current file
    $backupPath = GATES_JSON . '.backup_' . date('Ymd_His');
    $currentData = read_json(GATES_JSON);
    file_put_contents($backupPath, json_encode($currentData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    // Write new data
    write_json(GATES_JSON, $newData);

    header('Location: backup.php?key=' . urlencode(ADMIN_KEY) . '&msg=' . urlencode('JSON uploaded successfully. Previous data backed up.'));
    exit;
  }
}

// FLASH MESSAGE
$flashMsg = $_GET['msg'] ?? '';
$errorMsg = $_GET['error'] ?? '';

require_once __DIR__ . '/includes/header.php';
?>

<!-- BACKUP PAGE CONTENT -->
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
      <h1 class="page-title">Backup & Restore</h1>
      <p class="page-subtitle">Download or upload your gates.json data</p>
    </div>
  </div>
</div>

<div class="backup-container">
  <div class="backup-scroll-wrapper">
    <div class="card">
      <h2 class="card-title">Download Backup</h2>
      <p style="color: var(--muted); margin-bottom: 16px;">Download a backup copy of your gates.json file.</p>
      <a href="?key=<?= urlencode(ADMIN_KEY) ?>&action=download_json" class="btn btn-primary" download>📥 Download gates.json</a>
    </div>

    <div class="card">
      <h2 class="card-title">Upload JSON</h2>
      <p style="color: var(--muted); margin-bottom: 16px;">Upload a gates.json file to replace the current data. A backup will be created automatically.</p>

      <form method="post" enctype="multipart/form-data" id="uploadJsonForm">
        <input type="hidden" name="key" value="<?= htmlspecialchars(ADMIN_KEY) ?>">
        <input type="hidden" name="action" value="upload_json">

        <div class="file-upload-wrapper">
          <input type="file" name="json_file" id="jsonFileInput" accept=".json,application/json" required style="display: none;">
          <label for="jsonFileInput" class="file-upload-label">📤 Choose JSON File</label>
          <span class="file-name" id="fileName">No file chosen</span>
        </div>

        <div class="btn-group" style="margin-top: 20px;">
          <button type="submit" class="btn btn-primary" id="uploadBtn" disabled>Upload & Replace</button>
        </div>
      </form>
    </div>

    <div class="card" style="background: var(--panel-2); border-left: 4px solid var(--warning);">
      <h2 class="card-title" style="color: var(--warning);">⚠️ Important Notes</h2>
      <ul style="margin: 12px 0 0 20px; color: var(--text); line-height: 1.8;">
        <li>Uploading a new JSON file will replace all current data</li>
        <li>A backup of the current data will be created automatically</li>
        <li>Make sure the JSON file has the correct structure</li>
        <li>Backups are stored in the data directory with timestamp</li>
      </ul>
    </div>
  </div>
</div>

<style>
.backup-container {
  display: flex;
  flex-direction: column;
  flex: 1;
  overflow: hidden;
  min-height: 0;
}

.backup-scroll-wrapper {
  flex: 1;
  overflow-y: auto;
  overflow-x: hidden;
  padding-right: 8px;
  padding-bottom: 100px;
  min-height: 0;
}

.card {
  margin-bottom: 24px;
}

.card:last-child {
  margin-bottom: 0;
}

.file-upload-wrapper {
  display: flex;
  align-items: center;
  gap: 16px;
  flex-wrap: wrap;
}

.file-upload-label {
  display: inline-block;
  padding: 12px 24px;
  background: var(--brand);
  color: white;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 500;
  transition: all 0.2s;
}

.file-upload-label:hover {
  background: var(--brand-dark);
  transform: translateY(-1px);
}

.file-name {
  color: var(--muted);
  font-size: 0.9rem;
  font-style: italic;
}

.file-name.selected {
  color: var(--text);
  font-style: normal;
  font-weight: 500;
}

@media (max-width: 768px) {
  .file-upload-wrapper {
    flex-direction: column;
    align-items: flex-start;
  }

  .backup-scroll-wrapper {
    padding-right: 4px;
  }
}
</style>

<script>
const jsonFileInput = document.getElementById('jsonFileInput');
const fileName = document.getElementById('fileName');
const uploadBtn = document.getElementById('uploadBtn');
const uploadForm = document.getElementById('uploadJsonForm');

jsonFileInput.addEventListener('change', (e) => {
  if (e.target.files.length > 0) {
    const file = e.target.files[0];
    fileName.textContent = file.name;
    fileName.classList.add('selected');
    uploadBtn.disabled = false;
  } else {
    fileName.textContent = 'No file chosen';
    fileName.classList.remove('selected');
    uploadBtn.disabled = true;
  }
});

uploadForm.addEventListener('submit', (e) => {
  e.preventDefault();

  showAlert({
    type: 'warning',
    title: 'Replace Data',
    message: 'Are you sure you want to replace the current data? A backup will be created automatically.',
    buttons: [
      {
        text: 'No',
        className: 'btn-alert-secondary'
      },
      {
        text: 'Yes',
        className: 'btn-alert-primary',
        onClick: () => {
          uploadForm.submit();
        }
      }
    ]
  });
});

// Show flash messages
<?php if ($flashMsg): ?>
  showAlert('<?= addslashes($flashMsg) ?>', 'Success');
<?php endif; ?>

<?php if ($errorMsg): ?>
  showAlert('<?= addslashes($errorMsg) ?>', 'Error');
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
