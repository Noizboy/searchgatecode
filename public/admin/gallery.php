<?php
/******************** CONFIG ********************/
require_once __DIR__ . '/includes/config.php';
require_key();

/******************** COUNT SUGGESTIONS ********************/
$suggest_count = 0;
if(file_exists(SUGGEST_JSON)){
  $sugg = json_decode(file_get_contents(SUGGEST_JSON), true);
  if(is_array($sugg)) $suggest_count = count($sugg);
}

/******************** LOAD GATES DATA ********************/
$gates_data = [];
if(file_exists(GATES_JSON)){
  $gates_data = json_decode(file_get_contents(GATES_JSON), true);
  if(!is_array($gates_data)) $gates_data = [];
}

/******************** GET ALL IMAGES FROM ASSETS ********************/
$assets_dir = __DIR__ . '/../assets/';
$assets_url = '../assets/';
$images = [];

if(is_dir($assets_dir)){
  $files = scandir($assets_dir);
  foreach($files as $file){
    if($file === '.' || $file === '..' || $file === 'thumbnailnone.png') continue;
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if(in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])){
      // Find which community uses this image
      $community_name = '';
      foreach($gates_data as $community){
        if(isset($community['codes']) && is_array($community['codes'])){
          foreach($community['codes'] as $code){
            if(isset($code['photo']) && strpos($code['photo'], $file) !== false){
              $community_name = $community['community'] ?? '';
              break 2;
            }
          }
        }
      }

      $images[] = [
        'filename' => $file,
        'path' => $assets_url . $file,
        'full_path' => $assets_dir . $file,
        'community' => $community_name ?: 'Unknown',
        'size' => filesize($assets_dir . $file),
        'modified' => filemtime($assets_dir . $file)
      ];
    }
  }
}

// Sort by most recent first
usort($images, function($a, $b){
  return $b['modified'] - $a['modified'];
});

/******************** HANDLE ACTIONS ********************/
$flash_msg = '';
$flash_type = '';

if($_SERVER['REQUEST_METHOD'] === 'POST'){
  $action = $_POST['action'] ?? '';

  // DELETE IMAGE
  if($action === 'delete_image'){
    $filename = $_POST['filename'] ?? '';
    $filepath = $assets_dir . basename($filename);

    if(file_exists($filepath) && $filename !== 'thumbnailnone.png'){
      if(unlink($filepath)){
        // Update gates.json to replace photo references with default image
        $updated_count = 0;
        foreach($gates_data as &$community){
          if(isset($community['codes']) && is_array($community['codes'])){
            foreach($community['codes'] as &$code){
              if(isset($code['photo']) && strpos($code['photo'], $filename) !== false){
                $code['photo'] = 'assets/thumbnailnone.png';
                $updated_count++;
              }
            }
          }
        }
        write_json(GATES_JSON, $gates_data);

        $flash_msg = "Image deleted successfully! $updated_count gate code(s) updated to use default image.";
        $flash_type = 'success';
      } else {
        $flash_msg = 'Failed to delete image.';
        $flash_type = 'error';
      }
    } else {
      $flash_msg = 'Image not found or cannot be deleted.';
      $flash_type = 'error';
    }

    header('Location: gallery.php');
    exit;
  }

  // UPLOAD IMAGE
  if($action === 'upload_image'){
    if(isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK){
      $file = $_FILES['photo'];
      $allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];

      if(in_array($file['type'], $allowed)){
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $unique_name = 'gate_' . date('Ymd_His') . '_' . substr(md5(uniqid()), 0, 6) . '.' . $ext;
        $destination = $assets_dir . $unique_name;

        if(move_uploaded_file($file['tmp_name'], $destination)){
          $flash_msg = 'Image uploaded successfully!';
          $flash_type = 'success';
        } else {
          $flash_msg = 'Failed to upload image.';
          $flash_type = 'error';
        }
      } else {
        $flash_msg = 'Invalid file type. Only images allowed.';
        $flash_type = 'error';
      }
    } else {
      $flash_msg = 'No file uploaded.';
      $flash_type = 'error';
    }

    header('Location: gallery.php');
    exit;
  }
}

/******************** HEADER ********************/
$page_title = 'Image Gallery';
$current_page = 'gallery';
include __DIR__ . '/includes/header.php';
?>

<style>
  .gallery-card {
    background: linear-gradient(180deg, var(--panel), var(--panel-2));
    border: 1px solid var(--line);
    border-radius: 12px;
    box-shadow: 0 2px 8px var(--shadow-sm);
    display: flex;
    flex-direction: column;
    height: calc(100vh - 210px);
    overflow: hidden;
    padding: 20px;
    margin-bottom: 20px;
  }

  .gallery-scroll-wrapper {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
    padding: 16px 8px;
    min-height: 0;
    margin: -16px -8px;
  }

  .gallery-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 24px;
  }

  .gallery-item {
    background: linear-gradient(180deg, var(--panel), var(--panel-2));
    border: 1px solid var(--line);
    border-radius: 12px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    box-shadow: 0 2px 8px var(--shadow-sm);
  }

  .gallery-image-wrapper {
    position: relative;
    width: 100%;
    padding-top: 75%; /* 4:3 aspect ratio */
    background: var(--panel-2);
    overflow: hidden;
    border-bottom: 1px solid var(--line);
  }

  .gallery-image {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
  }

  .gallery-info {
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    flex: 1;
  }

  .gallery-community {
    font-weight: 600;
    color: var(--text);
    font-size: 0.95rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .gallery-filename {
    font-size: 0.8rem;
    color: var(--muted);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-family: monospace;
  }

  .gallery-meta {
    display: flex;
    gap: 12px;
    font-size: 0.75rem;
    color: var(--muted);
    margin-top: 4px;
  }

  .gallery-actions {
    display: flex;
    gap: 8px;
    padding: 12px 16px;
    border-top: 1px solid var(--line);
    background: var(--panel-2);
  }

  .gallery-action-btn {
    flex: 1;
    padding: 8px 12px;
    border: 1px solid var(--line);
    background: var(--panel);
    border-radius: 8px;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    text-decoration: none;
  }

  .gallery-action-btn:hover {
    transform: translateY(-1px);
  }

  .btn-download {
    color: var(--brand);
    border-color: var(--brand);
    text-decoration: none;
  }

  .btn-download:hover {
    background: var(--brand);
    color: white;
    text-decoration: none;
  }

  .btn-delete {
    color: var(--danger);
    border-color: var(--danger);
  }

  .btn-delete:hover {
    background: var(--danger);
    color: white;
  }

  .empty-state {
    text-align: center;
    padding: 80px 20px;
    color: var(--muted);
  }

  .empty-icon {
    width: 120px;
    height: 120px;
    margin: 0 auto 24px;
    opacity: 0.5;
  }

  /* Upload Modal */
  .modal-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(4px);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 1000;
  }

  .modal-backdrop.open {
    display: flex;
  }

  .modal {
    background: var(--panel);
    border: 1px solid var(--border);
    border-radius: 16px;
    max-width: 500px;
    width: 90%;
    max-height: 90vh;
    overflow: auto;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
  }

  .modal-header {
    padding: 24px;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
  }

  .modal-title {
    font-size: 1.4rem;
    font-weight: 700;
    color: var(--text);
    margin: 0;
  }

  .modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    color: var(--muted);
    cursor: pointer;
    padding: 0;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    transition: all 0.2s ease;
  }

  .modal-close:hover {
    background: var(--panel-2);
    color: var(--text);
  }

  .modal-body {
    padding: 24px;
  }

  .form-group {
    margin-bottom: 20px;
  }

  .form-label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: var(--text);
    font-size: 0.9rem;
  }

  .file-upload-area {
    border: 2px dashed var(--border);
    border-radius: 12px;
    padding: 40px 20px;
    text-align: center;
    background: var(--panel-2);
    cursor: pointer;
    transition: all 0.2s ease;
  }

  .file-upload-area:hover {
    border-color: var(--brand);
    background: var(--input-bg-1);
  }

  .file-upload-area.dragover {
    border-color: var(--brand);
    background: var(--input-bg-1);
  }

  .upload-icon {
    width: 60px;
    height: 60px;
    margin: 0 auto 16px;
    color: var(--muted);
  }

  .upload-text {
    color: var(--text);
    font-weight: 600;
    margin-bottom: 8px;
  }

  .upload-hint {
    color: var(--muted);
    font-size: 0.85rem;
  }

  .file-input {
    display: none;
  }

  .preview-area {
    margin-top: 16px;
    display: none;
  }

  .preview-image {
    max-width: 100%;
    border-radius: 12px;
    border: 1px solid var(--border);
  }

  .modal-footer {
    padding: 24px;
    border-top: 1px solid var(--border);
    display: flex;
    gap: 12px;
    justify-content: flex-end;
  }

  .btn {
    padding: 12px 24px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.95rem;
    cursor: pointer;
    transition: all 0.2s ease;
    border: none;
  }

  .btn-secondary {
    background: var(--panel-2);
    color: var(--text);
    border: 1px solid var(--border);
  }

  .btn-secondary:hover {
    background: var(--input-bg-1);
  }

  .btn-primary {
    background: linear-gradient(135deg, var(--brand), var(--brand-2));
    color: white;
    box-shadow: 0 4px 12px rgba(59, 221, 130, 0.3);
  }

  .btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(59, 221, 130, 0.4);
  }

  .btn-primary:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
  }

  @media (max-width: 768px) {
    .gallery-card {
      height: calc(100vh - 280px);
      padding: 16px;
      margin-bottom: 40px;
    }

    .gallery-scroll-wrapper {
      padding: 16px 8px;
      margin: 3px -8px;
    }

    .gallery-grid {
      grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
      gap: 16px;
    }
  }
</style>

<!-- PAGE HEADER -->
<div class="page-header">
  <div class="page-header-left">
    <button class="mobile-menu-toggle" id="mobileMenuToggle">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="3" y1="12" x2="21" y2="12"/>
        <line x1="3" y1="6" x2="21" y2="6"/>
        <line x1="3" y1="18" x2="21" y2="18"/>
      </svg>
    </button>
    <div class="page-header-content">
      <h1 class="page-title">Image Gallery</h1>
      <p class="page-subtitle">Manage community photos and assets • <?= count($images) ?> images • <?= number_format(array_sum(array_column($images, 'size')) / 1024 / 1024, 2) ?> MB</p>
    </div>
  </div>
  <div class="page-header-right">
    <button class="btn btn-primary" id="openUploadModal">
      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
        <polyline points="17 8 12 3 7 8"/>
        <line x1="12" y1="3" x2="12" y2="15"/>
      </svg>
      Upload Image
    </button>
  </div>
</div>

<?php if($flash_msg): ?>
  <div class="flash-message <?= $flash_type ?>">
    <?= htmlspecialchars($flash_msg) ?>
  </div>
<?php endif; ?>

<!-- GALLERY CARD -->
<div class="gallery-card">
  <div class="gallery-scroll-wrapper">
    <?php if(empty($images)): ?>
      <div class="empty-state">
        <svg class="empty-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
          <circle cx="8.5" cy="8.5" r="1.5"/>
          <polyline points="21 15 16 10 5 21"/>
        </svg>
        <h3>No Images Found</h3>
        <p>Upload your first image to get started!</p>
      </div>
    <?php else: ?>
      <div class="gallery-grid">
        <?php foreach($images as $image): ?>
          <div class="gallery-item">
        <div class="gallery-image-wrapper">
          <img src="<?= htmlspecialchars($image['path']) ?>" alt="<?= htmlspecialchars($image['community']) ?>" class="gallery-image">
        </div>
        <div class="gallery-info">
          <div class="gallery-community"><?= htmlspecialchars($image['community']) ?></div>
          <div class="gallery-filename"><?= htmlspecialchars($image['filename']) ?></div>
          <div class="gallery-meta">
            <span><?= number_format($image['size'] / 1024, 2) ?> KB</span>
            <span>•</span>
            <span><?= date('M d, Y', $image['modified']) ?></span>
          </div>
        </div>
        <div class="gallery-actions">
          <a href="<?= htmlspecialchars($image['path']) ?>" download="<?= htmlspecialchars($image['filename']) ?>" class="gallery-action-btn btn-download">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
              <polyline points="7 10 12 15 17 10"/>
              <line x1="12" y1="15" x2="12" y2="3"/>
            </svg>
            Download
          </a>
          <button class="gallery-action-btn btn-delete" onclick="confirmDelete('<?= htmlspecialchars($image['filename'], ENT_QUOTES) ?>')">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="3 6 5 6 21 6"/>
              <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
            </svg>
            Delete
          </button>
        </div>
      </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Upload Modal -->
<div class="modal-backdrop" id="uploadModal">
  <div class="modal">
    <div class="modal-header">
      <h2 class="modal-title">Upload Image</h2>
      <button class="modal-close" id="closeUploadModal">✕</button>
    </div>
    <form method="POST" enctype="multipart/form-data" id="uploadForm">
      <input type="hidden" name="action" value="upload_image">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Select Image</label>
          <div class="file-upload-area" id="fileUploadArea">
            <svg class="upload-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
              <polyline points="17 8 12 3 7 8"/>
              <line x1="12" y1="3" x2="12" y2="15"/>
            </svg>
            <div class="upload-text">Click to upload or drag and drop</div>
            <div class="upload-hint">JPEG, PNG, GIF, WebP (Max 10MB)</div>
            <input type="file" name="photo" accept="image/*" class="file-input" id="fileInput" required>
          </div>
          <div class="preview-area" id="previewArea">
            <img src="" alt="Preview" class="preview-image" id="previewImage">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" id="cancelUpload">Cancel</button>
        <button type="submit" class="btn btn-primary" id="submitUpload" disabled>Upload</button>
      </div>
    </form>
  </div>
</div>

<!-- Delete Form (hidden) -->
<form method="POST" id="deleteForm" style="display: none;">
  <input type="hidden" name="action" value="delete_image">
  <input type="hidden" name="filename" id="deleteFilename">
</form>

<script>
// Upload Modal
const uploadModal = document.getElementById('uploadModal');
const openUploadModalBtn = document.getElementById('openUploadModal');
const closeUploadModalBtn = document.getElementById('closeUploadModal');
const cancelUploadBtn = document.getElementById('cancelUpload');
const fileUploadArea = document.getElementById('fileUploadArea');
const fileInput = document.getElementById('fileInput');
const previewArea = document.getElementById('previewArea');
const previewImage = document.getElementById('previewImage');
const submitUploadBtn = document.getElementById('submitUpload');

openUploadModalBtn.addEventListener('click', () => {
  uploadModal.classList.add('open');
  document.body.style.overflow = 'hidden';
});

function closeUploadModal() {
  uploadModal.classList.remove('open');
  document.body.style.overflow = '';
  fileInput.value = '';
  previewArea.style.display = 'none';
  submitUploadBtn.disabled = true;
}

closeUploadModalBtn.addEventListener('click', closeUploadModal);
cancelUploadBtn.addEventListener('click', closeUploadModal);

uploadModal.addEventListener('click', (e) => {
  if (e.target === uploadModal) {
    closeUploadModal();
  }
});

// File upload area click
fileUploadArea.addEventListener('click', () => {
  fileInput.click();
});

// File input change
fileInput.addEventListener('change', (e) => {
  const file = e.target.files[0];
  if (file) {
    const reader = new FileReader();
    reader.onload = (e) => {
      previewImage.src = e.target.result;
      previewArea.style.display = 'block';
      submitUploadBtn.disabled = false;
    };
    reader.readAsDataURL(file);
  }
});

// Drag and drop
fileUploadArea.addEventListener('dragover', (e) => {
  e.preventDefault();
  fileUploadArea.classList.add('dragover');
});

fileUploadArea.addEventListener('dragleave', () => {
  fileUploadArea.classList.remove('dragover');
});

fileUploadArea.addEventListener('drop', (e) => {
  e.preventDefault();
  fileUploadArea.classList.remove('dragover');

  const files = e.dataTransfer.files;
  if (files.length > 0) {
    fileInput.files = files;
    fileInput.dispatchEvent(new Event('change'));
  }
});

// Delete confirmation
function confirmDelete(filename) {
  showAlert({
    type: 'warning',
    title: 'Delete Image',
    message: `Are you sure you want to delete "${filename}"?\n\nThis will also remove the photo reference from all gate codes using this image.`,
    buttons: [
      {
        text: 'Cancel',
        className: 'btn-alert-secondary'
      },
      {
        text: 'Delete',
        className: 'btn-alert-danger',
        onClick: () => {
          document.getElementById('deleteFilename').value = filename;
          document.getElementById('deleteForm').submit();
        }
      }
    ]
  });
}

// ESC key to close modal
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape' && uploadModal.classList.contains('open')) {
    closeUploadModal();
  }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
