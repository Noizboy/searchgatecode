<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Gate Code</title>
<style>
  :root{
    --bg:#0b0d10; --panel:#151a20; --panel-2:#0f1318;
    --text:#e8eef4; --muted:#93a0ad; --brand:#3bdd82; --brand-2:#1bbf67;
    --danger:#ff5c5c; --danger-2:#e53935; --radius:14px;
    --gradient-1:#1a2330; --gradient-2:#11202a;
    --border:#2a3340; --border-2:#1e2a34;
    --input-bg-1:#0f141a; --input-bg-2:#0c1116;
    --scrollbar-track:#0f141a; --scrollbar-thumb:#2a3340; --scrollbar-thumb-hover:#364456;
    --modal-bg-1:#1a1f26; --modal-bg-2:#12161c; --modal-border:#233041;
    --btn-secondary-bg:#22272f; --btn-secondary-text:#d0d7de; --btn-secondary-border:#2e3947;
    --btn-secondary-hover:#2a3240;
    --footer-bg:rgba(15,19,24,0.5);
  }

  [data-theme="light"]{
    --bg:#f5f7fa; --panel:#ffffff; --panel-2:#f8f9fa;
    --text:#1a1f26; --muted:#5a6c7d; --brand:#3bdd82; --brand-2:#1bbf67;
    --danger:#ff5c5c; --danger-2:#e53935; --radius:14px;
    --gradient-1:#e0f5ee; --gradient-2:#d4ede2;
    --border:#d1dce5; --border-2:#e1e8ed;
    --input-bg-1:#ffffff; --input-bg-2:#f9fafb;
    --scrollbar-track:#e8eef4; --scrollbar-thumb:#c1ccd7; --scrollbar-thumb-hover:#a8b5c2;
    --modal-bg-1:#ffffff; --modal-bg-2:#f8f9fa; --modal-border:#d1dce5;
    --btn-secondary-bg:#f0f3f6; --btn-secondary-text:#2c3845; --btn-secondary-border:#d1dce5;
    --btn-secondary-hover:#e4e9ed;
    --footer-bg:rgba(255,255,255,0.5);
  }

  html,body{
    height:100%; margin:0; font-family:system-ui,Segoe UI,Roboto,Arial; color:var(--text);
    background:
      radial-gradient(1000px 500px at 80% -10%, var(--gradient-1) 0%, transparent 60%),
      radial-gradient(900px 400px at -10% 90%, var(--gradient-2) 0%, transparent 55%),
      var(--bg);
    transition: background 0.3s ease, color 0.3s ease;
  }
  body{display:flex;flex-direction:column;min-height:100vh}
  
  main{
    flex:1;
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:flex-start;
    padding:40px 20px 60px 20px;
    text-align:center;
  }

  h1{margin:0 0 10px 0;font-size:2rem}
  .sub{color:var(--muted);margin-bottom:30px}

  /* Title style + underline animation */
  .title {
    font-size: 3rem; font-weight: 800; text-transform: uppercase;
    letter-spacing: 2px; margin: 0 0 15px 0;
    background: linear-gradient(90deg, #3bdd82, #1bbf67);
    -webkit-background-clip: text; -webkit-text-fill-color: transparent;
    background-clip: text;
    position: relative; display: inline-block; text-shadow: 0 2px 6px rgba(0,0,0,.3);
    text-decoration: none;
    cursor: pointer;
    transition: opacity .2s ease;
  }
  .title:hover {
    opacity: 0.85;
  }
  .title::after {
    content:""; position: absolute; left: 0; bottom: -6px; width: 100%; height: 3px;
    background: linear-gradient(90deg, #3bdd82, #1bbf67);
    border-radius: 2px; transform: scaleX(0); transform-origin: left; transition: transform .4s ease;
  }
  .title.animate::after { transform: scaleX(1); }

  /* Search Form - Vertical Layout */
  .search{
    width:100%;
    max-width:420px;
    margin-bottom:24px;
    display:flex;
    flex-direction:column;
    gap:12px;
  }
  .search input{
    padding:14px 16px;
    width:100%;
    box-sizing:border-box;
    border-radius:12px;
    border:1px solid var(--border);
    background:linear-gradient(180deg,var(--input-bg-1),var(--input-bg-2));
    color:var(--text);
    font-size:15px;
    outline:none;
    transition:border-color .15s ease, box-shadow .15s ease;
  }
  .search input::placeholder{color:var(--muted)}
  .search input:focus{
    border-color:var(--brand);
    box-shadow:0 0 0 3px rgba(59,221,130,.15);
  }
  
  .btn-primary{
    padding:14px 20px;
    width:100%;
    border-radius:12px;
    background:linear-gradient(135deg,var(--brand),var(--brand-2));
    border:0;
    font-weight:700;
    font-size:16px;
    cursor:pointer;
    color:#07140c;
    box-shadow:0 4px 14px rgba(59,221,130,.4);
    transition:transform .1s ease, box-shadow .2s ease;
  }
  .btn-primary:hover{box-shadow:0 6px 18px rgba(59,221,130,.55)}
  .btn-primary:active{transform:translateY(1px)}

  /* Results Container with Scroll */
  .results-container{
    width:100%;
    max-width:420px;
    max-height:400px;
    overflow-y:auto;
    overflow-x:hidden;
    padding-right:8px;
  }
  
  /* Custom Scrollbar */
  .results-container::-webkit-scrollbar {
    width: 8px;
  }
  .results-container::-webkit-scrollbar-track {
    background: var(--scrollbar-track);
    border-radius: 10px;
  }
  .results-container::-webkit-scrollbar-thumb {
    background: var(--scrollbar-thumb);
    border-radius: 10px;
  }
  .results-container::-webkit-scrollbar-thumb:hover {
    background: var(--scrollbar-thumb-hover);
  }

  .grid{display:grid;gap:14px;}
  .item{
    background:var(--panel);
    padding:16px;
    border-radius:var(--radius);
    text-align:left;
    border:1px solid var(--border-2);
  }
  .item-head{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:10px;
  }
  .community{font-weight:700;font-size:18px}
  .codes{display:grid;gap:8px}
  .code-row{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:10px;
    background:var(--panel-2);
    border:1px solid var(--border-2);
    border-radius:10px;
    padding:10px;
    text-align:left;
  }
  .code-row > div:first-child{
    flex:1;
    display:flex;
    flex-direction:column;
    align-items:flex-start;
  }
  .actions{display:flex; gap:8px; margin-left:auto;}

  .code{font-family:monospace;font-size:17px;font-weight:600; display:flex; align-items:center; gap:8px;}
  .note{color:var(--muted);font-size:13px;text-align:left;margin-top:2px}

  .report-badge{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    width:20px;
    height:20px;
    min-width:20px;
    min-height:20px;
    max-width:20px;
    max-height:20px;
    background:#ff3b3b;
    color:#fff;
    font-size:14px;
    font-weight:800;
    border-radius:50%;
    font-family:system-ui,Segoe UI,Roboto,Arial;
    flex-shrink:0;
    line-height:1;
    padding-bottom:2px;
    box-sizing:border-box;
  }

  .btn-secondary{
    background:var(--btn-secondary-bg); color:var(--btn-secondary-text); border:1px solid var(--btn-secondary-border);
    padding:8px 12px; border-radius:10px; cursor:pointer; font-weight:600;
    transition:background .15s ease, transform .1s ease;
    font-size:14px;
  }
  .btn-secondary:hover{ background:var(--btn-secondary-hover) }
  .btn-secondary:active{ transform:translateY(1px) }

  .btn-report{
    background:linear-gradient(135deg,var(--danger),var(--danger-2));
    color:#fff;font-weight:600;border:0;
    padding:8px 12px;border-radius:10px;cursor:pointer;
    box-shadow:0 4px 12px rgba(255,92,92,.35);
    transition:transform .1s ease, box-shadow .2s ease;
    font-size:14px;
  }
  .btn-report:hover{box-shadow:0 6px 16px rgba(255,92,92,.5)}
  .btn-report:active{transform:translateY(1px)}

  .empty,.hint{
    color:var(--muted);
    margin-top:10px;
    font-size:14px;
  }
  
  /* Footer */
  footer{
    padding:16px;
    text-align:center;
    font-size:13px;
    color:var(--muted);
    border-top:1px solid var(--border-2);
    background:var(--footer-bg);
  }
  footer a{
    color:var(--brand);
    text-decoration:none;
    font-weight:600;
    transition:color .15s ease;
  }
  footer a:hover{
    color:var(--brand-2);
    text-decoration:underline;
  }

  /* Modal */
  .modal-backdrop{
    position:fixed; inset:0; background:rgba(0,0,0,.55);
    display:none; align-items:center; justify-content:center; padding:16px; z-index:50;
  }
  .modal-backdrop.open{ display:flex; }
  .modal{
    width:min(92vw, 700px);
    max-height:90vh;
    display:flex; flex-direction:column;
    background:linear-gradient(180deg, var(--modal-bg-1), var(--modal-bg-2));
    border:1px solid var(--modal-border); border-radius:12px; box-shadow:0 20px 60px rgba(0,0,0,.5);
    overflow:hidden; text-align:left;
  }
  .modal-header{
    display:flex; justify-content:space-between; align-items:center;
    padding:14px 16px; border-bottom:1px solid var(--border)
  }
  .modal-title{font-weight:700}
  .modal-close{
    background:var(--btn-secondary-bg); color:var(--btn-secondary-text); border:1px solid var(--btn-secondary-border);
    padding:6px 10px; border-radius:8px; cursor:pointer; font-weight:600
  }
  .modal-body{
    padding:0; display:flex; flex-direction:column; overflow:auto;
  }
  .modal-img{
    width:100%; height:auto; max-height:50vh;
    object-fit:contain; background:#000;
    border:none; border-radius:0; display:block;
  }
  .modal-note{ color:var(--text); font-size:14px; line-height:1.45; padding:12px 16px 6px 16px }
  .modal-meta{ color:var(--muted); font-size:13px; padding:0 16px 14px 16px }

  /* Theme Toggle Button */
  .theme-toggle {
    position: fixed;
    top: 20px;
    right: 20px;
    background: var(--panel);
    border: 1px solid var(--border);
    border-radius: 50%;
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 12px rgba(0,0,0,.2);
    z-index: 100;
  }
  .theme-toggle:hover {
    transform: scale(1.1);
    box-shadow: 0 6px 16px rgba(59,221,130,.3);
  }
  .theme-toggle svg {
    width: 24px;
    height: 24px;
    fill: var(--brand);
    transition: transform 0.3s ease;
  }
  .theme-toggle:hover svg {
    transform: rotate(20deg);
  }

  @media (max-width: 480px) {
    .title{font-size:2.2rem}
    .search{max-width:100%}
    .results-container{max-width:100%}
    .theme-toggle {
      width: 44px;
      height: 44px;
      top: 15px;
      right: 15px;
    }
    .theme-toggle svg {
      width: 20px;
      height: 20px;
    }
  }
</style>
</head>
<body>
  <!-- Theme Toggle Button -->
  <button id="themeToggle" class="theme-toggle" aria-label="Toggle theme">
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

  <main>
    <a href="/" id="title" class="title">Gate Codes</a>
    <div class="sub">Search community gate codes</div>

    <form class="search" id="searchForm" role="search" aria-label="Community search">
      <input id="q" placeholder="e.g. Water Oaks" aria-label="Community name" required>
      <button class="btn-primary" type="submit">Search</button>
    </form>

    <div id="msg" class="hint">Type a community name and press Search.</div>

    <div class="results-container">
      <div id="results" class="grid"></div>
    </div>
  </main>

  <footer>
      <span>© <?=date('Y')?> Built by <a href="mailto:blancuniverse@gmail.com" class="footer-by">Alejandro</a> | <a href="submit.html">Submit Community</a></span>

  </footer>

  <!-- Modal -->
  <div id="backdrop" class="modal-backdrop" aria-hidden="true">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
      <div class="modal-header">
        <div id="modalTitle" class="modal-title">Details</div>
        <button class="modal-close" id="modalClose" type="button">Close</button>
      </div>
      <div class="modal-body">
        <img id="modalImg" class="modal-img" alt="Location photo" />
        <div id="modalText" class="modal-note"></div>
        <div id="modalMeta" class="modal-meta"></div>
      </div>
    </div>
  </div>

<script>
const JSON_URL   = 'data/gates.json';
const ASSETS_URL = 'assets/';
const DEFAULT_PHOTO = 'thumbnailnone.png';
let DATA = [];

function norm(s){
  return (s||'').toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'');
}

async function loadData(){
  try{
    const r = await fetch(JSON_URL, { cache: 'no-store' });
    if(!r.ok) throw new Error('Failed to load JSON');
    DATA = await r.json();
  }catch(e){
    DATA = [];
    document.getElementById('msg').textContent = 'Error loading data file.';
  }
}

function renderNone(q){
  const res = document.getElementById('results');
  res.innerHTML = '';
  const msg = document.getElementById('msg');
  if(!q){
    msg.textContent = 'Type a community name and press Search.';
  } else {
    msg.textContent = `No results for "${q}".`;
  }
  msg.style.display = 'block';
}

function renderResults(items){
  const res = document.getElementById('results');
  const msg = document.getElementById('msg');
  msg.style.display = 'none';
  res.innerHTML = items.map(it => `
    <section class="item">
      <div class="item-head">
        <div class="community">${escapeHtml(it.community)}</div>
      </div>
      <div class="codes">
        ${it.codes.map(c => `
          <div class="code-row">
            <div>
              <div class="code">
                <span>${escapeHtml(c.code)}</span>
                ${c.report_count > 0 ? `<span class="report-badge" title="${c.report_count} report${c.report_count > 1 ? 's' : ''}">!</span>` : ''}
              </div>
              ${c.notes ? `<div class="note">${escapeHtml(c.notes)}</div>` : ``}
            </div>
            <div class="actions">
              <button class="btn-secondary btn-details"
                data-community="${escapeHtml(it.community)}"
                data-code="${escapeHtml(c.code)}"
                data-details="${escapeHtml(c.details||'')}"
                data-photo="${escapeHtml(c.photo||'')}"
                data-notes="${escapeHtml(c.notes||'')}">Details</button>
              <button class="btn-report" data-community="${escapeHtml(it.community)}" data-code="${escapeHtml(c.code)}">Report</button>
            </div>
          </div>
        `).join('')}
      </div>
    </section>
  `).join('');

  res.querySelectorAll('.btn-details').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      openModal({
        community: btn.dataset.community,
        code: btn.dataset.code,
        details: btn.dataset.details,
        photo: btn.dataset.photo,
        notes: btn.dataset.notes
      });
    });
  });

  res.querySelectorAll('.btn-report').forEach(btn=>{
    btn.addEventListener('click', async ()=>{
      const comm = btn.getAttribute('data-community');
      const code = btn.getAttribute('data-code');

      // Disable button to prevent double-clicks
      btn.disabled = true;
      btn.textContent = 'Reporting...';

      try {
        const response = await fetch('report_gate.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            community: comm,
            code: code
          })
        });

        const result = await response.json();

        if (result.success) {
          // Update local DATA
          const community = DATA.find(x => x.community === comm);
          if (community) {
            const codeObj = community.codes.find(c => c.code === code);
            if (codeObj) {
              codeObj.report_count = (codeObj.report_count || 0) + 1;
            }
          }

          // Re-render results to show updated badge
          const q = document.getElementById('q').value.trim();
          search(q);

          alert(`Report submitted for ${code} from ${comm}`);
        } else {
          alert(`Error: ${result.message}`);
          btn.disabled = false;
          btn.textContent = 'Report';
        }
      } catch (error) {
        console.error('Error reporting gate:', error);
        alert('Failed to submit report. Please try again.');
        btn.disabled = false;
        btn.textContent = 'Report';
      }
    });
  });
}

function search(q){
  const qn = norm(q);
  const hits = DATA.filter(x => norm(x.community).includes(qn));
  if(hits.length === 0){ renderNone(q); return; }
  renderResults(hits);
}

function escapeHtml(s){ return String(s||'').replace(/[&<>"']/g,c=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' })[c]); }

function resolvePhoto(p){
  const v = (p||'').trim();
  if (/^(https?:)?\/\//.test(v)) return v;
  if (/^\.{1,2}\//.test(v)) return v;
  if (/^\//.test(v)) return v;
  if (/^assets\//i.test(v)) return v;
  return ASSETS_URL + (v || DEFAULT_PHOTO);
}

document.getElementById('searchForm').addEventListener('submit', async (e)=>{
  e.preventDefault();
  const q = document.getElementById('q').value.trim();
  if(!DATA.length) await loadData();
  
  const title = document.getElementById('title');
  title.classList.add('animate');
  setTimeout(()=> title.classList.remove('animate'), 600);

  if(!q){ renderNone(''); return; }
  search(q);
});

const backdrop = document.getElementById('backdrop');
const modalImg = document.getElementById('modalImg');
const modalText = document.getElementById('modalText');
const modalMeta = document.getElementById('modalMeta');
const modalClose = document.getElementById('modalClose');

function openModal({community, code, details, photo, notes}){
  document.getElementById('modalTitle').textContent = `Details — ${community}`;

  const src = resolvePhoto(photo);
  modalImg.onerror = () => {
    modalImg.onerror = null;
    modalImg.src = resolvePhoto(DEFAULT_PHOTO);
  };
  modalImg.src = src;
  modalImg.alt = photo ? `Photo for ${community} (${code})` : 'No photo available';

  modalText.textContent = details || 'No extra details.';
  modalMeta.textContent = `${community} • Code: ${code}${notes ? ' • ' + notes : ''}`;
  backdrop.classList.add('open');
  backdrop.setAttribute('aria-hidden','false');
  modalClose.focus();
}

function closeModal(){
  backdrop.classList.remove('open');
  backdrop.setAttribute('aria-hidden','true');
}
modalClose.addEventListener('click', closeModal);
backdrop.addEventListener('click', (e)=> { if(e.target === backdrop) closeModal(); });
window.addEventListener('keydown', (e)=> { if(e.key === 'Escape' && backdrop.classList.contains('open')) closeModal(); });

// Theme Toggle Functionality
const themeToggle = document.getElementById('themeToggle');
const moonIcon = document.getElementById('moonIcon');
const sunIcon = document.getElementById('sunIcon');
const htmlElement = document.documentElement;

// Load theme from localStorage or default to dark
const savedTheme = localStorage.getItem('theme') || 'dark';
if (savedTheme === 'light') {
  htmlElement.setAttribute('data-theme', 'light');
  moonIcon.style.display = 'block';
  sunIcon.style.display = 'none';
} else {
  moonIcon.style.display = 'none';
  sunIcon.style.display = 'block';
}

// Toggle theme
themeToggle.addEventListener('click', () => {
  const currentTheme = htmlElement.getAttribute('data-theme');
  const newTheme = currentTheme === 'light' ? 'dark' : 'light';

  htmlElement.setAttribute('data-theme', newTheme);
  localStorage.setItem('theme', newTheme);

  if (newTheme === 'light') {
    moonIcon.style.display = 'block';
    sunIcon.style.display = 'none';
  } else {
    moonIcon.style.display = 'none';
    sunIcon.style.display = 'block';
  }
});

loadData();
</script>
</body>
</html>