<?php
require_once __DIR__ . '/includes/config.php';
require_key();

// Set current page for sidebar active state
$current_page = 'users';

// READ DATA
$data = read_json(GATES_JSON);
$suggestions = read_json(SUGGEST_JSON);
$pins = read_json(PIN_JSON);

// GET CURRENT USER ROLE
$current_user_role = $_SESSION['user_role'] ?? 'user';
$is_supervisor = ($current_user_role === 'supervisor');
$is_admin = ($current_user_role === 'admin');

/******************** ACTIONS ********************/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  // ADD USER
  if ($action === 'add_pin') {
    $name = trim($_POST['pin_name'] ?? '');
    $email = trim($_POST['pin_email'] ?? '');
    $role = trim($_POST['pin_role'] ?? 'user');
    $pin = trim($_POST['pin_value'] ?? '');

    // Prevent supervisors from creating admin accounts
    if ($is_supervisor && $role === 'admin') {
      header('Location: users.php?error=' . urlencode('You cannot create administrator accounts.'));
      exit;
    }

    if ($name === '' || $email === '' || $pin === '') {
      header('Location: users.php?error=' . urlencode('Name, Email and PIN are required'));
      exit;
    }

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      header('Location: users.php?error=' . urlencode('Invalid email address'));
      exit;
    }

    // Check if PIN already exists
    foreach ($pins as $existingPin) {
      if ($existingPin['pin'] === $pin) {
        header('Location: users.php?error=' . urlencode('PIN already exists. Please use a different PIN.'));
        exit;
      }
    }

    // Check if email already exists
    foreach ($pins as $existingPin) {
      if ($existingPin['email'] === $email) {
        header('Location: users.php?error=' . urlencode('Email already exists. Please use a different email.'));
        exit;
      }
    }

    $pins[] = [
      'name' => $name,
      'pin' => $pin,
      'email' => $email,
      'role' => $role,
      'date' => date('Y-m-d H:i:s')
    ];
    write_json(PIN_JSON, $pins);
    header('Location: users.php?msg=' . urlencode('User added successfully'));
    exit;
  }

  // UPDATE USER
  if ($action === 'update_pin') {
    $index = intval($_POST['index'] ?? -1);
    $name = trim($_POST['pin_name'] ?? '');
    $email = trim($_POST['pin_email'] ?? '');
    $role = trim($_POST['pin_role'] ?? 'user');
    $pin = trim($_POST['pin_value'] ?? '');

    if ($index >= 0 && $index < count($pins)) {
      // Prevent supervisors from editing admin accounts
      if ($is_supervisor && isset($pins[$index]['role']) && $pins[$index]['role'] === 'admin') {
        header('Location: users.php?error=' . urlencode('You cannot modify administrator accounts.'));
        exit;
      }
      // Prevent supervisors from promoting users to admin
      if ($is_supervisor && $role === 'admin') {
        header('Location: users.php?error=' . urlencode('You cannot promote users to administrator role.'));
        exit;
      }
      if ($name === '' || $email === '' || $pin === '') {
        header('Location: users.php?error=' . urlencode('Name, Email and PIN are required'));
        exit;
      }

      // Validate email
      if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header('Location: users.php?error=' . urlencode('Invalid email address'));
        exit;
      }

      // Check if PIN already exists (excluding current PIN being edited)
      foreach ($pins as $idx => $existingPin) {
        if ($idx !== $index && $existingPin['pin'] === $pin) {
          header('Location: users.php?error=' . urlencode('PIN already exists. Please use a different PIN.'));
          exit;
        }
      }

      // Check if email already exists (excluding current user being edited)
      foreach ($pins as $idx => $existingPin) {
        if ($idx !== $index && $existingPin['email'] === $email) {
          header('Location: users.php?error=' . urlencode('Email already exists. Please use a different email.'));
          exit;
        }
      }

      $pins[$index]['name'] = $name;
      $pins[$index]['pin'] = $pin;
      $pins[$index]['email'] = $email;
      $pins[$index]['role'] = $role;
      write_json(PIN_JSON, $pins);
      header('Location: users.php?msg=' . urlencode('User updated successfully'));
      exit;
    } else {
      header('Location: users.php?error=' . urlencode('Invalid user index'));
      exit;
    }
  }

  // DELETE PIN
  if ($action === 'delete_pin') {
    $index = intval($_POST['index'] ?? -1);

    if ($index >= 0 && $index < count($pins)) {
      // Prevent supervisors from deleting admin accounts
      if ($is_supervisor && isset($pins[$index]['role']) && $pins[$index]['role'] === 'admin') {
        header('Location: users.php?error=' . urlencode('You cannot delete administrator accounts.'));
        exit;
      }
      array_splice($pins, $index, 1);
      write_json(PIN_JSON, $pins);
      header('Location: users.php?msg=' . urlencode('PIN deleted successfully'));
      exit;
    } else {
      header('Location: users.php?error=' . urlencode('Invalid PIN index'));
      exit;
    }
  }
}

// FLASH MESSAGE
$flashMsg = $_GET['msg'] ?? '';
$errorMsg = $_GET['error'] ?? '';

// SEARCH FILTER
$q = trim($_GET['q'] ?? '');
$filtered_pins = $pins;

if ($q !== '') {
  $filtered_pins = array_filter($pins, function($pin) use ($q) {
    $search = strtolower($q);
    $name = strtolower($pin['name'] ?? '');
    $pin_value = strtolower($pin['pin'] ?? '');
    $email = strtolower($pin['email'] ?? '');
    $role = strtolower($pin['role'] ?? '');

    return strpos($name, $search) !== false ||
           strpos($pin_value, $search) !== false ||
           strpos($email, $search) !== false ||
           strpos($role, $search) !== false;
  });
  $filtered_pins = array_values($filtered_pins); // Re-index array
}

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
      <p class="page-subtitle">Manage user accounts and access permissions</p>
    </div>
  </div>
</div>

<div class="users-container">
  <div class="users-scroll-wrapper">
    <!-- SEARCH BAR -->
    <div class="search-section">
      <div class="search-bar-container">
        <input type="text" class="field" id="searchInput" placeholder="Search by name or PIN..." value="<?= htmlspecialchars($q) ?>">
        <button class="btn btn-primary" id="searchBtn">Search</button>
      </div>
    </div>

    <div class="card">
      <h2 class="card-title">PIN Management</h2>

      <div class="pins-grid">
    <?php if (empty($filtered_pins)): ?>
      <div class="empty-state">
        <div class="empty-state-icon">🔐</div>
        <p><?= $q !== '' ? 'No PINs found matching your search.' : 'No PINs configured.' ?></p>
      </div>
    <?php else:
      // Need to find original indices for edit/delete operations
      foreach ($filtered_pins as $pin):
        // Find the original index in $pins array
        $original_idx = array_search($pin, $pins, true);
    ?>
      <div class="pin-item">
        <div class="pin-info">
          <div class="user-header">
            <h3><?= htmlspecialchars($pin['name'] ?? '') ?></h3>
            <span class="role-badge role-<?= htmlspecialchars($pin['role'] ?? 'user') ?>">
              <?= ucfirst(htmlspecialchars($pin['role'] ?? 'user')) ?>
            </span>
          </div>
          <div class="user-details">
            <div class="detail-item">
              <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor" style="opacity: 0.6;">
                <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/>
                <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/>
              </svg>
              <span><?= htmlspecialchars($pin['email'] ?? '') ?></span>
            </div>
            <div class="detail-item">
              <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor" style="opacity: 0.6;">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
              </svg>
              <span><?= htmlspecialchars($pin['date'] ?? '') ?></span>
            </div>
          </div>
          <div class="pin-value"><?= htmlspecialchars($pin['pin'] ?? '') ?></div>
        </div>
        <?php
        // Hide edit/delete buttons for admin users if current user is supervisor
        $can_modify = !($is_supervisor && ($pin['role'] ?? 'user') === 'admin');
        ?>
        <?php if ($can_modify): ?>
        <div class="btn-group">
          <button class="btn btn-edit-pin"
                  data-index="<?= $original_idx ?>"
                  data-name="<?= htmlspecialchars($pin['name'] ?? '') ?>"
                  data-email="<?= htmlspecialchars($pin['email'] ?? '') ?>"
                  data-role="<?= htmlspecialchars($pin['role'] ?? 'user') ?>"
                  data-pin="<?= htmlspecialchars($pin['pin'] ?? '') ?>">Edit</button>
          <button type="button" class="btn btn-danger btn-delete-user-pin" data-index="<?= $original_idx ?>">Delete</button>
        </div>
        <?php else: ?>
        <div class="btn-group">
          <span class="protected-badge">Protected</span>
        </div>
        <?php endif; ?>
      </div>
    <?php endforeach; endif; ?>
  </div>

      <button class="btn btn-primary" id="addPinBtn">+ Add New User</button>
    </div>

    <!-- ADD/EDIT USER FORM (Hidden by default) -->
    <div class="card" id="pinFormCard" style="display: none;">
  <h2 class="card-title" id="pinFormTitle">Add New User</h2>

  <form method="post" id="pinForm">
    <input type="hidden" name="action" value="add_pin" id="pinFormAction">
    <input type="hidden" name="index" value="" id="pinFormIndex">

    <div class="form-group">
      <label class="form-label">Name</label>
      <input type="text" class="field" name="pin_name" id="pinName" placeholder="e.g., John Doe" required>
    </div>

    <div class="form-group">
      <label class="form-label">Email</label>
      <input type="email" class="field" name="pin_email" id="pinEmail" placeholder="e.g., john@example.com" required>
    </div>

    <div class="form-group">
      <label class="form-label">Role</label>
      <select class="field" name="pin_role" id="pinRole" required>
        <option value="user">User - Search and contribute only</option>
        <option value="supervisor">Supervisor - Dashboard access (except Settings & Backup)</option>
        <option value="admin">Admin - Full dashboard access</option>
      </select>
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
      <button type="submit" class="btn btn-primary">Save User</button>
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
  gap: 20px;
}

.users-scroll-wrapper {
  flex: 1;
  overflow-y: auto;
  overflow-x: hidden;
  padding-right: 8px;
  padding-bottom: 100px;
  min-height: 0;
}

/* SEARCH SECTION - STATIC */
.search-section {
  flex-shrink: 0;
  margin-bottom: 24px;
}

.search-bar-container {
  display: flex;
  gap: 12px;
  align-items: center;
}

.search-bar-container .field {
  flex: 1;
}

.search-bar-container .btn {
  flex-shrink: 0;
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

.user-header {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 12px;
}

.user-header h3 {
  margin: 0;
  color: var(--text);
  font-size: 1.1rem;
  font-weight: 600;
}

.role-badge {
  display: inline-block;
  padding: 4px 10px;
  border-radius: 6px;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.role-admin {
  background: linear-gradient(135deg, #3bdd82, #2bc76a);
  color: white;
}

.role-supervisor {
  background: linear-gradient(135deg, #f39c12, #e67e22);
  color: white;
}

.role-user {
  background: linear-gradient(135deg, #4a90e2, #357abd);
  color: white;
}

.user-details {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-bottom: 12px;
}

.detail-item {
  display: flex;
  align-items: center;
  gap: 8px;
  color: var(--muted);
  font-size: 0.9rem;
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

.protected-badge {
  display: inline-block;
  padding: 8px 16px;
  background: linear-gradient(135deg, #95a5a6, #7f8c8d);
  color: white;
  border-radius: 6px;
  font-size: 0.85rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  cursor: not-allowed;
  opacity: 0.8;
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
// Current user role from PHP
const currentUserRole = '<?= $current_user_role ?>';
const isSupervisor = (currentUserRole === 'supervisor');

// SEARCH FUNCTIONALITY
const searchInput = document.getElementById('searchInput');
const searchBtn = document.getElementById('searchBtn');

searchBtn.addEventListener('click', () => {
  const query = searchInput.value.trim();
  window.location.href = `?q=${encodeURIComponent(query)}`;
});

searchInput.addEventListener('keypress', (e) => {
  if (e.key === 'Enter') {
    e.preventDefault();
    searchBtn.click();
  }
});

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

// Validate PIN before form submission
pinForm.addEventListener('submit', (e) => {
  const pin = pinValue.value.trim();
  const action = pinFormAction.value;
  const currentIndex = pinFormIndex.value;

  // Check if PIN already exists
  const isDuplicate = existingPins.some((existingPin, idx) => {
    if (action === 'update_pin' && idx === parseInt(currentIndex)) {
      return false; // Skip current PIN when editing
    }
    return existingPin === pin;
  });

  if (isDuplicate) {
    e.preventDefault();
    showAlert({
      type: 'error',
      title: 'Duplicate PIN',
      message: 'This PIN already exists. Please use a different PIN or click "Generate" for a unique one.',
      buttons: [{
        text: 'OK',
        className: 'btn-alert-primary'
      }]
    });
    return false;
  }
});

// Generate PIN button
generatePinBtn.addEventListener('click', () => {
  const newPin = generateMemorablePIN();
  pinValue.value = newPin;
});

addPinBtn.addEventListener('click', () => {
  pinFormTitle.textContent = 'Add New User';
  pinFormAction.value = 'add_pin';
  pinFormIndex.value = '';
  pinName.value = '';
  pinEmail.value = '';
  pinRole.value = 'user';

  // Hide admin option for supervisors
  updateRoleDropdownForSupervisor();

  // Auto-generate a PIN when opening form
  const newPin = generateMemorablePIN();
  pinValue.value = newPin;
  pinFormCard.style.display = 'block';
  pinFormCard.scrollIntoView({ behavior: 'smooth' });
});

cancelPinBtn.addEventListener('click', () => {
  pinFormCard.style.display = 'none';
});

const pinEmail = document.getElementById('pinEmail');
const pinRole = document.getElementById('pinRole');

// Function to hide/show admin option based on supervisor status
function updateRoleDropdownForSupervisor() {
  if (isSupervisor) {
    // Hide the admin option for supervisors
    const adminOption = Array.from(pinRole.options).find(opt => opt.value === 'admin');
    if (adminOption) {
      adminOption.style.display = 'none';
      adminOption.disabled = true;
    }
  } else {
    // Show admin option for admins
    const adminOption = Array.from(pinRole.options).find(opt => opt.value === 'admin');
    if (adminOption) {
      adminOption.style.display = '';
      adminOption.disabled = false;
    }
  }
}

document.addEventListener('click', (e) => {
  if (e.target.classList.contains('btn-edit-pin')) {
    const index = e.target.getAttribute('data-index');
    const name = e.target.getAttribute('data-name');
    const email = e.target.getAttribute('data-email');
    const role = e.target.getAttribute('data-role');
    const pin = e.target.getAttribute('data-pin');

    pinFormTitle.textContent = 'Edit User';
    pinFormAction.value = 'update_pin';
    pinFormIndex.value = index;
    pinName.value = name;
    pinEmail.value = email;
    pinRole.value = role;
    pinValue.value = pin;

    // Hide admin option for supervisors
    updateRoleDropdownForSupervisor();

    pinFormCard.style.display = 'block';
    pinFormCard.scrollIntoView({ behavior: 'smooth' });
  }

  // Copy PIN to clipboard when clicked
  if (e.target.classList.contains('pin-value')) {
    const pinText = e.target.textContent.trim();
    const originalText = e.target.textContent;

    // Copy to clipboard
    navigator.clipboard.writeText(pinText).then(() => {
      // Change text to "Copied"
      e.target.textContent = 'Copied';
      e.target.classList.add('copied');

      // Restore original PIN after 2 seconds
      setTimeout(() => {
        e.target.textContent = originalText;
        e.target.classList.remove('copied');
      }, 2000);
    }).catch(err => {
      showAlert({
        type: 'error',
        title: 'Error',
        message: 'Failed to copy PIN to clipboard.',
        buttons: [{
          text: 'OK',
          className: 'btn-alert-primary'
        }]
      });
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
