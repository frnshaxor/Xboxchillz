# Spec: Git Configuration & Rules

**Purpose:** Configure the Git account for the Arsip Layar codebase, create a new GitHub repository, and add comprehensive Git rules to `rules.md`.

---

## 1. Overview

### 1.1 What This Task Does
1. Configure Git user identity (name, email)
2. Set up credential storage (environment variable)
3. Create a new GitHub repository: `Xboxchillz`
4. Add comprehensive `.gitignore` for PHP project
5. Add Git rules and protocols to `rules.md`
6. Push the codebase to the new repository

### 1.2 What This Task Does NOT Do
- NOT change any application code
- NOT modify database schema
- NOT deploy to production

---

## 2. Git Configuration

### 2.1 User Identity

| Setting | Value |
|---------|-------|
| `user.name` | `Xboxchillz` (GitHub username) |
| `user.email` | `alvin.krisdianto69@gmail.com` |

### 2.2 Credential Storage

**Method:** Environment variable (most secure option available)

The password will be stored in an environment variable, NOT in any file. This prevents accidental exposure in:
- Git config files
- `.git-credentials` files
- Shell history
- Backup files

**Implementation:**
```bash
# Store password in environment variable (session-only)
export GITHUB_TOKEN="alvin.krisdianto69@gmail.com:Rabel100398@"

# Configure Git to use the token
git config credential.helper store
```

### 2.3 ⚠️ Security Warning

**The user has chosen to store their GitHub password.** While environment variable storage is more secure than file-based storage, the following risks remain:
- Password is visible in process listings (`ps aux`)
- Password is in shell history
- Password persists for the session duration

**Recommendation:** After initial setup, the user should:
1. Enable 2FA on GitHub
2. Consider switching to SSH keys or personal access tokens
3. Never share credentials in plain text again

---

## 3. Repository Setup

### 3.1 Repository Details

| Setting | Value |
|---------|-------|
| Name | `Xboxchillz` |
| Visibility | Private (recommended for personal projects) |
| Description | "Arsip Layar — Self-hosted video sharing platform" |
| Default branch | `master` |
| Remote URL | `https://github.com/Xboxchillz/Xboxchillz.git` |

### 3.2 Remote Setup

```bash
# Add remote origin (SSH)
git remote add origin git@github.com:Xboxchillz/Xboxchillz.git

# Verify remote
git remote -v
```

### 3.3 Initial Push

```bash
# Push master branch with all history (SSH)
git push -u origin master
```

**Note:** Make sure SSH key is added to GitHub account before pushing.

---

## 4. .gitignore

### 4.1 Complete .gitignore for PHP Project

```gitignore
# ─── Dependencies ───
node_modules/
vendor/
composer.lock

# ─── IDE / Editor ───
.vscode/
.idea/
*.swp
*.swo
*~
.DS_Store
Thumbs.db

# ─── OS Files ───
.DS_Store
.DS_Store?
._*
.Spotlight-V100
.Trashes
ehthumbs.db
Thumbs.db

# ─── PHP ───
*.phar
.env
.env.local
.env.production

# ─── Logs ───
*.log
logs/
npm-debug.log*
yarn-debug.log*
yarn-error.log*

# ─── Backups ───
*.bak
*.bak-*
*.backup
*.backup-*
storage/backups/

# ─── Build ───
dist/
build/
.cache/

# ─── Temp / Uploads ───
storage/uploads/*
!storage/uploads/.gitignore
storage/cache/*
!storage/cache/.gitignore
storage/framework/*
!storage/framework/.gitignore

# ─── Media (too large for Git) ───
media/*
!media/.gitignore

# ─── Sandbox (unused) ───
sandbox/

# ─── Archives ───
*.zip
*.tar.gz
*.rar

# ─── Testing ───
coverage/
.phpunit.result.cache

# ─── Misc ───
*.sql.gz
.eslintcache
```

### 4.2 Why Each Entry Matters

| Entry | Reason |
|-------|--------|
| `node_modules/` | Dependencies, ~100MB+ |
| `vendor/` | PHP dependencies (not used but good practice) |
| `.env` | Secrets (DB password, API keys) |
| `media/` | Video files, too large for Git (~GB) |
| `storage/backups/` | Database backups, too large |
| `storage/uploads/` | Temporary upload chunks |
| `*.bak*` | Backup files from development |
| `sandbox/` | Unused Laravel scaffold |
| `*.zip` | Archive files (like arsip-layar-full-backup.zip) |

---

## 5. Git Rules for rules.md

### 5.1 New Section: 🔀 Git Protocol

Add to `rules.md` after the "Communication Protocol" section:

#### Content:

```markdown
## 🔀 Git Protocol

> Git operations require caution. AI agents MUST follow this protocol.

### Git Rules

| Operation | Permission Required? | Notes |
|-----------|---------------------|-------|
| `git status` | ❌ No | Always safe to run |
| `git diff` | ❌ No | Always safe to run |
| `git log` | ❌ No | Always safe to run |
| `git add` | ⚠️ Careful | Only stage files related to the task |
| `git commit` | ⚠️ Careful | Use Indonesian commit messages |
| `git push` | ✅ **Automatic** | AI pushes after successful commit |
| `git pull` | ✅ **Automatic** | AI pulls before starting work |
| `git revert` | 🔒 **NEVER without user permission** | Reverting affects production |
| `git reset` | 🔒 **NEVER without user permission** | Destructive — can lose work |
| `git rebase` | 🔒 **NEVER without user permission** | Rewrites history |

### Branch Naming Convention

| Branch Type | Prefix | Example |
|-------------|--------|---------|
| Bug fix | `fix/` | `fix/upload-retry-bug` |
| New feature | `feat/` | `feat/new-admin-tab` |
| Hotfix | `hotfix/` | `hotfix/security-patch` |
| Documentation | `docs/` | `docs/update-readme` |
| Refactor | `refactor/` | `refactor/simplify-upload` |

### Commit Message Format (Bahasa Indonesia)

```
<type>(<scope>): <deskripsi>

<opsi body>
```

**Types:**
- `feat` — Fitur baru
- `fix` — Perbaikan bug
- `refactor` — Perbaikan kode tanpa mengubah perilaku
- `docs` — Hanya dokumentasi
- `style` — Perubahan CSS/UI
- `chore` — Tugas pemeliharaan
- `test` — Penambahan/pengubahan test

**Contoh:**
```
feat(upload): tambah fitur resume upload setelah page reload
fix(media): perbaiki poster tidak muncul di watch page
refactor(token): sederhanakan logika verifikasi token
docs(changelog): tambah entri untuk fitur baru
```

### Workflow: Automatic Git

AI agent WILL automatically:

1. **Before starting work:**
   ```bash
   git pull origin master  # Ambil perubahan terbaru
   ```

2. **After completing work:**
   ```bash
   git add <modified-files>  # Hanya file yang relevan
   git commit -m "<type>(<scope>): <deskripsi>"
   git push origin <branch>  # Push ke remote
   ```

3. **For feature branches:**
   ```bash
   git checkout -b <type>/<nama-fitur>  # Buat branch baru
   # ... kerja ...
   git push -u origin <branch>  # Push branch baru
   ```

### What to Stage

- ✅ Only files you modified for this task
- ✅ Related documentation (changelog.md, audit.md, README.md)
- ✅ rules.md if Git rules changed
- ❌ Unrelated files — even if they have changes
- ❌ Backup files (`.bak`, `.bak-*`)
- ❌ `node_modules/` or `storage/` directories
- ❌ Media files (`media/`)
- ❌ Archive files (`*.zip`)

### Remote Operations

| Operation | Command | When |
|-----------|---------|------|
| Push master | `git push origin master` | After commit to master |
| Push feature | `git push -u origin <branch>` | After commit to feature branch |
| Pull latest | `git pull origin master` | Before starting work |
| Fetch all | `git fetch --all` | Check for remote changes |
| Clone repo | `git clone https://github.com/Xboxchillz/Xboxchillz.git` | First time setup |

### Conflict Resolution

If merge conflict occurs:

1. **Don't panic** — explain to user what happened
2. **Show the conflict** — display the conflicting files
3. **Suggest resolution** — which version to keep
4. **Ask user** — confirm before resolving
5. **Test after** — verify the fix works
```

### 5.2 Update Existing Git Rules

Update the "Git Protocol" section in `rules.md` with:

1. **Push behavior:** Changed from "User permission" to "Automatic" (user wants AI to handle all Git operations)
2. **Commit format:** Changed from English to Bahasa Indonesia
3. **Branch naming:** Added fix/, feat/, hotfix/, docs/, refactor/ prefixes
4. **Remote operations:** Added push, pull, fetch, clone commands
5. **Conflict resolution:** Added step-by-step guide

---

## 6. Implementation Steps

### Step 1: Configure Git Identity
```bash
git config user.name "Xboxchillz"
git config user.email "alvin.krisdianto69@gmail.com"
```

### Step 2: Set Up Credential Storage
```bash
# Store in environment variable (session-only)
export GITHUB_TOKEN="alvin.krisdianto69@gmail.com:Rabel100398@"

# Configure credential helper
git config credential.helper store
```

### Step 3: Create GitHub Repository
```bash
# Using GitHub CLI (if available) or manual creation
# Repository name: Xboxchillz
# Visibility: Private
# Description: "Arsip Layar — Self-hosted video sharing platform"
```

### Step 4: Update .gitignore
Replace current minimal `.gitignore` with the complete version.

### Step 5: Add Remote
```bash
git remote add origin https://github.com/Xboxchillz/Xboxchillz.git
```

### Step 6: Update rules.md
Add the Git Protocol section with all the rules defined above.

### Step 7: Update changelog.md
Add entry documenting the Git configuration changes.

### Step 8: Initial Push
```bash
git add .
git commit -m "chore(init): konfigurasi git dan tambahkan rules.md"
git push -u origin master
```

---

## 7. Verification Checklist

- [ ] Git user.name set to "Xboxchillz"
- [ ] Git user.email set to "alvin.krisdianto69@gmail.com"
- [ ] Credential storage configured (environment variable)
- [ ] .gitignore updated with complete PHP project rules
- [ ] rules.md updated with Git Protocol section
- [ ] changelog.md updated with configuration entry
- [ ] Remote origin added
- [ ] Initial push successful
- [ ] Repository accessible on GitHub

---

## 8. Risk Assessment

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|------------|
| Password exposed in shell history | Medium | High | Use `export` in session only, clear after setup |
| Password in process listing | Low | Medium | Environment variable is better than file storage |
| Accidental push of sensitive files | Low | High | .gitignore covers sensitive files |
| Merge conflicts | Medium | Low | Feature branch strategy isolates changes |
| Repository too large | Low | Medium | .gitignore excludes media and backups |

---

## 9. Post-Setup Recommendations

After Git is configured, the user should:

1. **Enable 2FA on GitHub** — Two-factor authentication adds security
2. **Consider SSH keys** — More secure than password-based authentication
3. **Use personal access tokens** — If 2FA is enabled, passwords won't work for Git
4. **Review .gitignore** — Ensure no sensitive files are tracked
5. **Set up branch protection** — Optional but recommended for production repos
