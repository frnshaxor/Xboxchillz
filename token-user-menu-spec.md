# Token User Menu — Spesifikasi Fitur

**Nama Fitur:** Token User Menu (User Session Info di Navigasi)
**Tanggal:** August 21, 2026
**Status:** Draft Spec
**Author:** Buffy (AI Agent)

---

## 1. Ringkasan Fitur

Menambahkan menu dropdown di tombol navigasi (burger menu) yang menampilkan informasi pemilik token kepada user yang sedang login menggunakan token akses. Informasi yang ditampilkan: nama pemilik token (label) dan tanggal pembuatan token. Menu ini juga menyediakan tombol logout untuk menghapus session token, serta opsi perpanjang/masukkan token baru jika token sudah expired.

---

## 2. Temuan Teknis (Konteks Codebase)

### 2.1 Gap Kritis — Session Tidak Menyimpan Info Token

Saat ini, alur verifikasi token hanya menyimpan status akses, bukan identitas token:

```php
// app/Services/TokenManager.php (line 40)
grant_access();  // hanya set $_SESSION['access_granted'] = true
```

```php
// app/helpers.php (line 161-163)
function has_access(): bool { return !empty($_SESSION['access_granted']); }
function grant_access(): void { $_SESSION['access_granted'] = true; }
function revoke_access(): void { unset($_SESSION['access_granted']); }
```

**Dampak:** Tidak ada token ID, label, created_at, atau info apapun yang tersimpan di session. Header tidak bisa menampilkan info token tanpa modifikasi alur ini.

### 2.2 Entry Points

| Entry Point | File | Digunakan Oleh |
|-------------|------|----------------|
| `public/index.php` | Front controller (55 baris) | Nginx (production) |
| `index.php` | Legacy monolith (997 baris) | Tidak digunakan Nginx tapi masih di repo |

**Catatan:** Modifikasi harus dilakukan di **kedua** file untuk konsistensi, meskipun root `index.php` tidak digunakan Nginx.

### 2.3 Navigasi Header Saat Ini

```php
// views/layouts/header.php
<nav class="nav" id="nav">
  <a href="."><span class="material-symbols-rounded">home</span>Beranda</a>
  <a href="?page=contact"><span class="material-symbols-rounded">support_agent</span>Kontak</a>
  <?php if (admin()): ?>
    <a href="?page=admin"><span class="material-symbols-rounded">tune</span>Panel</a>
    <a href="?page=logout"><span class="material-symbols-rounded">logout</span>Keluar</a>
  <?php else: ?>
    <a href="?page=login"><span class="material-symbols-rounded">login</span>Masuk</a>
  <?php endif; ?>
</nav>
```

**Catatan:** Belum ada branch untuk `has_access()` (token holder). Hanya ada branch untuk admin vs non-admin.

### 2.4 API State Endpoint

```php
// routes/api.php — op=state
j([
    'csrf' => csrf(),
    'site' => setting($db, 'site_name', 'Arsip Layar'),
    'admin' => admin(),
]);
```

**Catatan:** Endpoint `state` belum mengembalikan info token (access_granted, token_id, label, dll).

### 2.5 Route Revoke Access

```php
// index.php (legacy) — already exists
if ($page === 'revoke-access') {
    revoke_access();
    go('.');
}
```

Route ini sudah berfungsi dan akan digunakan oleh tombol logout token.

### 2.6 Token Data Schema

Tabel `access_tokens` memiliki kolom:
- `id` (int, PK)
- `token` (varchar, unique — format XXXX-XXXX-XXXX)
- `label` (varchar — nama pemilik, misal "Member VIP — Budi")
- `contact_type` (enum — telegram/whatsapp/facebook)
- `contact_value` (varchar)
- `status` (enum — active/suspended/expired)
- `created_by` (int — admin ID yang membuat)
- `use_count` (int)
- `last_used_at` (datetime)
- `expires_at` (datetime — default 30 hari)
- `created_at` (datetime)

**Data yang perlu ditampilkan:** `label` + `created_at` (sesuai permintaan user).

### 2.7 Existing UI Patterns

| Pattern | Implementasi | Konsisten? |
|---------|-------------|------------|
| Toast notifikasi | `showToast(msg, type, duration)` | ✅ |
| Burger menu toggle | CSS class `.open` pada `<nav>` | ✅ |
| Shadcn dark theme | CSS vars `--card`, `--accent`, `--border` | ✅ |
| Modal/Dialog | `.token-modal-overlay` dengan focus trap | ✅ |
| Material Icons | `.material-symbols-rounded` | ✅ |

---

## 3. Kebutuhan Fungsional

### 3.1 Perilaku Menu Token

| Kondisi | Tampilan | Aksi |
|---------|----------|------|
| User belum login token | Burger menu hanya untuk navigasi (Beranda, Kontak, Masuk) | Tidak ada info token |
| User login token aktif | Burger menu + info token (nama + tanggal) + tombol logout | Klik logout → hapus session + redirect ke home |
| User login token expired | Burger menu + info "Kedaluwarsa" + tombol Hubungi Admin + tombol Masukkan Token | Hubungi Admin → ke /contact; Masukkan Token → buka modal verifikasi |
| User login token suspended | Burger menu + info "Suspended" + tombol Hubungi Admin + tombol Masukkan Token | Sama seperti expired |

### 3.2 Informasi yang Ditampilkan

```
┌─────────────────────────────────┐
│ 🍔 Menu (burger icon)          │
├─────────────────────────────────┤
│ 🏠 Beranda                      │
│ 📞 Kontak                       │
├─────────────────────────────────┤
│ 🔑 Info Token Anda              │
│ 👤 Nama: Member VIP — Budi     │
│ 📅 Dibuat: 15 Agustus 2026     │
│                                 │
│ [🚪 Keluar] [🔄 Perpanjang]    │
└─────────────────────────────────┘
```

### 3.3 Perilaku Logout Token

1. User klik tombol "Keluar" di dropdown
2. Toast `showToast('Logout berhasil', 'success')` muncul
3. Redirect ke halaman utama (`.`)
4. Session token dihapus via `revoke_access()`
5. User kembali ke status "tanpa akses"

---

## 4. Spesifikasi Teknis

### 4.1 Modifikasi Session — Simpan Info Token

**File:** `app/Services/TokenManager.php` (atau inline di `index.php` legacy)

```php
// Sebelum:
grant_access();

// Sesudah:
function grant_access_with_token(mysqli $db, int $tokenId): void {
    // Simpan token ID di session
    $_SESSION['access_token_id'] = $tokenId;
    
    // Query info token
    $s = $db->prepare('SELECT label, created_at FROM access_tokens WHERE id=?');
    $s->bind_param('i', $tokenId);
    $s->execute();
    $token = $s->get_result()->fetch_assoc();
    
    if ($token) {
        $_SESSION['access_token_label'] = $token['label'];
        $_SESSION['access_token_created_at'] = $token['created_at'];
    }
    
    grant_access(); // set access_granted = true
}
```

**Session variables baru:**
- `$_SESSION['access_token_id']` (int) — ID token
- `$_SESSION['access_token_label']` (string) — Nama pemilik
- `$_SESSION['access_token_created_at']` (string) — Tanggal pembuatan

**Catatan:** Kita tidak perlu menyimpan `expires_at` di session karena:
1. User diminta menampilkan "tanggal pembuatan" saja
2. Kita bisa cek expiry secara real-time saat render header

### 4.2 Modifikasi Header — Tampilkan Info Token

**File:** `views/layouts/header.php`

```php
<?php if (admin()): ?>
  <a href="?page=admin"><span class="material-symbols-rounded">tune</span>Panel</a>
  <a href="?page=logout"><span class="material-symbols-rounded">logout</span>Keluar</a>
<?php elseif (has_access()): ?>
  <!-- Token user section -->
  <div class="nav-token-info">
    <div class="nav-token-label">
      <span class="material-symbols-rounded">vpn_key</span>
      <?= e($_SESSION['access_token_label'] ?? 'Token Aktif') ?>
    </div>
    <div class="nav-token-date">
      Dibuat: <?= e($_SESSION['access_token_created_at'] ?? '') ?>
    </div>
    <div class="nav-token-actions">
      <form method="post" action="?page=revoke-access" style="margin:0">
        <input type="hidden" name="csrf" value="<?= csrf() ?>">
        <button type="submit" class="button ghost small">
          <span class="material-symbols-rounded">logout</span> Keluar
        </button>
      </form>
    </div>
  </div>
<?php else: ?>
  <a href="?page=login"><span class="material-symbols-rounded">login</span>Masuk</a>
<?php endif; ?>
```

**Catatan:** Route `?page=revoke-access` belum memvalidasi CSRF. Perlu ditambahkan CSRF check untuk keamanan.

### 4.3 Modifikasi State API

**File:** `routes/api.php` — op=state

```php
// Tambahkan token info ke response
j([
    'csrf' => csrf(),
    'site' => setting($db, 'site_name', 'Arsip Layar'),
    'admin' => admin(),
    'has_access' => has_access(),
    'token_label' => $_SESSION['access_token_label'] ?? null,
    'token_created_at' => $_SESSION['access_token_created_at'] ?? null,
]);
```

**Catatan:** Ini memungkinkan `vue_enhance.js` mengakses info token tanpa HTTP tambahan.

### 4.4 Modifikasi Revoke Access — Tambah CSRF

**File:** `index.php` (legacy) dan `public/index.php`

```php
// Sebelum:
if ($page === 'revoke-access') {
    revoke_access();
    go('.');
}

// Sesudah:
if ($page === 'revoke-access') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_csrf(); // Validasi CSRF
    }
    revoke_access();
    go('.');
}
```

**Catatan:** Route ini harus menerima POST (dari form) dan memvalidasi CSRF. Saat ini belum ada CSRF check.

### 4.5 Modifikasi revoke_access() — Bersihkan Semua Session Token

**File:** `app/helpers.php`

```php
// Sebelum:
function revoke_access(): void {
    unset($_SESSION['access_granted']);
}

// Sesudah:
function revoke_access(): void {
    unset($_SESSION['access_granted']);
    unset($_SESSION['access_token_id']);
    unset($_SESSION['access_token_label']);
    unset($_SESSION['access_token_created_at']);
}
```

### 4.6 CSS — Token Info Dropdown

**File:** `public/assets/css/style.css`

```css
/* Token info in nav dropdown */
.nav-token-info {
  padding: 12px 16px;
  border-top: 1px solid var(--border);
  margin-top: 8px;
  background: var(--surface);
  border-radius: 8px;
}
.nav-token-label {
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 500;
  color: var(--ink);
  margin-bottom: 4px;
}
.nav-token-date {
  font-size: 12px;
  color: var(--muted);
  margin-bottom: 12px;
}
.nav-token-actions {
  display: flex;
  gap: 8px;
}
```

### 4.7 JavaScript — Burger Toggle & Token Info

**File:** `public/assets/js/vue_enhance.js`

Perubahan pada fungsi `initNavBurger()` untuk:
1. Menampilkan token info di dropdown (mobile)
2. Toast notifikasi "Logout berhasil" setelah logout
3. Handle expired token state

**Catatan:** Burger menu sudah berfungsi di mobile via CSS. Perlu memastikan token info juga terlihat di dropdown.

---

## 5. Edge Cases & Penanganan

### 5.1 Token Expired

| Skenario | Penanganan |
|----------|------------|
| Token expired saat session masih aktif | Tampilkan badge "Kedaluwarsa" + tombol "Hubungi Admin" + "Masukkan Token" |
| User klik "Masukkan Token" | Buka modal verifikasi token (sudah ada di watch page) |
| User klik "Hubungi Admin" | Redirect ke `/contact` (sudah ada) |
| Admin suspend token | Tampilkan badge "Suspended" + tombol yang sama |

### 5.2 Session Race Condition

| Skenario | Penanganan |
|----------|------------|
| Admin hapus token saat user sedang online | Token tetap aktif sampai session expiry atau user logout. Cek `has_access()` berdasarkan `$_SESSION['access_granted']`, bukan query DB. |
| User buka tab baru | Session ID sama → token info otomatis terlihat di tab baru |

### 5.3 Performance

| Aspek | Penanganan |
|-------|------------|
| Query DB per request | Tidak ada — info disimpan di session, di-query sekali saat verifikasi |
| Session size | ~200 bytes tambahan (label max 120 chars + timestamps) |
| Cache invalidation | Tidak perlu — session dihapus saat logout |

---

## 6. File yang Perlu Dimodifikasi

### 6.1 File Utama

| File | Perubahan | Prioritas |
|------|-----------|-----------|
| `app/Services/TokenManager.php` | Modifikasi `grant_access()` untuk simpan token info di session | 🔴 CRITICAL |
| `app/helpers.php` | Modifikasi `revoke_access()` untuk bersihkan semua session token | 🔴 CRITICAL |
| `views/layouts/header.php` | Tambahkan UI token info di nav dropdown | 🔴 CRITICAL |
| `public/assets/css/style.css` | Tambahkan CSS untuk `.nav-token-info` | 🔴 CRITICAL |
| `public/assets/js/vue_enhance.js` | Modifikasi burger toggle, tambahkan toast logout | 🟠 HIGH |

### 6.2 File Pendukung

| File | Perubahan | Prioritas |
|------|-----------|-----------|
| `routes/api.php` | Tambahkan token info ke `op=state` response | 🟡 MEDIUM |
| `index.php` (legacy) | Modifikasi token verify + revoke-access | 🟡 MEDIUM |
| `style.css` | Sync dengan `public/assets/css/style.css` | 🟢 LOW |

### 6.3 File yang TIDAK Perlu Dimodifikasi

| File | Alasan |
|------|--------|
| `app/bootstrap.php` | Tidak ada perubahan session logic |
| `views/layouts/head.php` | Tidak ada perubahan meta/CSS |
| `app/Database/Connection.php` | Tidak ada perubahan DB layer |
| `app/Middleware/AuthMiddleware.php` | Tidak ada perubahan auth logic |
| `app/Middleware/CsrfMiddleware.php` | Tidak ada perubahan CSRF logic |

---

## 7. Alur Verifikasi Token (Modifikasi)

### 7.1 Alur Saat Ini

```
User masukkan token di modal → POST ?page=verify-token
  → Query access_tokens WHERE token=? AND status='active'
  → Cek expires_at
  → grant_access()  ← HANYA SET access_granted = true
  → UPDATE access_tokens SET use_count+1
  → Redirect ke halaman sebelumnya
```

### 7.2 Alur Setelah Modifikasi

```
User masukkan token di modal → POST ?page=verify-token
  → Query access_tokens WHERE token=? AND status='active'
  → Cek expires_at
  → grant_access_with_token($db, $tokenId)  ← SET access_granted + simpan info token
    → $_SESSION['access_granted'] = true
    → $_SESSION['access_token_id'] = $tokenId
    → $_SESSION['access_token_label'] = $label
    → $_SESSION['access_token_created_at'] = $created_at
  → UPDATE access_tokens SET use_count+1
  → Redirect ke halaman sebelumnya
```

### 7.3 Alur Logout Token

```
User klik "Keluar" di dropdown → POST ?page=revoke-access (dengan CSRF)
  → check_csrf()
  → revoke_access()
    → unset($_SESSION['access_granted'])
    → unset($_SESSION['access_token_id'])
    → unset($_SESSION['access_token_label'])
    → unset($_SESSION['access_token_created_at'])
  → showToast('Logout berhasil', 'success')
  → Redirect ke home
```

---

## 8. Keamanan

### 8.1 Checklist Keamanan

| Langkah | Status | Keterangan |
|---------|--------|------------|
| CSRF validation di revoke-access | ⚠️ BELUM ADA | Route `?page=revoke-access` belum memvalidasi CSRF |
| Session regeneration | ✅ ADA | Sudah dilakukan saat login token (`session_regenerate_id(true)`) |
| Token info tidak sensitif | ✅ OK | Label dan created_at bukan data sensitif |
| Tidak ada token value di session | ✅ OK | Hanya ID yang disimpan, bukan string token |
| XSS protection | ✅ OK | Semua output menggunakan `e()` escaping |
| Path traversal | ✅ Tidak relevan | Tidak ada file serving |
| Rate limiting | ✅ Tidak relevan | Tidak ada endpoint baru |

### 8.2 Risiko Keamanan

| Risiko | Likelihood | Impact | Mitigasi |
|--------|-----------|--------|----------|
| Session fixation | Low | Medium | `session_regenerate_id(true)` sudah dilakukan |
| CSRF di logout | Medium | Low | Tambahkan CSRF check di revoke-access |
| Token info leakage | Very Low | Low | Label tidak sensitif, hanya ditampilkan di browser user sendiri |

---

## 9. Testing Checklist

### 9.1 Fungsional

- [ ] User dengan token aktif bisa melihat info token di burger menu
- [ ] Info token menampilkan nama (label) dan tanggal pembuatan
- [ ] Tombol logout berfungsi → session dihapus → redirect ke home
- [ ] Toast "Logout berhasil" muncul setelah logout
- [ ] User tanpa token tidak melihat info token di burger menu
- [ ] Admin tidak melihat info token di burger menu (punya sidebar sendiri)
- [ ] Token expired menampilkan badge "Kedaluwarsa" + tombol Hubungi Admin
- [ ] Token suspended menampilkan badge "Suspended"
- [ ] Tombol "Hubungi Admin" redirect ke /contact
- [ ] Tombol "Masukkan Token" buka modal verifikasi

### 9.2 Keamanan

- [ ] Revoke access membutuhkan CSRF token
- [ ] Token value tidak pernah ditampilkan atau disimpan di session
- [ ] Semua output menggunakan `e()` escaping
- [ ] Tidak ada informasi token di URL atau query string

### 9.3 Responsive

- [ ] Burger menu berfungsi di mobile (di bawah 820px)
- [ ] Nav links selalu terlihat di desktop (di atas 820px)
- [ ] Token info terlihat dengan jelas di mobile
- [ ] Token info tidak terlihat di desktop (hanya di dropdown)

### 9.4 Syntax Check

- [ ] Semua file PHP pass `php -l`
- [ ] ESLint 0 errors (npm run lint)

---

## 10. Dokumentasi yang Perlu Diupdate

| File | Update |
|------|--------|
| `changelog.md` | Tambah entry baru: "Token User Menu" |
| `audit.md` | Tambah section baru: "Feature Audit — Token User Menu" |
| `README.md` | Update Section 7.5 (Access Token System), Section 6 (Routing Reference) |

---

## 11. Estimasi Kompleksitas

| Aspek | Estimasi |
|-------|----------|
| File dimodifikasi | 5-6 file |
| Baris kode baru | ~80-120 baris (PHP + CSS + JS) |
| Risiko | Medium (modifikasi session dan auth flow) |
| Waktu implementasi | 1-2 jam |
| Testing | 30-45 menit |

---

## 12. Referensi

- `README.md` — Section 7.5 (Access Token System), Section 10 (Known Issues)
- `audit.md` — Section 9 (HLS Status Bug), Section 12 (Bulk Upload)
- `changelog.md` — Format entry changelog
- `views/layouts/header.php` — Current navigation structure
- `app/helpers.php` — Session functions (has_access, grant_access, revoke_access)
- `app/Services/TokenManager.php` — Token verification logic

---

## 13. Wireframe & Mockup

### 13.1 Mobile (< 820px) — Burger Menu Tertutup

```
┌──────────────────────────────────────┐
│ 🔵 Arsip · arsip          [☰] Menu  │
└──────────────────────────────────────┘
```

### 13.2 Mobile (< 820px) — Burger Menu Terbuka (User Tanpa Token)

```
┌──────────────────────────────────────┐
│ 🔵 Arsip · arsip          [✕] Menu  │
├──────────────────────────────────────┤
│ 🏠 Beranda                           │
│ 📞 Kontak                            │
│ 🔑 Masuk                             │
└──────────────────────────────────────┘
```

### 13.3 Mobile (< 820px) — Burger Menu Terbuka (User dengan Token Aktif)

```
┌──────────────────────────────────────┐
│ 🔵 Arsip · arsip          [✕] Menu  │
├──────────────────────────────────────┤
│ 🏠 Beranda                           │
│ 📞 Kontak                            │
├──────────────────────────────────────┤
│ 🔑 Member VIP — Budi                │
│ Dibuat: 15 Aug 2026 10:30:00        │
│                                      │
│ [🚪 Keluar]                          │
└──────────────────────────────────────┘
```

### 13.4 Mobile (< 820px) — Burger Menu Terbuka (Token Expired)

```
┌──────────────────────────────────────┐
│ 🔵 Arsip · arsip          [✕] Menu  │
├──────────────────────────────────────┤
│ 🏠 Beranda                           │
│ 📞 Kontak                            │
├──────────────────────────────────────┤
│ 🔑 Member VIP — Budi                │
│ Dibuat: 15 Aug 2026 10:30:00        │
│                                      │
│ ⚠️ Kedaluwarsa  [📞 Hubungi Admin]  │
│ [🚪 Keluar]                          │
└──────────────────────────────────────┘
```

### 13.5 Mobile (< 820px) — Admin Login

```
┌──────────────────────────────────────┐
│ 🔵 Arsip · arsip          [✕] Menu  │
├──────────────────────────────────────┤
│ 🏠 Beranda                           │
│ 📞 Kontak                            │
│ ⚙️ Panel                             │
│ 🚪 Keluar                            │
└──────────────────────────────────────┘
```

### 13.6 Desktop (> 820px) — Nav Selalu Visible

```
┌──────────────────────────────────────────────────────────────────┐
│ 🔵 Arsip · arsip    Beranda    Kontak    Panel    Keluar       │
└──────────────────────────────────────────────────────────────────┘
```
**Catatan:** Di desktop, nav links selalu terlihat. Token info TIDAK ditampilkan di desktop karena:
1. Burger menu hanya berfungsi di mobile
2. Di desktop, user bisa melihat info token di halaman watch atau admin panel

---

**Catatan Penting:** Spesifikasi ini harus divalidasi ulang sebelum implementasi. Pastikan untuk:
1. Membaca `app/Services/TokenManager.php` secara lengkap
2. Membaca `public/assets/js/vue_enhance.js` untuk memahami burger toggle
3. Memverifikasi route `?page=revoke-access` di `routes/web.php`
4. Mengecek CSS `.burger` dan `.nav` di `public/assets/css/style.css`
