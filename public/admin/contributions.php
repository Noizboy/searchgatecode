<?php
require_once __DIR__ . '/includes/config.php';
require_key();

// Set Florida timezone
date_default_timezone_set('America/New_York');

// Set current page for sidebar active state
$current_page = 'contributions';

// READ DATA
$data = read_json(GATES_JSON);
$suggestions = read_json(SUGGEST_JSON);

// Flash message
$flashMsg = '';

/******************** ACTIONS ********************/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  // APPROVE CONTRIBUTION
  if ($action === 'approve_contribution') {
    $index = (int)($_POST['index'] ?? -1);

    if ($index >= 0 && isset($suggestions[$index])) {
      $sugg = $suggestions[$index];
      $community = trim($sugg['community'] ?? '');
      $city = trim($sugg['city'] ?? '');
      $codes = $sugg['codes'] ?? [];

      if ($community && !empty($codes)) {
        $idx = find_comm_index($data, $community);

        // Process each code and extract coordinates for community level
        $processedCodes = [];
        $communityCoordinates = null;

        foreach ($codes as $codeData) {
          $newCode = [
            'code' => trim($codeData['code'] ?? ''),
            'notes' => trim($codeData['notes'] ?? ''),
            'details' => trim($codeData['details'] ?? ''),
            'photo' => trim($codeData['photo'] ?? '')
          ];

          // Extract coordinates to community level (don't store in individual codes)
          if (!$communityCoordinates && isset($codeData['coordinates']) &&
              isset($codeData['coordinates']['latitude']) &&
              isset($codeData['coordinates']['longitude'])) {
            $communityCoordinates = $codeData['coordinates'];
          }

          // If photo is in temp_assets, move it to assets with community name
          if (!empty($newCode['photo']) && strpos($newCode['photo'], 'temp_assets/') !== false) {
            $tempFile = __DIR__ . '/../' . $newCode['photo'];
            if (file_exists($tempFile)) {
              // Get original filename and extension
              $originalFilename = basename($newCode['photo']);
              $pathInfo = pathinfo($originalFilename);
              $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';

              // Extract the unique identifier (timestamp and hash) from original filename
              // Original format: gate_20251009_214617_807fbd.jpg
              // We want to keep the timestamp and hash
              $uniqueId = '';
              if (preg_match('/gate_(\d{8}_\d{6}_[a-f0-9]{6})/', $originalFilename, $matches)) {
                $uniqueId = $matches[1];
              } else {
                // Fallback: generate new timestamp and hash if pattern doesn't match
                $uniqueId = date('Ymd_His') . '_' . substr(md5(uniqid()), 0, 6);
              }

              // Create new filename with community name
              // Format: gate_communityname_20251009_214617_807fbd.jpg
              $sanitizedCommunity = preg_replace('/[^a-z0-9]+/i', '', strtolower($community));
              $newFilename = 'gate_' . $sanitizedCommunity . '_' . $uniqueId . $extension;
              $newPath = ASSETS_DIR . $newFilename;

              // Ensure assets directory exists
              if (!is_dir(ASSETS_DIR)) {
                @mkdir(ASSETS_DIR, 0775, true);
              }

              // Try to move, if fails try to copy
              if (@rename($tempFile, $newPath)) {
                $newCode['photo'] = ASSETS_RELATIVE . $newFilename;
              } elseif (@copy($tempFile, $newPath)) {
                $newCode['photo'] = ASSETS_RELATIVE . $newFilename;
                @unlink($tempFile); // Delete temp file after successful copy
              }
            }
          }

          if (!empty($newCode['code'])) {
            $processedCodes[] = $newCode;
          }
        }

        if (!empty($processedCodes)) {
          if ($idx >= 0) {
            // Community exists - add codes (avoid duplicates)
            $existing_codes = array_column($data[$idx]['codes'], 'code');
            foreach ($processedCodes as $newCode) {
              if (!in_array($newCode['code'], $existing_codes)) {
                $data[$idx]['codes'][] = $newCode;
              }
            }
            // Update city if provided
            if ($city !== '') {
              $data[$idx]['city'] = $city;
            }
            // Update submitted_date if provided
            if (!empty($sugg['submitted_date'])) {
              $data[$idx]['submitted_date'] = $sugg['submitted_date'];
            }
            // Update coordinates at community level if available and not already set
            if ($communityCoordinates && !isset($data[$idx]['coordinates'])) {
              $data[$idx]['coordinates'] = $communityCoordinates;
            }
          } else {
            // New community
            $newCommunity = [
              'community' => $community,
              'codes' => $processedCodes
            ];
            if ($city !== '') {
              $newCommunity['city'] = $city;
            }
            // Add submitted_date if available
            if (!empty($sugg['submitted_date'])) {
              $newCommunity['submitted_date'] = $sugg['submitted_date'];
            }
            // Add coordinates at community level if available
            if ($communityCoordinates) {
              $newCommunity['coordinates'] = $communityCoordinates;
            }
            $data[] = $newCommunity;
          }

          write_json(GATES_JSON, $data);

          // Remove from suggestions
          array_splice($suggestions, $index, 1);
          write_json(SUGGEST_JSON, $suggestions);

          $flashMsg = 'Contribution approved and added to database!';
        }
      }
    }
  }

  // REJECT CONTRIBUTION
  if ($action === 'reject_contribution') {
    $index = (int)($_POST['index'] ?? -1);

    if ($index >= 0 && isset($suggestions[$index])) {
      // Delete all photos in temp_assets for this contribution
      $codes = $suggestions[$index]['codes'] ?? [];
      foreach ($codes as $codeData) {
        $photo = $codeData['photo'] ?? '';
        if (!empty($photo) && strpos($photo, 'temp_assets/') !== false) {
          $tempFile = __DIR__ . '/../' . $photo;
          if (file_exists($tempFile)) {
            @unlink($tempFile);
          }
        }
      }

      // Remove from suggestions
      array_splice($suggestions, $index, 1);
      write_json(SUGGEST_JSON, $suggestions);

      $flashMsg = 'Contribution rejected and removed.';
    }
  }

  // UPDATE CONTRIBUTION (from edit modal)
  if ($action === 'update_contribution') {
    $index = (int)($_POST['index'] ?? -1);

    if ($index >= 0 && isset($suggestions[$index])) {
      $community = trim($_POST['community'] ?? '');
      $city = trim($_POST['city'] ?? '');

      if ($community !== '') {
        $suggestions[$index]['community'] = $community;
        $suggestions[$index]['city'] = $city;

        // Update codes
        $codeInputs = $_POST['code'] ?? [];
        $notesInputs = $_POST['notes'] ?? [];
        $detailsInputs = $_POST['details'] ?? [];
        $photoInputs = $_POST['photo'] ?? [];
        $coordinatesInputs = $_POST['coordinates'] ?? [];

        $updatedCodes = [];
        foreach ($codeInputs as $idx => $codeVal) {
          $codeVal = trim($codeVal);
          if ($codeVal !== '') {
            $codeData = [
              'code' => $codeVal,
              'notes' => trim($notesInputs[$idx] ?? ''),
              'details' => trim($detailsInputs[$idx] ?? ''),
              'photo' => trim($photoInputs[$idx] ?? '')
            ];

            // Preserve coordinates
            $coordsJson = trim($coordinatesInputs[$idx] ?? '');
            if ($coordsJson !== '') {
              $coords = json_decode($coordsJson, true);
              if ($coords) {
                $codeData['coordinates'] = $coords;
              }
            }

            $updatedCodes[] = $codeData;
          }
        }

        if (!empty($updatedCodes)) {
          $suggestions[$index]['codes'] = $updatedCodes;
          write_json(SUGGEST_JSON, $suggestions);
          $flashMsg = 'Contribution updated successfully!';
        }
      }
    }
  }
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- CONTRIBUTIONS PAGE CONTENT -->
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
      <h1 class="page-title">User Contributions</h1>
      <p class="page-subtitle">Review and approve community suggestions from users</p>
    </div>
  </div>
</div>

<div class="contributions-container">
  <div class="card contributions-card">
    <div class="contributions-scroll-wrapper">
      <?php if (empty($suggestions)): ?>
        <div class="empty-state">
          <div class="empty-state-icon">✨</div>
          <p>No contributions yet.</p>
        </div>
      <?php else: foreach ($suggestions as $idx => $suggestion): ?>
        <div class="suggestion-item">
          <div class="suggestion-header">
            <div class="suggestion-info">
              <h3 class="suggestion-title"><?= htmlspecialchars($suggestion['community'] ?? 'Unknown') ?></h3>
              <?php if (!empty($suggestion['city'])): ?>
                <p class="suggestion-city"><?= htmlspecialchars($suggestion['city']) ?></p>
              <?php endif; ?>
              <?php if (!empty($suggestion['submitted_date'])): ?>
                <p class="suggestion-date">Submitted: <?= htmlspecialchars($suggestion['submitted_date']) ?></p>
              <?php endif; ?>
            </div>
            <div class="btn-group">
              <button type="button" class="btn btn-secondary btn-view" data-index="<?= $idx ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                  <circle cx="12" cy="12" r="3"/>
                </svg>
                View
              </button>
            </div>
          </div>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
</div>

<!-- VIEW/EDIT MODAL -->
<div id="viewModal" class="modal-backdrop">
  <div class="modal-box">
    <div class="modal-header">
      <h2 class="modal-title">Review Contribution</h2>
      <button type="button" class="modal-close" id="closeModal">&times;</button>
    </div>

    <form method="POST" id="editContributionForm">
      <input type="hidden" name="key" value="<?= htmlspecialchars(ADMIN_KEY) ?>">
      <input type="hidden" name="action" value="update_contribution">
      <input type="hidden" name="index" id="editIndex">

      <div class="modal-body">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Community Name</label>
            <input type="text" class="field" name="community" id="editCommunity" required>
          </div>

          <div class="form-group">
            <label class="form-label">City</label>
            <input type="text" class="field" name="city" id="editCity">
          </div>
        </div>

        <div class="codes-section">
          <h3 class="section-title">Gate Codes</h3>
          <div id="editCodesList" class="codes-list"></div>
          <div id="submittedDateDisplay" class="code-submitted-date"></div>
        </div>
      </div>

      <div class="modal-footer">
        <div style="display: flex; gap: 12px; margin-left: auto;">
          <button type="button" class="btn btn-danger" id="rejectBtn">Reject</button>
          <button type="submit" class="btn btn-primary" id="approveBtn">Approve</button>
        </div>
      </div>
    </form>
  </div>
</div>

<style>
.contributions-container {
  display: flex;
  flex-direction: column;
  flex: 1;
  overflow: hidden;
  min-height: 0;
}

.contributions-card {
  display: flex;
  flex-direction: column;
  height: 100%;
  overflow: hidden;
  margin-bottom: 0;
}

.contributions-scroll-wrapper {
  flex: 1;
  overflow-y: auto;
  overflow-x: hidden;
  padding-right: 8px;
  min-height: 0;
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

.empty-state {
  text-align: center;
  padding: 60px 20px;
}

.empty-state-icon {
  font-size: 4rem;
  margin-bottom: 16px;
}

.empty-state p {
  color: var(--muted);
  font-size: 1.1rem;
}

.suggestion-item {
  padding: 20px 24px;
  border: 1px solid var(--border);
  border-radius: 12px;
  margin-bottom: 12px;
  background: var(--panel);
}

.suggestion-item:last-child {
  margin-bottom: 0;
}

.suggestion-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
}

.suggestion-info {
  flex: 1;
}

.suggestion-title {
  margin: 0 0 6px 0;
  color: var(--text);
  font-size: 1.2rem;
  font-weight: 700;
}

.suggestion-city {
  margin: 0 0 4px 0;
  color: var(--brand);
  font-size: 0.9rem;
  font-weight: 600;
}

.suggestion-date {
  margin: 0;
  color: var(--muted);
  font-size: 0.85rem;
}

.btn-view {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

/* Modal */
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.6);
  display: none;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 20px;
}

.modal-backdrop.open {
  display: flex;
}

.modal-box {
  background: linear-gradient(180deg, var(--modal-bg-1), var(--modal-bg-2));
  border: 1px solid var(--modal-border);
  border-radius: 12px;
  width: min(95vw, 900px);
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 20px 60px rgba(0,0,0,.5);
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 24px;
  border-bottom: 1px solid var(--border);
  flex-shrink: 0;
}

.modal-title {
  margin: 0;
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--text);
}

.modal-close {
  background: var(--btn-secondary-bg);
  color: var(--btn-secondary-text);
  border: 1px solid var(--btn-secondary-border);
  width: 32px;
  height: 32px;
  border-radius: 8px;
  cursor: pointer;
  font-size: 24px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s ease;
}

.modal-close:hover {
  background: var(--btn-secondary-hover);
}

.modal-body {
  flex: 1;
  overflow-y: auto;
  overflow-x: hidden;
  padding: 24px;
  min-height: 0;
}

.modal-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 24px;
  border-top: 1px solid var(--border);
  flex-shrink: 0;
  gap: 12px;
}

/* Form Styles */
.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  margin-bottom: 16px;
}

.form-group {
  margin-bottom: 16px;
}

.form-label {
  display: block;
  margin-bottom: 8px;
  color: var(--text);
  font-weight: 600;
  font-size: 0.95rem;
}

.codes-section {
  margin-top: 24px;
  padding-top: 24px;
  border-top: 1px solid var(--border);
}

.section-title {
  font-size: 1.2rem;
  font-weight: 700;
  color: var(--text);
  margin: 0 0 16px 0;
}

.codes-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
  max-height: 400px;
  overflow-y: auto;
  overflow-x: hidden;
  padding-right: 8px;
  scroll-snap-type: y mandatory;
}

.code-item {
  background: var(--panel-2);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 20px;
  scroll-snap-align: start;
  scroll-snap-stop: always;
  flex-shrink: 0;
}

.code-header {
  margin-bottom: 16px;
}

.code-number {
  font-weight: 700;
  color: var(--text);
  font-size: 1.1rem;
  display: block;
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

.code-photo-preview {
  margin-top: 12px;
  max-width: 300px;
}

.code-photo-preview img {
  width: 100%;
  border-radius: 8px;
  border: 1px solid var(--border);
}

.code-coords {
  margin-top: 8px;
  padding: 8px 12px;
  background: var(--input-bg-1);
  border: 1px solid var(--border);
  border-radius: 8px;
  font-size: 0.85rem;
  color: var(--muted);
  font-family: 'Courier New', monospace;
}

.code-submitted-date {
  margin-top: 16px;
  padding: 8px 12px;
  background: var(--input-bg-1);
  border: 1px solid var(--border);
  border-radius: 8px;
  font-size: 0.85rem;
  color: var(--muted);
  font-family: 'Courier New', monospace;
}

@media (max-width: 768px) {
  .form-row {
    grid-template-columns: 1fr;
  }

  .code-row {
    grid-template-columns: 1fr;
  }

  .suggestion-header {
    flex-direction: column;
    align-items: flex-start;
  }

  .btn-group {
    width: 100%;
  }

  .btn-view {
    width: 100%;
    justify-content: center;
  }

  .modal-footer {
    justify-content: stretch;
  }

  .modal-footer > div {
    width: 100%;
    margin-left: 0 !important;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
  }

  .modal-footer button {
    width: 100%;
  }

  .codes-list {
    max-height: 300px;
  }

  .modal-box {
    width: 95vw;
    max-height: 85vh;
  }

  .modal-body {
    padding: 16px;
  }
}

/* Alert Modal */
.alert-backdrop {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.7);
  backdrop-filter: blur(4px);
  z-index: 2000;
  align-items: center;
  justify-content: center;
  opacity: 0;
  transition: opacity 0.2s ease;
}

.alert-backdrop.open {
  display: flex;
  opacity: 1;
}

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
</style>

<!-- Alert Modal -->
<div id="alertBackdrop" class="alert-backdrop" aria-hidden="true">
  <div class="alert-modal" role="alertdialog" aria-modal="true">
    <div id="alertIcon" class="alert-icon"></div>
    <div id="alertTitle" class="alert-title"></div>
    <div id="alertMessage" class="alert-message"></div>
    <div id="alertActions" class="alert-actions"></div>
  </div>
</div>

<script>
// Alert Modal Functions
const alertBackdrop = document.getElementById('alertBackdrop');
const alertIcon = document.getElementById('alertIcon');
const alertTitle = document.getElementById('alertTitle');
const alertMessage = document.getElementById('alertMessage');
const alertActions = document.getElementById('alertActions');

function showAlert({ type = 'warning', title, message, buttons = [] }) {
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

const suggestions = <?= json_encode($suggestions) ?>;
let currentIndex = -1;
let editCodeCounter = 0;

// View button click
document.addEventListener('click', (e) => {
  if (e.target.closest('.btn-view')) {
    const btn = e.target.closest('.btn-view');
    const index = parseInt(btn.dataset.index);
    openViewModal(index);
  }
});

// Open view/edit modal
function openViewModal(index) {
  if (!suggestions[index]) return;

  currentIndex = index;
  const sugg = suggestions[index];

  document.getElementById('editIndex').value = index;
  document.getElementById('editCommunity').value = sugg.community || '';
  document.getElementById('editCity').value = sugg.city || '';

  // Load codes
  const codesList = document.getElementById('editCodesList');
  codesList.innerHTML = '';
  editCodeCounter = 0;

  if (sugg.codes && sugg.codes.length > 0) {
    sugg.codes.forEach(code => {
      addEditCodeItem(code);
    });
  }

  // Display submitted date below codes
  const submittedDateDisplay = document.getElementById('submittedDateDisplay');
  if (sugg.submitted_date) {
    submittedDateDisplay.textContent = `📅 Submitted: ${sugg.submitted_date}`;
    submittedDateDisplay.style.display = 'block';
  } else {
    submittedDateDisplay.style.display = 'none';
  }

  document.getElementById('viewModal').classList.add('open');
}

// Create code item for editing
function addEditCodeItem(codeData = null) {
  const index = editCodeCounter++;
  const div = document.createElement('div');
  div.className = 'code-item';
  div.dataset.index = index;

  const code = codeData || {};
  const hasCoords = code.coordinates && code.coordinates.latitude && code.coordinates.longitude;

  div.innerHTML = `
    <div class="code-header">
      <span class="code-number">Code #${index + 1}</span>
    </div>

    <div class="code-row">
      <div class="form-group">
        <label class="form-label">Gate Code</label>
        <input type="text" class="field" name="code[]" value="${escapeHtml(code.code || '')}" required>
      </div>

      <div class="form-group">
        <label class="form-label">Notes</label>
        <input type="text" class="field" name="notes[]" value="${escapeHtml(code.notes || '')}">
      </div>
    </div>

    <div class="form-group code-full">
      <label class="form-label">Details</label>
      <textarea class="field" name="details[]">${escapeHtml(code.details || '')}</textarea>
    </div>

    <input type="hidden" name="photo[]" value="${escapeHtml(code.photo || '')}">
    <input type="hidden" name="coordinates[]" value='${code.coordinates ? JSON.stringify(code.coordinates) : ''}'>

    ${code.photo ? `
      <div class="code-photo-preview">
        <img src="${resolvePhotoUrl(code.photo)}" alt="Code photo">
      </div>
    ` : ''}

    ${hasCoords ? `
      <div class="code-coords">
        📍 GPS: ${code.coordinates.latitude.toFixed(6)}, ${code.coordinates.longitude.toFixed(6)}
      </div>
    ` : ''}
  `;

  document.getElementById('editCodesList').appendChild(div);
}

// Close modal
document.getElementById('closeModal').addEventListener('click', () => {
  document.getElementById('viewModal').classList.remove('open');
});

// Reject button
document.getElementById('rejectBtn').addEventListener('click', () => {
  showAlert({
    type: 'warning',
    title: 'Reject Contribution',
    message: 'Are you sure you want to reject this contribution? This will delete all associated images and cannot be undone.',
    buttons: [
      {
        text: 'No',
        className: 'btn-alert-secondary'
      },
      {
        text: 'Yes',
        className: 'btn-alert-danger',
        onClick: () => {
          const form = document.createElement('form');
          form.method = 'POST';
          form.innerHTML = `
            <input type="hidden" name="key" value="<?= htmlspecialchars(ADMIN_KEY) ?>">
            <input type="hidden" name="action" value="reject_contribution">
            <input type="hidden" name="index" value="${currentIndex}">
          `;
          document.body.appendChild(form);
          form.submit();
        }
      }
    ]
  });
});

// Approve button - First saves changes, then approves
document.getElementById('approveBtn').addEventListener('click', (e) => {
  e.preventDefault();

  showAlert({
    type: 'success',
    title: 'Approve Contribution',
    message: 'Save changes and approve this contribution? This will add it to the main database.',
    buttons: [
      {
        text: 'No',
        className: 'btn-alert-secondary'
      },
      {
        text: 'Yes',
        className: 'btn-alert-primary',
        onClick: () => {
          const mainForm = document.getElementById('editContributionForm');

          // First save the changes (update_contribution)
          const formData = new FormData(mainForm);

          fetch('', {
            method: 'POST',
            body: formData
          }).then(() => {
            // After saving, approve the contribution
            const approveForm = document.createElement('form');
            approveForm.method = 'POST';
            approveForm.innerHTML = `
              <input type="hidden" name="key" value="<?= htmlspecialchars(ADMIN_KEY) ?>">
              <input type="hidden" name="action" value="approve_contribution">
              <input type="hidden" name="index" value="${currentIndex}">
            `;
            document.body.appendChild(approveForm);
            approveForm.submit();
          }).catch(err => {
            console.error('Error saving changes:', err);
            showAlert({
              type: 'error',
              title: 'Error',
              message: 'Error saving changes. Please try again.',
              buttons: [
                {
                  text: 'OK',
                  className: 'btn-alert-primary'
                }
              ]
            });
          });
        }
      }
    ]
  });
});

// Helper functions
function escapeHtml(text) {
  const map = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;'
  };
  return String(text || '').replace(/[&<>"']/g, m => map[m]);
}

function resolvePhotoUrl(photo) {
  if (!photo) return '';
  if (photo.indexOf('temp_assets/') !== -1) {
    return '../' + photo;
  }
  return '../' + photo;
}

// Show flash message
<?php if ($flashMsg): ?>
  showAlert('<?= addslashes($flashMsg) ?>', 'Success');
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
