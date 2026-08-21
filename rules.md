# Arsip Layar — AI Agent Guidelines

> **MANDATORY READING** — Every AI agent MUST read this file before performing ANY work.
> This document consolidates rules, workflows, and lessons from `README.md`, `audit.md`, and `changelog.md`.
> **Non-optional.** Violation of these rules can cause production outages, data loss, or security vulnerabilities.

---

## ⚡ Quick Reference

### Pre-Task Checklist

Before starting ANY work, AI agent MUST complete:

- [ ] Read `rules.md` (this file) — understand the protocol
- [ ] Read relevant sections of `README.md` — architecture and patterns
- [ ] Read relevant sections of `audit.md` — check for related findings
- [ ] Read `changelog.md` — check what was done before
- [ ] Identify which workflow applies (fix bug / add feature / refactor / etc.)
- [ ] Identify severity level (P1–P4)
- [ ] Confirm no breaking changes (or get user override)

### Post-Task Checklist

After finishing ANY work, AI agent MUST complete:

**PHP Verification:**
- [ ] All modified PHP files pass `php -l` — **no exceptions** 🔒
- [ ] Type strings match parameter counts exactly (if DB changes)
- [ ] No duplicate function definitions introduced
- [ ] Session variables set before accessing them

**Frontend Verification (if JS/CSS changed):**
- [ ] ESLint passes: `npm run lint` — **0 errors required** 🔒
- [ ] No new warnings introduced (baseline: 25 warnings)
- [ ] CSS synced: `public/assets/css/style.css` ↔ `style.css`
- [ ] Responsive design tested at 320px, 768px, 1024px, 1440px
- [ ] Keyboard navigation works (Tab through all interactive elements)
- [ ] Toast/error messages appear correctly on failure

**Code Quality:**
- [ ] No debug code left (`var_dump`, `error_log`, `dd()`, `console.log`) 🔒
- [ ] No temporary test files or debug scripts left
- [ ] All output escaped with `e()` (no raw user input)
- [ ] All SQL uses prepared statements (no raw queries)
- [ ] All shell commands use `escapeshellarg()`

**Documentation:**
- [ ] `changelog.md` updated with new entry 🔒
- [ ] `audit.md` updated (if new findings discovered)
- [ ] `README.md` updated (if architecture changed)
- [ ] Cross-references added (changelog ↔ audit)
- [ ] Backup created (if major changes)

**Final Check:**
- [ ] No `die()`, `exit()` added (except in helpers like `go()`)
- [ ] No hardcoded colors (use CSS variables)
- [ ] No secrets in code (use `settings` table)

### Severity Levels

| Level | Name | Description | SLA | Example |
|-------|------|-------------|-----|---------|
| **P1** | 🔴 Critical | Site down, data loss, security breach | Fix within **15 minutes** | SQL injection, production outage, auth bypass |
| **P2** | 🟠 High | Feature broken, user-facing bug | Fix within **1 hour** | Upload broken, payment failed, video unplayable |
| **P3** | 🟡 Medium | Minor bug, UI glitch, cosmetic issue | Fix within **24 hours** | Typo, color mismatch, broken responsive layout |
| **P4** | 🟢 Low | Enhancement, optimization, cleanup | Fix when convenient | Performance improvement, code cleanup, new feature |

### Override Protocol

**All rules CAN be overridden by user request**, but AI agent MUST:

1. **Explain briefly but in detail** what rule is being overridden and why
2. **Explain the risks** — what could go wrong if this rule is skipped
3. **Get explicit confirmation** from user before proceeding
4. **Document the override** in `changelog.md` entry with the reason

**🔒 EXCEPTION — These rules CANNOT be overridden even by user request. AI must refuse and explain why:**

| # | Rule | Why It's Non-Negotiable |
|---|------|------------------------|
| 🔒1 | **Security (Rule 5)** | Never output user input without `e()` escaping, never use raw SQL, never store secrets in code | Vulnerabilities affect all users |
| 🔒2 | **Documentation (Rule 7)** | Always update `changelog.md` after every implementation | Future AI agents and developers need change history |
| 🔒3 | **Syntax Check (Rule 4)** | Always run `php -l` on modified PHP files | Syntax errors crash production |
| 🔒4 | **No Debug Code** | Never leave `var_dump`, `error_log`, `dd()` in production | Exposes internal data to users |

---

## 🔒 Non-Negotiable Rules (NEVER override)

These are the absolute minimum standards. Every AI agent MUST follow them without exception.

### 🔒1. Security First [README.md Rule 5]

- **NEVER** output user input without `e()` escaping — XSS vulnerability
- **NEVER** use raw SQL — always use prepared statements with `bind_param`
- **NEVER** store secrets in code — use the `settings` table via `setting()`/`set_setting()`
- **ALWAYS** validate file uploads: extension (`.mp4` only), MIME (finfo + ftyp), size (`upload_max_mb`)
- **ALWAYS** use `escapeshellarg()` for shell commands
- **ALWAYS** check `realpath()` for path traversal prevention

### 🔒2. Documentation Required [README.md Rule 7]

- **`changelog.md`**: Add entry with date, description, files modified, audit section reference
- **`audit.md`**: Add investigation section with symptom, root cause, methodology, fixes, verification, lessons learned
- **`README.md`**: Update relevant sections (architecture, gotchas, patterns) if the fix changes understanding
- **Cross-reference**: Every changelog entry must reference the audit section, and vice versa

### 🔒3. Syntax Verification [README.md Rule 4]

```bash
php -l <modified_file.php>   # Repeat for EVERY modified PHP file
```

- All files must pass with "No syntax errors detected"
- If any file fails, fix the syntax error before proceeding
- Also verify: type strings match parameter counts exactly

### 🔒4. No Debug Code in Production

- No `var_dump()`, `print_r()`, `error_log()` (except in `log_activity()` calls)
- No `dd()`, `dump()`, `die()`, `exit()` (except in helpers like `go()`)
- No `console.log()` left in frontend JS (except in `onerror` CDN fallbacks)
- No temporary test files or debug scripts

---

## 🔍 ESLint Protocol

> ESLint is MANDATORY for all frontend JavaScript changes. AI agents MUST follow this protocol.

### When to Run ESLint

| Change Type | Run ESLint? | Command |
|-------------|-------------|--------|
| Any JS change in `public/assets/js/` | ✅ **YES** | `npm run lint` |
| CSS-only changes | ❌ No | — |
| PHP-only changes | ❌ No | — |
| New Vue component (inline template) | ✅ **YES** | `npm run lint` |
| Service worker (`sw.js`) changes | ❌ No | Different rules apply |

### ESLint Commands

```bash
npm run lint          # Check for errors/warnings — MUST show 0 errors
npm run lint:fix      # Auto-fix fixable issues (run first if many warnings)
npm run lint:report   # Generate JSON report to storage/eslint-report.json
```

### Current Baseline

| Metric | Value | Notes |
|--------|-------|-------|
| Errors | **0** | Must stay at 0 — any error is a blocker |
| Warnings | **25** | Accepted warnings (architectural/intentional) |
| Plugins | 3 | `vue`, `vuejs-accessibility`, `tailwindcss` |

### Understanding ESLint Results

**Errors (❌ — must fix before proceeding):**
- `no-var` — Use `let` or `const` instead of `var`
- `vuejs-accessibility/aria-props` — Invalid ARIA attribute
- `vuejs-accessibility/aria-role` — Invalid ARIA role
- `tailwindcss/no-contradicting-classname` — Conflicting Tailwind classes

**Warnings (⚠️ — acceptable, do NOT fix unless relevant):**
- `vue/one-component-per-file` — Expected: all Vue components in single IIFE
- `no-unused-vars` — Acceptable: unused catch params, function params
- `no-alert` — Intentional: `confirm()` for admin delete dialogs
- `tailwindcss/classnames-order` — Tailwind class ordering

### Common ESLint Fixes

| Issue | Fix |
|-------|-----|
| `no-var` error | Change `var x` → `let x` or `const x` |
| `no-unused-vars` warning | Prefix with `_` or remove if truly unused |
| `no-alert` warning | Keep `confirm()` for admin dialogs — it's intentional |
| `eqeqeq` warning | Change `==` to `===` (or `==` to `===` with type coercion) |
| `vue/no-v-html` warning | Review if `v-html` is necessary — XSS risk |
| `tailwindcss/no-contradicting-classname` error | Remove conflicting Tailwind classes |
| `vuejs-accessibility/click-events-have-key-events` warning | Add `@keydown.enter` or `@keydown.space` alongside `@click` |
| `vuejs-accessibility/form-control-has-label` warning | Add `<label>` or `aria-label` to form inputs |

### ESLint Workflow

1. **Before making changes:** Run `npm run lint` to confirm baseline
2. **After making changes:** Run `npm run lint` again
3. **If errors appear:** Fix them immediately — do NOT proceed
4. **If new warnings appear:** Evaluate — fix if easy, document if intentional
5. **If baseline changes:** Update this section in `rules.md`

### ESLint Configuration

The ESLint config (`eslint.config.js`) is tailored for this CDN-based architecture:
- **No `.vue` SFC files** — all Vue components are inline string templates
- **No build step** — CDN-loaded Vue 3, Tailwind CSS, Plyr.js, HLS.js
- **IIFE pattern** — `vue_enhance.js` is a single IIFE, not modules
- **PHP-rendered globals** — `TailwindCSS`, `Vue`, `Plyr`, `Hls` are CDN globals

### What NOT to Do with ESLint

- ❌ Do NOT disable rules globally — fix the issue instead
- ❌ Do NOT add `eslint-disable` comments without user approval
- ❌ Do NOT change `eslint.config.js` without understanding the impact
- ❌ Do NOT ignore errors — they are blockers
- ❌ Do NOT run `npm run lint:fix` on files you didn't modify — it might change unrelated code

---

## 🎨 CSS Sync Protocol

> CSS files must stay in sync. AI agents MUST follow this protocol when making CSS changes.

### The Two CSS Files

| File | Purpose |
|------|--------|
| `public/assets/css/style.css` | **Source of truth** — served by nginx |
| `style.css` | **Legacy copy** — kept in sync for consistency |

### CSS Sync Rules

1. **Always edit `public/assets/css/style.css`** — this is the source of truth
2. **After editing, sync to root:** `cp public/assets/css/style.css style.css`
3. **Never edit `style.css` directly** — always edit the public version
4. **CSS changes must include:** Shadcn CSS variables, responsive breakpoints, `!important` overrides

### CSS Change Checklist

- [ ] Edit `public/assets/css/style.css` (source of truth)
- [ ] Sync: `cp public/assets/css/style.css style.css`
- [ ] Use CSS variables (`--surface`, `--accent`, `--ink`) — no hardcoded colors
- [ ] Add `!important` on input/color overrides (Tailwind CDN conflict)
- [ ] Test responsive at 320px, 768px, 1024px, 1440px
- [ ] Verify contrast passes WCAG AA (4.5:1 minimum)

---

## 🔀 Git Protocol

> Git operations require caution. AI agents MUST follow this protocol.

### Git Rules

| Operation | Permission Required? | Notes |
|-----------|---------------------|-------|
| `git status` | ❌ No | Always safe to run |
| `git diff` | ❌ No | Always safe to run |
| `git log` | ❌ No | Always safe to run |
| `git add` | ⚠️ Careful | Only stage files related to the task |
| `git commit` | ⚠️ Careful | Use conventional commit messages |
| `git revert` | ✅ **User permission** | Reverting changes affects production |
| `git push` | 🔒 **NEVER without explicit user permission** | Pushing can break production |
| `git reset` | 🔒 **NEVER without explicit user permission** | Destructive — can lose work |
| `git rebase` | 🔒 **NEVER without explicit user permission** | Rewrites history |

### Commit Message Format

```
<type>(<scope>): <description>

<optional body>

<optional footer>
```

**Types:**
- `feat` — New feature
- `fix` — Bug fix
- `refactor` — Code improvement without behavior change
- `docs` — Documentation only
- `style` — CSS/UI changes
- `chore` — Maintenance tasks

**Examples:**
```
fix(upload): prevent retry loop for null file objects
feat(token): add expiry check on token verification
refactor(media): simplify poster URL generation
```

### What to Stage

- ✅ Only files you modified for this task
- ✅ Related documentation (changelog.md, audit.md, README.md)
- ❌ Unrelated files — even if they have changes
- ❌ Backup files (`.bak`, `.bak-*`)
- ❌ `node_modules/` or `storage/` directories

---

## 💬 Communication Protocol

> AI agents MUST communicate clearly with users. Follow this protocol.

### Progress Updates

For tasks taking >5 minutes, provide progress updates:

1. **Start:** "Reading codebase to understand the issue..."
2. **Analysis:** "Found the root cause: [brief explanation]"
3. **Implementation:** "Implementing fix..."
4. **Verification:** "Running verification checks..."
5. **Done:** "Fix complete. Here's what was changed: [summary]"

### Asking Questions

When the request is ambiguous, use `ask_user` tool with multiple-choice options:
- Provide 2-4 clear options
- Include brief descriptions for each option
- Allow "Other" for custom answers
- Don't ask obvious questions — infer from context

### Reporting Bugs

When you discover a bug during your work:

1. **Don't ignore it** — report to user immediately
2. **Classify severity** — P1/P2/P3/P4
3. **Explain impact** — what does it affect?
4. **Suggest fix** — brief description of how to fix
5. **Ask permission** — should you fix it now or later?

### Error Handling

If something goes wrong:

1. **Don't panic** — explain what happened
2. **Don't hide errors** — show the full error message
3. **Suggest recovery** — what can be done to fix it
4. **Offer rollback** — if the error is from your changes

### Response Format

Structure responses to users clearly:

**For Bug Fixes:**
```
## 🔴 Bug Fix: [Brief Title]

**Problem:** [What was broken]
**Root Cause:** [Why it broke]
**Fix:** [What was changed]
**Files Modified:** [List of files]
**Verification:** [What checks were run]
```

**For New Features:**
```
## 🟢 Feature: [Brief Title]

**Description:** [What was added]
**Files Modified:** [List of files]
**How to Use:** [Brief instructions]
**Verification:** [What checks were run]
```

**For Audit/Analysis:**
```
## 🔍 Audit: [Brief Title]

**Scope:** [What was analyzed]
**Findings:** [Summary of findings]
**Recommendations:** [What should be done]
```

---

## 🔀 Git Protocol

> Git operations require caution. AI agents MUST follow this protocol.
> **User preference:** AI agent handles ALL Git operations automatically.

### Git Rules

| Operation | Permission Required? | Notes |
|-----------|---------------------|-------|
| `git status` | ❌ No | Always safe to run |
| `git diff` | ❌ No | Always safe to run |
| `git log` | ❌ No | Always safe to run |
| `git add` | ✅ **Automatic** | AI stages only relevant files |
| `git commit` | ✅ **Automatic** | AI commits after successful work |
| `git push` | ✅ **Automatic** | AI pushes after commit |
| `git pull` | ✅ **Automatic** | AI pulls before starting work |
| `git checkout -b` | ✅ **Automatic** | AI creates feature branches |
| `git merge` | ✅ **Automatic** | AI merges feature branches to master |
| `git revert` | 🔒 **NEVER without user permission** | Reverting affects production |
| `git reset` | 🔒 **NEVER without user permission** | Destructive — can lose work |
| `git rebase` | 🔒 **NEVER without user permission** | Rewrites history |

### Branch Naming Convention

| Branch Type | Prefix | Example |
|-------------|--------|--------|
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
chore(git): konfigurasi git dan tambahkan rules.md
```

### Workflow: Automatic Git

AI agent WILL automatically:

**1. Before starting work:**
```bash
git pull origin master  # Ambil perubahan terbaru
```

**2. After completing work:**
```bash
git add <modified-files>  # Hanya file yang relevan
git commit -m "<type>(<scope>): <deskripsi>"
git push origin <branch>  # Push ke remote
```

**3. For feature branches:**
```bash
git checkout -b <type>/<nama-fitur>  # Buat branch baru
# ... kerja ...
git push -u origin <branch>  # Push branch baru
```

**4. After feature branch is complete:**
```bash
git checkout master
git merge <branch>
git push origin master
git branch -d <branch>  # Hapus branch lokal
git push origin --delete <branch>  # Hapus branch remote
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
| Clone repo | `git clone git@github.com:frnshaxor/Xboxchillz.git` | First time setup |

### Conflict Resolution

If merge conflict occurs:

1. **Don't panic** — explain to user what happened
2. **Show the conflict** — display the conflicting files
3. **Suggest resolution** — which version to keep
4. **Ask user** — confirm before resolving
5. **Test after** — verify the fix works

### Current Git Configuration

| Setting | Value |
|---------|-------|
| `user.name` | `Xboxchillz` |
| `user.email` | `alvin.krisdianto69@gmail.com` |
| SSH Key | `~/.ssh/id_ed25519` (ed25519) |
| Remote | `git@github.com:frnshaxor/Xboxchillz.git` (SSH) |
| Default branch | `master` |
| Branch strategy | Feature branches (`fix/`, `feat/`, `hotfix/`, `docs/`, `refactor/`) |
| Commit format | Bahasa Indonesia (`feat(tambah): deskripsi`) |
| Push behavior | Automatic (AI handles all Git operations) |

---

## 🗄️ Database Change Protocol

> Database changes require extra caution. AI agents MUST follow this protocol.

### Schema Changes

| Change Type | Required Steps |
|-------------|---------------|
| Add column | `schema.sql` update + `migrations/YYYYMMDD_HHMMSS_add_column.sql` |
| Drop column | `schema.sql` update + migration + check for orphaned references |
| Add index | `schema.sql` update + migration |
| Modify column | `schema.sql` update + migration + data migration if needed |
| New table | `schema.sql` update + migration + new Model class |

### Migration File Format

```sql
-- Migration: <description>
-- Date: YYYY-MM-DD HH:MM:SS
-- Author: AI Agent

-- Forward migration
ALTER TABLE table_name ADD COLUMN column_name TYPE;

-- Rollback (commented out)
-- ALTER TABLE table_name DROP COLUMN column_name;
```

### Database Rules

1. **Always use prepared statements** — never raw SQL 🔒
2. **Always use `Connection` class** — never raw `mysqli` 🔒
3. **Type strings must match param count** — verify before executing 🔒
4. **Test migrations** — run `php migrate.php --status` before and after
5. **Backup before major changes** — `php migrate.php` creates backups
6. **Check for orphaned references** — if dropping columns/tables

### Type String Reference

| Type | PHP Type | MySQL Type |
|------|----------|------------|
| `s` | string | VARCHAR, TEXT, DATE |
| `i` | integer | INT, BIGINT, TINYINT |
| `d` | float | DECIMAL, DOUBLE |
| `b` | blob | BLOB, BINARY |

**Rule:** Type string length MUST equal parameter array count.
```php
// ✅ Correct: 3 params, type 'ssi'
$conn->execute('INSERT INTO t(a,b,c) VALUES(?,?,?)', [$a, $b, $c], 'ssi');
// ❌ Wrong: 3 params, type 'ss' — will crash
$conn->execute('INSERT INTO t(a,b,c) VALUES(?,?,?)', [$a, $b, $c], 'ss');
```

---

## 📋 Detailed Protocols

### Workflow: Fix Bug

**Trigger:** User reports a bug or AI discovers one during code review.

**Steps:**

1. **Read** — Read the relevant source files completely (Rule 1: Understand Before Modify)
2. **Search** — Check `audit.md` for related investigation reports. Search for the affected feature in the cumulative findings index (Section 10)
3. **Understand** — Trace the data flow: entry point → controller → service → model → DB → response
4. **Reproduce** — Identify the exact conditions that trigger the bug
5. **Root Cause** — Find the actual root cause, not just the symptom
6. **Fix** — Implement the minimal fix that addresses the root cause
7. **Verify** — Run `php -l`, test the fix, check edge cases
8. **Document** — Update `changelog.md` and `audit.md` (if investigation was needed)

**Files to check:** Based on README.md Section 15 "Common Tasks" mapping.

**Special considerations:**
- If the bug is in the upload pipeline, check audit.md Sections 12 and 16
- If the bug is in the HLS/video processing, check audit.md Section 9
- If the bug involves slug generation, check audit.md Section 13
- If the bug is security-related, escalate to P1 immediately

### Workflow: Add Feature

**Trigger:** User requests new functionality.

**Steps:**

1. **Read** — Read README.md Section 15 "Common Tasks" to identify which files to modify
2. **Plan** — Identify all files that need changes. Check for edge cases and breaking changes
3. **Implement** — Follow existing patterns (Rule 2: Follow Existing Patterns)
4. **Test** — Verify the feature works end-to-end. Check responsive design if UI changes
5. **Verify** — Run `php -l` on PHP files, `npm run lint` on JS files
6. **Document** — Update `changelog.md` with full description. Update `audit.md` if new patterns are introduced

**Files to modify:** Based on README.md Section 15:

| Task | Files |
|------|-------|
| Add new admin tab | `views/admin/index.php` + `vue_enhance.js` + `routes/api.php` + new Controller |
| Add new page | `routes/web.php` + new Controller + new View |
| Add new API endpoint | `routes/api.php` + new Controller method |
| Add new model | `app/Models/NewModel.php` (auto-loaded by glob) |
| Add new service | `app/Services/NewService.php` (auto-loaded by glob) |
| Add Shadcn component | Create HTML using CSS vars (`--surface`, `--accent`, etc.) in relevant view |
| Add database column | `schema.sql` + `ALTER TABLE` in `migrations/` |

### Workflow: Refactor

**Trigger:** User requests code improvement without changing behavior.

**Steps:**

1. **Read** — Read the entire file(s) to be refactored
2. **Understand** — Map all callers and dependencies of the code being changed
3. **Refactor** — Make changes that preserve external behavior
4. **Verify** — Run `php -l`. Verify no behavior change by testing the same inputs produce the same outputs
5. **Document** — Update `changelog.md` with "Refactor:" prefix

**Special considerations:**
- Never refactor security-critical code without explicit user confirmation
- Always keep backward compatibility unless user explicitly approves breaking changes
- If refactoring touches multiple files, verify each file independently

### Workflow: Delete Feature

**Trigger:** User requests removal of functionality.

**Steps:**

1. **Read** — Read all files that reference the feature being deleted
2. **Understand** — Map all dependencies: models, controllers, routes, views, JS, CSS
3. **Remove** — Delete the feature code and all references
4. **Update** — Update any documentation, routes, menus, or navigation that referenced the feature
5. **Verify** — Run `php -l`. Test that removing the feature doesn't break unrelated functionality
6. **Document** — Update `changelog.md` with "Removed:" prefix. Update `README.md` if architecture changed

**Special considerations:**
- Check for orphaned references in routes, views, and JS
- Check for database schema changes needed (e.g., dropping columns)
- Check for systemd service or cron job changes needed

### Workflow: Production Incident

**Trigger:** Bug affecting live users right now.

**Steps:**

1. **Assess** — Determine severity (P1 or P2). Check if site is down, data is lost, or security is breached
2. **Stabilize** — If P1, implement immediate fix or rollback. Don't wait for perfect solution
3. **Fix** — Implement proper fix following the Fix Bug workflow
4. **Verify** — Test the fix in production. Monitor for 15 minutes
5. **Post-mortem** — Write `audit.md` section with full investigation
6. **Document** — Update `changelog.md` with severity and timeline

**P1 SLA:** Fix within 15 minutes. If fix takes longer, implement temporary workaround first.

**P2 SLA:** Fix within 1 hour. Communicate status to user every 15 minutes.

### Workflow: Rollback

**Trigger:** Recent change caused problems.

**Steps:**

1. **Identify** — Use `git log` to find the problematic commit(s)
2. **Assess** — Determine if full rollback or partial revert is needed
3. **Revert** — Use `git revert` or manually undo the changes
4. **Verify** — Test that the rollback fixes the problem without breaking other features
5. **Document** — Update `changelog.md` with "Rollback:" prefix and reason

**Special considerations:**
- Never `git push` without explicit user permission
- If the rollback involves database schema changes, create a migration to undo them
- Check if other changes depend on the rolled-back code

### Workflow: Breaking Change

**Trigger:** User requests change that affects existing functionality.

**Steps:**

1. **Assess** — List all features/code that will be affected
2. **Warn** — Present the impact to the user clearly
3. **Confirm** — Get explicit user override confirmation (see Override Protocol above)
4. **Implement** — Make the breaking change
5. **Migrate** — Provide migration path for affected data/code
6. **Document** — Update `changelog.md` with "⚠️ Breaking Change:" prefix

**Special considerations:**
- Breaking changes require explicit user confirmation — AI cannot decide alone
- Always provide a migration guide or script
- Consider backward compatibility — can the old behavior be preserved with a flag?

---

## 🧠 Known Gotchas & Lessons Learned

> Extracted from `audit.md` and `README.md`. AI agents MUST be aware of these before making changes.

### Gotcha 1: Two Entry Points (Legacy vs New) [README.md 10.1]

The project has TWO `index.php` files:
- **`public/index.php`** (55 lines) — NEW MVC entry point. **This is what nginx uses.**
- **`index.php`** (997 lines) — LEGACY monolith. **NOT used by nginx.**

**Rule:** When modifying routes or controllers, **only** modify files in `public/`, `app/`, `controllers/`, `routes/`, `views/`. Never modify root `index.php` or `config.php` for the live site.

### Gotcha 2: Media Never Served Directly by Nginx [README.md 10.3]

All media requests go through PHP:
- `/protected-media/*` → nginx rewrites to `?page=media&path=...`
- `?page=poster&path=...` → PHP serves poster images (public)
- `?page=preview&path=...` → PHP serves preview clips (public)
- `?page=media&path=...` → PHP serves protected media (token/admin required)

**Rule:** Never serve media files directly. Always use the PHP media delivery endpoints.

### Gotcha 3: Background Workers — Fire and Forget [audit.md 9.6]

- `arsip-hls-worker` handles the entire pipeline end-to-end
- Fired via `setsid nohup ... &` (orphaned background process)
- The `job_queue` table exists but is **NOT the primary path**
- **Lesson:** A job queue without a consumer is worse than no queue

**Rule:** Always fire `arsip-hls-worker` directly. Don't rely on the job queue.

### Gotcha 4: localStorage Cannot Store File Objects [audit.md 16.6]

The upload queue is saved to `localStorage` for resume support. But `File` objects cannot be serialized to JSON. After page reload, `file: null` causes upload to fail.

**Rule:** Always handle `file: null` gracefully in upload code. Show clear message to user.

### Gotcha 5: Slug Format is a Contract [audit.md 13]

Slug generation must produce media-delivery-compatible slugs. The regex `^[a-z0-9-]+/...` requires:
- Only lowercase alphanumeric and hyphens
- No leading/trailing hyphens
- No consecutive hyphens

**Rule:** Always test slug generation with edge-case titles: `(2014) German...`, `-test-`, `_test_`.

### Gotcha 6: CSS Load Order Conflict [README.md 10.12]

Tailwind CDN injects a `<style>` tag at the END of `<head>`, overriding our input styles. **Moving `<link>` doesn't help** because the `<script>` injects at runtime.

**Rule:** Use `!important` on critical input/color overrides at the end of `style.css`.

### Gotcha 7: Assembly Validation Before DB Insert [audit.md 16.6]

Always validate file integrity BEFORE creating database records. A corrupt file with a DB record causes cascading failures in background workers.

**Rule:** Check file size, MIME type, and integrity before `$this->videoModel->create()`.

### Gotcha 8: Rate Limiting — Two Approaches [README.md 10.10]

1. `RateLimitMiddleware` class — used by `AnalyticsApiController`
2. `rate_limit()` function — available globally

Both use the same file-based approach. **Use the function for simple cases, the middleware for complex ones.**

### Gotcha 9: Telegram Has Two Implementations [README.md 10.9]

1. `app/Services/TelegramNotifier.php` — admin panel API
2. `cli/notify_video_ready.php` + `lib/Telegram.php` — CLI script for HLS worker

**Rule:** Use `TelegramNotifier` for web requests, `lib/Telegram.php` for CLI scripts.

### Gotcha 10: Dual Token API Paths [README.md 10.8]

Token management has two paths:
1. `?op=token_list/create/toggle/delete` → JSON API (used by `vue_enhance.js`)
2. `?page=token-create/toggle/delete` → Form-based fallback

**Rule:** Use the `?op=` JSON API path for new features. The `?page=` path is legacy fallback.

---

## 📐 Architecture Quick Reference

### Directory Structure (Abbreviated)

```
arsip-layar/
├── public/                    ← Document root (nginx serves from here)
│   ├── index.php              ← Front controller (ALL web requests)
│   ├── api.php                ← API entry point for vue_enhance.js
│   ├── sw.js                  ← Service worker (PWA)
│   └── assets/
│       ├── css/style.css      ← Main stylesheet (~1370 lines, Shadcn dark-first)
│       └── js/vue_enhance.js  ← Main frontend JS (~2100 lines, Vue 3 CDN)
│
├── app/                       ← Core application layer (auto-loaded by bootstrap)
│   ├── bootstrap.php          ← Init: constants, helpers, DB, models, services, session
│   ├── helpers.php            ← 30+ pure functions: e(), csrf, setting, totp, rate_limit
│   ├── Database/
│   │   └── Connection.php     ← DB singleton + prepared statement helpers
│   ├── Http/
│   │   ├── Request.php        ← Wraps $_GET, $_POST, $_FILES, $_SERVER
│   │   └── Response.php       ← JSON, redirect, view, download, serveMedia, error
│   ├── Middleware/
│   │   ├── AuthMiddleware.php ← requireAdmin(), isAdmin()
│   │   ├── CsrfMiddleware.php ← validate(), validateApi()
│   │   └── RateLimitMiddleware.php ← File-based rate limiter
│   ├── Models/                ← 11 thin DB models (auto-loaded via glob)
│   └── Services/              ← 8 business logic services (auto-loaded via glob)
│
├── controllers/               ← 11 route handlers (auto-loaded via glob)
├── routes/                    ← web.php, api.php, webhook.php
├── views/                     ← PHP templates (plain PHP, no engine)
├── cli/                       ← CLI scripts (health_check, run_jobs)
├── media/                     ← Video storage (denied by nginx, served via PHP)
├── storage/                   ← Backups, cache, temp uploads
├── systemd/                   ← Service/timer units
├── schema.sql                 ← Database schema (idempotent CREATE TABLE)
├── README.md                  ← Technical reference
├── audit.md                   ← Investigation reports
├── changelog.md               ← Change history
└── rules.md                   ← This file — AI agent guidelines
```

### Important Patterns [README.md Section 15]

| # | Pattern | Detail |
|---|---------|--------|
| 1 | **Auto-loading** | New files in `app/Models/`, `app/Services/`, `controllers/` are auto-loaded via `glob()` in `bootstrap.php`. No manual registration needed. |
| 2 | **Type strings** | Must match param count exactly. When using `$conn->insert()` or `$conn->execute()`, verify type string length equals parameter array count. |
| 3 | **Auth first** | Controllers call `AuthMiddleware::requireAdmin()` FIRST for admin-only actions. |
| 4 | **CSRF always** | `CsrfMiddleware::validate()` on ALL POST requests. |
| 5 | **Settings** | Use `setting()` and `set_setting()` — NOT raw queries. Cache is in-memory, updates immediately. |
| 6 | **Logging** | Use `log_activity()` or `log_activity_diff()` — NOT raw inserts to `activity_log`. |
| 7 | **Background tasks** | Fire `arsip-hls-worker` directly via `setsid nohup`. The worker handles the entire pipeline. |
| 8 | **API responses** | Always use `Response::json()` — never `echo json_encode()` directly. |
| 9 | **Views** | Plain PHP templates. Use `e()` for escaping, `csrf()` for CSRF tokens, `setting()` for config. |
| 10 | **CSS variables** | Use Shadcn CSS vars (`--surface`, `--accent`, `--ink`, etc.) — not hardcoded colors. |

### Common Tasks → Files to Modify [README.md Section 15]

| Task | Files to Modify |
|------|-----------------|
| Add new admin tab | `views/admin/index.php` + `vue_enhance.js` + `routes/api.php` + new Controller |
| Add new page | `routes/web.php` + new Controller + new View |
| Add new API endpoint | `routes/api.php` + new Controller method |
| Add new model | `app/Models/NewModel.php` (auto-loaded by glob) |
| Add new service | `app/Services/NewService.php` (auto-loaded by glob) |
| Modify video upload | `app/Services/VideoUpload.php` + `controllers/VideoController.php` |
| Modify chunked upload | `routes/api.php`, `controllers/VideoController.php`, `app/Services/VideoUpload.php` |
| Modify video player | `views/pages/watch.php` + `public/assets/js/vue_enhance.js` |
| Modify theme colors | `public/assets/css/style.css` (CSS custom properties in `:root`) |
| Add Shadcn component | Create HTML using CSS vars in relevant view |
| Add database column | `schema.sql` + `ALTER TABLE` in `migrations/` |

---

## 📚 Cross-Reference Index

| Topic | rules.md | README.md | audit.md | changelog.md |
|-------|----------|-----------|----------|--------------|
| Security rules | 🔒1 | Section 9 | Section 6 | Section 1 (Security) |
| Upload pipeline | Gotcha 4, 7 | Section 7.6, 10.11 | Section 12, 16 | 2026-08-21 (Bulk Upload, Pipeline Fixes) |
| HLS / video processing | Gotcha 3 | Section 7.6, 10.4 | Section 9 | 2026-08-20 (HLS Fix) |
| Slug validation | Gotcha 5 | Section 10.3 | Section 13 | 2026-08-21 (Slug Fix) |
| CSS / theming | Gotcha 6 | Section 10.12 | Section 7, 15 | 2026-08-20 (UI Overhaul), 2026-08-21 (Input Text Fix) |
| Token system | Gotcha 10 | Section 7.5 | Section 14 | 2026-08-21 (Token User Menu) |
| Health checks | — | Section 10.4 | Section 16 | 2026-08-21 (Slug Fix + Health Check) |
| Telegram notifications | Gotcha 9 | Section 10.9 | — | — |
| Rate limiting | Gotcha 8 | Section 10.10 | Section 3 (Fix #3) | 2026-08-20 (Security) |

---

*Last updated: August 21, 2026*
*Source documents: README.md (v1041 lines), audit.md (v1136 lines), changelog.md (v772 lines)*
