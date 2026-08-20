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
    let data = null;
    try { data = await r.json(); } catch (e) { data = null; }
    if (!r.ok) {
      const msg = (data && (data.error || data.message)) || (op + ' ' + r.status);
      const err = new Error(msg); err.data = data; err.status = r.status;
      throw err;
    }
    return data;
  }
  const fd = (obj) => { const f = new FormData(); Object.entries(obj).forEach(([k, v]) => f.append(k, v)); return f; };

  // ---------- Boot Vue enhancements ----------
  async function boot() {
    // Core navigation and access UI must not depend on third-party CDN scripts.
    // This keeps the token modal and admin tabs usable if Vue fails to load.
    initTabs();
    initBurger();
    initUploadProgress();
    initTokenModal();
    initPlyr();

    const state = await api('state').catch(() => ({ csrf: '', theme: 'obsidian', site: 'Arsip Layar', admin: false }));
    document.documentElement.dataset.theme = state.theme || 'obsidian';

    try {
      await fetch('api.php?op=event', {
        method: 'POST', credentials: 'same-origin', body: fd({
          csrf: state.csrf, event: 'page_view', path: location.pathname + location.search,
          device: /Mobi/i.test(navigator.userAgent) ? 'mobile' : 'desktop',
          browser: navigator.userAgent.slice(0, 80)
        })
      });
    } catch (e) {}

    initRetentionTracker(state.csrf);
    initMidtransPurchase(state.csrf);
    if (state.admin && location.search.includes('page=admin')) loadPaymentOrders();

    // The payment form, token modal, and tabs remain usable without Vue.
    if (!window.Vue) return;

    const showInsightFloater = state.admin && !location.search.includes('page=admin') && !location.search.includes('page=login');
    if (state.admin) mountThemeSwitcher(state);
    if (showInsightFloater) mountInsightFloater(state);

    if (location.search.includes('page=admin')) {
      mountAnalytics(state);
      mountSecurity(state);
      mountSystem(state);
      mountWatermark(state);
      mountTelegram(state);
      initTokenManager();
    }
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
            if (r.ok) { msg.value = 'Pengaturan Telegram disimpan.'; tokenInput.value = ''; await load(); }
            else err.value = r.error || 'Gagal menyimpan';
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
    // Close on nav link click (mobile)
    n.querySelectorAll('a').forEach(a => a.addEventListener('click', () => {
      b.setAttribute('aria-expanded', 'false');
      n.classList.remove('open');
    }));
    // Close on outside click
    document.addEventListener('click', (e) => {
      if (!n.classList.contains('open')) return;
      if (b.contains(e.target) || n.contains(e.target)) return;
      b.setAttribute('aria-expanded', 'false');
      n.classList.remove('open');
    });
  }

  function initPlyr() {
    const wrap = document.querySelector('.player-wrap');
    if (!wrap || !window.Plyr) return;
    const video = wrap.querySelector('video');
    const hlsSrc = wrap.dataset.hls;
    const qualityButtons = $$('[data-quality]', wrap.parentElement);
    const markQuality = (quality) => qualityButtons.forEach((button) => button.classList.toggle('active', Number(button.dataset.quality) === quality));

    const createPlayer = (qualityOptions) => new Plyr(video, {
      controls: ['play-large', 'restart', 'rewind', 'play', 'fast-forward', 'progress', 'current-time', 'duration', 'mute', 'volume', 'captions', 'settings', 'pip', 'airplay', 'fullscreen'],
      settings: ['captions', 'quality', 'speed', 'loop'],
      quality: { default: 0, options: qualityOptions },
      iconUrl: 'plyr.svg',
      blankVideo: 'https://cdn.jsdelivr.net/npm/plyr@3.7.8/dist/blank.mp4',
      speed: { selected: 1, options: [0.5, 0.75, 1, 1.25, 1.5, 1.75, 2] },
      keyboard: { focused: true, global: true },
      tooltips: { controls: true, seek: true },
      seekTime: 10,
      ratio: '16:9',
      storage: { enabled: true, key: 'arsip-plyr' },
      i18n: { play: 'Putar', pause: 'Jeda', mute: 'Bisukan', unmute: 'Suarakan', enableCaptions: 'Aktifkan takarir', disableCaptions: 'Matikan takarir', enterFullscreen: 'Layar penuh', exitFullscreen: 'Keluar layar penuh', settings: 'Pengaturan', speed: 'Kecepatan', normal: 'Normal', quality: 'Kualitas', pip: 'Picture in Picture', qualityBadge: 'Resolusi', loop: 'Ulangi' }
    });

    if (hlsSrc && !video.canPlayType('application/vnd.apple.mpegurl') && window.Hls && window.Hls.isSupported()) {
      const hls = new Hls({ enableWorker: true, lowLatencyMode: false, capLevelToPlayerSize: true, backBufferLength: 90 });
      hls.loadSource(hlsSrc);
      hls.attachMedia(video);
      hls.on(Hls.Events.MANIFEST_PARSED, () => {
        const heights = [...new Set(hls.levels.map(level => Number(level.height)).filter(Boolean))].sort((a, b) => b - a);
        const player = createPlayer([0, ...heights]);
        player.on('qualitychange', (event) => {
          const quality = Number(event.detail && event.detail.quality);
          hls.currentLevel = quality === 0 ? -1 : hls.levels.findIndex(level => Number(level.height) === quality);
          markQuality(quality);
        });
        qualityButtons.forEach((button) => button.addEventListener('click', () => {
          const quality = Number(button.dataset.quality);
          hls.currentLevel = quality === 0 ? -1 : hls.levels.findIndex(level => Number(level.height) === quality);
          markQuality(quality);
        }));
        window.__player = player;
      });
      hls.on(Hls.Events.ERROR, (_event, data) => {
        if (data && data.fatal) console.warn('HLS playback error', data.type);
      });
      window.__hls = hls;
    } else {
      window.__player = createPlayer([0]);
      // Safari/native HLS: load the selected rendition directly.
      qualityButtons.forEach((button) => button.addEventListener('click', () => {
        const quality = Number(button.dataset.quality);
        const src = quality === 720 ? wrap.dataset.hls720 : quality === 360 ? wrap.dataset.hls360 : hlsSrc;
        if (src) { video.src = src; video.load(); video.play().catch(() => {}); markQuality(quality); }
      }));
    }
  }

  function initUploadProgress() {
    const form = document.getElementById('upload-form'); if (!form) return;
    const bar = document.getElementById('upload-progress');
    const fill = bar.querySelector('.up-fill');
    const pct = bar.querySelector('.up-pct');
    const bytes = bar.querySelector('.up-bytes');
    const submit = form.querySelector('button[type=submit]');

    form.addEventListener('submit', (e) => {
      const fileEl = form.querySelector('input[type=file]');
      const file = fileEl && fileEl.files && fileEl.files[0];
      if (!file) return;
      e.preventDefault();
      bar.classList.remove('hidden');
      submit.disabled = true;
      submit.textContent = 'Mengunggah…';

      const xhr = new XMLHttpRequest();
      xhr.open('POST', form.action, true);
      xhr.upload.onprogress = (ev) => {
        if (!ev.lengthComputable) return;
        const p = (ev.loaded / ev.total) * 100;
        fill.style.width = p.toFixed(1) + '%';
        pct.textContent = p.toFixed(1) + '%';
        bytes.textContent = (ev.loaded / 1048576).toFixed(1) + ' / ' + (ev.total / 1048576).toFixed(1) + ' MB';
      };
      xhr.onload = () => {
        if (xhr.status >= 200 && xhr.status < 400) {
          submit.textContent = 'Selesai · memproses HLS';
          setTimeout(() => location.href = '?page=admin&uploaded=1', 800);
        } else {
          submit.disabled = false; submit.textContent = 'Unggah ulang'; alert('Upload gagal: ' + xhr.status);
        }
      };
      xhr.onerror = () => { submit.disabled = false; submit.textContent = 'Coba lagi'; alert('Koneksi terputus.'); };
      xhr.send(new FormData(form));
    });
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
            if (r.ok) msg.value = 'Watermark disimpan. Video baru & yang ditonton berikutnya akan menampilkan teks ini.';
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

  function mountThemeSwitcher(state) {
    const root = document.createElement('div');
    document.body.appendChild(root);
    const { createApp, ref } = Vue;
    createApp({
      setup() {
        const theme = ref(state.theme);
        const csrf = ref(state.csrf);
        const panel = ref(false);
        const saving = ref(false);
        const labels = { ivory: 'Ivory Dispatch', obsidian: 'Obsidian Atelier', emerald: 'Emerald Cinema' };
        async function choose(t) {
          saving.value = true;
          try {
            const r = await api('theme', 'POST', fd({ csrf: csrf.value, theme: t }));
            if (r.ok) { theme.value = t; document.documentElement.dataset.theme = t; }
          } finally { saving.value = false; }
        }
        return { theme, labels, panel, saving, choose };
      },
      template: `
        <div class="vue-atelier">
          <button class="atelier-trigger" @click="panel=!panel" data-testid="theme-switcher-button" aria-label="Pilih tema">Tema</button>
          <div v-if="panel" class="atelier-popover" data-testid="theme-switcher-panel">
            <div class="head">Direction · Theme</div>
            <button v-for="(label,key) in labels" :key="key" @click="choose(key)"
                    :class="{selected:theme===key}" :data-testid="'theme-option-'+key">
              <span class="dot" :class="key"></span>{{label}}
            </button>
            <small class="muted" v-if="saving">Menyimpan…</small>
          </div>
        </div>`
    }).mount(root);
  }

  function mountInsightFloater(state) {
    const root = document.createElement('div'); document.body.appendChild(root);
    const { createApp, ref, onMounted } = Vue;
    createApp({
      setup() {
        const d = ref(null);
        onMounted(async () => { try { d.value = await api('insights&days=30'); } catch (e) {} });
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
    const { createApp, ref, onMounted } = Vue;
    createApp({
      setup() {
        const d = ref(null); const days = ref(30); const loading = ref(false);
        async function load() {
          loading.value = true;
          try { d.value = await api('insights&days=' + days.value); } finally { loading.value = false; }
        }
        onMounted(load);
        const days_list = [7, 30, 90];
        const dow_labels = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
        function heatLevel(val, max) { if (!max || !val) return 0; const p = val / max; if (p >= 0.75) return 4; if (p >= 0.5) return 3; if (p >= 0.25) return 2; return 1; }
        function heatmapMax(h) { let m = 0; for (const row of h || []) for (const c of row) if (c > m) m = c; return m; }
        function retentionPct(r) { if (!r.duration_sec || !r.avg_sec) return 0; return Math.min(100, Math.round((r.avg_sec / r.duration_sec) * 100)); }
        return { d, days, days_list, loading, load, dow_labels, heatLevel, heatmapMax, retentionPct };
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
          <div v-if="loading" class="muted" style="padding:20px">Memuat…</div>
          <div v-if="d">
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
                    <div style="color:var(--ink)">{{r.title}}</div>
                    <div class="track"><div class="fill" :style="{width: retentionPct(r)+'%'}"></div></div>
                  </div>
                  <div class="num">{{retentionPct(r)}}% · {{r.samples||0}} sampel</div>
                </div>
              </div>
              <p class="muted" v-else>Retention akan tampil setelah ada penonton.</p>
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
            totpOn.value = true; setup.value = null; codeInput.value = ''; msg.value = '2FA berhasil diaktifkan.';
          } catch (e) { err.value = 'Kode salah atau kedaluwarsa.'; }
        }
        async function disable2FA() {
          if (!confirm('Nonaktifkan 2FA?')) return;
          await api('2fa_disable', 'POST', fd({ csrf: state.csrf }));
          totpOn.value = false; msg.value = '2FA dinonaktifkan.';
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
        async function doBackup() { busy.value = true; msg.value = ''; try { const r = await api('backup', 'POST', fd({ csrf: state.csrf })); if (r.ok) { msg.value = 'Backup dibuat: ' + r.file; await reload(); } else msg.value = 'Gagal: ' + (r.error || ''); } finally { busy.value = false; } }
        async function toggleMaintenance() { maintenance.value = !maintenance.value; await api('maintenance', 'POST', fd({ csrf: state.csrf, on: maintenance.value ? '1' : '0' })); }
        async function saveUpload() { const r = await api('upload_limit', 'POST', fd({ csrf: state.csrf, mb: uploadMb.value })); if (r.ok) msg.value = 'Batas upload disimpan: ' + r.mb + ' MB'; }
        async function bustCache() { const r = await api('cache_bust', 'POST', fd({ csrf: state.csrf })); if (r.ok) msg.value = 'Cache di-refresh (v' + r.cache_ver + ')'; }
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
  // ─────────────────────────────────────────────────────────
  function initTokenModal() {
    const modal   = document.getElementById('token-modal');
    const openBtn = document.getElementById('open-token-modal');
    const closeBtn = document.getElementById('close-token-modal');
    const input   = document.getElementById('token-input');
    if (!modal) return;

    function openModal() {
      modal.classList.add('active');
      if (input) setTimeout(() => input.focus(), 80);
    }
    function closeModal() { modal.classList.remove('active'); }

    // Auto-open jika ada query token_err atau ada error dari PHP
    const params = new URLSearchParams(location.search);
    if (params.get('token_err')) openModal();

    if (openBtn)  openBtn.addEventListener('click', openModal);
    if (closeBtn) closeBtn.addEventListener('click', closeModal);

    // Tutup saat klik backdrop
    modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });

    // Tutup dengan ESC
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && modal.classList.contains('active')) closeModal();
    });

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
            .then(() => { /* bisa tambah toast feedback di sini */ })
            .catch(() => {
              // fallback: prompt
              prompt('Salin token ini:', tokenStr);
            });
        }

        function fmtDate(d) {
          if (!d) return '—';
          return new Date(d).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
        }

        onMounted(loadTokens);

        return {
          tokens, loading, globalErr, showCreate, editingToken,
          form, editForm, contactIcons, contactPlaceholders,
          createToken, toggleToken, startEdit, saveEdit, deleteToken, copyToken, fmtDate,
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

          <!-- ── Form buat token baru ── -->
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

          <!-- ── Modal edit token ── -->
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

          <!-- ── Tabel token ── -->
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
              <span>Terakhir Digunakan</span>
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
              <span style="font-size:12px;color:var(--fg-muted,#888)">{{ fmtDate(tok.last_used_at) }}</span>
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

})();
