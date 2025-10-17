<?php
require_once __DIR__ . '/includes/config.php';
require_key();

// Set Florida timezone
date_default_timezone_set('America/New_York');

// Set current page for sidebar active state
$current_page = 'user_history';

// READ DATA
$data = read_json(GATES_JSON);
$suggestions = read_json(SUGGEST_JSON);

// Collect all contributions grouped by user
$userContributions = [];

// Get all approved contributions from gates.json
foreach ($data as $community) {
  if (isset($community['submitted_by']) && !empty($community['submitted_by'])) {
    $userName = $community['submitted_by'];

    if (!isset($userContributions[$userName])) {
      $userContributions[$userName] = [
        'approved' => [],
        'pending' => []
      ];
    }

    $userContributions[$userName]['approved'][] = [
      'community' => $community['community'] ?? 'Unknown',
      'city' => $community['city'] ?? '',
      'codes_count' => count($community['codes'] ?? []),
      'submitted_date' => $community['submitted_date'] ?? 'Unknown',
      'status' => 'approved'
    ];
  }
}

// Get all pending contributions from suggest.json
foreach ($suggestions as $suggestion) {
  if (isset($suggestion['submitted_by']) && !empty($suggestion['submitted_by'])) {
    $userName = $suggestion['submitted_by'];

    if (!isset($userContributions[$userName])) {
      $userContributions[$userName] = [
        'approved' => [],
        'pending' => []
      ];
    }

    $userContributions[$userName]['pending'][] = [
      'community' => $suggestion['community'] ?? 'Unknown',
      'city' => $suggestion['city'] ?? '',
      'codes_count' => count($suggestion['codes'] ?? []),
      'submitted_date' => $suggestion['submitted_date'] ?? 'Unknown',
      'status' => 'pending'
    ];
  }
}

// Sort users by total contribution count (approved + pending)
uksort($userContributions, function($a, $b) use ($userContributions) {
  $countA = count($userContributions[$a]['approved']) + count($userContributions[$a]['pending']);
  $countB = count($userContributions[$b]['approved']) + count($userContributions[$b]['pending']);
  return $countB - $countA; // Descending order
});

require_once __DIR__ . '/includes/header.php';
?>

<!-- USER HISTORY PAGE CONTENT -->
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
      <h1 class="page-title">User Contribution History</h1>
      <p class="page-subtitle">View all community contributions by user</p>
    </div>
  </div>
  <a href="contributions.php" class="btn btn-secondary">
    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <line x1="19" y1="12" x2="5" y2="12"></line>
      <polyline points="12 19 5 12 12 5"></polyline>
    </svg>
    Back to Contributions
  </a>
</div>

<div class="history-container">
  <div class="card history-card">
    <div class="history-scroll-wrapper">
      <?php if (empty($userContributions)): ?>
        <div class="empty-state">
          <div class="empty-state-icon">📊</div>
          <p>No user contributions recorded yet.</p>
        </div>
      <?php else: foreach ($userContributions as $userName => $contributions): ?>
        <?php
          $approvedCount = count($contributions['approved']);
          $pendingCount = count($contributions['pending']);
          $totalCount = $approvedCount + $pendingCount;
          $allContributions = array_merge($contributions['approved'], $contributions['pending']);

          // Sort by date (newest first)
          usort($allContributions, function($a, $b) {
            return strtotime($b['submitted_date']) - strtotime($a['submitted_date']);
          });
        ?>
        <div class="user-section">
          <div class="user-header">
            <div class="user-avatar-large"><?= strtoupper(substr($userName, 0, 1)) ?></div>
            <div class="user-info">
              <h2 class="user-name"><?= htmlspecialchars($userName) ?></h2>
              <div class="user-stats">
                <span class="stat-item">
                  <span class="stat-number"><?= $totalCount ?></span>
                  <span class="stat-label">Total Contributions</span>
                </span>
                <span class="stat-divider">•</span>
                <span class="stat-item">
                  <span class="stat-number approved-color"><?= $approvedCount ?></span>
                  <span class="stat-label">Approved</span>
                </span>
                <span class="stat-divider">•</span>
                <span class="stat-item">
                  <span class="stat-number pending-color"><?= $pendingCount ?></span>
                  <span class="stat-label">Pending</span>
                </span>
              </div>
            </div>
          </div>

          <div class="contributions-list">
            <?php foreach ($allContributions as $contribution): ?>
              <div class="contribution-item <?= $contribution['status'] ?>">
                <div class="contribution-badge <?= $contribution['status'] ?>">
                  <?= $contribution['status'] === 'approved' ? '✓' : '⏳' ?>
                </div>
                <div class="contribution-details">
                  <h3 class="contribution-community"><?= htmlspecialchars($contribution['community']) ?></h3>
                  <?php if (!empty($contribution['city'])): ?>
                    <p class="contribution-city"><?= htmlspecialchars($contribution['city']) ?></p>
                  <?php endif; ?>
                  <p class="contribution-meta">
                    <?= $contribution['codes_count'] ?> gate code<?= $contribution['codes_count'] !== 1 ? 's' : '' ?>
                    • <?= htmlspecialchars($contribution['submitted_date']) ?>
                  </p>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
</div>

<style>
.history-container {
  display: flex;
  flex-direction: column;
  flex: 1;
  overflow: hidden;
  min-height: 0;
}

.history-card {
  display: flex;
  flex-direction: column;
  height: 100%;
  overflow: hidden;
  margin-bottom: 0;
}

.history-scroll-wrapper {
  flex: 1;
  overflow-y: auto;
  overflow-x: hidden;
  padding-right: 8px;
  padding-bottom: 100px;
  min-height: 0;
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

.user-section {
  margin-bottom: 32px;
  padding: 24px;
  border: 1px solid var(--border);
  border-radius: 12px;
  background: var(--panel);
}

.user-section:last-child {
  margin-bottom: 0;
}

.user-header {
  display: flex;
  align-items: center;
  gap: 20px;
  margin-bottom: 24px;
  padding-bottom: 20px;
  border-bottom: 1px solid var(--border);
}

.user-avatar-large {
  width: 64px;
  height: 64px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--brand), var(--brand-2));
  display: flex;
  align-items: center;
  justify-content: center;
  color: #07140c;
  font-weight: 700;
  font-size: 24px;
  flex-shrink: 0;
}

.user-info {
  flex: 1;
}

.user-name {
  margin: 0 0 12px 0;
  color: var(--text);
  font-size: 1.5rem;
  font-weight: 700;
}

.user-stats {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.stat-item {
  display: flex;
  align-items: center;
  gap: 6px;
}

.stat-number {
  font-size: 1.2rem;
  font-weight: 700;
  color: var(--text);
}

.stat-number.approved-color {
  color: var(--brand);
}

.stat-number.pending-color {
  color: #ff9800;
}

.stat-label {
  font-size: 0.9rem;
  color: var(--muted);
  font-weight: 500;
}

.stat-divider {
  color: var(--muted);
  opacity: 0.5;
}

.contributions-list {
  display: grid;
  gap: 12px;
}

.contribution-item {
  display: flex;
  align-items: flex-start;
  gap: 16px;
  padding: 16px;
  background: var(--panel-2);
  border: 1px solid var(--border);
  border-radius: 10px;
  transition: all 0.2s ease;
}

.contribution-item:hover {
  transform: translateX(4px);
  border-color: var(--brand);
}

.contribution-badge {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
  font-weight: 700;
  flex-shrink: 0;
}

.contribution-badge.approved {
  background: linear-gradient(135deg, rgba(59, 221, 130, 0.2), rgba(27, 191, 103, 0.15));
  color: var(--brand);
}

.contribution-badge.pending {
  background: linear-gradient(135deg, rgba(255, 152, 0, 0.2), rgba(245, 124, 0, 0.15));
  color: #ff9800;
}

.contribution-details {
  flex: 1;
  min-width: 0;
}

.contribution-community {
  margin: 0 0 6px 0;
  color: var(--text);
  font-size: 1rem;
  font-weight: 700;
}

.contribution-city {
  margin: 0 0 6px 0;
  color: var(--brand);
  font-size: 0.85rem;
  font-weight: 600;
}

.contribution-meta {
  margin: 0;
  color: var(--muted);
  font-size: 0.8rem;
  font-weight: 500;
}

@media (max-width: 768px) {
  .user-header {
    flex-direction: row;
    align-items: center;
  }

  .user-avatar-large {
    width: 48px;
    height: 48px;
    font-size: 20px;
  }

  .user-name {
    font-size: 1.25rem;
  }

  .user-stats {
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
  }

  .stat-divider {
    display: none;
  }

  .contribution-item {
    padding: 12px;
    gap: 12px;
  }

  .contribution-badge {
    width: 32px;
    height: 32px;
    font-size: 16px;
  }

  .user-section {
    padding: 16px;
  }
}
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
