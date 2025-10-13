  </div>
</main>

<!-- ALERT MODAL -->
<div id="alertModal" class="alert-modal">
  <div class="alert-modal-content">
    <div id="alertModalIcon" class="alert-modal-icon"></div>
    <div id="alertModalTitle" class="alert-modal-title"></div>
    <div id="alertModalMessage" class="alert-modal-message"></div>
    <div id="alertModalActions" class="alert-modal-actions"></div>
  </div>
</div>

<script>
const ADMIN_KEY = "<?= htmlspecialchars(ADMIN_KEY) ?>";

// ALERT MODAL
const alertModal = document.getElementById('alertModal');
const alertModalIcon = document.getElementById('alertModalIcon');
const alertModalTitle = document.getElementById('alertModalTitle');
const alertModalMessage = document.getElementById('alertModalMessage');
const alertModalActions = document.getElementById('alertModalActions');

function showAlert({ type = 'warning', title, message, buttons = [] }) {
  const icons = {
    success: `<svg class="alert-icon-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#3bdd82" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
      <polyline points="22 4 12 14.01 9 11.01"></polyline>
    </svg>`,
    error: `<svg class="alert-icon-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#ff5c5c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <circle cx="12" cy="12" r="10"></circle>
      <line x1="15" y1="9" x2="9" y2="15"></line>
      <line x1="9" y1="9" x2="15" y2="15"></line>
    </svg>`,
    warning: `<svg class="alert-icon-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#ff9800" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
      <line x1="12" y1="9" x2="12" y2="13"></line>
      <line x1="12" y1="17" x2="12.01" y2="17"></line>
    </svg>`
  };

  alertModalIcon.innerHTML = icons[type] || icons.warning;
  alertModalIcon.className = `alert-modal-icon ${type}`;
  alertModalTitle.textContent = title;
  alertModalMessage.textContent = message;

  alertModalActions.innerHTML = '';

  // If no buttons provided, add a default OK button
  if (buttons.length === 0) {
    buttons = [{
      text: 'OK',
      className: 'btn-alert-primary'
    }];
  }

  buttons.forEach(btn => {
    const button = document.createElement('button');
    button.className = `btn-alert ${btn.className || 'btn-alert-secondary'}`;
    button.textContent = btn.text;
    button.onclick = () => {
      closeAlert();
      if (btn.onClick) btn.onClick();
    };
    alertModalActions.appendChild(button);
  });

  alertModal.classList.add('open');
  document.body.style.overflow = 'hidden';
}

function closeAlert() {
  alertModal.classList.remove('open');
  document.body.style.overflow = '';
}

alertModal.addEventListener('click', (e) => {
  if (e.target === alertModal) {
    closeAlert();
  }
});

document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape' && alertModal.classList.contains('open')) {
    closeAlert();
  }
});

// Show flash message on load (backward compatibility)
const flashMessage = document.getElementById('flashMessage');
if (flashMessage) {
  const message = flashMessage.getAttribute('data-message');
  if (message) {
    setTimeout(() => showAlert({
      type: 'success',
      title: 'Success',
      message: message
    }), 100);
  }
}

// THEME INITIALIZATION - Apply immediately
const htmlElement = document.documentElement;
const savedTheme = localStorage.getItem('theme') || 'dark';
htmlElement.setAttribute('data-theme', savedTheme);

// THEME TOGGLE
const themeToggle = document.getElementById('themeToggle');
const moonIcon = document.getElementById('moonIcon');
const sunIcon = document.getElementById('sunIcon');

if (themeToggle && moonIcon && sunIcon) {
  // Set initial icon state
  if (savedTheme === 'light') {
    moonIcon.style.display = 'none';
    sunIcon.style.display = 'block';
  } else {
    moonIcon.style.display = 'block';
    sunIcon.style.display = 'none';
  }

  themeToggle.addEventListener('click', () => {
    const currentTheme = htmlElement.getAttribute('data-theme');
    const newTheme = currentTheme === 'light' ? 'dark' : 'light';
    htmlElement.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
    if (newTheme === 'light') {
      moonIcon.style.display = 'none';
      sunIcon.style.display = 'block';
    } else {
      moonIcon.style.display = 'block';
      sunIcon.style.display = 'none';
    }
  });
}

// MOBILE MENU
const mobileMenuToggle = document.getElementById('mobileMenuToggle');
const sidebar = document.getElementById('sidebar');

if (mobileMenuToggle && sidebar) {
  mobileMenuToggle.addEventListener('click', () => {
    sidebar.classList.toggle('open');
  });

  // Close sidebar when clicking outside on mobile
  document.addEventListener('click', (e) => {
    if (window.innerWidth <= 768 &&
        sidebar.classList.contains('open') &&
        !sidebar.contains(e.target) &&
        !mobileMenuToggle.contains(e.target)) {
      sidebar.classList.remove('open');
    }
  });
}
</script>

<!-- FOOTER -->
<footer style="
  position: fixed;
  bottom: 0;
  left: var(--sidebar-width);
  right: 0;
  padding: 12px 32px;
  background: var(--footer-bg);
  backdrop-filter: blur(10px);
  border-top: 1px solid var(--line);
  text-align: center;
  font-size: 0.85rem;
  color: var(--muted);
  z-index: 100;
  transition: left 0.3s ease;
">
  © <span id="currentYear"></span> Built by <a href="mailto:blancuniverse@gmail.com" style="color: var(--brand); text-decoration: none; font-weight: 600; transition: color 0.2s ease;">Alejandro</a>
  <span style="margin: 0 8px; opacity: 0.5;">•</span>
  <span style="font-family: 'Courier New', monospace; color: var(--brand); font-weight: 600;">Build v<?= APP_VERSION ?></span>
</footer>

<script>
  document.getElementById('currentYear').textContent = new Date().getFullYear();

  // Update footer position on mobile
  if (window.innerWidth <= 768) {
    document.querySelector('footer').style.left = '0';
  }

  window.addEventListener('resize', () => {
    if (window.innerWidth <= 768) {
      document.querySelector('footer').style.left = '0';
    } else {
      document.querySelector('footer').style.left = 'var(--sidebar-width)';
    }
  });
</script>
</body>
</html>
