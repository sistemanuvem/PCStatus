<?php
require_once __DIR__ . '/auth.php';
auth_check();
$logged_user = auth_user();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>PC Status</title>
<link rel="icon" type="image/webp" href="icone.webp">
<style>
:root {
  --bg:#0d1117; --card:#161b22; --border:#30363d;
  --text:#c9d1d9; --dim:#8b949e; --accent:#58a6ff;
  --ok:#3fb950; --warn:#d29922; --danger:#f85149; --bar-bg:#21262d;
}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Consolas','Courier New',monospace;background:var(--bg);color:var(--text);padding:20px}
h1{color:var(--accent);font-size:1.4rem;margin-bottom:0}
#meta{color:var(--dim);font-size:.8rem;margin-bottom:16px}

/* Barra do topo */
.top-bar{display:flex;justify-content:space-between;align-items:center;
  margin-bottom:4px;gap:12px}
.top-user{display:flex;align-items:center;gap:10px;flex-shrink:0}
.top-user-name{font-size:.8rem;color:var(--dim)}
.btn-logout{font-family:inherit;font-size:.78rem;color:var(--dim);
  text-decoration:none;padding:4px 10px;border:1px solid var(--border);
  border-radius:5px;transition:border-color .2s,color .2s;white-space:nowrap}
.btn-logout:hover{border-color:var(--danger);color:var(--danger)}

/* Abas de PC */
#pc-tabs{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px}
.pc-tab{background:var(--card);border:1px solid var(--border);border-radius:6px;
  padding:9px 14px;cursor:pointer;color:var(--text);font-family:inherit;
  font-size:.82rem;text-align:left;display:flex;flex-direction:column;gap:3px;
  transition:border-color .2s,background .2s;min-width:180px}
.pc-tab:hover{border-color:var(--accent)}
.pc-tab.active{border-color:var(--accent);background:#1c2330}
.pc-tab-row{display:flex;align-items:center;gap:6px}
.pc-dot{display:inline-block;width:8px;height:8px;border-radius:50%;flex-shrink:0}
.pc-dot.online{background:var(--ok)}
.pc-dot.offline{background:var(--danger)}
.pc-tab-name{font-weight:bold;font-size:.85rem}
.pc-tab-time{font-size:.7rem;color:var(--dim);padding-left:14px;line-height:1.3}

/* Animacao de alerta quando sem sincronizar por mais de 5 min */
@keyframes pulse-alert {
  0%,100%{border-color:#f85149;box-shadow:0 0 0 0 rgba(248,81,73,.4)}
  50%    {border-color:#ff9090;box-shadow:0 0 0 5px rgba(248,81,73,0)}
}
.pc-tab.stale{
  border-color:var(--danger) !important;
  animation:pulse-alert 2s ease-in-out infinite;
}
.pc-tab.stale .pc-tab-time{color:#f85149}

/* Grid principal */
.grid{display:grid;grid-template-columns:repeat(2,1fr);gap:14px;align-items:start}
@media(max-width:540px){.grid{grid-template-columns:1fr}}
.card{background:var(--card);border:1px solid var(--border);border-radius:8px;padding:14px 18px}
.card-title{color:var(--accent);font-size:.82rem;font-weight:bold;letter-spacing:.08em;
  text-transform:uppercase;margin-bottom:10px;padding-bottom:6px;border-bottom:1px solid var(--border)}
.gauge-needle{transition:transform .6s cubic-bezier(.4,0,.2,1)}
.gauge-temp{font-size:.76rem;text-align:center;margin-top:4px;padding-bottom:2px}
.metric-row{display:flex;align-items:center;gap:10px;margin:4px 0}
.metric-label{color:var(--dim);font-size:.78rem;width:72px;flex-shrink:0}
.bar-wrap{flex:1;height:10px;background:var(--bar-bg);border-radius:5px;overflow:hidden}
.bar-fill{height:100%;border-radius:5px;transition:width .4s,background .3s}
.metric-val{font-size:.78rem;width:185px;flex-shrink:0;text-align:right}
.item-name{font-size:.88rem;margin-bottom:5px}
.sep{border:none;border-top:1px solid var(--border);margin:8px 0}
.sub{font-size:.76rem;color:var(--dim);margin-top:3px}

/* Lista de processos — layout flex, responsivo */
.proc-hdr{display:flex;justify-content:space-between;align-items:center;
  padding:0 0 6px;border-bottom:1px solid var(--border);margin-bottom:2px}
.proc-hdr-l{font-size:.74rem;color:var(--dim);flex:1}
.proc-hdr-r{display:flex;gap:6px;flex-shrink:0}
.proc-hdr-r span{font-size:.74rem;color:var(--dim);text-align:right}
.ph-cpu{min-width:52px}.ph-bar{width:44px}.ph-mem{min-width:44px}

.proc-row{display:flex;align-items:center;gap:8px;
  padding:4px 0;border-bottom:1px solid var(--bar-bg)}
.proc-row:last-child{border-bottom:none}
.proc-row:hover{background:#1c2330;margin:0 -4px;padding-left:4px;padding-right:4px;border-radius:4px}
.proc-nm{font-size:.78rem;flex:1;min-width:0;overflow:hidden;
  text-overflow:ellipsis;white-space:nowrap}
.proc-st{display:flex;align-items:center;gap:6px;flex-shrink:0}
.proc-cpu{font-size:.78rem;min-width:52px;text-align:right;font-variant-numeric:tabular-nums}
.proc-cbar{width:44px;height:3px;background:var(--bar-bg);border-radius:2px;overflow:hidden;flex-shrink:0}
.proc-mem{font-size:.78rem;min-width:44px;text-align:right;color:var(--dim);font-variant-numeric:tabular-nums}
@media(max-width:420px){.proc-cbar,.ph-bar{display:none}}

#err{background:#2d1b1b;border:1px solid var(--danger);color:var(--danger);
  padding:12px;border-radius:6px;margin-bottom:16px;display:none}
.c-ok{color:var(--ok)}.c-warn{color:var(--warn)}.c-danger{color:var(--danger)}.c-dim{color:var(--dim)}

/* botao gerenciar */
.tabs-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:8px}
.tabs-label{font-size:.75rem;color:var(--dim)}
.btn-manage{font-family:inherit;font-size:.75rem;color:var(--dim);background:none;
  border:1px solid var(--border);border-radius:5px;padding:3px 10px;
  cursor:pointer;transition:border-color .2s,color .2s;white-space:nowrap}
.btn-manage:hover{border-color:var(--accent);color:var(--accent)}

/* modal */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.65);
  display:none;align-items:center;justify-content:center;z-index:200;padding:16px}
.modal-overlay.open{display:flex}
.modal{background:var(--card);border:1px solid var(--border);border-radius:10px;
  padding:24px;width:100%;max-width:400px;max-height:80vh;display:flex;flex-direction:column;gap:0}
.modal-head{display:flex;justify-content:space-between;align-items:center;
  padding-bottom:10px;border-bottom:1px solid var(--border);margin-bottom:4px}
.modal-head-title{color:var(--accent);font-size:.82rem;font-weight:bold;
  letter-spacing:.08em;text-transform:uppercase}
.modal-close{background:none;border:none;color:var(--dim);font-size:1.3rem;
  cursor:pointer;line-height:1;padding:0 2px}
.modal-close:hover{color:var(--text)}
.modal-body{overflow-y:auto;flex:1}
.pc-item{display:flex;align-items:center;justify-content:space-between;
  padding:11px 0;border-bottom:1px solid var(--bar-bg)}
.pc-item:last-child{border-bottom:none}
.pc-item-info{display:flex;flex-direction:column;gap:2px}
.pc-item-name{font-size:.85rem}
.pc-item-status{font-size:.72rem;color:var(--dim)}
.toggle{position:relative;display:inline-block;width:40px;height:22px;flex-shrink:0}
.toggle input{opacity:0;width:0;height:0}
.toggle-sl{position:absolute;inset:0;background:#30363d;border-radius:22px;
  cursor:pointer;transition:background .2s}
.toggle-sl:before{content:'';position:absolute;width:16px;height:16px;
  left:3px;bottom:3px;background:white;border-radius:50%;transition:transform .2s}
.toggle input:checked+.toggle-sl{background:var(--ok)}
.toggle input:checked+.toggle-sl:before{transform:translateX(18px)}
.modal-hint{font-size:.72rem;color:var(--dim);margin-top:12px;padding-top:10px;
  border-top:1px solid var(--border);line-height:1.5}
</style>
</head>
<body>

<div class="top-bar">
  <h1>PC Status Monitor</h1>
  <div class="top-user">
    <span class="top-user-name"><?= htmlspecialchars($logged_user) ?></span>
    <a href="settings.php" class="btn-logout" title="Configuracoes">&#9881;</a>
    <a href="logout.php" class="btn-logout">Sair</a>
  </div>
</div>
<div id="meta">Atualizando a cada 2s &mdash; <span id="last-ts">—</span></div>
<div id="err"></div>

<!-- Abas de PC -->
<div class="tabs-header">
  <span class="tabs-label">PCs conectados</span>
  <button class="btn-manage" onclick="openModal()">&#9881; Gerenciar</button>
</div>
<div id="pc-tabs"><p class="sub">Aguardando conexao de algum PC...</p></div>

<!-- Modal gerenciar PCs -->
<div class="modal-overlay" id="modal-overlay" onclick="if(event.target===this)closeModal()">
  <div class="modal">
    <div class="modal-head">
      <span class="modal-head-title">Gerenciar PCs</span>
      <button class="modal-close" onclick="closeModal()">&#x2715;</button>
    </div>
    <div class="modal-body" id="modal-list">
      <p class="sub">Nenhum PC conectado ainda.</p>
    </div>
    <p class="modal-hint">PCs desativados ficam ocultos no dashboard mas continuam enviando dados ao servidor.</p>
  </div>
</div>

<!-- Conteudo do PC selecionado -->
<div id="pc-content" style="display:none">
  <div class="grid" style="margin-bottom:14px">

    <!-- CPU -->
    <div class="card">
      <div class="card-title">CPU</div>
      <div id="gauge-cpu"></div>
      <p id="txt-cpu-temp" class="gauge-temp c-dim">Temp: —</p>
    </div>

    <!-- Memoria -->
    <div class="card">
      <div class="card-title">Memoria RAM</div>
      <div id="gauge-mem"></div>
    </div>

    <!-- GPU -->
    <div class="card" id="card-gpu">
      <div class="card-title">GPU</div>
      <p class="sub">Aguardando...</p>
    </div>

    <!-- Armazenamento -->
    <div class="card">
      <div id="disk-usage">
        <div class="card-title">Armazenamento</div>
        <p class="sub">Aguardando...</p>
      </div>
      <div id="disk-io" style="display:none">
        <hr class="sep" style="margin:10px 0 8px">
        <div style="display:flex;gap:6px">
          <div style="flex:1;text-align:center">
            <div id="gauge-dr"></div>
            <div style="font-size:.72rem;color:var(--dim);margin-top:1px">Leitura</div>
          </div>
          <div style="flex:1;text-align:center">
            <div id="gauge-dw"></div>
            <div style="font-size:.72rem;color:var(--dim);margin-top:1px">Escrita</div>
          </div>
        </div>
      </div>
    </div>

  </div>

  <!-- Processos (largura total) -->
  <div class="card" id="card-procs">
    <div class="card-title">Processos em execucao</div>
    <p class="sub">Aguardando...</p>
  </div>
</div>

<script>
// ── Gauge (velocimetro segmentado com agulha) ─────────────────────────────────

const GCX = 100, GCY = 100, GRO = 84, GRI = 52;
const SEGS = [
    { s:   0, e: 33.6, c: '#3fb950' },
    { s:36.6, e: 70.2, c: '#a5d860' },
    { s:73.2, e:106.8, c: '#f0c040' },
    { s:109.8,e:143.4, c: '#f08858' },
    { s:146.4,e:  180, c: '#f85149' },
];

function gXY(r, ga) {
    const a = (180 - ga) * Math.PI / 180;
    return [GCX + r * Math.cos(a), GCY - r * Math.sin(a)];
}

function gSeg(s, e) {
    const n = v => v.toFixed(2);
    const [ox1,oy1]=gXY(GRO,s), [ox2,oy2]=gXY(GRO,e);
    const [ix1,iy1]=gXY(GRI,s), [ix2,iy2]=gXY(GRI,e);
    return `M${n(ox1)},${n(oy1)} A${GRO},${GRO} 0 0,0 ${n(ox2)},${n(oy2)} L${n(ix2)},${n(iy2)} A${GRI},${GRI} 0 0,1 ${n(ix1)},${n(iy1)}Z`;
}

function initGauge(cid, nid, pid, sid, mw) {
    const el = document.getElementById(cid);
    if (!el) return;
    const segs = SEGS.map(sg => `<path d="${gSeg(sg.s,sg.e)}" fill="${sg.c}"/>`).join('');
    el.innerHTML = `
      <svg viewBox="0 0 200 108" style="display:block;margin:auto;width:100%;max-width:${mw}px">
        ${segs}
        <circle cx="${GCX}" cy="${GCY}" r="50" fill="var(--card)"/>
        <g id="${nid}" class="gauge-needle">
          <polygon points="100,28 97.5,98 102.5,98" fill="#c9d1d9" opacity=".9"/>
        </g>
        <circle cx="${GCX}" cy="${GCY}" r="7" fill="#21262d" stroke="var(--border)" stroke-width="1.5"/>
        <text id="${pid}" x="${GCX}" y="84" text-anchor="middle"
              font-family="Consolas,monospace" font-size="22" font-weight="bold" fill="white">—</text>
        <text id="${sid}" x="${GCX}" y="97" text-anchor="middle"
              font-family="Consolas,monospace" font-size="7" fill="var(--dim)">—</text>
      </svg>`;
    const needle = document.getElementById(nid);
    if (needle) {
        needle.style.transformOrigin = `${GCX}px ${GCY}px`;
        needle.style.transform = 'rotate(-90deg)';
    }
}

function updateGauge(nid, pid, sid, pct, mainTxt, subTxt) {
    const needle = document.getElementById(nid);
    if (needle)
        needle.style.transform = `rotate(${Math.max(0, Math.min(100, pct ?? 0)) * 1.8 - 90}deg)`;
    setText(pid, mainTxt);
    setText(sid, subTxt ?? '');
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function setText(id, v) { const e = document.getElementById(id); if (e) e.textContent = v; }
function setClass(id, c) { const e = document.getElementById(id); if (e) e.className = c; }
function f(v, d = 1) { return v != null ? Number(v).toFixed(d) : '—'; }
function esc(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function trunc(s, n) { return s.length > n ? s.slice(0, n - 1) + '…' : s; }
function tempInfo(t) {
    if (t == null) return { txt: 'Temp: N/A', cls: 'c-dim' };
    return { txt: `Temp: ${t}°C`, cls: t < 70 ? 'c-ok' : t < 85 ? 'c-warn' : 'c-danger' };
}
function barColor(p) { return p < 70 ? 'var(--ok)' : p < 85 ? 'var(--warn)' : 'var(--danger)'; }

function row(label, pct, val) {
    const p = Math.max(0, Math.min(100, pct ?? 0));
    return `<div class="metric-row">
      <div class="metric-label">${esc(label)}</div>
      <div class="bar-wrap"><div class="bar-fill" style="width:${p}%;background:${barColor(p)}"></div></div>
      <div class="metric-val">${val}</div>
    </div>`;
}

// ── Render: GPU ───────────────────────────────────────────────────────────────

function renderGPU(gpus) {
    if (!gpus?.length)
        return '<div class="card-title">GPU</div><p class="sub">Nenhuma GPU detectada</p>';
    let h = '<div class="card-title">GPU</div>';
    gpus.forEach((g, i) => {
        if (i) h += '<hr class="sep">';
        const t  = tempInfo(g.temperature);
        const vl = g.vram_total_gb
            ? `${f(g.vram_used_gb)} / ${f(g.vram_total_gb)} GB (${f(g.vram_percent)}%)`
            : '—';
        h += `<div class="item-name">${esc(g.name)}</div>
              ${row('Uso', g.usage, f(g.usage) + '%')}
              ${row('VRAM', g.vram_percent, vl)}
              <div class="sub ${t.cls}">${t.txt}</div>`;
    });
    return h;
}

// ── Render: Disco ─────────────────────────────────────────────────────────────

function renderDisk(disks) {
    if (!disks?.length)
        return '<div class="card-title">Armazenamento</div><p class="sub">Nenhum disco</p>';
    let h = '<div class="card-title">Armazenamento</div>';
    disks.forEach((d, i) => {
        if (i) h += '<hr class="sep">';
        h += row(d.mountpoint, d.percent, `${f(d.used_gb)} / ${f(d.total_gb)} GB (${f(d.percent)}%)`);
    });
    return h;
}

const IO_MAX = 500;

function updateDiskIO(d0) {
    const ioArea = document.getElementById('disk-io');
    if (!d0 || d0.read_mb_s == null) { ioArea.style.display = 'none'; return; }
    ioArea.style.display = 'block';
    const rP = Math.min(100, d0.read_mb_s  / IO_MAX * 100);
    const wP = Math.min(100, d0.write_mb_s / IO_MAX * 100);
    const rv = d0.read_mb_s  < 10 ? f(d0.read_mb_s)  : String(Math.round(d0.read_mb_s));
    const wv = d0.write_mb_s < 10 ? f(d0.write_mb_s) : String(Math.round(d0.write_mb_s));
    updateGauge('needle-dr', 'ptx-dr', 'stx-dr', rP, rv, 'MB/s');
    updateGauge('needle-dw', 'ptx-dw', 'stx-dw', wP, wv, 'MB/s');
}

// ── Render: Processos ─────────────────────────────────────────────────────────

function renderProcesses(procs) {
    const card = document.getElementById('card-procs');
    if (!procs?.length) {
        card.innerHTML = '<div class="card-title">Processos em execucao</div><p class="sub">Sem dados de processos</p>';
        return;
    }

    const rows = procs.map(p => {
        const barW  = Math.min(100, p.cpu);
        const color = barW < 70 ? 'var(--ok)' : barW < 85 ? 'var(--warn)' : 'var(--danger)';
        const cpuTxt = p.cpu >= 100 ? Math.round(p.cpu) + '%' : f(p.cpu) + '%';
        return `<div class="proc-row">
          <div class="proc-nm" title="${esc(p.name)}">${esc(p.name)}</div>
          <div class="proc-st">
            <div class="proc-cpu" style="color:${color}">${cpuTxt}</div>
            <div class="proc-cbar"><div style="width:${barW}%;background:${color};height:100%;border-radius:2px"></div></div>
            <div class="proc-mem">${f(p.mem)}%</div>
          </div>
        </div>`;
    }).join('');

    card.innerHTML =
        `<div class="card-title">Processos em execucao</div>
         <div class="proc-hdr">
           <div class="proc-hdr-l">Processo</div>
           <div class="proc-hdr-r">
             <span class="ph-cpu">CPU</span>
             <span class="ph-bar"></span>
             <span class="ph-mem">RAM</span>
           </div>
         </div>
         ${rows}`;
}

// ── Render: dados de um PC ────────────────────────────────────────────────────

function renderPcData(data) {
    // CPU
    const cpu    = data.cpu ?? {};
    const cpuPct = cpu.usage ?? 0;
    updateGauge('needle-cpu', 'ptx-cpu', 'stx-cpu',
        cpuPct, f(cpuPct) + '%', trunc(cpu.name ?? '—', 27));
    const ct = tempInfo(cpu.temperature);
    setText('txt-cpu-temp', ct.txt);
    setClass('txt-cpu-temp', ct.cls + ' gauge-temp');

    // Memoria
    const mem    = data.memory ?? {};
    const memPct = mem.percent ?? 0;
    updateGauge('needle-mem', 'ptx-mem', 'stx-mem',
        memPct, f(memPct) + '%', `${f(mem.used_gb)} / ${f(mem.total_gb)} GB`);

    // GPU
    document.getElementById('card-gpu').innerHTML = renderGPU(data.gpu);

    // Discos
    const disks = data.disks ?? [];
    document.getElementById('disk-usage').innerHTML = renderDisk(disks);
    updateDiskIO(disks[0]);

    // Processos
    renderProcesses(data.processes);

    // Hora
    if (data.timestamp)
        document.getElementById('last-ts').textContent =
            new Date(data.timestamp).toLocaleString('pt-BR');
}

// ── Gerenciar PCs (localStorage) ─────────────────────────────────────────────

let disabledPcs = new Set(JSON.parse(localStorage.getItem('pcstatus_disabled') || '[]'));

function saveDisabled() {
    localStorage.setItem('pcstatus_disabled', JSON.stringify([...disabledPcs]));
}

function togglePcEnabled(name, enabled) {
    if (enabled) disabledPcs.delete(name);
    else         disabledPcs.add(name);
    saveDisabled();
    renderModal();
    if (!enabled && selectedPc === name) {
        selectedPc = null;
        document.getElementById('pc-content').style.display = 'none';
    }
    renderTabs();
}

function openModal()  { renderModal(); document.getElementById('modal-overlay').classList.add('open'); }
function closeModal() { document.getElementById('modal-overlay').classList.remove('open'); }

function renderModal() {
    const el  = document.getElementById('modal-list');
    const pcs = Object.keys(allData);
    if (!pcs.length) { el.innerHTML = '<p class="sub" style="padding:10px 0">Nenhum PC conectado ainda.</p>'; return; }
    const now = Date.now();
    el.innerHTML = pcs.map(name => {
        const d       = allData[name];
        const ts      = d.timestamp ? new Date(d.timestamp) : null;
        const ageSec  = ts ? (now - ts.getTime()) / 1000 : 99999;
        const online  = ageSec < STALE_SEC;
        const enabled = !disabledPcs.has(name);
        const sid     = 'tog-' + name.replace(/\W/g, '_');
        const statusTxt = !ts ? 'sem dados'
            : online ? 'online'
            : `offline há ${Math.round(ageSec/60)} min`;
        const statusCls = online ? 'c-ok' : 'c-danger';
        return `<div class="pc-item">
          <div class="pc-item-info">
            <span class="pc-item-name">${esc(name)}</span>
            <span class="pc-item-status ${enabled ? statusCls : 'c-dim'}">${enabled ? statusTxt : 'desativado'}</span>
          </div>
          <label class="toggle">
            <input type="checkbox" id="${sid}" ${enabled ? 'checked' : ''}
              onchange="togglePcEnabled('${esc(name)}', this.checked)">
            <span class="toggle-sl"></span>
          </label>
        </div>`;
    }).join('');
}

// ── Notificações ──────────────────────────────────────────────────────────────

const notifiedOffline = new Set();
const NOTIFY_SEC = 600; // 10 minutos

function initNotifications() {
    if ('Notification' in window && Notification.permission === 'default') {
        Notification.requestPermission();
    }
}

function checkNotifications() {
    if (!('Notification' in window) || Notification.permission !== 'granted') return;
    const now = Date.now();
    for (const [name, d] of Object.entries(allData)) {
        if (disabledPcs.has(name)) continue;
        const ts     = d.timestamp ? new Date(d.timestamp) : null;
        const ageSec = ts ? (now - ts.getTime()) / 1000 : 99999;
        if (ageSec > NOTIFY_SEC && !notifiedOffline.has(name)) {
            notifiedOffline.add(name);
            new Notification('PC offline: ' + name, {
                body: 'Sem dados há mais de 10 minutos.',
                icon: 'icone.webp',
                tag:  'pcoffline-' + name,
            });
        } else if (ageSec < 60) {
            notifiedOffline.delete(name); // voltou online
        }
    }
}

// ── Abas de PC ────────────────────────────────────────────────────────────────

let allData    = {};
let selectedPc = null;

function selectPc(name) {
    selectedPc = name;
    renderTabs();
    if (allData[name]) {
        renderPcData(allData[name]);
        document.getElementById('pc-content').style.display = 'block';
    }
}

const STALE_SEC = 300; // 5 minutos

function renderTabs() {
    const el  = document.getElementById('pc-tabs');
    const now = Date.now();
    const pcs = Object.keys(allData).filter(n => !disabledPcs.has(n));

    if (!pcs.length) {
        el.innerHTML = '<p class="sub">Aguardando conexao de algum PC...</p>';
        return;
    }

    el.innerHTML = pcs.map(name => {
        const d     = allData[name];
        const ts    = d.timestamp ? new Date(d.timestamp) : null;
        const ageSec = ts ? (now - ts.getTime()) / 1000 : 99999;
        const online = ageSec < STALE_SEC;

        // Data e hora completas
        const dtStr = ts
            ? ts.toLocaleString('pt-BR', {day:'2-digit',month:'2-digit',year:'numeric',
                                           hour:'2-digit',minute:'2-digit',second:'2-digit'})
            : '—';

        // Texto de status
        let statusTxt;
        if (!ts) {
            statusTxt = 'sem dados';
        } else if (ageSec < 60) {
            statusTxt = `${dtStr}`;
        } else {
            const mins = Math.floor(ageSec / 60);
            statusTxt = `${dtStr}  (ha ${mins} min)`;
        }

        const active  = name === selectedPc ? ' active' : '';
        const stale   = online ? '' : ' stale';
        const dotCls  = online ? 'online' : 'offline';
        const icon    = online ? '●' : '⚠';

        return `<button class="pc-tab${active}${stale}" onclick="selectPc(this.dataset.pc)" data-pc="${esc(name)}">
          <div class="pc-tab-row">
            <span class="pc-dot ${dotCls}"></span>
            <span class="pc-tab-name">${esc(name)}</span>
          </div>
          <div class="pc-tab-time">${icon} ${statusTxt}</div>
        </button>`;
    }).join('');
}

// ── Refresh ───────────────────────────────────────────────────────────────────

async function refresh() {
    try {
        const res  = await fetch('api/status.php');
        if (res.status === 401) { window.location.href = 'login.php'; return; }
        const json = await res.json();

        document.getElementById('err').style.display = 'none';
        allData = json.pcs ?? {};

        checkNotifications();

        // Seleciona o primeiro PC ativo automaticamente se nenhum estiver selecionado
        const pcs = Object.keys(allData).filter(n => !disabledPcs.has(n));
        if (!selectedPc && pcs.length) selectedPc = pcs[0];
        // Se o PC selecionado sumiu ou foi desativado, troca para o primeiro disponivel
        if (selectedPc && (!allData[selectedPc] || disabledPcs.has(selectedPc))) selectedPc = pcs[0] ?? null;

        renderTabs();

        if (selectedPc && allData[selectedPc]) {
            renderPcData(allData[selectedPc]);
            document.getElementById('pc-content').style.display = 'block';
        } else {
            document.getElementById('pc-content').style.display = 'none';
        }

    } catch {
        document.getElementById('err').style.display = 'block';
        document.getElementById('err').textContent   =
            'Nao foi possivel conectar a API. Verifique se o Apache esta rodando.';
    }
}

// ── Inicializacao ─────────────────────────────────────────────────────────────

initGauge('gauge-cpu', 'needle-cpu', 'ptx-cpu', 'stx-cpu', 240);
initGauge('gauge-mem', 'needle-mem', 'ptx-mem', 'stx-mem', 240);
initGauge('gauge-dr',  'needle-dr',  'ptx-dr',  'stx-dr',  155);
initGauge('gauge-dw',  'needle-dw',  'ptx-dw',  'stx-dw',  155);

initNotifications();
refresh();
setInterval(refresh, 2000);
</script>
</body>
</html>
