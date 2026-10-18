(() => {
  'use strict';
  const root = document.body;
  if (!root || root.classList.contains('auth-body')) return;
  const KEY = 'nowrd-ui-v12';
  const defaults = { mode: 'classic', theme: 'light', palette: 'classic', motion: 'smooth', blur: 24, alpha: 68, dockSize: 'regular', dockPosition: 'center' };
  let prefs = { ...defaults };
  prefs = { ...defaults, ...(window.NOWRD_VISUAL || {}) };
  const safeNumber = (n, min, max, fallback) => Number.isFinite(Number(n)) ? Math.min(max, Math.max(min, Number(n))) : fallback;
  prefs.blur = safeNumber(prefs.blur, 0, 40, defaults.blur);
  prefs.alpha = safeNumber(prefs.alpha, 20, 96, defaults.alpha);
  if (!['classic','modern'].includes(prefs.mode)) prefs.mode = defaults.mode;
  if (!['light','dark','system'].includes(prefs.theme)) prefs.theme = defaults.theme;
  if (!['classic','glass','ocean','violet','forest','sunset'].includes(prefs.palette)) prefs.palette = defaults.palette;
  if (!['off','subtle','smooth','playful'].includes(prefs.motion)) prefs.motion = defaults.motion;
  if (!['compact','regular','large'].includes(prefs.dockSize)) prefs.dockSize = defaults.dockSize;
  if (!['center','left','right','left-center','right-center'].includes(prefs.dockPosition)) prefs.dockPosition = defaults.dockPosition;
  const save = () => { try { localStorage.setItem(KEY, JSON.stringify(prefs)); } catch (_) {} if (window.NOWRD_VISUAL_ADMIN) { clearTimeout(window.__nowrdVisualSaveTimer); window.__nowrdVisualSaveTimer=setTimeout(async()=>{try{const r=await fetch(window.NOWRD_VISUAL_SAVE_URL,{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({ _csrf: window.NOWRD_CSRF, preferences:prefs })});const d=await r.json();let n=document.getElementById('nowrd-save-status');if(n)n.textContent=d.message||'Status tidak diketahui';}catch(_){let n=document.getElementById('nowrd-save-status');if(n)n.textContent='Gagal menyimpan ke server. Periksa koneksi.'}},450); } };
  const apply = () => {
    root.classList.toggle('modern-ui', prefs.mode === 'modern');
    root.classList.toggle('nowrd-dark', prefs.theme === 'dark' || (prefs.theme === 'system' && matchMedia('(prefers-color-scheme: dark)').matches));
    root.dataset.nowrdPalette = prefs.palette; root.dataset.nowrdMotion = prefs.motion;
    root.dataset.nowrdDockSize = prefs.dockSize;
    root.dataset.nowrdDockPosition = prefs.dockPosition;
    root.style.setProperty('--nowrd-dock-blur', `${prefs.blur}px`);
    root.style.setProperty('--nowrd-dock-alpha', `${prefs.alpha / 100}`);
    document.documentElement.dataset.nowrdUi = prefs.mode;
    document.documentElement.dataset.nowrdTheme = prefs.theme;
    document.querySelectorAll('[data-nowrd-mode]').forEach(b => { b.classList.toggle('is-selected', b.dataset.nowrdMode === prefs.mode); b.setAttribute('aria-pressed', b.dataset.nowrdMode === prefs.mode ? 'true' : 'false'); });
    document.querySelectorAll('[data-nowrd-theme]').forEach(b => { b.classList.toggle('is-selected', b.dataset.nowrdTheme === prefs.theme); b.setAttribute('aria-pressed', b.dataset.nowrdTheme === prefs.theme ? 'true' : 'false'); });
    const setValue = (id, value) => { const el = document.getElementById(id); if (el) el.value = String(value); };
    const setText = (id, value) => { const el = document.getElementById(id); if (el) el.textContent = value; };
    setValue('nowrd-blur', prefs.blur); setValue('nowrd-alpha', prefs.alpha);
    setText('nowrd-blur-value', `${prefs.blur}px`); setText('nowrd-alpha-value', `${prefs.alpha}%`);
    setValue('nowrd-dock-size', prefs.dockSize); setValue('nowrd-dock-position', prefs.dockPosition);
    setValue('nowrd-palette', prefs.palette); setValue('nowrd-motion', prefs.motion);
  };
  if (!window.NOWRD_VISUAL_ADMIN) {
    // Public visitors can independently choose Classic or Glassmorphism.
    // The site's saved preferences remain the default; visitor choice is local to this browser.
    const VISITOR_KEY = 'nowrd-visitor-visual-v1';
    try {
      const choice = localStorage.getItem(VISITOR_KEY);
      if (choice === 'classic' || choice === 'modern') prefs.mode = choice;
    } catch (_) {}
    apply();
    const switcher = document.createElement('div');
    switcher.className = 'nowrd-visitor-switch';
    switcher.setAttribute('role', 'group');
    switcher.setAttribute('aria-label', 'Pilih tampilan website');
    switcher.innerHTML = '<span class="nowrd-visitor-switch-label">Tampilan</span><button type="button" data-visitor-mode="classic" aria-label="Gunakan tampilan Classic">Classic</button><button type="button" data-visitor-mode="modern" aria-label="Gunakan tampilan Glassmorphism">Glass</button>';
    document.body.appendChild(switcher);
    const syncSwitch = () => switcher.querySelectorAll('[data-visitor-mode]').forEach(button => {
      const selected = button.dataset.visitorMode === prefs.mode;
      button.classList.toggle('is-selected', selected);
      button.setAttribute('aria-pressed', selected ? 'true' : 'false');
    });
    switcher.addEventListener('click', event => {
      const button = event.target.closest('[data-visitor-mode]');
      if (!button) return;
      prefs.mode = button.dataset.visitorMode;
      try { localStorage.setItem(VISITOR_KEY, prefs.mode); } catch (_) {}
      apply(); syncSwitch();
    });
    syncSwitch();
    return;
  }
  const nav = [['⌂','Dashboard','admin/index.php'],['＋','New story','admin/post.php'],['▧','Media','admin/media.php'],['◇','Taxonomies','admin/taxonomies.php'],['⚙','Settings','admin/settings.php']];
  const current = location.pathname;
  const base = current.replace(/\/admin\/(?:index|post|media|taxonomies|settings)\.php$/, '').replace(/\/$/, '');
  const url = path => `${base}/${path}`.replace(/\/+/g, '/');
  const dock = document.createElement('nav'); dock.className = 'nowrd-ui-dock'; dock.setAttribute('aria-label','Navigasi utama NOWRD');
  dock.innerHTML = nav.map(([icon,label,path]) => `<a class="nowrd-dock-item" href="${url(path)}" aria-label="${label}" title="${label}"><span aria-hidden="true">${icon}</span><small>${label}</small></a>`).join('') + '<button class="nowrd-dock-item nowrd-dock-settings" type="button" data-nowrd-open aria-label="Pengaturan tampilan" title="Pengaturan tampilan"><span aria-hidden="true">◐</span><small>Tampilan</small></button>';
  const launcher = document.createElement('button'); launcher.type='button'; launcher.className='nowrd-ui-launcher'; launcher.dataset.nowrdOpen=''; launcher.innerHTML='<span aria-hidden="true">◐</span><span>Tampilan</span>'; launcher.setAttribute('aria-label','Pengaturan tampilan NOWRD');
  const panel = document.createElement('section'); panel.className='nowrd-ui-panel'; panel.hidden=true; panel.setAttribute('aria-label','Pengaturan tampilan');
  panel.innerHTML = `<div class="nowrd-ui-panel-head"><div><span class="nowrd-ui-kicker">NOWRD STUDIO · V20</span><h2>Pengaturan tampilan</h2></div><button type="button" class="nowrd-ui-close" data-nowrd-close aria-label="Tutup pengaturan">×</button></div><p class="nowrd-ui-desc">Pilih karakter visual, warna, dock, dan motion. Preferensi ini diterapkan ke seluruh website dan disimpan ke server oleh admin.</p><div class="nowrd-ui-mode"><button type="button" data-nowrd-mode="classic"><span>▤</span><b>Classic</b><small>Tampilan asli NOWRD</small></button><button type="button" data-nowrd-mode="modern"><span>◈</span><b>Glassmorphism</b><small>Kaca berlapis, blur halus & dock</small></button></div><div class="nowrd-ui-control"><label for="nowrd-palette">Preset visual</label><select id="nowrd-palette"><option value="classic">Classic — bersih & fokus</option><option value="glass">Glass — frosted glass</option><option value="ocean">Ocean Blue</option><option value="violet">Purple Dream</option><option value="forest">Forest Green</option><option value="sunset">Sunset Warm</option></select></div><div class="nowrd-ui-section-title">Mode warna</div><div class="nowrd-ui-theme"><button type="button" data-nowrd-theme="light">☼ Terang</button><button type="button" data-nowrd-theme="dark">◐ Gelap</button><button type="button" data-nowrd-theme="system">◑ Sistem</button></div><div class="nowrd-ui-control"><label for="nowrd-dock-size">Ukuran dock</label><select id="nowrd-dock-size"><option value="compact">Compact</option><option value="regular">Regular</option><option value="large">Large</option></select></div><div class="nowrd-ui-control"><label for="nowrd-dock-position">Posisi navigasi</label><select id="nowrd-dock-position"><option value="center">Bawah — tengah</option><option value="left">Bawah — kiri</option><option value="right">Bawah — kanan</option><option value="left-center">Sisi kiri — tengah</option><option value="right-center">Sisi kanan — tengah</option></select></div><div class="nowrd-ui-control"><label for="nowrd-alpha">Transparansi dock <output id="nowrd-alpha-value">72%</output></label><input id="nowrd-alpha" type="range" min="20" max="96" step="1"><div class="nowrd-ui-range-labels"><span>Transparan</span><span>Solid</span></div></div><div class="nowrd-ui-control"><label for="nowrd-blur">Efek blur <output id="nowrd-blur-value">22px</output></label><input id="nowrd-blur" type="range" min="0" max="40" step="1"><div class="nowrd-ui-range-labels"><span>Jernih</span><span>Glass</span></div></div><div class="nowrd-ui-control"><label for="nowrd-motion">Animasi & motion</label><select id="nowrd-motion"><option value="off">Mati — minim gerak</option><option value="subtle">Subtle — sangat lembut</option><option value="smooth">Smooth — rekomendasi</option><option value="playful">Playful — lebih ekspresif</option></select></div><div class="nowrd-ui-panel-foot"><a href="${url('admin/settings.php')}">Branding & situs ↗</a><button type="button" data-nowrd-reset>Reset preferensi</button><span id="nowrd-save-status" role="status" aria-live="polite">Perubahan tersimpan otomatis.</span></div>`;
  root.append(dock,launcher,panel);
  const open=()=>{panel.hidden=false;panel.classList.add('is-open');panel.querySelector('[data-nowrd-close]')?.focus();};
  const close=()=>{panel.hidden=true;panel.classList.remove('is-open');};
  root.addEventListener('click',e=>{const opener=e.target.closest('[data-nowrd-open]');if(opener){e.preventDefault();panel.hidden?open():close();return;}if(e.target.closest('[data-nowrd-close]')){close();return;}const mode=e.target.closest('[data-nowrd-mode]');if(mode){prefs.mode=mode.dataset.nowrdMode;save();apply();return;}const theme=e.target.closest('[data-nowrd-theme]');if(theme){prefs.theme=theme.dataset.nowrdTheme;save();apply();return;}if(e.target.closest('[data-nowrd-reset]')){prefs={...defaults};save();apply();return;}if(!panel.hidden&&!e.target.closest('.nowrd-ui-panel'))close();});
  panel.addEventListener('input',e=>{if(e.target.id==='nowrd-alpha')prefs.alpha=safeNumber(e.target.value,20,96,defaults.alpha);if(e.target.id==='nowrd-blur')prefs.blur=safeNumber(e.target.value,0,40,defaults.blur);if(e.target.id==='nowrd-dock-size')prefs.dockSize=e.target.value;if(e.target.id==='nowrd-dock-position')prefs.dockPosition=e.target.value;if(e.target.id==='nowrd-palette')prefs.palette=e.target.value;if(e.target.id==='nowrd-motion')prefs.motion=e.target.value;save();apply();});
  document.addEventListener('keydown',e=>{if(e.key==='Escape')close();});
  try { const old=localStorage.getItem('nowrd-ui-v10'); if(old&&!localStorage.getItem(KEY)){const prev=JSON.parse(old);prefs={...prefs,...prev};save();} } catch (_) {}
  apply();
})();
