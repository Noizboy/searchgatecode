<?php
require_once __DIR__ . '/includes/config.php';
require_key();

// Set current page for sidebar active state
$current_page = 'users';

// READ DATA
$data = read_json(GATES_JSON);
$suggestions = read_json(SUGGEST_JSON);
$pins = read_json(PIN_JSON);

/******************** ACTIONS ********************/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  // ADD PIN
  if ($action === 'add_pin') {
    $name = trim($_POST['pin_name'] ?? '');
    $pin = trim($_POST['pin_value'] ?? '');

    if ($name === '' || $pin === '') {
      header('Location: users.php?key=' . urlencode(ADMIN_KEY) . '&error=' . urlencode('Name and PIN are required'));
      exit;
    }

    $pins[] = [
      'name' => $name,
      'pin' => $pin,
      'date' => date('Y-m-d H:i:s')
    ];
    write_json(PIN_JSON, $pins);
    header('Location: users.php?key=' . urlencode(ADMIN_KEY) . '&msg=' . urlencode('PIN added successfully'));
    exit;
  }

  // UPDATE PIN
  if ($action === 'update_pin') {
    $index = intval($_POST['index'] ?? -1);
    $name = trim($_POST['pin_name'] ?? '');
    $pin = trim($_POST['pin_value'] ?? '');

    if ($index >= 0 && $index < count($pins)) {
      if ($name === '' || $pin === '') {
        header('Location: users.php?key=' . urlencode(ADMIN_KEY) . '&error=' . urlencode('Name and PIN are required'));
        exit;
      }

      $pins[$index]['name'] = $name;
      $pins[$index]['pin'] = $pin;
      write_json(PIN_JSON, $pins);
      header('Location: users.php?key=' . urlencode(ADMIN_KEY) . '&msg=' . urlencode('PIN updated successfully'));
      exit;
    } else {
      header('Location: users.php?key=' . urlencode(ADMIN_KEY) . '&error=' . urlencode('Invalid PIN index'));
      exit;
    }
  }

  // DELETE PIN
  if ($action === 'delete_pin') {
    $index = intval($_POST['index'] ?? -1);

    if ($index >= 0 && $index < count($pins)) {
      array_splice($pins, $index, 1);
      write_json(PIN_JSON, $pins);
      header('Location: users.php?key=' . urlencode(ADMIN_KEY) . '&msg=' . urlencode('PIN deleted successfully'));
      exit;
    } else {
      header('Location: users.php?key=' . urlencode(ADMIN_KEY) . '&error=' . urlencode('Invalid PIN index'));
      exit;
    }
  }
}

// FLASH MESSAGE
$flashMsg = $_GET['msg'] ?? '';
$errorMsg = $_GET['error'] ?? '';

require_once __DIR__ . '/includes/header.php';
?>

<!-- USERS PAGE CONTENT -->
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
      <h1 class="page-title">Users</h1>
      <p class="page-subtitle">Manage user PIN codes for app access</p>
    </div>
  </div>
</div>

<div class="users-container">
  <div class="users-scroll-wrapper">
    <div class="card">
      <h2 class="card-title">PIN Management</h2>

      <div class="pins-grid">
    <?php if (empty($pins)): ?>
      <div class="empty-state">
        <div class="empty-state-icon">🔐</div>
        <p>No PINs configured.</p>
      </div>
    <?php else: foreach ($pins as $idx => $pin): ?>
      <div class="pin-item">
        <div class="pin-info">
          <h3><?= htmlspecialchars($pin['name'] ?? '') ?></h3>
          <div class="pin-value"><?= htmlspecialchars($pin['pin'] ?? '') ?></div>
          <p>Created: <?= htmlspecialchars($pin['date'] ?? '') ?></p>
        </div>
        <div class="btn-group">
          <button class="btn btn-edit-pin" data-index="<?= $idx ?>" data-name="<?= htmlspecialchars($pin['name'] ?? '') ?>" data-pin="<?= htmlspecialchars($pin['pin'] ?? '') ?>">Edit</button>
          <button type="button" class="btn btn-danger btn-delete-user-pin" data-index="<?= $idx ?>">Delete</button>
        </div>
      </div>
    <?php endforeach; endif; ?>
  </div>

      <button class="btn btn-primary" id="addPinBtn" style="margin-top: 20px;">+ Add New PIN</button>
    </div>

    <!-- ADD/EDIT PIN FORM (Hidden by default) -->
    <div class="card" id="pinFormCard" style="display: none;">
  <h2 class="card-title" id="pinFormTitle">Add New PIN</h2>

  <form method="post" id="pinForm">
    <input type="hidden" name="key" value="<?= htmlspecialchars(ADMIN_KEY) ?>">
    <input type="hidden" name="action" value="add_pin" id="pinFormAction">
    <input type="hidden" name="index" value="" id="pinFormIndex">

    <div class="form-group">
      <label class="form-label">Name</label>
      <input type="text" class="field" name="pin_name" id="pinName" placeholder="e.g., John Doe" required>
    </div>

    <div class="form-group">
      <label class="form-label">PIN</label>
      <div style="display: flex; gap: 8px;">
        <input type="text" class="field" name="pin_value" id="pinValue" placeholder="e.g., 12345" required maxlength="5" pattern="[0-9]{5}">
        <button type="button" class="btn btn-primary" id="generatePinBtn" style="white-space: nowrap;">
          <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor" style="margin-right: 4px;">
            <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd"/>
          </svg>
          Generate
        </button>
      </div>
    </div>

    <div class="btn-group">
      <button type="submit" class="btn btn-primary">Save PIN</button>
      <button type="button" class="btn" id="cancelPinBtn">Cancel</button>
    </div>
  </form>
    </div>
  </div>
</div>

<style>
.users-container {
  display: flex;
  flex-direction: column;
  flex: 1;
  overflow: hidden;
  min-height: 0;
}

.users-scroll-wrapper {
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

.pins-grid {
  display: grid;
  gap: 16px;
  margin-bottom: 20px;
}

.pin-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px;
  background: var(--panel);
  border: 1px solid var(--border);
  border-radius: 12px;
  gap: 16px;
}

.pin-info h3 {
  margin: 0 0 8px 0;
  color: var(--text);
  font-size: 1.1rem;
  font-weight: 600;
}

.pin-value {
  display: inline-block;
  padding: 8px 16px;
  background: linear-gradient(135deg, #2c3e50, #34495e);
  color: #ecf0f1;
  border-radius: 6px;
  font-weight: 700;
  font-size: 1.2rem;
  letter-spacing: 3px;
  font-family: 'Courier New', monospace;
  margin-bottom: 8px;
  cursor: pointer;
  transition: background 0.2s ease;
  position: relative;
  user-select: none;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}

.pin-value.copied {
  background: linear-gradient(135deg, #27ae60, #229954);
  animation: copiedPulse 0.5s ease;
}

@keyframes copiedPulse {
  0%, 100% {
    transform: scale(1);
  }
  50% {
    transform: scale(1.05);
  }
}

.pin-info p {
  margin: 0;
  color: var(--muted);
  font-size: 0.85rem;
}

@media (max-width: 768px) {
  .pin-item {
    flex-direction: column;
    align-items: flex-start;
  }

  .btn-group {
    width: 100%;
  }

  .users-scroll-wrapper {
    padding-right: 4px;
  }
}
</style>

<script>
// Existing PINs from PHP
const existingPins = <?= json_encode(array_column($pins, 'pin')) ?>;

// PIN GENERATOR - Creates memorable 5-digit PINs with repeated digits
function generateMemorablePIN() {
  const patterns = [
    // Pattern: AABBC (e.g., 11223)
    () => {
      const a = Math.floor(Math.random() * 10);
      const b = Math.floor(Math.random() * 10);
      const c = Math.floor(Math.random() * 10);
      return `${a}${a}${b}${b}${c}`;
    },
    // Pattern: AABBB (e.g., 11222)
    () => {
      const a = Math.floor(Math.random() * 10);
      const b = Math.floor(Math.random() * 10);
      return `${a}${a}${b}${b}${b}`;
    },
    // Pattern: AAABB (e.g., 11122)
    () => {
      const a = Math.floor(Math.random() * 10);
      const b = Math.floor(Math.random() * 10);
      return `${a}${a}${a}${b}${b}`;
    },
    // Pattern: ABABA (e.g., 12121)
    () => {
      const a = Math.floor(Math.random() * 10);
      const b = Math.floor(Math.random() * 10);
      return `${a}${b}${a}${b}${a}`;
    },
    // Pattern: AABBA (e.g., 11221)
    () => {
      const a = Math.floor(Math.random() * 10);
      const b = Math.floor(Math.random() * 10);
      return `${a}${a}${b}${b}${a}`;
    },
    // Pattern: ABBBA (e.g., 12221)
    () => {
      const a = Math.floor(Math.random() * 10);
      const b = Math.floor(Math.random() * 10);
      return `${a}${b}${b}${b}${a}`;
    }
  ];

  let pin;
  let attempts = 0;
  const maxAttempts = 100;

  do {
    // Choose random pattern
    const pattern = patterns[Math.floor(Math.random() * patterns.length)];
    pin = pattern();
    attempts++;
  } while (existingPins.includes(pin) && attempts < maxAttempts);

  // If we couldn't find a unique PIN with patterns, generate a random unique one
  if (existingPins.includes(pin)) {
    do {
      pin = String(Math.floor(Math.random() * 100000)).padStart(5, '0');
      attempts++;
    } while (existingPins.includes(pin) && attempts < maxAttempts * 2);
  }

  return pin;
}

// PIN MANAGEMENT
const addPinBtn = document.getElementById('addPinBtn');
const pinFormCard = document.getElementById('pinFormCard');
const pinForm = document.getElementById('pinForm');
const pinFormTitle = document.getElementById('pinFormTitle');
const pinFormAction = document.getElementById('pinFormAction');
const pinFormIndex = document.getElementById('pinFormIndex');
const pinName = document.getElementById('pinName');
const pinValue = document.getElementById('pinValue');
const cancelPinBtn = document.getElementById('cancelPinBtn');
const generatePinBtn = document.getElementById('generatePinBtn');

// Generate PIN button
generatePinBtn.addEventListener('click', () => {
  const newPin = generateMemorablePIN();
  pinValue.value = newPin;
  // Add to existing pins to prevent duplicates in same session
  if (!existingPins.includes(newPin)) {
    existingPins.push(newPin);
  }
});

addPinBtn.addEventListener('click', () => {
  pinFormTitle.textContent = 'Add New PIN';
  pinFormAction.value = 'add_pin';
  pinFormIndex.value = '';
  pinName.value = '';
  // Auto-generate a PIN when opening form
  const newPin = generateMemorablePIN();
  pinValue.value = newPin;
  if (!existingPins.includes(newPin)) {
    existingPins.push(newPin);
  }
  pinFormCard.style.display = 'block';
  pinFormCard.scrollIntoView({ behavior: 'smooth' });
});

cancelPinBtn.addEventListener('click', () => {
  pinFormCard.style.display = 'none';
});

document.addEventListener('click', (e) => {
  if (e.target.classList.contains('btn-edit-pin')) {
    const index = e.target.getAttribute('data-index');
    const name = e.target.getAttribute('data-name');
    const pin = e.target.getAttribute('data-pin');

    pinFormTitle.textContent = 'Edit PIN';
    pinFormAction.value = 'update_pin';
    pinFormIndex.value = index;
    pinName.value = name;
    pinValue.value = pin;
    pinFormCard.style.display = 'block';
    pinFormCard.scrollIntoView({ behavior: 'smooth' });
  }

  // Copy PIN to clipboard when clicked
  if (e.target.classList.contains('pin-value')) {
    const pinText = e.target.textContent.trim();

    // Copy to clipboard
    navigator.clipboard.writeText(pinText).then(() => {
      // Add copied class for visual feedback
      e.target.classList.add('copied');

      // Show alert
      showAlert(`PIN ${pinText} copied to clipboard!`, 'Copied');

      // Remove copied class after animation
      setTimeout(() => {
        e.target.classList.remove('copied');
      }, 500);
    }).catch(err => {
      showAlert('Failed to copy PIN', 'Error');
    });
  }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<script>
// Delete PIN handler
document.addEventListener('click', (e) => {
  if (e.target.classList.contains('btn-delete-user-pin')) {
    const index = e.target.getAttribute('data-index');

    showAlert({
      type: 'warning',
      title: 'Delete PIN',
      message: 'Are you sure you want to delete this PIN? This action cannot be undone.',
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
            form.method = 'post';
            form.innerHTML = `
              <input type="hidden" name="key" value="${ADMIN_KEY}">
              <input type="hidden" name="action" value="delete_pin">
              <input type="hidden" name="index" value="${index}">
            `;
            document.body.appendChild(form);
            form.submit();
          }
        }
      ]
    });
  }
});

// Show flash messages after footer is loaded (showAlert is defined there)
<?php if ($flashMsg): ?>
  showAlert({
    type: 'success',
    title: 'Success',
    message: '<?= addslashes($flashMsg) ?>'
  });
<?php endif; ?>

<?php if ($errorMsg): ?>
  showAlert({
    type: 'error',
    title: 'Error',
    message: '<?= addslashes($errorMsg) ?>'
  });
<?php endif; ?>
</script>
