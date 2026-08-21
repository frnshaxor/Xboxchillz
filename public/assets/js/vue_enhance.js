(function () {
  'use strict';
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));

  // ---------- Simple tab switcher (works without Vue) ----------
  function initTabs() {
    const tabs = $$('.tab');
    if (!tabs.length) return;
    const container = document.querySelector('.tabs');
    const urlTab = new URLSearchParams(location.search).get('tab');
    const initial = urlTab || (container && container.dataset.initial) || 'content';
    function activate(key) {
      tabs.forEach(x => x.classList.toggle('active', x.dataset.tab === key));
      $$('.tabpane').forEach(p => p.classList.toggle('hidden', p.dataset.pane !== key));
    }
    tabs.forEach(t => t.addEventListener('click', () => activate(t.dataset.tab)));
    activate(initial);
  }

  // ---------- API helpers ----------
  async function api(op, method = 'GET', body = null) {
    const opts = { method, credentials: 'same-origin' };
    if (body) opts.body = body;
    const r = await fetch('api.php?op=' + op, opts);
    let data;
    try { data = await r.json(); } catch (_) { /* JSON parse failed */ }
    if (!r.ok) {
      const msg = (data && (data.error || data.message)) || (op + ' ' + r.status);
      const err = new Error(msg); err.data = data; err.status = r.status;
      throw err;
    }
    return data;
  }
  const fd = (obj) => { const f = new FormData(); Object.entries(obj).forEach(([k, v]) => f.append(k, v)); return f; };

  // ---------- Toast notification system ----------
  function ensureToastContainer() {
    let c = document.getElementById('toast-container');
    if (!c) { c = document.createElement('div'); c.id = 'toast-container'; document.body.appendChild(c); }
    return c;
  }
  function showToast(message, type = 'info', durationMs = 3500) {
    const container = ensureToastContainer();
    const toast = document.createElement('div');
    toast.className = 'toast toast-' + type;
    toast.setAttribute('role', 'status');
    toast.setAttribute('aria-live', 'polite');
    const icons = { success: '✓', error: '✕', info: 'ℹ', warning: '⚠' };
    toast.innerHTML = '<span class="toast-icon">' + (icons[type] || icons.info) + '</span><span class="toast-msg">' + message + '</span>';
    container.appendChild(toast);
    requestAnimationFrame(() => toast.classList.add('toast-show'));
    setTimeout(() => { toast.classList.remove('toast-show'); toast.classList.add('toast-hide'); setTimeout(() => toast.remove(), 300); }, durationMs);
  }

  // ---------- Form double-click prevention ----------
  function initFormProtection() {
    document.addEventListener('submit', function (e) {
      const form = e.target;
      if (!(form instanceof HTMLFormElement)) return;
      const btn = form.querySelector('button[type=submit], button:not([type])');
      if (!btn || btn.disabled) return;
      btn.disabled = true;
      btn.dataset.wasDisabled = '1';
      // Re-enable after 3 seconds in case form doesn't navigate (e.g. XHR upload)
      setTimeout(() => {
        if (btn.dataset.wasDisabled === '1') { btn.disabled = false; delete btn.dataset.wasDisabled; }
      }, 3000);
    });
  }

  // ---------- Gallery search with debounce + highlight ----------
  function initGallerySearch() {
    const gallery = document.querySelector('.gallery');
    if (!gallery) return;
    const cards = Array.from(gallery.querySelectorAll('.card'));
    if (!cards.length) return;
    const filtersBar = document.querySelector('.filters');
    if (!filtersBar) return;
    const searchWrap = document.createElement('div');
    searchWrap.className = 'gallery-search';
    searchWrap.innerHTML = '<input type="search" placeholder="Cari video..." aria-label="Cari video" class="gallery-search-input" data-testid="gallery-search">';
    filtersBar.parentNode.insertBefore(searchWrap, filtersBar.nextSibling);
    const input = searchWrap.querySelector('input');
    let debounceTimer;

    // Store original titles for highlight
    cards.forEach(card => {
      const h3 = card.querySelector('h3');
      if (h3) card.dataset原标题 = h3.textContent;
    });

    function highlightText(el, query) {
      if (!query) { el.textContent = el.dataset原标题 || ''; return; }
      const text = el.dataset原标题 || '';
      const regex = new RegExp('(' + query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
      el.innerHTML = text.replace(regex, '<mark class="search-highlight">$1</mark>');
    }

    function doSearch() {
      const q = input.value.toLowerCase().trim();
      cards.forEach(card => {
        const title = (card.dataset原标题 || '').toLowerCase();
        const cat = (card.querySelector('.kick')?.textContent || '').toLowerCase();
        const match = !q || title.includes(q) || cat.includes(q);
        card.style.display = match ? '' : 'none';
        // Highlight matching text in title
        const h3 = card.querySelector('h3');
        if (h3) highlightText(h3, q);
      });
      // Update empty state
      let emptyMsg = gallery.parentNode.querySelector('.gallery-empty-dynamic');
      const visibleCount = cards.filter(c => c.style.display !== 'none').length;
      if (visibleCount === 0 && q) {
        if (!emptyMsg) {
          emptyMsg = document.createElement('div');
          emptyMsg.className = 'gallery-empty-dynamic';
          emptyMsg.innerHTML = '<p class="muted" style="text-align:center;padding:40px 0"><span class="material-symbols-rounded" style="font-size:32px;opacity:0.3;display:block;margin-bottom:8px">search_off</span>Tidak ada video yang cocok dengan pencarian.</p>';
          gallery.parentNode.insertBefore(emptyMsg, gallery.nextSibling);
        }
      } else if (emptyMsg) {
        emptyMsg.remove();
      }
    }

    input.addEventListener('input', function () {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(doSearch, 200);
    });

    // Support Ctrl+K to focus
    document.addEventListener('keydown', function (e) {
      if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        input.focus();
        input.select();
      }
    });
  }

  // ---------- Keyboard shortcuts + Command Palette (Ctrl+K) ----------
  function initKeyboardShortcuts(state) {
    document.addEventListener('keydown', function (e) {
      const ctrl = e.ctrlKey || e.metaKey;
      // Ctrl+K: open command palette
      if (ctrl && e.key === 'k') {
        e.preventDefault();
        openCommandPalette(state);
        return;
      }
      if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') return;
      if (ctrl && e.key === 'u') { e.preventDefault(); location.href = '?page=admin'; }
      if (e.key === '?') { e.preventDefault(); toggleShortcutHelp(); }
      if (e.key === 'Escape') {
        const oh = document.getElementById('shortcut-overlay'); if (oh) oh.remove();
        const cp = document.querySelector('.command-overlay'); if (cp) cp.remove();
      }
    });
  }
  function toggleShortcutHelp() {
    let oh = document.getElementById('shortcut-overlay');
    if (oh) { oh.remove(); return; }
    oh = document.createElement('div');
    oh.id = 'shortcut-overlay';
    oh.innerHTML = '<div class="shortcut-card"><button class="shortcut-close" onclick="this.closest(\'#shortcut-overlay\').remove()">&times;</button>' +
      '<h3>Keyboard Shortcuts</h3><dl>' +
      '<dt><kbd>Ctrl</kbd>+<kbd>K</kbd></dt><dd>Command palette</dd>' +
      '<dt><kbd>Ctrl</kbd>+<kbd>U</kbd></dt><dd>Buka panel admin</dd>' +
      '<dt><kbd>?</kbd></dt><dd>Tampilkan shortcut ini</dd>' +
      '<dt><kbd>Esc</kbd></dt><dd>Tutup overlay</dd>' +
      '</dl></div>';
    document.body.appendChild(oh);
    oh.addEventListener('click', function (e) { if (e.target === oh) oh.remove(); });
  }

  // ---------- Dropdown Menu (Shadcn style) ----------
  function initDropdowns() {
    document.addEventListener('click', function(e) {
      // Toggle dropdown on trigger click
      const trigger = e.target.closest('.dropdown-trigger');
      if (trigger) {
        e.preventDefault();
        const wrap = trigger.closest('.dropdown-wrap');
        const menu = wrap.querySelector('.dropdown-menu');
        const isOpen = menu.classList.contains('open');
        // Close all other dropdowns
        document.querySelectorAll('.dropdown-menu.open').forEach(function(m) { m.classList.remove('open'); });
        if (!isOpen) menu.classList.add('open');
        return;
      }
      // Close dropdown on outside click
      if (!e.target.closest('.dropdown-menu')) {
        document.querySelectorAll('.dropdown-menu.open').forEach(function(m) { m.classList.remove('open'); });
      }
    });
    // Close on Escape
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        document.querySelectorAll('.dropdown-menu.open').forEach(function(m) { m.classList.remove('open'); });
      }
    });
  }

  // ---------- Command Palette (Shadcn style) ----------
  function openCommandPalette(state) {
    // Close existing
    const existing = document.querySelector('.command-overlay');
    if (existing) { existing.remove(); return; }

    // Build command list
    const commands = [
      { group: 'Navigasi', icon: 'home', title: 'Beranda', desc: 'Lihat koleksi video', href: '.', kbd: '' },
      { group: 'Navigasi', icon: 'contact_support', title: 'Kontak', desc: 'Hubungi admin', href: '?page=contact', kbd: '' },
      { group: 'Navigasi', icon: 'login', title: 'Masuk', desc: 'Login ke panel admin', href: '?page=login', kbd: '' },
    ];
    if (state.admin) {
      commands.push(
        { group: 'Admin', icon: 'tune', title: 'Panel Admin', desc: 'Buka ruang kendali', href: '?page=admin', kbd: 'Ctrl+U' },
        { group: 'Admin', icon: 'video_library', title: 'Perpustakaan Video', desc: 'Kelola koleksi video', href: '?page=admin&tab=content', kbd: '' },
        { group: 'Admin', icon: 'vpn_key', title: 'Token Akses', desc: 'Kelola token pelanggan', href: '?page=admin&tab=tokens', kbd: '' },
        { group: 'Admin', icon: 'payments', title: 'Pembayaran', desc: 'Midtrans settings & orders', href: '?page=admin&tab=payments', kbd: '' },
        { group: 'Admin', icon: 'analytics', title: 'Analytics', desc: 'Wawasan & heatmap', href: '?page=admin&tab=analytics', kbd: '' },
        { group: 'Admin', icon: 'logout', title: 'Keluar', desc: 'Logout dari panel', href: '?page=logout', kbd: '' }
      );
    }

    // Render
    const overlay = document.createElement('div');
    overlay.className = 'command-overlay';
    overlay.innerHTML = '<div class="command-dialog">' +
      '<div class="command-input-wrap">' +
        '<span class="material-symbols-rounded">search</span>' +
        '<input class="command-input" placeholder="Ketik perintah atau cari..." autocomplete="off" autofocus>' +
        '<span class="command-kbd">Esc</span>' +
      '</div>' +
      '<div class="command-list"></div>' +
      '<div class="command-footer">' +
        '<span><kbd>↑↓</kbd> Navigasi</span>' +
        '<span><kbd>↵</kbd> Buka</span>' +
        '<span><kbd>Esc</kbd> Tutup</span>' +
      '</div>' +
    '</div>';
    document.body.appendChild(overlay);

    const input = overlay.querySelector('.command-input');
    const list = overlay.querySelector('.command-list');
    let activeIdx = 0;

    function render(filter) {
      filter = (filter || '').toLowerCase();
      const filtered = commands.filter(function(c) {
        return !filter || c.title.toLowerCase().includes(filter) || c.desc.toLowerCase().includes(filter) || c.group.toLowerCase().includes(filter);
      });
      activeIdx = 0;
      if (!filtered.length) {
        list.innerHTML = '<div class="command-empty">Tidak ada perintah ditemukan</div>';
        return;
      }
      let html = '';
      let lastGroup = '';
      filtered.forEach(function(c, i) {
        if (c.group !== lastGroup) {
          html += '<div class="command-group-label">' + c.group + '</div>';
          lastGroup = c.group;
        }
        html += '<div class="command-item' + (i === 0 ? ' active' : '') + '" data-href="' + c.href + '" data-idx="' + i + '">' +
          '<span class="material-symbols-rounded">' + c.icon + '</span>' +
          '<div class="command-item-text"><div class="command-item-title">' + c.title + '</div>' +
          '<div class="command-item-desc">' + c.desc + '</div></div>' +
          (c.kbd ? '<span class="command-item-kbd">' + c.kbd + '</span>' : '') +
        '</div>';
      });
      list.innerHTML = html;
      // Bind clicks
      list.querySelectorAll('.command-item').forEach(function(item) {
        item.addEventListener('click', function() {
          overlay.remove();
          location.href = item.dataset.href;
        });
      });
    }

    render('');
    input.focus();

    input.addEventListener('input', function() { render(input.value); });

    input.addEventListener('keydown', function(e) {
      const items = list.querySelectorAll('.command-item');
      if (e.key === 'ArrowDown') {
        e.preventDefault();
        activeIdx = Math.min(activeIdx + 1, items.length - 1);
        items.forEach(function(it, i) { it.classList.toggle('active', i === activeIdx); });
        items[activeIdx] && items[activeIdx].scrollIntoView({ block: 'nearest' });
      } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        activeIdx = Math.max(activeIdx - 1, 0);
        items.forEach(function(it, i) { it.classList.toggle('active', i === activeIdx); });
        items[activeIdx] && items[activeIdx].scrollIntoView({ block: 'nearest' });
      } else if (e.key === 'Enter') {
        e.preventDefault();
        const active = list.querySelector('.command-item.active');
        if (active) { overlay.remove(); location.href = active.dataset.href; }
      } else if (e.key === 'Escape') {
        overlay.remove();
      }
    });

    overlay.addEventListener('click', function(e) {
      if (e.target === overlay) overlay.remove();
    });
  }

  // ---------- Boot Vue enhancements ----------
  async function boot() {
    // Core navigation and access UI must not depend on third-party CDN scripts.
    initTabs();
    initBurger();
    // Show logout toast if redirected from revoke-access
    if (new URLSearchParams(window.location.search).get('logged_out') === '1') {
      showToast('Logout berhasil', 'success');
      window.history.replaceState({}, '', window.location.pathname);
    }
    initUploadProgress();
    initTokenModal();
    initPreviewPlayer();
    initPlyr();
    initGallerySearch();
    initFormProtection();
    initDropdowns();
    initDownloadLoading();

    const state = await api('state').catch(() => ({ csrf: '', site: 'Arsip Layar', admin: false }));

    try {
      await fetch('api.php?op=event', {
        method: 'POST', credentials: 'same-origin', body: fd({
          csrf: state.csrf, event: 'page_view', path: location.pathname + location.search,
          device: /Mobi/i.test(navigator.userAgent) ? 'mobile' : 'desktop',
          browser: navigator.userAgent.slice(0, 80)
        })
      });
    } catch (_) { /* analytics fire-and-forget */ }

    initRetentionTracker(state.csrf);
    initMidtransPurchase(state.csrf);
    initKeyboardShortcuts(state);
    if (state.admin && location.search.includes('page=admin')) loadPaymentOrders();

    if (!window.Vue) return;

    const showInsightFloater = state.admin && !location.search.includes('page=admin') && !location.search.includes('page=login');
    if (showInsightFloater) mountInsightFloater(state);

    if (location.search.includes('page=admin')) {
      mountAnalytics(state);
      mountSecurity(state);
      mountSystem(state);
      mountWatermark(state);
      mountTelegram(state);
      initTokenManager();
      initVideoLibrary();
    }
  }

  // ---------- Preview player (15s teaser for non-token users) ----------
  function initPreviewPlayer() {
    const wrap = document.getElementById('preview-player');
    if (!wrap) return;
    const video = wrap.querySelector('video');
    const overlay = document.getElementById('preview-overlay');
    if (!video || !overlay) return;

    const previewSec = parseInt(wrap.dataset.previewSec || '15', 10);
    const previewUrl = wrap.dataset.previewUrl;
    if (!previewUrl) return; // no preview available

    // Add progress bar
    const progressWrap = document.createElement('div');
    progressWrap.className = 'preview-progress';
    progressWrap.innerHTML = '<div class="preview-progress-fill" id="preview-progress-fill"></div>';
    wrap.appendChild(progressWrap);

    // Add preview badge
    const badge = document.createElement('div');
    badge.className = 'preview-badge';
    badge.textContent = 'PREVIEW';
    wrap.appendChild(badge);

    const progressFill = document.getElementById('preview-progress-fill');

    // Track time and show overlay at previewSec
    let overlayShown = false;
    video.addEventListener('timeupdate', function () {
      if (overlayShown) return;
      // Update progress bar
      if (progressFill && video.duration) {
        const pct = Math.min(100, (video.currentTime / previewSec) * 100);
        progressFill.style.width = pct + '%';
      }
      // Pause at preview limit
      if (video.currentTime >= previewSec && !overlayShown) {
        overlayShown = true;
        video.pause();
        video.currentTime = previewSec;
        overlay.classList.add('active');
        // Remove progress bar
        if (progressWrap) progressWrap.style.display = 'none';
      }
    });

    // If video ends before previewSec (short video), also show overlay
    video.addEventListener('ended', function () {
      if (!overlayShown) {
        overlayShown = true;
        overlay.classList.add('active');
        if (progressWrap) progressWrap.style.display = 'none';
      }
    });

    // Use Plyr for better controls
    if (window.Plyr) {
      const player = new Plyr(video, {
        controls: ['play-large', 'play', 'progress', 'current-time', 'duration', 'mute', 'volume', 'fullscreen'],
        settings: [],
        tooltips: { controls: true, seek: true },
        seekTime: 10,
        ratio: '16:9',
        keyboard: { focused: true, global: true },
        i18n: { play: 'Putar', pause: 'Jeda', mute: 'Bisukan', unmute: 'Suarakan', enterFullscreen: 'Layar penuh', exitFullscreen: 'Keluar layar penuh' }
      });
      window.__previewPlayer = player;
    }
  }

  // ---------- Download button loading indicator ----------
  function initDownloadLoading() {
    document.addEventListener('click', function (e) {
      const link = e.target.closest('a[href*="page=download"]');
      if (!link) return;
      if (link.dataset.loading === '1') { e.preventDefault(); return; }
      link.dataset.loading = '1';
      const originalHTML = link.innerHTML;
      link.dataset.originalHtml = originalHTML;
      link.innerHTML = '<span class="material-symbols-rounded spin">progress_activity</span> Menyiapkan...';
      link.style.pointerEvents = 'none';
      // Reset after 10 seconds (download should have started)
      setTimeout(() => {
        link.innerHTML = originalHTML;
        link.style.pointerEvents = '';
        delete link.dataset.loading;
      }, 10000);
    });
  }

  function mountTelegram(state) {
    const el = document.getElementById('telegram-mount'); if (!el) return;
    const { createApp, ref, onMounted } = Vue;
    createApp({
      setup() {
        const cfg = ref({ has_token: false, token_mask: '', chat_id: '', enabled: false });
        const tokenInput = ref('');
        const chatInput = ref('');
        const enabled = ref(false);
        const busy = ref(false); const msg = ref(''); const err = ref('');
        const chats = ref([]);
        async function load() {
          cfg.value = await api('telegram_get');
          chatInput.value = cfg.value.chat_id || '';
          enabled.value = !!cfg.value.enabled;
        }
        async function save() {
          busy.value = true; err.value = ''; msg.value = '';
          try {
            const body = { csrf: state.csrf, chat_id: chatInput.value, enabled: enabled.value ? '1' : '0' };
            if (tokenInput.value) body.token = tokenInput.value.trim();
            const r = await api('telegram_save', 'POST', fd(body));
            if (r.ok) { msg.value = 'Pengaturan Telegram disimpan.'; showToast('Telegram tersimpan', 'success'); tokenInput.value = ''; await load(); }
            else { err.value = r.error || 'Gagal menyimpan'; showToast('Gagal menyimpan Telegram', 'error'); }
          } catch (e) { err.value = 'Kesalahan: ' + e.message; }
          finally { busy.value = false; }
        }
        async function test() {
          busy.value = true; err.value = ''; msg.value = '';
          try {
            const r = await api('telegram_test', 'POST', fd({ csrf: state.csrf }));
            if (r.ok) {
              msg.value = 'Test terkirim ke ' + (r.bot ? '@' + r.bot.username : 'bot') + (r.note ? ' — ' + r.note : '. Cek chat Anda.');
            } else err.value = r.error || 'Gagal';
          } catch (e) { err.value = e.message; }
          finally { busy.value = false; }
        }
        async function fetchUpdates() {
          busy.value = true; err.value = ''; msg.value = '';
          try {
            const r = await api('telegram_updates');
            if (r.ok) {
              chats.value = r.chats;
              if (!r.chats.length) msg.value = 'Belum ada pesan. Kirim pesan apa saja ke bot Anda, lalu klik lagi.';
            } else err.value = r.error || 'Gagal';
          } catch (e) { err.value = e.message; }
          finally { busy.value = false; }
        }
        function useChat(id) { chatInput.value = String(id); }
        onMounted(load);
        return { cfg, tokenInput, chatInput, enabled, busy, msg, err, chats, save, test, fetchUpdates, useChat };
      },
      template: `
        <div class="panel">
          <h3><span class="material-symbols-rounded">send</span> Notifikasi Telegram</h3>
          <p class="muted" style="margin-bottom:14px;font-size:13px">Bot akan mengirim poster + judul + link watch ke chat Anda setiap kali sebuah video selesai transcode HLS.</p>

          <div class="grid2" style="margin-bottom:0">
            <label>Bot token <small class="muted" v-if="cfg.has_token">(saat ini: {{cfg.token_mask}})</small>
              <input v-model="tokenInput" type="password" :placeholder="cfg.has_token ? 'Kosongkan untuk mempertahankan token saat ini' : 'Tempel token dari @BotFather'" autocomplete="off" data-testid="tg-token">
            </label>
            <label>Chat ID <small class="muted">(chat pribadi / group)</small>
              <input v-model="chatInput" placeholder="123456789 atau -100...  (gunakan tombol 'Ambil chat ID')" data-testid="tg-chat-id">
            </label>
          </div>

          <label class="switch" style="margin-top:8px">
            <input type="checkbox" v-model="enabled" data-testid="tg-enabled">
            <span class="track"><span class="knob"></span></span>
            <span>{{enabled?'Notifikasi AKTIF':'Notifikasi nonaktif'}}</span>
          </label>

          <div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:16px">
            <button @click="save" :disabled="busy" data-testid="tg-save"><span class="material-symbols-rounded">save</span> Simpan</button>
            <button class="ghost" @click="test" :disabled="busy || !cfg.has_token" data-testid="tg-test"><span class="material-symbols-rounded">bolt</span> Test kirim</button>
            <button class="ghost" @click="fetchUpdates" :disabled="busy || !cfg.has_token" data-testid="tg-fetch"><span class="material-symbols-rounded">refresh</span> Ambil chat ID</button>
          </div>

          <p v-if="msg" class="notice">{{msg}}</p>
          <p v-if="err" class="notice err">{{err}}</p>

          <div v-if="chats.length" style="margin-top:18px">
            <div class="eyebrow" style="margin-bottom:8px">Chat terdeteksi — klik untuk mengisi Chat ID</div>
            <div class="slist">
              <div class="row" v-for="c in chats" :key="c.id" style="cursor:pointer" @click="useChat(c.id)" data-testid="tg-chat-row">
                <span>{{c.title || c.username}} <small class="muted">· {{c.type}} · {{c.when}}</small></span>
                <span>{{c.id}}</span>
              </div>
            </div>
          </div>

          <details style="margin-top:20px">
            <summary class="muted" style="cursor:pointer;font-size:12px;font-family:var(--f-mono);letter-spacing:.1em;text-transform:uppercase">Cara pakai</summary>
            <ol class="muted" style="line-height:1.8;margin-top:10px;padding-left:20px;font-size:13px">
              <li>Cari bot Anda di Telegram, tekan <code>/start</code>.</li>
              <li>Kirim pesan apa saja ke bot (mis. <code>halo</code>).</li>
              <li>Kembali ke sini, klik <b>Ambil chat ID</b>, lalu klik baris chat Anda untuk mengisi otomatis.</li>
              <li>Aktifkan toggle, tekan <b>Simpan</b>, lalu <b>Test kirim</b> untuk memastikan.</li>
              <li>Selesai — setiap video baru yang selesai transcode akan tampil di chat Anda.</li>
            </ol>
          </details>
        </div>`
    }).mount(el);
  }

  function initBurger() {
    const b = document.querySelector('.burger'); const n = document.getElementById('nav');
    if (!b || !n) return;
    b.addEventListener('click', () => {
      const open = b.getAttribute('aria-expanded') === 'true';
      b.setAttribute('aria-expanded', String(!open));
      n.classList.toggle('open', !open);
    });
    n.querySelectorAll('a').forEach(a => a.addEventListener('click', () => {
      b.setAttribute('aria-expanded', 'false');
      n.classList.remove('open');
    }));
    document.addEventListener('click', (e) => {
      if (!n.classList.contains('open')) return;
      if (b.contains(e.target) || n.contains(e.target)) return;
      b.setAttribute('aria-expanded', 'false');
      n.classList.remove('open');
    });
  }

  // ---------- Plyr + HLS player with quality picker fix + error fallback ----------
  function initPlyr() {
    const wrap = document.querySelector('.player-wrap');
    if (!wrap || !window.Plyr) return;
    const video = wrap.querySelector('video');
    const hlsSrc = wrap.dataset.hls;
    const qualityButtons = $$('[data-quality]', wrap.parentElement);
    const QUALITY_STORAGE_KEY = 'arsip-quality';
    let _syncing = false; // re-entrant guard for applyQuality ↔ qualitychange

    const markQuality = (quality) => qualityButtons.forEach((button) => button.classList.toggle('active', Number(button.dataset.quality) === quality));

    // Restore persisted quality or default to Auto (0)
    let savedQuality = 0;
    try {
      const stored = localStorage.getItem(QUALITY_STORAGE_KEY);
      if (stored !== null) savedQuality = Number(stored);
    } catch (e) { void e; /* quota */ }
    markQuality(savedQuality);

    const createPlayer = (qualityOptions) => new Plyr(video, {
      controls: ['play-large', 'restart', 'rewind', 'play', 'fast-forward', 'progress', 'current-time', 'duration', 'mute', 'volume', 'captions', 'settings', 'pip', 'airplay', 'fullscreen'],
      settings: ['captions', 'quality', 'loop'],
      quality: { default: savedQuality, options: qualityOptions },
      iconUrl: '/assets/plyr.svg',
      blankVideo: 'https://cdn.jsdelivr.net/npm/plyr@3.7.8/dist/blank.mp4',
      keyboard: { focused: true, global: true },
      tooltips: { controls: true, seek: true },
      seekTime: 10,
      ratio: '16:9',
      storage: { enabled: true, key: 'arsip-plyr' },
      i18n: { play: 'Putar', pause: 'Jeda', mute: 'Bisukan', unmute: 'Suarakan', enableCaptions: 'Aktifkan takarir', disableCaptions: 'Matikan takarir', enterFullscreen: 'Layar penuh', exitFullscreen: 'Keluar layar penuh', settings: 'Pengaturan', normal: 'Normal', quality: 'Kualitas', pip: 'Picture in Picture', qualityBadge: 'Resolusi', loop: 'Ulangi' }
    });

    if (hlsSrc && !video.canPlayType('application/vnd.apple.mpegurl') && window.Hls && window.Hls.isSupported()) {
      const hls = new Hls({ enableWorker: true, lowLatencyMode: false, capLevelToPlayerSize: false, backBufferLength: 90 });
      hls.loadSource(hlsSrc);
      hls.attachMedia(video);
      hls.on(Hls.Events.MANIFEST_PARSED, () => {
        // Build sorted level map: height → hls level index
        const sortedLevels = hls.levels
          .map(function (level, idx) { return { height: Number(level.height) || 0, idx: idx }; })
          .filter(function (l) { return l.height > 0; })
          .sort(function (a, b) { return b.height - a.height; });
        const heightToIdx = {};
        sortedLevels.forEach(function (l) { heightToIdx[l.height] = l.idx; });

        // Plyr quality options: [0=Auto, ...sorted heights]
        const heights = sortedLevels.map(function (l) { return l.height; });
        const opts = heights.length > 0 ? [0].concat(heights) : [0, 720, 360];
        // Remove <source> elements BEFORE creating Plyr — they conflict with HLS.js
        $$('source[type*="mpegURL"], source[type*="mpegurl"]', video).forEach(function (s) { s.remove(); });
        const player = createPlayer(opts);

        // Core: set HLS level from button quality value
        function setHlsLevel(quality) {
          if (quality === 0) {
            hls.currentLevel = -1; // Auto
          } else if (heightToIdx[quality] !== undefined) {
            hls.currentLevel = heightToIdx[quality];
          } else {
            // Fallback: find closest height
            let best = sortedLevels[0];
            let diff = Math.abs(best.height - quality);
            sortedLevels.forEach(function (l) {
              const d = Math.abs(l.height - quality);
              if (d < diff) { diff = d; best = l; }
            });
            hls.currentLevel = best.idx;
          }
        }

        // Apply quality from any source (button click or Plyr gear) with re-entrant guard
        function applyQuality(quality, fromPlyr) {
          if (_syncing) return;
          _syncing = true;
          try {
            setHlsLevel(quality);
            markQuality(quality);
            try { localStorage.setItem(QUALITY_STORAGE_KEY, String(quality)); } catch (e) { void e; /* quota */ }
            // Sync Plyr gear icon if change came from custom button
            if (!fromPlyr && player) {
              try { player.quality = quality; } catch (e) { void e; /* Plyr internal */ }
            }
          } finally {
            _syncing = false;
          }
        }

        // Apply saved quality on load
        if (savedQuality !== 0) {
          setHlsLevel(savedQuality);
          markQuality(savedQuality);
        }

        // Plyr gear icon → HLS
        player.on('qualitychange', function (event) {
          const quality = Number(event.detail && event.detail.quality);
          if (Number.isNaN(quality)) return;
          applyQuality(quality, true);
        });

        // Custom buttons → HLS + Plyr
        qualityButtons.forEach(function (button) {
          button.addEventListener('click', function () {
            const quality = Number(button.dataset.quality);
            applyQuality(quality, false);
          });
        });

        window.__player = player;
      });
      hls.on(Hls.Events.ERROR, function (_event, data) {
        if (data && data.fatal) {
          showVideoError(wrap, 'Gagal memuat video. Pastikan koneksi internet stabil dan coba muat ulang.');
        }
      });
      window.__hls = hls;
    } else {
      // Safari/native HLS fallback: load the selected rendition directly.
      const opts = hlsSrc ? [0, 720, 360] : [0];
      window.__player = createPlayer(opts);
      // Apply saved quality on load
      if (savedQuality !== 0 && hlsSrc) {
        const src = savedQuality === 720 ? wrap.dataset.hls720 : savedQuality === 360 ? wrap.dataset.hls360 : hlsSrc;
        if (src) { video.src = src; video.load(); video.play().catch(function () {}); markQuality(savedQuality); }
      }
      qualityButtons.forEach(function (button) {
        button.addEventListener('click', function () {
          const quality = Number(button.dataset.quality);
          const src = quality === 720 ? wrap.dataset.hls720 : quality === 360 ? wrap.dataset.hls360 : hlsSrc;
          if (src) {
            video.src = src;
            video.load();
            video.play().catch(function () {});
          }
          markQuality(quality);
          try { localStorage.setItem(QUALITY_STORAGE_KEY, String(quality)); } catch (e) { void e; /* quota */ }
        });
      });
    }

    // Video error fallback
    video.addEventListener('error', function () {
      showVideoError(wrap, 'Terjadi kesalahan saat memutar video. Silakan muat ulang halaman.');
    });
  }

  function showVideoError(wrap, message) {
    if (wrap.querySelector('.video-error-overlay')) return;
    const overlay = document.createElement('div');
    overlay.className = 'video-error-overlay';
    overlay.innerHTML = '<div class="video-error-content">' +
      '<span class="material-symbols-rounded" style="font-size:48px;color:var(--accent);opacity:0.7">error</span>' +
      '<p style="color:var(--ink);margin:12px 0 16px;font-size:15px">' + message + '</p>' +
      '<button class="button" onclick="location.reload()"><span class="material-symbols-rounded">refresh</span> Muat Ulang</button>' +
      '</div>';
    wrap.style.position = 'relative';
    wrap.appendChild(overlay);
  }

  // ---------- Bulk Chunked Upload (up to 4 files, resumable) ----------
  function initUploadProgress() {
    const form = document.getElementById('upload-form'); if (!form) return;
    const queueEl = document.getElementById('upload-queue');
    const fileInput = document.getElementById('upload-file-input');
    const submitBtn = document.getElementById('upload-submit-btn');
    const csrfInput = form.querySelector('input[name=csrf]');
    if (!queueEl || !fileInput || !submitBtn || !csrfInput) return;

    const MAX_FILES = 4;
    const MAX_SIZE_MB = parseInt(form.closest('[data-upload-mb]')?.dataset?.uploadMb || '2048', 10);
    const CHUNK_SIZE = 5 * 1024 * 1024; // 5MB — must match server
    const STORAGE_KEY = 'arsip_upload_queue';

    let uploadQueue = []; // [{file, uploadId, status, progress, chunks, totalChunks, name, size}]
    let isUploading = false;
    let aborting = false; // FIX #4: prevent processQueue() during abort

    // --- Persist/restore from localStorage ---
    function saveQueueState() {
      const state = uploadQueue.map(function (item) {
        return {
          uploadId: item.uploadId, name: item.name, size: item.size,
          status: item.status, progress: item.progress,
          uploadedBytes: item.uploadedBytes || 0,
          chunks: item.chunks, totalChunks: item.totalChunks,
          errorAt: item.errorAt || 0, retryCount: item.retryCount || 0
        };
      });
      try { localStorage.setItem(STORAGE_KEY, JSON.stringify(state)); } catch (_) { /* quota */ }
    }
    function restoreQueueState() {
      try {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (!raw) return [];
        const state = JSON.parse(raw);
        const now = Date.now();
        const STALE_MS = 30 * 60 * 1000; // FIX #2: 30 min — stale error items auto-cleared
        return state.filter(function (s) {
          if (s.status === 'done' || s.status === 'aborted') return false;
          // FIX #2: error items older than 30 min are considered stale
          if (s.status === 'error' && s.errorAt && (now - s.errorAt) > STALE_MS) return false;
          return true;
        })
          .map(function (s) {
            return Object.assign(s, {
              file: null, // FIX #1: File object cannot be serialized to localStorage
              needsFile: true, // FIX #1: Flag that file must be re-selected
              progress: s.progress || 0,
              uploadedBytes: s.uploadedBytes || 0,
              retryCount: s.retryCount || 0
            });
          });
      } catch (_) { return []; }
    }
    function clearQueueState() {
      try { localStorage.removeItem(STORAGE_KEY); } catch (_) { /* ignore */ }
    }

    // --- Render queue UI ---
    function renderQueue() {
      if (!uploadQueue.length) { queueEl.classList.add('hidden'); queueEl.innerHTML = ''; return; }
      queueEl.classList.remove('hidden');        let html = '<div class="upload-queue-header"><span class="material-symbols-rounded">queue</span> Antrian upload (' + uploadQueue.length + '/' + MAX_FILES + ')</div>';
        uploadQueue.forEach(function (item, idx) {
          const pct = item.progress || 0;
          const uploaded = item.uploadedBytes || 0;
          const sizeStr = formatBytes(item.size);
          let statusIcon = 'pending';
          let statusClass = '';
          let statusLabel = 'Menunggu…';
          // FIX #1: Show 'needs file' state for restored items with null file
          if (item.needsFile && !item.file) {
            statusIcon = 'warning'; statusClass = 'uq-needs-file'; statusLabel = '⚠ Pilih ulang file';
          }
          else if (item.status === 'uploading') { statusIcon = 'progress_activity'; statusClass = 'spin'; statusLabel = 'Uploading'; }
          else if (item.status === 'processing') { statusIcon = 'settings'; statusClass = 'spin'; statusLabel = 'Memproses…'; }
          else if (item.status === 'done') { statusIcon = 'check_circle'; statusClass = 'uq-done'; statusLabel = 'Selesai'; }
          else if (item.status === 'error') { statusIcon = 'error'; statusClass = 'uq-error'; statusLabel = item.error || 'Gagal'; }
          else if (item.status === 'aborted') { statusIcon = 'cancel'; statusClass = 'uq-abort'; statusLabel = 'Dibatalkan'; }

          const showProgress = item.status === 'uploading' || item.status === 'processing';
          const isUploading = item.status === 'uploading';

          html += '<div class="upload-queue-item ' + (item.status || '') + '" data-idx="' + idx + '">' +
          '<div class="uq-info">' +
          '<span class="material-symbols-rounded ' + statusClass + '">' + statusIcon + '</span>' +
          '<div class="uq-details">' +
          '<span class="uq-name" title="' + escapeAttr(item.name) + '">' + escapeHtml(item.name) + '</span>' +
          '<span class="uq-meta">' + sizeStr + ' &middot; ' + statusLabel + '</span>' +
          '</div>' +
          (isUploading || item.status === 'processing' ? '<button class="uq-cancel ghost small" data-idx="' + idx + '" title="Batalkan"><span class="material-symbols-rounded">close</span></button>' : '') +
          (item.status === 'error' ? '<button class="uq-retry ghost small" data-idx="' + idx + '" title="Coba lagi"><span class="material-symbols-rounded">refresh</span></button>' : '') +
          '</div>' +
          (showProgress ? '<div class="uq-progress">' +
          '<div class="uq-track"><div class="uq-fill" style="width:' + (isUploading ? pct.toFixed(1) : (item.status === 'processing' ? '100' : '0')) + '%"></div></div>' +
          '<div class="uq-progress-info">' +
          '<span class="uq-pct">' + (isUploading ? pct.toFixed(1) + '%' : (item.status === 'processing' ? '100%' : '0%')) + '</span>' +
          '<span class="uq-bytes">' + (isUploading ? formatBytes(uploaded) + ' / ' + sizeStr : (item.status === 'processing' ? sizeStr : '')) + '</span>' +
          '</div></div>' : '') +
          '</div>';
      });
      queueEl.innerHTML = html;

      // Bind cancel buttons
      queueEl.querySelectorAll('.uq-cancel').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
          e.preventDefault();
          const idx = parseInt(btn.dataset.idx, 10);
          abortUpload(idx);
        });
      });
      // Bind retry buttons
      queueEl.querySelectorAll('.uq-retry').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
          e.preventDefault();
          const idx = parseInt(btn.dataset.idx, 10);
          retryUpload(idx);
        });
      });
    }

    function formatBytes(b) {
      if (b < 1024) return b + ' B';
      if (b < 1048576) return (b / 1024).toFixed(1) + ' KB';
      return (b / 1048576).toFixed(1) + ' MB';
    }
    function escapeHtml(s) {
      const d = document.createElement('div'); d.textContent = s; return d.innerHTML;
    }
    function escapeAttr(s) {
      return s.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
    }

    // --- API helper for chunk uploads ---
    function apiPost(op, body) {
      return fetch('api.php?op=' + op, {
        method: 'POST', credentials: 'same-origin', body: body
      }).then(function (r) { return r.json(); });
    }
    function apiGet(op) {
      return fetch('api.php?op=' + op, { credentials: 'same-origin' })
        .then(function (r) { return r.json(); });
    }

    // --- Initialize upload for a file ---
    function initFileUpload(item) {
      const fd2 = new FormData();
      fd2.append('csrf', csrfInput.value);
      fd2.append('filename', item.name);
      fd2.append('total_size', String(item.size));
      return apiPost('upload_init', fd2).then(function (res) {
        if (res.error) throw new Error(res.error);
        item.uploadId = res.upload_id;
        item.totalChunks = Math.ceil(item.size / (res.chunk_size || CHUNK_SIZE));
        item.chunks = [];
        item.status = 'uploading';
        item.progress = 0;
        saveQueueState();
        renderQueue();
        return item;
      });
    }

    // --- Check server for existing chunks (resume) ---
    function checkResume(item) {
      if (!item.uploadId) return Promise.resolve(item);
      return apiGet('upload_status&upload_id=' + encodeURIComponent(item.uploadId))
        .then(function (res) {
          if (res.ok && res.uploaded_chunks) {
            item.chunks = res.uploaded_chunks;
            item.totalChunks = res.total_chunks || item.totalChunks;
            const pct = item.totalChunks > 0 ? (item.chunks.length / item.totalChunks) * 100 : 0;
            item.progress = pct;
            saveQueueState();
            renderQueue();
          }
          return item;
        })
        .catch(function () { return item; });
    }

    // --- Upload a single chunk via XHR ---
    function uploadChunkXhr(item, chunkIdx) {
      return new Promise(function (resolve, reject) {
        const start = chunkIdx * CHUNK_SIZE;
        const end = Math.min(start + CHUNK_SIZE, item.size);
        const chunkBlob = item.file ? item.file.slice(start, end) : null;
        // FIX #1: Clearer error message — file object lost after page reload
        if (!chunkBlob) { reject(new Error('File tidak tersedia — muat ulang halaman dan pilih file lagi.')); return; }

        const fd2 = new FormData();
        fd2.append('csrf', csrfInput.value);
        fd2.append('upload_id', item.uploadId);
        fd2.append('chunk_number', String(chunkIdx));
        fd2.append('chunk', chunkBlob, 'chunk');

        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'api.php?op=upload_chunk', true);
        xhr.upload.onprogress = function (ev) {
          if (!ev.lengthComputable) return;
          const chunkPct = ev.loaded / ev.total;
          const basePct = (chunkIdx / item.totalChunks) * 100;
          const chunkWeight = (1 / item.totalChunks) * 100;
          item.progress = basePct + chunkPct * chunkWeight;
          const chunkBytesUploaded = ev.loaded;
          const prevChunksBytes = chunkIdx * CHUNK_SIZE;
          item.uploadedBytes = Math.min(prevChunksBytes + chunkBytesUploaded, item.size);
          renderQueue();
        };
        xhr.onload = function () {
          try {
            const res = JSON.parse(xhr.responseText);
            if (res.ok) {
              item.chunks = res.uploaded_chunks || item.chunks;
              item.progress = ((chunkIdx + 1) / item.totalChunks) * 100;
              item.uploadedBytes = Math.min((chunkIdx + 1) * CHUNK_SIZE, item.size);
              saveQueueState();
              renderQueue();
              resolve(res);
            } else {
              reject(new Error(res.error || 'Chunk upload failed'));
            }
          } catch (_) {
            reject(new Error('Respons tidak valid'));
          }
        };
        xhr.onerror = function () { reject(new Error('Koneksi terputus')); };
        xhr.ontimeout = function () { reject(new Error('Timeout')); };
        xhr.timeout = 300000; // 5 min per chunk
        xhr.send(fd2);
      });
    }

    // --- Upload all chunks for a file ---
    function uploadAllChunks(item) {
      // FIX #1: Guard — file must exist before attempting chunk upload
      if (!item.file) return Promise.reject(new Error('File tidak tersedia — muat ulang halaman dan pilih file lagi.'));
      let startChunk = 0;
      if (item.chunks && item.chunks.length) {
        startChunk = Math.max.apply(null, item.chunks) + 1;
      }
      let chain = Promise.resolve();
      for (let c = startChunk; c < item.totalChunks; c++) {
        (function (chunkIdx) {
          chain = chain.then(function () {
            if (item.status === 'aborted') return Promise.reject(new Error('aborted'));
            return uploadChunkXhr(item, chunkIdx);
          });
        })(c);
      }
      return chain;
    }

    // --- Finalize upload ---
    function finalizeUpload(item) {
      item.status = 'processing';
      item.progress = 100;
      renderQueue();

      const catEl = form.querySelector('select[name=category_id]');
      const titleEl = form.querySelector('input[name=title]');
      const fd2 = new FormData();
      fd2.append('csrf', csrfInput.value);
      fd2.append('upload_id', item.uploadId);
      fd2.append('category_id', catEl ? catEl.value : '0');
      fd2.append('title', titleEl ? titleEl.value : '');
      return apiPost('upload_complete', fd2).then(function (res) {
        if (res.error) throw new Error(res.error);
        item.status = 'done';
        item.progress = 100;
        saveQueueState();
        renderQueue();
        showToast(item.name + ' — upload selesai, transcode berjalan.', 'success');
        return res;
      });
    }

    // --- Process queue (sequential) ---
    function processQueue() {
      if (isUploading || aborting) return; // FIX #4: guard against abort race

      // FIX #2: If all items are error/null-file, clear queue and stop
      const hasUploadable = uploadQueue.some(function (q) {
        return (q.status === 'pending' || q.status === 'queued' || q.status === 'uploading') && q.file;
      });
      if (!hasUploadable && uploadQueue.every(function (q) { return q.status === 'error' || !q.file; })) {
        clearQueueState();
        uploadQueue = [];
        isUploading = false;
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<span class="material-symbols-rounded">upload</span> Mulai unggah & transcode';
        renderQueue();
        return;
      }

      let next = null;
      for (let i = 0; i < uploadQueue.length; i++) {
        if (uploadQueue[i].status === 'pending' || uploadQueue[i].status === 'queued') {
          next = uploadQueue[i]; break;
        }
      }
      if (!next) {
        // All done or error — check if any completed
        const hasDone = uploadQueue.some(function (q) { return q.status === 'done'; });
        isUploading = false;
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<span class="material-symbols-rounded">upload</span> Mulai unggah & transcode';
        if (hasDone) {
          clearQueueState();
          setTimeout(function () { location.href = '?page=admin&uploaded=1'; }, 1200);
        }
        return;
      }
      isUploading = true;
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<span class="material-symbols-rounded spin">progress_activity</span> Mengunggah…';

      const chain = Promise.resolve(next)
        .then(function (item) {
          // FIX #1: Guard — file must exist before attempting upload
          if (!item.file) throw new Error('File tidak tersedia — muat ulang halaman dan pilih file lagi.');
          if (!item.uploadId) return initFileUpload(item);
          return item;
        })
        .then(function (item) { return checkResume(item); })
        .then(function (item) { return uploadAllChunks(item); })
        .then(function () { return finalizeUpload(next); })
        .then(function () {
          isUploading = false;
          processQueue();
        })
        .catch(function (err) {
          if (err && err.message === 'aborted') {
            isUploading = false;
            processQueue();
            return;
          }
          next.status = 'error';
          next.error = err ? err.message : 'Gagal';
          next.errorAt = Date.now(); // FIX #2: Timestamp for stale error detection
          saveQueueState();
          renderQueue();
          isUploading = false;
          processQueue();
        });
    }

    // --- Abort a single upload ---
    function abortUpload(idx) {
      const item = uploadQueue[idx];
      if (!item) return;
      item.status = 'aborted';
      renderQueue();
      if (item.uploadId) {
        // FIX #4: Await server response before continuing to next file
        aborting = true;
        const fd2 = new FormData();
        fd2.append('csrf', csrfInput.value);
        fd2.append('upload_id', item.uploadId);
        apiPost('upload_abort', fd2)
          .catch(function () { /* ignore abort errors */ })
          .then(function () {
            aborting = false;
            if (isUploading) {
              isUploading = false;
              processQueue();
            }
          });
      } else {
        if (isUploading) {
          isUploading = false;
          processQueue();
        }
      }
    }

    // --- Retry a failed upload ---
    function retryUpload(idx) {
      const item = uploadQueue[idx];
      if (!item) return;

      // FIX #1: If file object is lost (page reload), cannot retry
      if (!item.file) {
        showToast(item.name + ' — file tidak tersedia. Pilih ulang file untuk melanjutkan.', 'warning');
        uploadQueue.splice(idx, 1);
        saveQueueState();
        renderQueue();
        return;
      }

      // FIX #3: Limit retries to prevent infinite loops
      const MAX_RETRY = 3;
      if ((item.retryCount || 0) >= MAX_RETRY) {
        showToast(item.name + ' — terlalu banyak percobaan gagal. Hapus dan pilih ulang file.', 'error');
        uploadQueue.splice(idx, 1);
        saveQueueState();
        renderQueue();
        return;
      }

      item.retryCount = (item.retryCount || 0) + 1;
      item.status = 'pending';
      item.error = '';
      item.progress = 0;
      item.chunks = []; // Reset chunks so resume check runs
      saveQueueState();
      renderQueue();
      processQueue();
    }

    // --- Form submit handler ---
    form.addEventListener('submit', function (e) {
      const files = fileInput.files;
      if (!files || !files.length) return;
      e.preventDefault();

      if (uploadQueue.length + files.length > MAX_FILES) {
        showToast('Maksimal ' + MAX_FILES + ' file sekaligus.', 'warning');
        return;
      }

      for (let i = 0; i < files.length; i++) {
        const f = files[i];
        if (f.size > MAX_SIZE_MB * 1048576) {
          showToast(f.name + ' melebihi batas ' + MAX_SIZE_MB + ' MB.', 'error');
          continue;
        }
        uploadQueue.push({
          file: f, uploadId: null, status: 'pending',
          progress: 0, chunks: [], totalChunks: Math.ceil(f.size / CHUNK_SIZE),
          name: f.name, size: f.size,
          retryCount: 0 // FIX #3: Track retry count per file
        });
      }
      fileInput.value = '';
      saveQueueState();
      renderQueue();
      processQueue();
    });

    // --- Restore queue from localStorage on page load ---
    const restored = restoreQueueState();
    if (restored.length) {
      uploadQueue = restored;
      renderQueue();
      processQueue();
    }
  }

  function mountWatermark(state) {
    const el = document.getElementById('watermark-mount'); if (!el) return;
    const { createApp, ref } = Vue;
    createApp({
      setup() {
        const text = ref(el.dataset.text || 'Codename F');
        const position = ref(el.dataset.position || 'br');
        const opacity = ref(parseInt(el.dataset.opacity || '60', 10));
        const saving = ref(false); const msg = ref('');
        const positions = [
          { v: 'tl', l: 'Kiri atas' }, { v: 'tr', l: 'Kanan atas' },
          { v: 'bl', l: 'Kiri bawah' }, { v: 'br', l: 'Kanan bawah' },
          { v: 'center', l: 'Tengah (besar)' }
        ];
        async function save() {
          saving.value = true; msg.value = '';
          try {
            const r = await api('watermark', 'POST', fd({ csrf: state.csrf, text: text.value, position: position.value, opacity: opacity.value }));
            if (r.ok) { msg.value = 'Watermark disimpan.'; showToast('Watermark disimpan', 'success'); }
          } finally { saving.value = false; }
        }
        return { text, position, opacity, positions, save, saving, msg };
      },
      template: `
        <form @submit.prevent="save" class="wm-form">
          <div class="grid2" style="margin-bottom:0">
            <label>Teks watermark<input v-model="text" maxlength="40" placeholder="Codename F" data-testid="wm-text"></label>
            <label>Posisi
              <select v-model="position" data-testid="wm-position">
                <option v-for="p in positions" :value="p.v" :key="p.v">{{p.l}}</option>
              </select>
            </label>
          </div>
          <label>Opasitas ({{opacity}}%)
            <input type="range" v-model.number="opacity" min="10" max="100" step="5" data-testid="wm-opacity" style="height:auto">
          </label>
          <div class="wm-preview" :class="'wm-preview-'+position">
            <span class="wm-preview-text" :style="{opacity: opacity/100}">{{text || 'Codename F'}}</span>
          </div>
          <button :disabled="saving" data-testid="wm-save"><span class="material-symbols-rounded">save</span> {{saving?'Menyimpan…':'Simpan watermark'}}</button>
          <p v-if="msg" class="notice">{{msg}}</p>
        </form>`
    }).mount(el);
  }


  function mountInsightFloater(state) {
    const root = document.createElement('div'); document.body.appendChild(root);
    const { createApp, ref, onMounted } = Vue;
    createApp({
      setup() {
        const d = ref(null);
        onMounted(async () => { try { d.value = await api('insights&days=30'); } catch (_) { /* silent */ } });
        return { d };
      },
      template: `
        <section v-if="d" class="insight-float" data-testid="insight-summary">
          <span class="k">30 hari · internal</span>
          <strong>{{d.metrics.visitors||0}}</strong>
          <small>pengunjung · {{d.metrics.video_views||0}} mulai video</small>
        </section>`
    }).mount(root);
  }

  function mountAnalytics(state) {
    const el = $('#analytics-mount'); if (!el) return;
    const { createApp, ref, reactive, onMounted } = Vue;
    createApp({
      setup() {
        const d = ref(null); const days = ref(30); const loading = ref(false);
        const videoHeatmap = reactive({ videoId: 0, data: [], duration: 0, loading: false });
        async function load() {
          loading.value = true;
          try { d.value = await api('insights&days=' + days.value); } finally { loading.value = false; }
        }
        async function loadVideoHeatmap(vid) {
          videoHeatmap.loading = true; videoHeatmap.videoId = vid; videoHeatmap.data = []; videoHeatmap.duration = 0;
          try {
            const r = await api('heatmap_data&video_id=' + vid);
            videoHeatmap.data = r.heatmap || [];
            videoHeatmap.duration = r.duration || 0;
          } catch (_) { /* silent */ } finally { videoHeatmap.loading = false; }
        }
        onMounted(load);
        const days_list = [7, 30, 90];
        const dow_labels = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
        function heatLevel(val, max) { if (!max || !val) return 0; const p = val / max; if (p >= 0.75) return 4; if (p >= 0.5) return 3; if (p >= 0.25) return 2; return 1; }
        function heatmapMax(h) { let m = 0; for (const row of h || []) for (const c of row) if (c > m) m = c; return m; }
        function retentionPct(r) { if (!r.duration_sec || !r.avg_sec) return 0; return Math.min(100, Math.round((r.avg_sec / r.duration_sec) * 100)); }
        function hmBarMax() { let m = 0; for (const h of videoHeatmap.data) if (h.total > m) m = h.total; return m; }
        function fmtSec(s) { const m = Math.floor(s / 60); const sec = s % 60; return m + ':' + String(sec).padStart(2, '0'); }
        return { d, days, days_list, loading, load, dow_labels, heatLevel, heatmapMax, retentionPct, videoHeatmap, loadVideoHeatmap, hmBarMax, fmtSec };
      },
      template: `
        <div>
          <div class="eyebrow" style="display:flex;justify-content:space-between;align-items:center">
            <span>WAWASAN INTERNAL / {{days}} HARI</span>
            <span>
              <button v-for="n in days_list" :key="n" class="ghost small" style="margin-left:6px"
                      :style="days===n?'border-color:var(--accent);color:var(--accent)':''"
                      @click="days=n;load()">{{n}} hari</button>
            </span>
          </div>
          <div v-if="loading" class="skeleton-wrap"><div class="skeleton skeleton-card" style="height:120px"></div><div class="skeleton skeleton-card" style="height:200px;margin-top:14px"></div></div>
          <div v-if="d && !loading">
            <div class="metric-grid">
              <div class="metric"><div class="k">Pengunjung</div><strong>{{d.metrics.visitors||0}}</strong><small>unik (harian)</small></div>
              <div class="metric"><div class="k">Total kunjungan</div><strong>{{d.metrics.page_views||0}}</strong><small>page views</small></div>
              <div class="metric"><div class="k">Video dimulai</div><strong>{{d.metrics.video_views||0}}</strong><small>play events</small></div>
              <div class="metric"><div class="k">Total event</div><strong>{{d.metrics.total||0}}</strong><small>termasuk retensi</small></div>
            </div>

            <div class="grid2">
              <div class="panel"><h3>Halaman populer</h3>
                <div class="slist" v-if="d.popular.length"><div class="row" v-for="p in d.popular"><span>{{p.path}}</span><span>{{p.views}}</span></div></div>
                <p class="muted" v-else>Belum ada data.</p>
              </div>
              <div class="panel"><h3>Sumber kunjungan</h3>
                <div class="slist" v-if="d.sources.length"><div class="row" v-for="s in d.sources"><span>{{s.src.slice(0,60)}}</span><span>{{s.hits}}</span></div></div>
                <p class="muted" v-else>Belum ada data referrer.</p>
              </div>
            </div>

            <div class="panel"><h3>Jam &amp; hari ramai</h3>
              <div class="heatmap-wrap"><div class="heatmap">
                <span></span><span class="hh" v-for="h in 24" :key="h">{{h-1}}</span>
                <template v-for="(row,i) in d.heatmap" :key="i">
                  <span>{{dow_labels[i]}}</span>
                  <div class="cell" v-for="(v,j) in row" :key="j" :data-lvl="heatLevel(v,heatmapMax(d.heatmap))" :title="dow_labels[i]+' '+j+':00 · '+v"></div>
                </template>
              </div></div>
            </div>

            <div class="panel"><h3>Retention video</h3>
              <div class="bars" v-if="d.retention.length">
                <div class="bar" v-for="r in d.retention" :key="r.id">
                  <div>
                    <div style="color:var(--ink);display:flex;justify-content:space-between;align-items:center;gap:8px">
                      <span>{{r.title}}</span>
                      <button class="ghost small" @click="loadVideoHeatmap(r.id)" style="padding:4px 10px;font-size:10px">Heatmap</button>
                    </div>
                    <div class="track"><div class="fill" :style="{width: retentionPct(r)+'%'}"></div></div>
                  </div>
                  <div class="num">{{retentionPct(r)}}% · {{r.samples||0}} sampel</div>
                </div>
              </div>
              <p class="muted" v-else>Retention akan tampil setelah ada penonton.</p>
            </div>

            <div class="panel" v-if="videoHeatmap.videoId">
              <h3>Engagement Heatmap — Video #{{videoHeatmap.videoId}}</h3>
              <p class="muted" style="margin-bottom:14px;font-size:12px">Intensitas warna = jumlah penonton yang menonton detik tersebut.</p>
              <div v-if="videoHeatmap.loading" class="muted" style="padding:20px">Memuat heatmap…</div>
              <div v-else-if="videoHeatmap.data.length" class="engagement-heatmap">
                <div class="eh-track" :title="fmtSec(h.second_index) + ' — ' + h.total + ' views'" v-for="h in videoHeatmap.data" :key="h.second_index"
                     :style="{ height: Math.max(4, (h.total / hmBarMax()) * 80) + 'px', background: 'var(--accent)' }"></div>
              </div>
              <p class="muted" v-else>Belum ada data engagement untuk video ini.</p>
            </div>

            <div class="panel"><h3>Perangkat</h3>
              <div class="slist"><div class="row" v-for="dv in d.devices"><span>{{dv.device}}</span><span>{{dv.c}}</span></div></div>
            </div>
          </div>
        </div>`
    }).mount(el);
  }

  function mountSecurity(state) {
    const el = $('#security-mount'); if (!el) return;
    const { createApp, ref, onMounted } = Vue;
    createApp({
      setup() {
        const totpOn = ref(el.dataset.totpEnabled === '1');
        const setup = ref(null); const codeInput = ref(''); const msg = ref(''); const err = ref('');
        const activity = ref([]); const failed = ref([]);
        async function loadLogs() { const r = await api('activity'); activity.value = r.activity; failed.value = r.failed_logins; }
        async function start2FA() { err.value = ''; msg.value = ''; setup.value = await api('2fa_setup'); }
        async function enable2FA() {
          err.value = ''; msg.value = '';
          try {
            await api('2fa_enable', 'POST', fd({ csrf: state.csrf, code: codeInput.value }));
            totpOn.value = true; setup.value = null; codeInput.value = ''; msg.value = '2FA berhasil diaktifkan.'; showToast('2FA diaktifkan', 'success');
          } catch (e) { err.value = 'Kode salah atau kedaluwarsa.'; }
        }
        async function disable2FA() {
          if (!confirm('Nonaktifkan 2FA?')) return;
          await api('2fa_disable', 'POST', fd({ csrf: state.csrf }));
          totpOn.value = false; msg.value = '2FA dinonaktifkan.'; showToast('2FA dinonaktifkan', 'warning');
        }
        function qrUrl(otpauth) {
          return 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=' + encodeURIComponent(otpauth);
        }
        onMounted(loadLogs);
        return { totpOn, setup, codeInput, msg, err, activity, failed, start2FA, enable2FA, disable2FA, qrUrl };
      },
      template: `
        <div>
          <div class="eyebrow">KEAMANAN AKUN</div>
          <div class="panel">
            <h3>Autentikasi dua faktor (TOTP)</h3>
            <p class="muted" v-if="!totpOn && !setup">Sangat disarankan. Gunakan Google Authenticator, 1Password, Aegis, atau Authy.</p>
            <div v-if="totpOn && !setup">
              <p class="notice">2FA aktif untuk akun Anda.</p>
              <button class="ghost" @click="disable2FA" data-testid="2fa-disable">Nonaktifkan 2FA</button>
            </div>
            <button v-if="!totpOn && !setup" @click="start2FA" data-testid="2fa-setup">Aktifkan 2FA</button>
            <div v-if="setup">
              <p class="muted">Pindai QR ini atau masukkan kunci manual, lalu isi kode 6 digit di bawah.</p>
              <div style="display:flex;gap:20px;flex-wrap:wrap;align-items:flex-start;margin:12px 0">
                <div class="qr"><img :src="qrUrl(setup.otpauth)" alt="QR 2FA" width="180" height="180"></div>
                <div>
                  <p style="font-size:11px;color:var(--muted);margin:0 0 6px">KUNCI MANUAL</p>
                  <div class="mono">{{setup.secret}}</div>
                </div>
              </div>
              <label>Kode 6 digit dari aplikasi Anda
                <input v-model="codeInput" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" data-testid="2fa-code">
              </label>
              <div style="display:flex;gap:10px;margin-top:10px">
                <button @click="enable2FA" data-testid="2fa-confirm">Konfirmasi &amp; aktifkan</button>
                <button class="ghost" @click="setup=null">Batal</button>
              </div>
            </div>
            <p v-if="msg" class="notice">{{msg}}</p>
            <p v-if="err" class="notice err">{{err}}</p>
          </div>

          <div class="grid2">
            <div class="panel">
              <h3>Percobaan login gagal</h3>
              <div class="slist"><div class="row" v-for="f in failed" :key="f.created_at">
                <span>{{f.email||'(kosong)'}} · {{f.reason}}</span><span>{{f.ip}} · {{f.created_at}}</span>
              </div></div>
              <p class="muted" v-if="!failed.length">Belum ada percobaan gagal — bagus!</p>
            </div>
            <div class="panel">
              <h3>Log aktivitas admin</h3>
              <div class="slist"><div class="row" v-for="a in activity" :key="a.id">
                <span>{{a.action}} · {{a.detail}}</span><span>{{a.name||'—'}} · {{a.created_at}}</span>
              </div></div>
            </div>
          </div>

          <div class="panel">
            <h3>Lapisan keamanan aktif</h3>
            <ul class="muted" style="line-height:1.9">
              <li>✓ CSRF token per-sesi wajib untuk semua form &amp; POST</li>
              <li>✓ Header CSP, HSTS, X-Frame-Options: DENY, Referrer-Policy strict</li>
              <li>✓ Cookie session SameSite=Strict, HttpOnly, Secure di HTTPS</li>
              <li>✓ Session terikat User-Agent (anti-hijack) &amp; regen setelah login</li>
              <li>✓ Argon2id password hashing (auto-upgrade dari bcrypt saat login)</li>
              <li>✓ Rate-limit login (8 gagal / 15 menit / IP) + activity log</li>
              <li>✓ 2FA TOTP opsional per admin</li>
              <li>✓ Firewall UFW + fail2ban di server</li>
              <li>✓ Upload dibatasi MIME + ekstensi + ukuran, folder media 0750</li>
              <li>✓ Nginx menolak eksekusi PHP di dalam /media</li>
            </ul>
          </div>
        </div>`
    }).mount(el);
  }

  function mountSystem(state) {
    const el = $('#system-mount'); if (!el) return;
    const { createApp, ref, onMounted } = Vue;
    createApp({
      setup() {
        const uploadMb = ref(parseInt(el.dataset.uploadMb || '2048', 10));
        const maintenance = ref(el.dataset.maintenance === '1');
        const backups = ref([]); const busy = ref(false); const msg = ref('');
        async function reload() { const r = await api('backup_list'); backups.value = r.items; }
        async function doBackup() { busy.value = true; msg.value = ''; try { const r = await api('backup', 'POST', fd({ csrf: state.csrf })); if (r.ok) { msg.value = 'Backup dibuat: ' + r.file; showToast('Backup berhasil: ' + r.file, 'success'); await reload(); } else { msg.value = 'Gagal: ' + (r.error || ''); showToast('Backup gagal', 'error'); } } finally { busy.value = false; } }
        async function toggleMaintenance() { maintenance.value = !maintenance.value; await api('maintenance', 'POST', fd({ csrf: state.csrf, on: maintenance.value ? '1' : '0' })); showToast(maintenance.value ? 'Mode perawatan diaktifkan' : 'Mode perawatan dinonaktifkan', 'info'); }
        async function saveUpload() { const r = await api('upload_limit', 'POST', fd({ csrf: state.csrf, mb: uploadMb.value })); if (r.ok) { msg.value = 'Batas upload disimpan: ' + r.mb + ' MB'; showToast('Batas upload: ' + r.mb + ' MB', 'success'); } }
        async function bustCache() { const r = await api('cache_bust', 'POST', fd({ csrf: state.csrf })); if (r.ok) { msg.value = 'Cache di-refresh (v' + r.cache_ver + ')'; showToast('Cache di-refresh', 'success'); } }
        function human(n) { if (n > 1073741824) return (n / 1073741824).toFixed(2) + ' GB'; if (n > 1048576) return (n / 1048576).toFixed(1) + ' MB'; return (n / 1024).toFixed(0) + ' KB'; }
        onMounted(reload);
        return { uploadMb, maintenance, backups, busy, msg, doBackup, toggleMaintenance, saveUpload, bustCache, human };
      },
      template: `
        <div>
          <p v-if="msg" class="notice">{{msg}}</p>
          <div class="grid2">
            <div class="panel">
              <h3>Backup database</h3>
              <p class="muted">Snapshot lengkap struktur + data. File di-gzip &amp; tersimpan aman di server.</p>
              <button @click="doBackup" :disabled="busy" data-testid="backup-now">{{busy?'Memproses…':'Backup sekarang'}}</button>
              <div class="table" style="margin-top:20px">
                <div class="tr head"><span>File</span><span>Ukuran</span><span>Tanggal</span></div>
                <div class="tr" v-for="b in backups" :key="b.id">
                  <span><a :href="'api.php?op=backup_download&file='+b.file">{{b.file}}</a></span>
                  <span>{{human(b.size_bytes)}}</span><span>{{b.created_at}}</span>
                </div>
                <p class="muted" v-if="!backups.length" style="padding:14px 0">Belum ada backup.</p>
              </div>
            </div>
            <div class="panel">
              <h3>Mode perawatan</h3>
              <p class="muted">Ketika aktif, hanya admin yang bisa mengakses. Pengunjung lain melihat halaman "Sedang dirapikan".</p>
              <label class="switch">
                <input type="checkbox" :checked="maintenance" @change="toggleMaintenance" data-testid="maintenance-toggle">
                <span class="track"><span class="knob"></span></span>
                <span>{{maintenance?'AKTIF':'Nonaktif'}}</span>
              </label>
            </div>
          </div>
          <div class="grid2">
            <div class="panel">
              <h3>Batas ukuran unggah</h3>
              <label>MB per file
                <input type="number" v-model.number="uploadMb" min="10" max="20480" data-testid="upload-limit-input">
              </label>
              <button @click="saveUpload">Simpan</button>
              <p class="muted" style="margin-top:10px">Nginx juga dikonfigurasi <code>client_max_body_size</code>; jika perlu &gt; 2 GB, hubungi kami untuk penyesuaian.</p>
            </div>
            <div class="panel">
              <h3>Cache</h3>
              <p class="muted">Paksa browser mengambil ulang CSS/JS &amp; kosongkan cache disk aplikasi.</p>
              <button @click="bustCache" data-testid="cache-bust">Refresh cache</button>
            </div>
          </div>
        </div>`
    }).mount(el);
  }

  function initRetentionTracker(csrf) {
    const wrap = document.querySelector('.player-wrap');
    if (!wrap) return;
    const player = wrap.querySelector('video');
    if (!player) return;
    const videoId = parseInt(wrap.dataset.videoId || '0', 10);
    if (!videoId) return;
    let started = false;
    player.addEventListener('play', () => {
      if (started) return; started = true;
      fetch('api.php?op=event', { method: 'POST', credentials: 'same-origin', body: fd({ csrf, event: 'video_start', path: location.pathname + location.search, video_id: videoId }) });
    });
    let last = 0;
    setInterval(() => {
      if (player.paused || player.ended) return;
      const t = Math.floor(player.currentTime);
      if (t - last >= 10) {
        last = t;
        fetch('api.php?op=event', { method: 'POST', credentials: 'same-origin', body: fd({ csrf, event: 'video_progress', path: location.pathname + location.search, video_id: videoId, progress: t }) });
      }
    }, 5000);
    player.addEventListener('ended', () => {
      fetch('api.php?op=event', { method: 'POST', credentials: 'same-origin', body: fd({ csrf, event: 'video_complete', path: location.pathname + location.search, video_id: videoId, progress: Math.floor(player.duration || 0) }) });
    });
    // Engagement heatmap: track which seconds are watched
    const heatmapBatch = [];
    let lastHmSecond = -1;
    setInterval(() => {
      if (player.paused || player.ended) return;
      const sec = Math.floor(player.currentTime);
      if (sec !== lastHmSecond) {
        lastHmSecond = sec;
        heatmapBatch.push(sec);
        if (heatmapBatch.length >= 15) {
          const batch = heatmapBatch.splice(0, 15);
          fetch('api.php?op=heatmap', { method: 'POST', credentials: 'same-origin', body: fd({ csrf, video_id: videoId, seconds: batch.join(',') }) });
        }
      }
    }, 1000);
    function flushHeatmap() {
      if (heatmapBatch.length > 0) {
        fetch('api.php?op=heatmap', { method: 'POST', credentials: 'same-origin', body: fd({ csrf, video_id: videoId, seconds: heatmapBatch.join(',') }) });
        heatmapBatch.length = 0;
      }
    }
    player.addEventListener('ended', flushHeatmap);
    window.addEventListener('beforeunload', flushHeatmap);
  }

  function initMidtransPurchase(csrf) {
    const box = document.getElementById('token-purchase');
    const button = document.getElementById('buy-token');
    if (!box || !button) return;
    const name = document.getElementById('buyer-name');
    const contact = document.getElementById('buyer-contact');
    const status = document.getElementById('purchase-status');
    const show = (message, error = false) => { status.textContent = message; status.classList.toggle('error', error); };
    const checkStatus = async (orderId, secret) => {
      const result = await api('payment_status&order_id=' + encodeURIComponent(orderId) + '&access_secret=' + encodeURIComponent(secret));
      if (result.status === 'settlement' && result.token) { show('Pembayaran berhasil. Token Anda: ' + result.token); return true; }
      show(result.status === 'pending' ? 'Pembayaran masih menunggu konfirmasi Midtrans.' : 'Status pembayaran: ' + result.status, result.status !== 'pending');
      return false;
    };
    button.addEventListener('click', async () => {
      if (!name.value.trim() || !contact.value.trim()) { show('Isi nama dan kontak terlebih dahulu.', true); return; }
      button.disabled = true; show('Membuat transaksi aman…');
      try {
        const checkout = await api('midtrans_checkout', 'POST', fd({ csrf, name: name.value.trim(), contact: contact.value.trim() }));
        if (!window.snap) throw new Error('Form pembayaran Midtrans belum siap. Muat ulang halaman.');
        window.snap.pay(checkout.snap_token, {
          onSuccess: () => { show('Pembayaran diterima. Memverifikasi token…'); setTimeout(() => checkStatus(checkout.order_id, checkout.access_secret), 1500); },
          onPending: () => show('Pembayaran dibuat. Selesaikan instruksi pembayaran di Midtrans.'),
          onError: () => show('Pembayaran gagal atau ditolak.', true),
          onClose: () => show('Jendela pembayaran ditutup. Order masih dapat diselesaikan bila belum kedaluwarsa.')
        });
      } catch (e) { show(e.message || 'Checkout gagal.', true); }
      finally { button.disabled = false; }
    });
  }

  async function loadPaymentOrders() {
    const el = document.getElementById('payments-orders'); if (!el) return;
    try {
      const data = await api('midtrans_orders');
      if (!data.orders.length) { el.textContent = 'Belum ada order.'; return; }
      el.innerHTML = '<div class="payment-order-list">' + data.orders.map((o) =>
        '<div><strong>' + esc(o.order_id) + '</strong><span>' + esc(o.buyer_name) + ' · Rp' + Number(o.amount).toLocaleString('id-ID') + ' · ' + esc(o.status) + (o.token ? ' · token: <code>' + esc(o.token) + '</code>' : '') + '</span></div>'
      ).join('') + '</div>';
    } catch (e) { el.textContent = 'Gagal memuat order: ' + e.message; }
  }
  function esc(value) { const d = document.createElement('div'); d.textContent = String(value || ''); return d.innerHTML; }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();

  // ─────────────────────────────────────────────────────────
  // initTokenModal — vanilla JS, modal token gate di watch page
  // With focus trap for accessibility
  // ─────────────────────────────────────────────────────────
  function initTokenModal() {
    const modal   = document.getElementById('token-modal');
    const openBtn = document.getElementById('open-token-modal');
    const closeBtn = document.getElementById('close-token-modal');
    const input   = document.getElementById('token-input');
    if (!modal) return;

    let previousFocus = null;

    function getFocusableElements() {
      return modal.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
    }

    function openModal() {
      previousFocus = document.activeElement;
      modal.classList.add('active');
      if (input) setTimeout(() => input.focus(), 80);
      // Trap focus
      document.addEventListener('keydown', trapFocusHandler);
    }
    function closeModal() {
      modal.classList.remove('active');
      document.removeEventListener('keydown', trapFocusHandler);
      if (previousFocus) previousFocus.focus();
    }

    function trapFocusHandler(e) {
      if (e.key === 'Escape') { closeModal(); return; }
      if (e.key !== 'Tab') return;
      const focusable = getFocusableElements();
      if (!focusable.length) return;
      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      if (e.shiftKey) {
        if (document.activeElement === first) { e.preventDefault(); last.focus(); }
      } else {
        if (document.activeElement === last) { e.preventDefault(); first.focus(); }
      }
    }

    // Auto-open if token_err or error from PHP
    const params = new URLSearchParams(location.search);
    if (params.get('token_err')) openModal();

    if (openBtn)  openBtn.addEventListener('click', openModal);
    if (closeBtn) closeBtn.addEventListener('click', closeModal);

    // Close on backdrop click
    modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });

    // Format input: uppercase + auto-dash XXXX-XXXX-XXXX
    if (input) {
      input.addEventListener('input', function () {
        const raw = this.value.replace(/[^A-Za-z0-9]/g, '').toUpperCase().slice(0, 12);
        let out = '';
        for (let i = 0; i < raw.length; i++) {
          if (i === 4 || i === 8) out += '-';
          out += raw[i];
        }
        this.value = out;
      });
    }
  }

  // ─────────────────────────────────────────────────────────
  // initTokenManager — Vue 3 component untuk admin panel tab Akses Token
  // With token expiry support
  // ─────────────────────────────────────────────────────────
  function initTokenManager() {
    const el = document.getElementById('tokens-mount');
    if (!el || !window.Vue) return;

    const { createApp, ref, reactive, onMounted } = Vue;

    createApp({
      setup() {
        const tokens      = ref([]);
        const loading     = ref(false);
        const globalErr   = ref('');
        const showCreate  = ref(false);
        const editingToken = ref(null);

        const form = reactive({ label: '', contact_type: 'telegram', contact_value: '' });
        const editForm = reactive({ id: 0, label: '', contact_type: 'telegram', contact_value: '' });

        const contactIcons  = { telegram: '✈️', whatsapp: '📱', facebook: '👤' };
        const contactPlaceholders = {
          telegram: '@username atau +62xxx',
          whatsapp: '+62812xxxxxxxx',
          facebook: 'URL profil / nama',
        };

        async function loadTokens() {
          loading.value = true; globalErr.value = '';
          try {
            const d = await api('token_list');
            tokens.value = d.tokens || [];
          } catch (e) { globalErr.value = e.message; }
          finally { loading.value = false; }
        }

        async function createToken() {
          if (!form.label.trim() || !form.contact_value.trim()) return;
          globalErr.value = '';
          try {
            const state = await api('state');
            const body  = fd({ csrf: state.csrf, label: form.label, contact_type: form.contact_type, contact_value: form.contact_value });
            const d = await api('token_create', 'POST', body);
            tokens.value.unshift(d.token);
            form.label = ''; form.contact_value = ''; form.contact_type = 'telegram';
            showCreate.value = false;
          } catch (e) { globalErr.value = e.message; }
        }

        async function toggleToken(tok) {
          globalErr.value = '';
          try {
            const state = await api('state');
            const body  = fd({ csrf: state.csrf, id: tok.id });
            const d = await api('token_toggle', 'POST', body);
            tok.status = d.status;
          } catch (e) { globalErr.value = e.message; }
        }

        function startEdit(tok) {
          editForm.id            = tok.id;
          editForm.label         = tok.label;
          editForm.contact_type  = tok.contact_type;
          editForm.contact_value = tok.contact_value;
          editingToken.value     = tok;
        }

        async function saveEdit() {
          globalErr.value = '';
          try {
            const state = await api('state');
            const body  = fd({ csrf: state.csrf, id: editForm.id, label: editForm.label, contact_type: editForm.contact_type, contact_value: editForm.contact_value });
            await api('token_edit', 'POST', body);
            const tok = tokens.value.find(t => t.id === editForm.id);
            if (tok) {
              tok.label         = editForm.label;
              tok.contact_type  = editForm.contact_type;
              tok.contact_value = editForm.contact_value;
            }
            editingToken.value = null;
          } catch (e) { globalErr.value = e.message; }
        }

        async function deleteToken(tok) {
          if (!confirm(`Hapus token "${tok.label}"?\nAksi ini tidak bisa dibatalkan.`)) return;
          globalErr.value = '';
          try {
            const state = await api('state');
            const body  = fd({ csrf: state.csrf, id: tok.id });
            await api('token_delete', 'POST', body);
            tokens.value = tokens.value.filter(t => t.id !== tok.id);
          } catch (e) { globalErr.value = e.message; }
        }

        function copyToken(tokenStr) {
          navigator.clipboard.writeText(tokenStr)
            .then(() => { showToast('Token disalin ke clipboard', 'success'); })
            .catch(() => { showToast('Token: ' + tokenStr, 'info', 8000); });
        }

        function fmtDate(d) {
          if (!d) return '—';
          return new Date(d).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
        }

        function tokenExpiry(expiresAt) {
          if (!expiresAt) return 'Tanpa batas';
          const diff = new Date(expiresAt) - new Date();
          if (diff <= 0) return 'Expired';
          const days = Math.floor(diff / 86400000);
          if (days > 0) return days + ' hari lagi';
          const hours = Math.floor(diff / 3600000);
          if (hours > 0) return hours + ' jam lagi';
          const mins = Math.floor(diff / 60000);
          return mins + ' menit lagi';
        }

        onMounted(loadTokens);

        return {
          tokens, loading, globalErr, showCreate, editingToken,
          form, editForm, contactIcons, contactPlaceholders,
          createToken, toggleToken, startEdit, saveEdit, deleteToken, copyToken, fmtDate, tokenExpiry,
        };
      },

      template: `
        <div class="panel">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;gap:12px;flex-wrap:wrap">
            <h3 style="margin:0"><span class="material-symbols-rounded">vpn_key</span> Manajemen Token Akses</h3>
            <button class="button" @click="showCreate=!showCreate">
              <span class="material-symbols-rounded">add</span> Buat Token Baru
            </button>
          </div>

          <p v-if="globalErr" class="notice err" style="margin-bottom:16px">⚠️ {{ globalErr }}</p>

          <!-- Form buat token baru -->
          <div v-if="showCreate" class="panel" style="background:var(--surface2,#1a1c1f);margin-bottom:20px">
            <h4 style="margin-top:0"><span class="material-symbols-rounded">add_circle</span> Token Baru</h4>
            <div class="grid2">
              <label>Label / Nama Penerima
                <input v-model="form.label" placeholder="Contoh: Member VIP — Budi" maxlength="120">
              </label>
              <label>Platform Kontak
                <select v-model="form.contact_type">
                  <option value="telegram">✈️ Telegram</option>
                  <option value="whatsapp">📱 WhatsApp</option>
                  <option value="facebook">👤 Facebook</option>
                </select>
              </label>
            </div>
            <label>Kontak (username / nomor / link profil)
              <input v-model="form.contact_value" :placeholder="contactPlaceholders[form.contact_type]" maxlength="200">
            </label>
            <div style="display:flex;gap:10px;margin-top:10px;flex-wrap:wrap">
              <button class="button" @click="createToken" :disabled="!form.label.trim()||!form.contact_value.trim()">
                <span class="material-symbols-rounded">auto_awesome</span> Generate &amp; Simpan Token
              </button>
              <button class="button ghost" @click="showCreate=false">Batal</button>
            </div>
          </div>

          <!-- Modal edit token -->
          <div v-if="editingToken" class="token-modal-overlay active" @click.self="editingToken=null">
            <div class="token-modal-card">
              <button class="token-modal-close" type="button" @click="editingToken=null">&times;</button>
              <div class="token-modal-icon"><span class="material-symbols-rounded">edit</span></div>
              <h2>Edit Token</h2>
              <p class="muted" style="font-family:monospace;font-size:13px;text-align:center;margin-bottom:18px;letter-spacing:2px">{{ editingToken.token }}</p>
              <label>Label / Nama Penerima
                <input v-model="editForm.label" maxlength="120">
              </label>
              <label>Platform Kontak
                <select v-model="editForm.contact_type">
                  <option value="telegram">✈️ Telegram</option>
                  <option value="whatsapp">📱 WhatsApp</option>
                  <option value="facebook">👤 Facebook</option>
                </select>
              </label>
              <label>Kontak
                <input v-model="editForm.contact_value" maxlength="200">
              </label>
              <div style="display:flex;gap:10px;margin-top:18px">
                <button class="button" @click="saveEdit"><span class="material-symbols-rounded">save</span> Simpan</button>
                <button class="button ghost" @click="editingToken=null">Batal</button>
              </div>
            </div>
          </div>

          <!-- Tabel token -->
          <div v-if="loading" class="muted" style="padding:28px 0;text-align:center">
            <span class="material-symbols-rounded" style="font-size:28px;opacity:.4">hourglass_empty</span><br>Memuat token…
          </div>
          <div v-else-if="tokens.length===0" class="muted" style="padding:28px 0;text-align:center">
            <span class="material-symbols-rounded" style="font-size:40px;opacity:.25">vpn_key_off</span>
            <p style="margin:10px 0 0">Belum ada token. Klik <b>Buat Token Baru</b> di atas.</p>
          </div>
          <div v-else class="table">
            <div class="tr head">
              <span>Label</span>
              <span>Token</span>
              <span>Kontak</span>
              <span style="text-align:center">Pakai</span>
              <span>Kadaluarsa</span>
              <span style="text-align:center">Status</span>
              <span style="text-align:right">Aksi</span>
            </div>
            <div class="tr" v-for="tok in tokens" :key="tok.id">
              <span style="font-weight:500;word-break:break-word">{{ tok.label }}</span>
              <span style="white-space:nowrap">
                <code style="font-size:12px;letter-spacing:2px">{{ tok.token }}</code>
                <button class="ghost small" style="margin-left:6px;padding:2px 7px;font-size:11px" @click="copyToken(tok.token)" title="Salin token">⎘</button>
              </span>
              <span style="word-break:break-word">
                {{ contactIcons[tok.contact_type] || '📋' }} {{ tok.contact_value }}
              </span>
              <span style="text-align:center">{{ tok.use_count }}×</span>
              <span style="font-size:12px;color:var(--fg-muted,#888)" :style="tok.expires_at && new Date(tok.expires_at) < new Date() ? 'color:#e07373' : ''">
                {{ tokenExpiry(tok.expires_at) }}
              </span>
              <span style="text-align:center">
                <span :class="tok.status==='active' ? 'status ready' : 'status processing'">
                  {{ tok.status==='active' ? 'Aktif' : 'Suspend' }}
                </span>
              </span>
              <span style="display:flex;gap:6px;justify-content:flex-end;flex-wrap:wrap">
                <button class="ghost small" @click="startEdit(tok)" title="Edit label / kontak">✏️</button>
                <button class="ghost small" @click="toggleToken(tok)"
                  :title="tok.status==='active' ? 'Suspend token' : 'Aktifkan token'"
                  :style="tok.status==='active' ? 'color:#e8a838' : 'color:#4caf7d'">
                  {{ tok.status==='active' ? '⏸' : '▶️' }}
                </button>
                <button class="ghost small" @click="deleteToken(tok)" title="Hapus token" style="color:#e05252">🗑</button>
              </span>
            </div>
          </div>

          <p class="muted" style="margin-top:18px;font-size:12px;line-height:1.6">
            <span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle">info</span>
            Token bersifat <b>shared</b> — satu token bisa digunakan banyak pengunjung.
            Status <b>Suspend</b> akan mencegah login baru dengan token tersebut secara langsung.
            Dibuat: {{ tokens.length }} token terdaftar.
          </p>
        </div>
      `,
    }).mount(el);
  }

  // ─────────────────────────────────────────────────────────
  // initVideoLibrary — Vue 3 component: grid 8×8, search, pagination, edit modal
  // ─────────────────────────────────────────────────────────
  function initVideoLibrary() {
    const el = document.getElementById('library-mount');
    if (!el || !window.Vue) return;

    const { createApp, ref, reactive, computed, watch, onMounted, onUnmounted } = Vue;

    createApp({
      setup() {
        const videos     = ref([]);
        const categories = ref([]);
        const loading    = ref(false);
        const globalErr  = ref('');
        const search     = ref('');
        const page       = ref(1);
        const totalPages = ref(1);
        const totalItems = ref(0);
        const perPage    = 64;
        const csrfToken  = ref('');

        // Edit modal
        const editing    = ref(null);
        const editForm   = reactive({ id: 0, title: '', category_id: 0 });
        const saving     = ref(false);

        let debounceTimer = null;

        async function loadVideos() {
          loading.value = true; globalErr.value = '';
          try {
            const params = new URLSearchParams({ page: page.value, per_page: perPage });
            if (search.value.trim()) params.set('search', search.value.trim());
            const d = await api('video_library&' + params.toString());
            videos.value     = d.videos || [];
            totalItems.value = d.total || 0;
            totalPages.value = d.pages || 1;
            page.value       = d.page || 1;
          } catch (e) { globalErr.value = e.message; }
          finally { loading.value = false; }
        }

        async function loadCategories() {
          try {
            const d = await api('categories_list');
            categories.value = d.categories || [];
          } catch (e) { /* ignore */ }
        }

        function onSearch() {
          clearTimeout(debounceTimer);
          debounceTimer = setTimeout(() => {
            page.value = 1;
            loadVideos();
          }, 350);
        }

        function goToPage(p) {
          if (p < 1 || p > totalPages.value) return;
          page.value = p;
          loadVideos();
        }

        function startEdit(vid) {
          editForm.id          = vid.id;
          editForm.title       = vid.title;
          editForm.category_id = vid.category_id || 0;
          editing.value        = vid;
        }

        async function saveEdit() {
          if (!editForm.title.trim()) return;
          saving.value = true; globalErr.value = '';
          try {
            const state = await api('state');
            const body  = fd({
              csrf: state.csrf,
              id: editForm.id,
              title: editForm.title.trim(),
              category_id: editForm.category_id,
            });
            await api('video_update', 'POST', body);
            // Update local state
            const vid = videos.value.find(v => v.id === editForm.id);
            if (vid) {
              vid.title       = editForm.title.trim();
              vid.category_id = editForm.category_id;
              const cat = categories.value.find(c => c.id === editForm.category_id);
              vid.category = cat ? cat.name : '—';
            }
            editing.value = null;
            showToast('Video berhasil diperbarui', 'success');
          } catch (e) { globalErr.value = e.message; }
          finally { saving.value = false; }
        }

        function fmtSize(bytes) {
          return (bytes / 1048576).toFixed(1) + ' MB';
        }

        function fmtDate(d) {
          if (!d) return '—';
          return new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
        }

        // Visible page numbers (max 7 buttons)
        const visiblePages = computed(() => {
          const tp = totalPages.value;
          const cp = page.value;
          let start = Math.max(1, cp - 3);
          const end   = Math.min(tp, start + 6);
          start = Math.max(1, end - 6);
          const pages = [];
          for (let i = start; i <= end; i++) pages.push(i);
          return pages;
        });

        onMounted(async () => {
          try { const s = await api('state'); csrfToken.value = s.csrf; } catch (e) { /* ignore */ }
          loadVideos(); loadCategories();
        });
        onUnmounted(() => clearTimeout(debounceTimer));

        return {
          videos, categories, loading, globalErr, search, page, totalPages, totalItems,
          csrfToken, editing, editForm, saving, visiblePages,
          onSearch, goToPage, startEdit, saveEdit, fmtSize, fmtDate,
        };
      },

      template: `
        <div>
          <!-- Header -->
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;gap:12px;flex-wrap:wrap">
            <h3 style="margin:0"><span class="material-symbols-rounded">video_library</span> Perpustakaan Video</h3>
            <span class="muted" style="font-size:13px">{{ totalItems }} video · Halaman {{ page }} / {{ totalPages }}</span>
          </div>

          <p v-if="globalErr" class="notice err" style="margin-bottom:16px">⚠️ {{ globalErr }}</p>

          <!-- Search bar -->
          <div style="margin-bottom:20px;position:relative">
            <span class="material-symbols-rounded" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);font-size:20px;color:var(--fg-muted,#888);pointer-events:none">search</span>
            <input v-model="search" @input="onSearch"
              placeholder="Cari berdasarkan judul atau kategori…"
              style="width:100%;padding:12px 16px 12px 44px;border-radius:12px;border:1px solid var(--border,#333);background:var(--surface,#16181a);color:var(--fg,#e8dfcf);font-size:15px;outline:none;box-sizing:border-box"
              data-testid="library-search">
          </div>

          <!-- Loading -->
          <div v-if="loading" style="text-align:center;padding:48px 0">
            <span class="material-symbols-rounded" style="font-size:32px;opacity:.3">hourglass_empty</span>
            <p class="muted">Memuat video…</p>
          </div>

          <!-- Empty state -->
          <div v-else-if="videos.length===0" style="text-align:center;padding:48px 0">
            <span class="material-symbols-rounded" style="font-size:48px;opacity:.2">videocam_off</span>
            <p class="muted" style="margin-top:8px">{{ search ? 'Tidak ditemukan video yang cocok.' : 'Belum ada video. Unggah di tab Konten.' }}</p>
          </div>

          <!-- Grid 8 columns -->
          <div v-else style="display:grid;grid-template-columns:repeat(8,1fr);gap:12px">
            <div v-for="vid in videos" :key="vid.id" class="library-card"
              style="background:var(--surface,#16181a);border:1px solid var(--border,#333);border-radius:10px;overflow:hidden;cursor:pointer;transition:border-color .15s,box-shadow .15s;display:flex;flex-direction:column"
              @mouseenter="$event.currentTarget.style.borderColor='var(--accent,#d96b45)';$event.currentTarget.style.boxShadow='0 0 0 1px var(--accent,#d96b45)'"
              @mouseleave="$event.currentTarget.style.borderColor='var(--border,#333)';$event.currentTarget.style.boxShadow='none'">

              <!-- Thumbnail -->
              <a :href="'?page=watch&id=' + vid.id" style="display:block;aspect-ratio:16/9;background:var(--surface2,#1a1c1f);position:relative;overflow:hidden">
                <img v-if="vid.poster_url" :src="vid.poster_url" :alt="vid.title"
                  style="width:100%;height:100%;object-fit:cover;display:block" loading="lazy">
                <div v-else style="display:flex;align-items:center;justify-content:center;height:100%;font-size:28px;font-weight:600;color:var(--fg-muted,#555);opacity:.4">
                  {{ String(vid.id).padStart(2,'0') }}
                </div>
                <span style="position:absolute;bottom:4px;right:4px;background:rgba(0,0,0,.7);color:#fff;font-size:10px;padding:2px 6px;border-radius:4px;backdrop-filter:blur(4px)">
                  {{ Math.floor(vid.duration_sec/60) }}:{{ String(vid.duration_sec%60).padStart(2,'0') }}
                </span>
              </a>

              <!-- Info -->
              <div style="padding:8px 10px;flex:1;min-height:0;display:flex;flex-direction:column;gap:4px">
                <a :href="'?page=watch&id=' + vid.id" :title="vid.title"
                  style="color:var(--fg,#e8dfcf);font-size:12px;font-weight:500;line-height:1.3;text-decoration:none;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;word-break:break-word">
                  {{ vid.title }}
                </a>
                <span style="font-size:11px;color:var(--fg-muted,#888)">{{ vid.category || '—' }}</span>
              </div>

              <!-- Actions -->
              <div style="padding:0 10px 8px;display:flex;gap:4px;justify-content:flex-end">
                <button class="ghost small" @click="startEdit(vid)" title="Edit metadata" style="font-size:12px;padding:3px 8px">
                  ✏️ Edit
                </button>
                <form method="post" action="?page=delete-video" @submit.prevent="if(confirm('Hapus video ini beserta file?')) $event.target.submit();"
                  style="margin:0;display:inline">
                  <input type="hidden" name="csrf" :value="csrfToken">
                  <input type="hidden" name="id" :value="vid.id">
                  <button class="ghost small" title="Hapus" style="font-size:12px;padding:3px 8px;color:#e05252">🗑</button>
                </form>
              </div>
            </div>
          </div>

          <!-- Pagination -->
          <div v-if="totalPages > 1" style="display:flex;align-items:center;justify-content:center;gap:6px;margin-top:24px;flex-wrap:wrap">
            <button class="ghost small" @click="goToPage(page-1)" :disabled="page<=1" style="padding:6px 12px">← Prev</button>
            <button v-for="p in visiblePages" :key="p"
              :class="['ghost small', p===page ? 'active' : '']"
              @click="goToPage(p)"
              style="min-width:36px;padding:6px 10px;text-align:center">
              {{ p }}
            </button>
            <button class="ghost small" @click="goToPage(page+1)" :disabled="page>=totalPages" style="padding:6px 12px">Next →</button>
          </div>

          <!-- Edit modal -->
          <div v-if="editing" class="token-modal-overlay active" @click.self="editing=null">
            <div class="token-modal-card" style="max-width:520px">
              <button class="token-modal-close" type="button" @click="editing=null">&times;</button>
              <div class="token-modal-icon"><span class="material-symbols-rounded">edit</span></div>
              <h2>Edit Video</h2>

              <label style="margin-top:16px;display:block;text-align:left">
                <span style="font-size:13px;color:var(--fg-muted,#aaa)">Judul Video</span>
                <input v-model="editForm.title" maxlength="255" required
                  style="width:100%;margin-top:4px;padding:10px 14px;border-radius:8px;border:1px solid var(--border,#333);background:var(--surface,#16181a);color:var(--fg,#e8dfcf);font-size:15px;box-sizing:border-box">
              </label>

              <label style="margin-top:14px;display:block;text-align:left">
                <span style="font-size:13px;color:var(--fg-muted,#aaa)">Kategori</span>
                <select v-model.number="editForm.category_id"
                  style="width:100%;margin-top:4px;padding:10px 14px;border-radius:8px;border:1px solid var(--border,#333);background:var(--surface,#16181a);color:var(--fg,#e8dfcf);font-size:15px;box-sizing:border-box">
                  <option :value="0">Tanpa kategori</option>
                  <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                </select>
              </label>

              <p class="muted" style="margin-top:14px;font-size:12px;text-align:left">
                Slug: <code style="font-size:11px">{{ editing.slug }}</code> ·
                Ukuran: {{ fmtSize(editing.size_bytes) }} ·
                {{ fmtDate(editing.created_at) }}
              </p>

              <div style="display:flex;gap:10px;margin-top:20px;justify-content:flex-end">
                <button class="button" @click="saveEdit" :disabled="saving||!editForm.title.trim()">
                  <span class="material-symbols-rounded">save</span> {{ saving ? 'Menyimpan…' : 'Simpan' }}
                </button>
                <button class="button ghost" @click="editing=null">Batal</button>
              </div>
            </div>
          </div>
        </div>
      `,
    }).mount(el);
  }

})();
