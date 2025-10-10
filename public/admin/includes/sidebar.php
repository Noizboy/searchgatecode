<?php
// Calculate suggestion count for badge
$suggest_count = count($suggestions ?? []);
?>

<!-- MOBILE MENU TOGGLE -->
<button class="mobile-menu-toggle" id="mobileMenuToggle">
  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
    <line x1="3" y1="12" x2="21" y2="12"></line>
    <line x1="3" y1="6" x2="21" y2="6"></line>
    <line x1="3" y1="18" x2="21" y2="18"></line>
  </svg>
</button>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <a href="index.php?key=<?= urlencode(ADMIN_KEY) ?>" class="sidebar-logo">GATE CODES</a>
    <button id="themeToggle" class="theme-toggle-sidebar" aria-label="Toggle theme">
      <svg id="moonIcon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
      </svg>
      <svg id="sunIcon" style="display:none;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="5"/>
        <path d="M12 1L13 5L11 5Z"/>
        <path d="M12 23L13 19L11 19Z"/>
        <path d="M23 12L19 13L19 11Z"/>
        <path d="M1 12L5 13L5 11Z"/>
        <path d="M19.07 4.93L16 7.5L15 6.5Z"/>
        <path d="M4.93 19.07L8 16.5L9 17.5Z"/>
        <path d="M19.07 19.07L16.5 16L17.5 15Z"/>
        <path d="M4.93 4.93L7.5 8L6.5 9Z"/>
      </svg>
    </button>
  </div>
  <nav class="sidebar-nav">
    <a href="index.php?key=<?= urlencode(ADMIN_KEY) ?>" class="nav-item <?= ($current_page ?? '') === 'home' ? 'active' : '' ?>">
      <svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20">
        <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"/>
      </svg>
      <span>Home</span>
    </a>
    <a href="contributions.php?key=<?= urlencode(ADMIN_KEY) ?>" class="nav-item <?= ($current_page ?? '') === 'contributions' ? 'active' : '' ?>">
      <svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20">
        <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/>
        <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/>
      </svg>
      <span>Contributions</span>
      <?php if ($suggest_count > 0): ?>
        <span class="nav-badge"><?= $suggest_count ?></span>
      <?php endif; ?>
    </a>
    <a href="users.php?key=<?= urlencode(ADMIN_KEY) ?>" class="nav-item <?= ($current_page ?? '') === 'users' ? 'active' : '' ?>">
      <svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20">
        <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/>
      </svg>
      <span>Users</span>
    </a>
    <a href="backup.php?key=<?= urlencode(ADMIN_KEY) ?>" class="nav-item <?= ($current_page ?? '') === 'backup' ? 'active' : '' ?>">
      <svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"/>
      </svg>
      <span>Backup</span>
    </a>
    <a href="settings.php?key=<?= urlencode(ADMIN_KEY) ?>" class="nav-item <?= ($current_page ?? '') === 'settings' ? 'active' : '' ?>">
      <svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"/>
      </svg>
      <span>Settings</span>
    </a>
  </nav>
</aside>
