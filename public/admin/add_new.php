<?php
require_once __DIR__ . '/includes/config.php';

// Set current page for sidebar active state
$current_page = 'add-new';

// READ DATA
$data = read_json(GATES_JSON);
$suggestions = read_json(SUGGEST_JSON);
$pins = read_json(PIN_JSON);

/******************** ACTIONS ********************/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  // ADD COMMUNITY
  if ($action === 'add') {
    $community = trim($_POST['community'] ?? '');
    $codesArr = $_POST['codes'] ?? [];

    if ($community) {
      $codes = [];
      foreach ($codesArr as $c) {
        $code = trim($c['code'] ?? '');
        if ($code) {
          $codes[] = [
            'code' => $code,
            'notes' => trim($c['notes'] ?? ''),
            'details' => trim($c['details'] ?? ''),
            'photo' => trim($c['photo'] ?? '')
          ];
        }
      }

      $idx = find_comm_index($data, $community);

      if ($idx >= 0) {
        // Community exists - check for duplicate codes
        $existing_codes = array_column($data[$idx]['codes'], 'code');
        $added_count = 0;

        foreach ($codes as $new_code) {
          if (!in_array($new_code['code'], $existing_codes)) {
            $data[$idx]['codes'][] = $new_code;
            $added_count++;
          }
        }

        if ($added_count > 0) {
          write_json(GATES_JSON, $data);
          header('Location: add_new.php?key=' . urlencode(ADMIN_KEY) . '&msg=' . urlencode("$added_count code(s) added to existing community"));
          exit;
        } else {
          header('Location: add_new.php?key=' . urlencode(ADMIN_KEY) . '&msg=' . urlencode('All codes already exist in this community'));
          exit;
        }
      } else {
        // New community
        $data[] = [
          'community' => $community,
          'codes' => $codes
        ];
        write_json(GATES_JSON, $data);
        header('Location: add_new.php?key=' . urlencode(ADMIN_KEY) . '&msg=' . urlencode('Community added successfully'));
        exit;
      }
    }
  }
}

// AJAX UPLOAD
if (isset($_GET['ajax']) && $_GET['ajax'] === 'upload') {
  header('Content-Type: application/json');

  if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['status' => 'error', 'error' => 'No file uploaded or upload error']);
    exit;
  }

  $file = $_FILES['photo'];
  $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

  // Get community name for filename
  $community_name = $_POST['community_name'] ?? '';
  if ($community_name) {
    // Sanitize community name for filename
    $safe_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', strtolower($community_name));
    $base = 'gate_' . $safe_name . '_' . date('Ymd_His');
  } else {
    $base = 'gate_' . date('Ymd_His') . '_' . substr(md5(uniqid()), 0, 6);
  }

  $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'heic'];
  if (!in_array($ext, $allowedExts)) {
    echo json_encode(['status' => 'error', 'error' => 'Invalid file type']);
    exit;
  }

  $filename = $base . '.' . $ext;
  $destination = ASSETS_DIR . $filename;

  if (move_uploaded_file($file['tmp_name'], $destination)) {
    echo json_encode(['status' => 'ok', 'url' => ASSETS_RELATIVE . $filename]);
  } else {
    echo json_encode(['status' => 'error', 'error' => 'Failed to save file']);
  }
  exit;
}

// FLASH MESSAGE
$flashMsg = $_GET['msg'] ?? '';

require_once __DIR__ . '/includes/header.php';
?>

<!-- ADD NEW PAGE CONTENT -->
<div class="page-header">
  <h1 class="page-title">Add New Community</h1>
  <p class="page-subtitle">Add a new community with gate codes</p>
</div>

<div class="add-new-container">
  <div class="card add-new-card">
    <form method="post" id="addForm">
      <input type="hidden" name="key" value="<?= htmlspecialchars(ADMIN_KEY) ?>">
      <input type="hidden" name="action" value="add">

      <!-- STATIC HEADER -->
      <div class="add-new-header">
        <div class="form-group">
          <label class="form-label">Community Name</label>
          <input type="text" class="field" name="community" id="communityNameInput" placeholder="e.g., Water Oaks" required>
        </div>

        <div class="form-group">
          <label class="form-label">Codes</label>
        </div>
      </div>

      <!-- SCROLLABLE CODES AREA -->
      <div class="codes-scroll-wrapper">
        <div class="codes-editor" id="codesEditor"></div>
      </div>

      <!-- STATIC FOOTER -->
      <div class="add-new-footer">
        <button type="button" class="btn" id="addCodeBtn">+ Add Code</button>
        <div class="btn-group" style="margin-top: 16px;">
          <button type="button" class="btn btn-primary" id="submitAddForm">Add Community</button>
        </div>
      </div>
    </form>
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

/* ADD NEW CONTAINER */
.add-new-container {
  display: flex;
  flex-direction: column;
  flex: 1;
  overflow: hidden;
  min-height: 0;
}

.add-new-card {
  display: flex;
  flex-direction: column;
  height: 100%;
  overflow: hidden;
  margin-bottom: 0;
}

.add-new-card form {
  display: flex;
  flex-direction: column;
  height: 100%;
  overflow: hidden;
}

.add-new-header {
  flex-shrink: 0;
  padding-bottom: 16px;
  border-bottom: 1px solid var(--line);
  margin-bottom: 16px;
}

.add-new-header .form-group:last-child {
  margin-bottom: 0;
}

.codes-scroll-wrapper {
  flex: 1;
  overflow-y: auto;
  overflow-x: hidden;
  padding-right: 8px;
  min-height: 0;
  margin-bottom: 16px;
}

.add-new-footer {
  flex-shrink: 0;
  padding-top: 16px;
  border-top: 1px solid var(--line);
}

.codes-editor {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.code-edit-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  padding: 20px;
  background: var(--panel-2);
  border: 1px solid var(--border);
  border-radius: 12px;
}

.code-edit-row .full-width {
  grid-column: 1 / -1;
}

.preview-box {
  display: none;
  margin-top: 12px;
  border: 2px solid var(--border);
  border-radius: 8px;
  overflow: hidden;
  max-width: 300px;
}

.preview-box.show {
  display: block;
}

.preview-thumb {
  width: 100%;
  height: auto;
  display: block;
}

.edit-file-input {
  display: none;
}

.file-upload-label {
  display: inline-block;
  padding: 10px 20px;
  background: var(--brand);
  color: white;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 500;
  transition: all 0.2s;
}

.file-upload-label:hover {
  background: var(--brand-2);
  transform: translateY(-1px);
}

.upload-status {
  margin-top: 8px;
  font-size: 0.85rem;
  color: var(--muted);
}
</style>

<script>
const ADMIN_KEY = '<?= ADMIN_KEY ?>';
const ASSETS_URL = '<?= ASSETS_URL ?>';

// ADD CODE ROWS
let codeIndex = 0;

function createCodeRow(index) {
  const div = document.createElement('div');
  div.className = 'code-edit-row';
  div.innerHTML = `
    <div class="form-group">
      <label class="form-label">Code</label>
      <input type="text" class="field" name="codes[${index}][code]" placeholder="e.g., #54839*" required maxlength="8" pattern="[A-Za-z0-9#*]{1,8}">
    </div>
    <div class="form-group">
      <label class="form-label">Notes</label>
      <input type="text" class="field" name="codes[${index}][notes]" placeholder="e.g., Main entrance">
    </div>
    <div class="form-group full-width">
      <label class="form-label">Details</label>
      <textarea class="field" name="codes[${index}][details]" placeholder="Additional details"></textarea>
    </div>
    <div class="form-group full-width">
      <label class="form-label">Photo (JPG/PNG/WebP/HEIC)</label>
      <input type="file" class="edit-file-input" accept="image/*" id="file-${index}">
      <label for="file-${index}" class="file-upload-label">📤 Choose Photo</label>
      <div class="preview-box" id="preview-${index}">
        <img class="preview-thumb" src="" alt="preview">
      </div>
      <input type="hidden" name="codes[${index}][photo]" id="photo-${index}" value="">
      <div class="upload-status" id="status-${index}"></div>
    </div>
    <div class="full-width">
      <button type="button" class="btn btn-danger btn-remove-code">Remove Code</button>
    </div>
  `;
  return div;
}

document.getElementById('addCodeBtn').addEventListener('click', () => {
  const row = createCodeRow(codeIndex);
  document.getElementById('codesEditor').appendChild(row);
  wireCodeRow(row, codeIndex);
  codeIndex++;
});

// Add initial row
document.getElementById('addCodeBtn').click();

// Submit Add Community Form
document.getElementById('submitAddForm').addEventListener('click', async () => {
  const form = document.getElementById('addForm');

  if (!form.checkValidity()) {
    form.reportValidity();
    return;
  }

  // Check if at least one code has a value
  const codeInputs = form.querySelectorAll('input[name*="[code]"]');
  let hasCode = false;
  for (const input of codeInputs) {
    if (input.value.trim()) {
      hasCode = true;
      break;
    }
  }

  if (!hasCode) {
    showAlert('Please add at least one code.', 'Error');
    return;
  }

  form.submit();
});

function wireCodeRow(row, index) {
  const fileInput = row.querySelector(`#file-${index}`);
  const preview = row.querySelector(`#preview-${index}`);
  const previewImg = preview.querySelector('img');
  const photoInput = row.querySelector(`#photo-${index}`);
  const status = row.querySelector(`#status-${index}`);
  const removeBtn = row.querySelector('.btn-remove-code');

  fileInput.addEventListener('change', async (e) => {
    const file = e.target.files[0];
    if (!file) return;

    status.textContent = 'Uploading...';
    status.style.color = 'var(--muted)';

    const fd = new FormData();
    fd.append('key', ADMIN_KEY);
    fd.append('photo', file);

    // Get community name for filename
    const communityInput = document.getElementById('communityNameInput');
    if (communityInput && communityInput.value.trim()) {
      fd.append('community_name', communityInput.value.trim());
    }

    try {
      const response = await fetch(`?ajax=upload&key=${ADMIN_KEY}`, {
        method: 'POST',
        body: fd
      });

      const result = await response.json();

      if (result.status === 'ok' && result.url) {
        photoInput.value = result.url;
        // Show preview with full URL
        const fullUrl = result.url.startsWith('http') ? result.url : `${ASSETS_URL}${result.url.replace('assets/', '')}`;
        previewImg.src = fullUrl;
        preview.classList.add('show');
        status.textContent = 'Uploaded!';
        status.style.color = 'var(--brand)';
      } else {
        status.textContent = `Error: ${result.error || 'Upload failed'}`;
        status.style.color = 'var(--danger)';
      }
    } catch (error) {
      status.textContent = 'Network error';
      status.style.color = 'var(--danger)';
    }
  });

  removeBtn.addEventListener('click', () => {
    row.remove();
  });
}

// Show flash message
<?php if ($flashMsg): ?>
  showAlert('<?= addslashes($flashMsg) ?>', 'Success');
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
