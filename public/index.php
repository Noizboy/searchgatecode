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
  }
  html,body{
    height:100%; margin:0; font-family:system-ui,Segoe UI,Roboto,Arial; color:var(--text);
    background:
      radial-gradient(1000px 500px at 80% -10%, #1a2330 0%, transparent 60%),
      radial-gradient(900px 400px at -10% 90%, #11202a 0%, transparent 55%),
      var(--bg);
  }
  body{display:flex;flex-direction:column;justify-content:center;align-items:center;text-align:center}
  h1{margin:0 0 10px 0;font-size:2rem}
  .sub{color:var(--muted);margin-bottom:20px}

  /* Search */
  .search{margin-bottom:20px}
  .search input{
    padding:12px;width:240px;max-width:90%;
    border-radius:10px;border:1px solid #222;background:#0f141a;color:var(--text)
  }
  .btn-primary{
    padding:12px 18px;margin-left:6px;border-radius:10px;
    background:linear-gradient(135deg,var(--brand),var(--brand-2));
    border:0;font-weight:600;cursor:pointer;color:#07140c;
    box-shadow:0 4px 14px rgba(59,221,130,.4);
    transition:transform .1s ease, box-shadow .2s ease;
  }
  .btn-primary:hover{box-shadow:0 6px 18px rgba(59,221,130,.55)}
  .btn-primary:active{transform:translateY(1px)}

  /* Results */
  .grid{display:grid;gap:14px;max-width:420px;margin:0 auto; padding:0 12px}
  .item{background:var(--panel);padding:16px;border-radius:var(--radius); text-align:left;}
  .item-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px}
  .community{font-weight:700}
  .codes{display:grid;gap:8px}
  .code-row{
    display:flex;justify-content:space-between;align-items:center;gap:10px;
    background:var(--panel-2);border:1px solid #1e2a34;border-radius:10px;padding:10px;
    text-align:left;
  }
  .code-row > div:first-child{
    flex:1;
    display:flex;
    flex-direction:column;
    align-items:flex-start; /* código y nota a la izquierda */
  }
  .actions{display:flex; gap:8px; margin-left:auto;}

  .code{font-family:monospace;font-size:15px}
  .note{color:#9fb0be;font-size:13px;text-align:left}

  .btn-secondary{
    background:#22272f; color:#d0d7de; border:1px solid #2e3947;
    padding:8px 12px; border-radius:10px; cursor:pointer; font-weight:600;
    transition:background .15s ease, transform .1s ease;
  }
  .btn-secondary:hover{ background:#2a3240 }
  .btn-secondary:active{ transform:translateY(1px) }

  .btn-report{
    background:linear-gradient(135deg,var(--danger),var(--danger-2));
    color:#fff;font-weight:600;border:0;
    padding:8px 12px;border-radius:10px;cursor:pointer;
    box-shadow:0 4px 12px rgba(255,92,92,.35);
    transition:transform .1s ease, box-shadow .2s ease;
  }
  .btn-report:hover{box-shadow:0 6px 16px rgba(255,92,92,.5)}
  .btn-report:active{transform:translateY(1px)}

  .empty,.hint{color:var(--muted);margin-top:10px}
  footer{position:absolute;bottom:12px;width:100%;text-align:center;font-size:13px;color:var(--muted)}

  /* Title style + underline animation */
  .title {
    font-size: 3rem; font-weight: 800; text-transform: uppercase;
    letter-spacing: 2px; margin: 0 0 15px 0;
    background: linear-gradient(90deg, #3bdd82, #1bbf67);
    -webkit-background-clip: text; -webkit-text-fill-color: transparent;
    position: relative; display: inline-block; text-shadow: 0 2px 6px rgba(0,0,0,.3);
  }
  .title::after {
    content:""; position: absolute; left: 0; bottom: -6px; width: 100%; height: 3px;
    background: linear-gradient(90deg, #3bdd82, #1bbf67);
    border-radius: 2px; transform: scaleX(0); transform-origin: left; transition: transform .4s ease;
  }
  .title.animate::after { transform: scaleX(1); }

  /* Modal (rectangular + imagen adaptativa completa) */
  .modal-backdrop{
    position:fixed; inset:0; background:rgba(0,0,0,.55);
    display:none; align-items:center; justify-content:center; padding:16px; z-index:50;
  }
  .modal-backdrop.open{ display:flex; }
  .modal{
    width:min(92vw, 700px);
    max-height:90vh;
    display:flex; flex-direction:column;
    background:linear-gradient(180deg, #1a1f26, #12161c);
    border:1px solid #233041; border-radius:12px; box-shadow:0 20px 60px rgba(0,0,0,.5);
    overflow:hidden; text-align:left;
  }
  .modal-header{
    display:flex; justify-content:space-between; align-items:center;
    padding:14px 16px; border-bottom:1px solid #22303b
  }
  .modal-title{font-weight:700}
  .modal-close{
    background:#21262d; color:#c9d1d9; border:1px solid #2e3947;
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
  .modal-note{ color:#c7d5e3; font-size:14px; line-height:1.45; padding:12px 16px 6px 16px }
  .modal-meta{ color:#9fb0be; font-size:13px; padding:0 16px 14px 16px }
</style>
</head>
<body>
  <main>
    <h1 id="title" class="title">Search Gate Codes</h1>
    <!-- testing 2-->
    <div class="sub">Search community gate codes</div>

    <form class="search" id="searchForm" role="search" aria-label="Community search">
      <input id="q" placeholder="e.g. Water Oaks" aria-label="Community name" required>
      <button class="btn-primary" type="submit">Search</button>
    </form>

    <div id="results" class="grid"></div>
    <div id="msg" class="hint">Type a community name and press Search.</div>
  </main>

  <footer>© <span id="year"></span> Made by Alejandro</footer>

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
const ASSETS_URL = 'assets/';            // ajusta si tu carpeta es otra
const DEFAULT_PHOTO = 'thumbnailnone.png';
let DATA = [];

// util: normalizar para búsqueda (lowercase + sin acentos)
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
}

function renderResults(items){
  const res = document.getElementById('results');
  const msg = document.getElementById('msg');
  msg.textContent = ''; // oculta mensaje
  res.innerHTML = items.map(it => `
    <section class="item">
      <div class="item-head">
        <div class="community">${escapeHtml(it.community)}</div>
      </div>
      <div class="codes">
        ${it.codes.map(c => `
          <div class="code-row">
            <div>
              <div class="code">${escapeHtml(c.code)}</div>
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

  // Details → Modal
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

  // Report (conecta a tu API cuando la tengas)
  res.querySelectorAll('.btn-report').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      const comm = btn.getAttribute('data-community');
      const code = btn.getAttribute('data-code');
      alert(`Reported ${code} from ${comm}`);
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

  // ya es absoluta o relativa completa
  if (/^(https?:)?\/\//.test(v)) return v;     // http(s) o protocolo relativo
  if (/^\.{1,2}\//.test(v)) return v;          // ./ ó ../
  if (/^\//.test(v)) return v;                 // desde raíz del dominio
  if (/^assets\//i.test(v)) return v;          // ya incluye 'assets/'

  // solo nombre de archivo ⇒ anteponer carpeta assets
  return ASSETS_URL + (v || DEFAULT_PHOTO);
}

document.getElementById('searchForm').addEventListener('submit', async (e)=>{
  e.preventDefault();
  const q = document.getElementById('q').value.trim();
  if(!DATA.length) await loadData();
  // animación del título
  const title = document.getElementById('title');
  title.classList.add('animate'); setTimeout(()=> title.classList.remove('animate'), 600);

  if(!q){ renderNone(''); return; }
  search(q);
});

// Modal refs y control
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

// Año dinámico
document.getElementById('year').textContent = new Date().getFullYear();

// Carga del JSON al iniciar (no muestra resultados hasta que busques)
loadData();
</script>

<script>
const form = document.getElementById('searchForm'); 
const titleEl = document.getElementById('title');
form.addEventListener('submit', () => {
  titleEl.classList.add('animate');
  setTimeout(()=> titleEl.classList.remove('animate'), 600);
});
</script>
</body>
</html>
