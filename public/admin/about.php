<?php
require_once __DIR__ . '/includes/config.php';
require_key();

// Set current page for sidebar active state
$current_page = 'about';

// READ DATA
$data = read_json(GATES_JSON);
$suggestions = read_json(SUGGEST_JSON);
$pins = read_json(PIN_JSON);

require_once __DIR__ . '/includes/header.php';
?>

<!-- ABOUT PAGE CONTENT -->
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
      <h1 class="page-title">About</h1>
      <p class="page-subtitle">Application information and documentation</p>
    </div>
  </div>
</div>

<div class="about-container">
  <div class="about-scroll-wrapper">

    <!-- APP VERSION -->
    <div class="card">
      <h2 class="card-title">
        <svg style="width: 24px; height: 24px; display: inline-block; vertical-align: middle; margin-right: 8px;" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
        </svg>
        Version Information
      </h2>
      <div class="version-info">
        <div class="version-badge">
          <span class="version-label">Build Version</span>
          <span class="version-number">v<?= APP_VERSION ?></span>
        </div>
      </div>
    </div>

    <!-- SUPPORT & DOCUMENTATION -->
    <div class="card">
      <h2 class="card-title">
        <svg style="width: 24px; height: 24px; display: inline-block; vertical-align: middle; margin-right: 8px;" fill="currentColor" viewBox="0 0 20 20">
          <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/>
          <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/>
        </svg>
        Support & Documentation
      </h2>
      <div class="support-links">
        <a href="mailto:support@gatecodes.app" class="support-link">
          <svg class="link-icon" fill="currentColor" viewBox="0 0 20 20">
            <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/>
            <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/>
          </svg>
          <div>
            <div class="link-title">Email Support</div>
            <div class="link-subtitle">support@gatecodes.app</div>
          </div>
        </a>

        <a href="https://github.com/yourusername/gatecodes" target="_blank" rel="noopener noreferrer" class="support-link">
          <svg class="link-icon" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 0C4.477 0 0 4.484 0 10.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0110 4.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.203 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.942.359.31.678.921.678 1.856 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0020 10.017C20 4.484 15.522 0 10 0z" clip-rule="evenodd"/>
          </svg>
          <div>
            <div class="link-title">GitHub Repository</div>
            <div class="link-subtitle">View source code and contribute</div>
          </div>
        </a>

        <a href="https://docs.gatecodes.app" target="_blank" rel="noopener noreferrer" class="support-link">
          <svg class="link-icon" fill="currentColor" viewBox="0 0 20 20">
            <path d="M9 4.804A7.968 7.968 0 005.5 4c-1.255 0-2.443.29-3.5.804v10A7.969 7.969 0 015.5 14c1.669 0 3.218.51 4.5 1.385A7.962 7.962 0 0114.5 14c1.255 0 2.443.29 3.5.804v-10A7.968 7.968 0 0014.5 4c-1.255 0-2.443.29-3.5.804V12a1 1 0 11-2 0V4.804z"/>
          </svg>
          <div>
            <div class="link-title">Documentation</div>
            <div class="link-subtitle">User guides and API reference</div>
          </div>
        </a>

        <a href="https://github.com/yourusername/gatecodes/blob/main/CHANGELOG.md" target="_blank" rel="noopener noreferrer" class="support-link">
          <svg class="link-icon" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/>
          </svg>
          <div>
            <div class="link-title">Changelog</div>
            <div class="link-subtitle">Release notes and version history</div>
          </div>
        </a>
      </div>
    </div>

    <!-- LICENSES -->
    <div class="card">
      <h2 class="card-title">
        <svg style="width: 24px; height: 24px; display: inline-block; vertical-align: middle; margin-right: 8px;" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1.323l3.954 1.582 1.599-.8a1 1 0 01.894 1.79l-1.233.616 1.738 5.42a1 1 0 01-.285 1.05A3.989 3.989 0 0115 15a3.989 3.989 0 01-2.667-1.019 1 1 0 01-.285-1.05l1.715-5.349L11 6.477V16h2a1 1 0 110 2H7a1 1 0 110-2h2V6.477L6.237 7.582l1.715 5.349a1 1 0 01-.285 1.05A3.989 3.989 0 015 15a3.989 3.989 0 01-2.667-1.019 1 1 0 01-.285-1.05l1.738-5.42-1.233-.617a1 1 0 01.894-1.788l1.599.799L9 4.323V3a1 1 0 011-1z" clip-rule="evenodd"/>
        </svg>
        Open Source Licenses
      </h2>
      <div class="licenses">
        <div class="license-item">
          <div class="license-header">
            <h3 class="license-name">PHP</h3>
            <span class="license-type">PHP License v3.01</span>
          </div>
          <p class="license-description">Server-side scripting language for web development</p>
          <a href="https://www.php.net/license/" target="_blank" rel="noopener noreferrer" class="license-link">View License →</a>
        </div>

        <div class="license-item">
          <div class="license-header">
            <h3 class="license-name">Heroicons</h3>
            <span class="license-type">MIT License</span>
          </div>
          <p class="license-description">Beautiful hand-crafted SVG icons by the makers of Tailwind CSS</p>
          <a href="https://github.com/tailwindlabs/heroicons" target="_blank" rel="noopener noreferrer" class="license-link">View License →</a>
        </div>

        <div class="license-item">
          <div class="license-header">
            <h3 class="license-name">Gate Codes Application</h3>
            <span class="license-type">MIT License</span>
          </div>
          <p class="license-description">This application is open source and available under the MIT License</p>
          <a href="https://github.com/yourusername/gatecodes/blob/main/LICENSE" target="_blank" rel="noopener noreferrer" class="license-link">View License →</a>
        </div>
      </div>
    </div>

    <!-- FOOTER INFO -->
    <div class="card">
      <div style="text-align: center; padding: 20px 0;">
        <p style="color: var(--muted); font-size: 0.9rem; margin-bottom: 8px;">
          Gate Codes Admin Dashboard
        </p>
        <p style="color: var(--muted); font-size: 0.85rem;">
          Built by Alejandro
        </p>
      </div>
    </div>

  </div>
</div>

<style>
.about-container {
  display: flex;
  flex-direction: column;
  flex: 1;
  overflow: hidden;
  min-height: 0;
}

.about-scroll-wrapper {
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

/* VERSION INFO */
.version-info {
  padding: 8px 0;
}

.version-badge {
  display: inline-flex;
  align-items: center;
  gap: 12px;
  padding: 12px 24px;
  background: var(--panel-2);
  border: 1px solid var(--border);
  border-radius: 12px;
  color: var(--text);
}

.version-label {
  font-size: 0.85rem;
  font-weight: 500;
  opacity: 0.9;
}

.version-number {
  font-size: 1.2rem;
  font-weight: 700;
  font-family: 'Courier New', monospace;
}

/* SUPPORT LINKS */
.support-links {
  display: grid;
  gap: 12px;
}

.support-link {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 16px 20px;
  background: var(--panel-2);
  border: 1px solid var(--border);
  border-radius: 12px;
  text-decoration: none;
  color: var(--text);
  transition: all 0.2s ease;
}

.support-link:hover {
  background: var(--panel);
  border-color: var(--brand);
  transform: translateX(4px);
}

.link-icon {
  width: 32px;
  height: 32px;
  color: var(--brand);
  flex-shrink: 0;
}

.link-title {
  font-weight: 600;
  font-size: 1rem;
  color: var(--text);
  margin-bottom: 2px;
}

.link-subtitle {
  font-size: 0.85rem;
  color: var(--muted);
}

/* LICENSES */
.licenses {
  display: grid;
  gap: 16px;
}

.license-item {
  padding: 20px;
  background: var(--panel-2);
  border: 1px solid var(--border);
  border-radius: 12px;
}

.license-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
  gap: 12px;
  flex-wrap: wrap;
}

.license-name {
  margin: 0;
  font-size: 1.1rem;
  font-weight: 700;
  color: var(--text);
}

.license-type {
  font-size: 0.8rem;
  padding: 4px 10px;
  background: var(--brand);
  color: white;
  border-radius: 6px;
  font-weight: 600;
}

.license-description {
  margin: 8px 0 12px 0;
  color: var(--muted);
  font-size: 0.9rem;
  line-height: 1.5;
}

.license-link {
  display: inline-flex;
  align-items: center;
  color: var(--brand);
  text-decoration: none;
  font-size: 0.9rem;
  font-weight: 600;
  transition: all 0.2s ease;
}

.license-link:hover {
  color: var(--brand-2);
  transform: translateX(4px);
}

@media (max-width: 768px) {
  .about-scroll-wrapper {
    padding-right: 4px;
  }

  .version-badge {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 6px;
  }

  .license-header {
    flex-direction: column;
    align-items: flex-start;
  }
}
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
