  </div>
</main>

<!-- ALERT MODAL -->
<div id="alertModal" class="alert-modal">
  <div class="alert-modal-content">
    <div class="alert-modal-icon" id="alertModalIcon">
      <svg class="alert-icon-svg" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
      </svg>
    </div>
    <div class="alert-modal-body">
      <h3 class="alert-modal-title" id="alertModalTitle">Notification</h3>
      <p class="alert-modal-message" id="alertModalMessage"></p>
    </div>
    <div class="alert-modal-footer">
      <button class="btn btn-primary" id="alertModalOk">OK</button>
    </div>
  </div>
</div>

<script>
const ADMIN_KEY = "<?= htmlspecialchars(ADMIN_KEY) ?>";

// ALERT MODAL
const alertModal = document.getElementById('alertModal');
const alertModalTitle = document.getElementById('alertModalTitle');
const alertModalMessage = document.getElementById('alertModalMessage');
const alertModalOk = document.getElementById('alertModalOk');

function showAlert(message, title = 'Notification') {
  alertModalTitle.textContent = title;
  alertModalMessage.textContent = message;
  alertModal.classList.add('open');
  document.body.style.overflow = 'hidden';
}

function closeAlert() {
  alertModal.classList.remove('open');
  document.body.style.overflow = '';
}

alertModalOk.addEventListener('click', closeAlert);

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

// Show flash message on load
const flashMessage = document.getElementById('flashMessage');
if (flashMessage) {
  const message = flashMessage.getAttribute('data-message');
  if (message) {
    setTimeout(() => showAlert(message), 100);
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
  <span style="font-family: 'Courier New', monospace; color: var(--brand); font-weight: 600;">Build v1.0.0</span>
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
