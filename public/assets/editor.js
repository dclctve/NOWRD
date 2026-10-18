(() => {
  'use strict';
  const editor = document.getElementById('storyEditor');
  const form = document.getElementById('storyForm');
  const hidden = document.getElementById('contentValue');
  if (!editor || !form || !hidden) return;
  const title = document.getElementById('storyTitle');
  const slug = document.getElementById('storySlug');
  const slugPreview = document.getElementById('slugPreview');
  const excerpt = document.getElementById('storyExcerpt');
  const wordCount = document.getElementById('wordCount');
  const readTime = document.getElementById('readTime');
  const charCount = document.getElementById('charCount');
  const status = document.getElementById('editorStatus');
  const previewToggle = document.getElementById('previewToggle');
  const focusToggle = document.getElementById('focusToggle');
  const saveKey = 'nowrd-writing-recovery:' + location.pathname + ':' + (new URLSearchParams(location.search).get('id') || 'new');
  let slugEdited = slug.value.trim() !== '';
  let previewMode = false;
  let statusTimer = null;
  let savedEditorRange = null;
  let recoveryTimer = null;
  let lastSnapshot = '';

  function makeSlug(value) {
    return value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 240);
  }
  function updateSlug() { slugPreview.textContent = '/' + (slug.value.trim() || makeSlug(title.value) || 'article'); }
  function syncContent() { hidden.value = editor.innerHTML; }
  function getPlainText() { return (editor.innerText || editor.textContent || '').replace(/\s+/g, ' ').trim(); }
  function updateStats(markDirty = true) {
    const text = getPlainText();
    const words = text ? text.split(' ').length : 0;
    wordCount.textContent = words + (words === 1 ? ' word' : ' words');
    readTime.textContent = Math.max(1, Math.ceil(words / 220)) + ' min read';
    if (charCount) charCount.textContent = text.length.toLocaleString() + ' characters';
    syncContent();
    if (markDirty) {
      status.textContent = 'Unsaved changes'; status.classList.add('is-dirty');
      window.clearTimeout(statusTimer);
      statusTimer = window.setTimeout(() => { status.textContent = 'Recovery copy saved locally'; status.classList.remove('is-dirty'); }, 1200);
      scheduleRecovery();
    }
  }
  function snapshot() { return JSON.stringify({title: title.value, excerpt: excerpt.value, content: editor.innerHTML, savedAt: Date.now()}); }
  function scheduleRecovery() {
    window.clearTimeout(recoveryTimer);
    recoveryTimer = window.setTimeout(() => {
      try {
        const value = snapshot();
        if (value !== lastSnapshot) { localStorage.setItem(saveKey, value); lastSnapshot = value; }
      } catch (_) { /* Private browsing/storage restrictions should not block writing. */ }
    }, 500);
  }
  function clearRecovery() { try { localStorage.removeItem(saveKey); } catch (_) {} }
  function focusEditor() { editor.focus(); }
  function exec(command, value = null) {
    if (previewMode) return;
    focusEditor(); document.execCommand(command, false, value); updateStats();
  }
  function insertNode(node) {
    focusEditor();
    const selection = window.getSelection();
    let range = null;
    if (selection && selection.rangeCount > 0 && editor.contains(selection.anchorNode)) range = selection.getRangeAt(0);
    else if (savedEditorRange) range = savedEditorRange;
    if (!range) editor.appendChild(node);
    else {
      range.deleteContents(); range.insertNode(node); range.setStartAfter(node); range.collapse(true);
      const currentSelection = window.getSelection();
      if (currentSelection) { currentSelection.removeAllRanges(); currentSelection.addRange(range); }
      savedEditorRange = null;
    }
    updateStats();
  }
  function safeUrl(raw, allowMail = false) {
    try { const url = new URL(raw.trim(), location.href); return ['http:', 'https:', ...(allowMail ? ['mailto:'] : [])].includes(url.protocol) ? url.href : null; }
    catch (_) { return null; }
  }

  // Offer browser-local recovery if a prior editing session was interrupted.
  try {
    if (new URLSearchParams(location.search).has('saved')) clearRecovery();
    const recovery = JSON.parse(localStorage.getItem(saveKey) || 'null');
    if (recovery && recovery.savedAt && recovery.savedAt > 0) {
      const current = JSON.stringify({title:title.value, excerpt:excerpt.value, content:editor.innerHTML});
      const recovered = JSON.stringify({title:recovery.title || '', excerpt:recovery.excerpt || '', content:recovery.content || ''});
      if (current !== recovered && window.confirm('Ditemukan salinan pemulihan tulisan dari ' + new Date(recovery.savedAt).toLocaleString() + '. Pulihkan tulisan terakhir?')) {
        title.value = recovery.title || title.value; excerpt.value = recovery.excerpt || ''; editor.innerHTML = recovery.content || '';
        slugEdited = slug.value.trim() !== ''; if (!slugEdited) slug.value = makeSlug(title.value); updateSlug(); updateStats(false);
        status.textContent = 'Recovered from this browser';
      }
      lastSnapshot = JSON.stringify({title:title.value, excerpt:excerpt.value, content:editor.innerHTML, savedAt:recovery.savedAt});
    }
  } catch (_) { /* Ignore invalid or unavailable browser recovery data. */ }

  document.querySelectorAll('[data-command]').forEach(button => {
    button.addEventListener('mousedown', event => event.preventDefault());
    button.addEventListener('click', () => exec(button.dataset.command));
  });
  document.querySelectorAll('[data-block]').forEach(button => {
    button.addEventListener('mousedown', event => event.preventDefault());
    button.addEventListener('click', () => exec('formatBlock', '<' + button.dataset.block + '>'));
  });
  document.getElementById('undoEdit')?.addEventListener('click', () => exec('undo'));
  document.getElementById('redoEdit')?.addEventListener('click', () => exec('redo'));
  document.getElementById('insertLink')?.addEventListener('click', () => {
    if (previewMode) return;
    const raw = window.prompt('Tempel tautan (https://…)'); if (!raw) return;
    const url = safeUrl(raw, true); if (!url) { window.alert('Tautan harus menggunakan HTTP, HTTPS, atau mailto.'); return; }
    exec('createLink', url);
  });
  document.getElementById('insertImage')?.addEventListener('click', () => {
    if (previewMode) return;
    const raw = window.prompt('URL gambar (HTTP atau HTTPS)'); if (!raw) return;
    const url = safeUrl(raw); if (!url || !/^https?:/i.test(url)) { window.alert('URL gambar tidak valid.'); return; }
    const image = document.createElement('img'); image.src = url; image.alt = window.prompt('Teks alternatif gambar (opsional)', '') || ''; image.loading = 'lazy';
    insertNode(image); const paragraph = document.createElement('p'); paragraph.appendChild(document.createElement('br')); insertNode(paragraph);
  });
  document.getElementById('insertCode')?.addEventListener('click', () => {
    if (previewMode) return;
    const selection = window.getSelection(); const selected = selection && selection.toString() ? selection.toString() : 'Tulis atau tempel kode di sini';
    const pre = document.createElement('pre'); const code = document.createElement('code'); code.textContent = selected; pre.appendChild(code); insertNode(pre);
    const paragraph = document.createElement('p'); paragraph.appendChild(document.createElement('br')); insertNode(paragraph);
  });
  function openMediaPicker(target = 'content') {
    if (previewMode) return;
    window.__nowrdMediaTarget = target;
    if (target === 'content') { const selection = window.getSelection(); if (selection && selection.rangeCount && editor.contains(selection.anchorNode)) savedEditorRange = selection.getRangeAt(0).cloneRange(); }
    const link = document.querySelector('.editor-top .brand')?.getAttribute('href') || '/';
    const base = new URL(link, location.href); base.pathname = base.pathname.replace(/admin\/index\.php$/, ''); if (!base.pathname.endsWith('/')) base.pathname += '/';
    window.open(new URL('admin/media.php?picker=1', base).href, 'nowrdMediaPicker', 'popup,width=1100,height=760,resizable=yes,scrollbars=yes');
  }
  document.getElementById('openMediaLibrary')?.addEventListener('click', () => openMediaPicker('content'));
  document.getElementById('chooseCover')?.addEventListener('click', () => openMediaPicker('cover'));
  window.addEventListener('message', event => {
    if (event.origin !== location.origin || !event.data || event.data.type !== 'nowrd:select-media') return;
    const url = safeUrl(String(event.data.url || '')); if (!url || !/^https?:/i.test(url)) return;
    const cover = document.getElementById('coverImage');
    if (window.__nowrdMediaTarget === 'cover' && cover) { cover.value = url; window.__nowrdMediaTarget = ''; return; }
    window.__nowrdMediaTarget = 'content'; editor.focus();
    if (savedEditorRange) { const selection = window.getSelection(); if (selection) { selection.removeAllRanges(); selection.addRange(savedEditorRange); } }
    const image = document.createElement('img'); image.src = url; image.alt = String(event.data.alt || '').slice(0, 300); image.loading = 'lazy'; insertNode(image);
    const paragraph = document.createElement('p'); paragraph.appendChild(document.createElement('br')); insertNode(paragraph);
  });
  previewToggle?.addEventListener('click', () => {
    previewMode = !previewMode; editor.contentEditable = previewMode ? 'false' : 'true'; editor.classList.toggle('is-preview', previewMode);
    previewToggle.textContent = previewMode ? 'Kembali edit' : 'Preview'; previewToggle.setAttribute('aria-pressed', previewMode ? 'true' : 'false'); syncContent();
  });
  focusToggle?.addEventListener('click', () => {
    const enabled = !document.body.classList.contains('writer-focus'); document.body.classList.toggle('writer-focus', enabled);
    focusToggle.textContent = enabled ? 'Exit focus' : 'Focus'; focusToggle.setAttribute('aria-pressed', enabled ? 'true' : 'false');
    if (enabled) editor.scrollIntoView({behavior:'smooth', block:'center'});
  });
  title?.addEventListener('input', () => { if (!slugEdited) slug.value = makeSlug(title.value); updateSlug(); scheduleRecovery(); });
  slug?.addEventListener('input', () => { slugEdited = slug.value.trim() !== ''; updateSlug(); });
  excerpt?.addEventListener('input', scheduleRecovery);
  editor.addEventListener('input', () => updateStats());
  editor.addEventListener('paste', event => {
    if (previewMode) { event.preventDefault(); return; }
    event.preventDefault(); const text = (event.clipboardData || window.clipboardData).getData('text/plain'); exec('insertText', text);
  });
  editor.addEventListener('keydown', event => {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') { event.preventDefault(); syncContent(); form.requestSubmit(); }
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') { event.preventDefault(); document.getElementById('insertLink')?.click(); }
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'b') { event.preventDefault(); exec('bold'); }
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'i') { event.preventDefault(); exec('italic'); }
    if (event.key === 'Tab' && !previewMode) { event.preventDefault(); exec('insertText', '    '); }
  });
  form.addEventListener('submit', () => { syncContent(); });
  window.addEventListener('beforeunload', () => { if (document.activeElement !== form.querySelector('[type="submit"]')) { try { localStorage.setItem(saveKey, snapshot()); } catch (_) {} } });
  updateSlug(); updateStats(false); if (!status.textContent.trim()) status.textContent = 'Ready to write';
})();
