# Arsip Layar — AI Agent Guidelines (AUTO-LOAD)

> **MANDATORY READING** — This file is auto-loaded before every task.
> Full reference: `rules.md` at project root.

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

**🔒 EXCEPTION — These rules CANNOT be overridden even by user request:**

| # | Rule | Why It's Non-Negotiable |
|---|------|------------------------|
| 🔒1 | **Security (Rule 5)** | Never output user input without `e()` escaping, never use raw SQL, never store secrets in code |
| 🔒2 | **Documentation (Rule 7)** | Always update `changelog.md` after every implementation |
| 🔒3 | **Syntax Check (Rule 4)** | Always run `php -l` on modified PHP files |
| 🔒4 | **No Debug Code** | Never leave `var_dump`, `error_log`, `dd()` in production |

---

## 🔒 Non-Negotiable Rules (NEVER override)

### 🔒1. Security First

- **NEVER** output user input without `e()` escaping — XSS vulnerability
- **NEVER** use raw SQL — always use prepared statements with `bind_param`
- **NEVER** store secrets in code — use the `settings` table via `setting()`/`set_setting()`
- **ALWAYS** validate file uploads: extension (`.mp4` only), MIME (finfo + ftyp), size (`upload_max_mb`)
- **ALWAYS** use `escapeshellarg()` for shell commands
- **ALWAYS** check `realpath()` for path traversal prevention

### 🔒2. Documentation Required

- **`changelog.md`**: Add entry with date, description, files modified, audit section reference
- **`audit.md`**: Add investigation section with symptom, root cause, methodology, fixes, verification, lessons learned
- **`README.md`**: Update relevant sections (architecture, gotchas, patterns) if the fix changes understanding
- **Cross-reference**: Every changelog entry must reference the audit section, and vice versa

### 🔒3. Syntax Verification

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

## 🔬 Static Analysis Protocol

### Tools

| Tool | Command | Purpose |
|------|---------|--------|
| PHPStan | `vendor/bin/phpstan analyse --level=0` | Static analysis — type safety |
| PHP-CS-Fixer | `vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php --dry-run --diff` | Code formatting check |

### When to Run

| Change Type | Run PHPStan? | Run PHP-CS-Fixer? |
|-------------|-------------|-------------------|
| New PHP class | ✅ YES | ✅ YES |
| Modify existing PHP | ✅ YES | ✅ YES |
| CSS-only changes | ❌ No | ❌ No |
| JS-only changes | ❌ No | ❌ No |
| Config changes | ❌ No | ❌ No |

---

## 🔄 CI/CD Protocol

### Pipeline Jobs

| Job | Command | When to Run |
|-----|---------|-------------|
| PHP Syntax | `php -l` on all files | Every push/PR |
| ESLint | `npm run lint` | Every push/PR |
| PHPUnit Tests | `vendor/bin/phpunit` | Every push/PR |
| Final Verification | Aggregates all checks | After all jobs complete |

### CI Workflow

1. **Before push:** Run `php -l`, `npm run lint`, `vendor/bin/phpunit` locally
2. **After push:** GitHub Actions runs automatically
3. **If CI fails:** Fix errors before merging to master
4. **If CI passes:** Safe to merge

---

## 🔍 ESLint Protocol

### ESLint Commands

```bash
npm run lint          # Check for errors/warnings — MUST show 0 errors
npm run lint:fix      # Auto-fix fixable issues
npm run lint:report   # Generate JSON report
```

### Current Baseline

| Metric | Value | Notes |
|--------|-------|-------|
| Errors | **0** | Must stay at 0 |
| Warnings | **25** | Accepted warnings |
| Plugins | 3 | `vue`, `vuejs-accessibility`, `tailwindcss` |

---

## 🎨 CSS Sync Protocol

### CSS Sync Rules

1. **Always edit `public/assets/css/style.css`** — source of truth
2. **After editing, sync to root:** `cp public/assets/css/style.css style.css`
3. **Never edit `style.css` directly**
4. **CSS changes must include:** Shadcn CSS variables, responsive breakpoints, `!important` overrides

---

## 🔀 Git Protocol

### Git Rules

| Operation | Permission Required? | Notes |
|-----------|---------------------|-------|
| `git status` | ❌ No | Always safe |
| `git diff` | ❌ No | Always safe |
| `git log` | ❌ No | Always safe |
| `git add` | ⚠️ Careful | Only stage related files |
| `git commit` | ⚠️ Careful | Use conventional commit messages |
| `git revert` | ✅ **User permission** | Affects production |
| `git push` | 🔒 **NEVER without permission** | Can break production |
| `git reset` | 🔒 **NEVER without permission** | Destructive |
| `git rebase` | 🔒 **NEVER without permission** | Rewrites history |

### Commit Message Format

```
<type>(<scope>): <description>

🤖 Generated with Codebuff
Co-Authored-By: Codebuff <noreply@codebuff.com>
```

**Types:** `feat`, `fix`, `refactor`, `docs`, `style`, `chore`

---

## 💬 Communication Protocol

### Response Format

**For Bug Fixes:**
```
## 🔴 Bug Fix: [Brief Title]
**Problem:** [What was broken]
**Root Cause:** [Why it broke]
**Fix:** [What was changed]
**Files Modified:** [List]
**Verification:** [Checks run]
```

**For New Features:**
```
## 🟢 Feature: [Brief Title]
**Description:** [What was added]
**Files Modified:** [List]
**Verification:** [Checks run]
```

---

## 🗄️ Database Change Protocol

### Database Rules

1. **Always use prepared statements** — never raw SQL 🔒
2. **Always use `Connection` class** — never raw `mysqli` 🔒
3. **Type strings must match param count** — verify before executing 🔒
4. **Test migrations** — run `php migrate.php --status` before and after
5. **Backup before major changes**
6. **Check for orphaned references**

### Type String Reference

| Type | PHP Type | MySQL Type |
|------|----------|------------|
| `s` | string | VARCHAR, TEXT, DATE |
| `i` | integer | INT, BIGINT, TINYINT |
| `d` | float | DECIMAL, DOUBLE |
| `b` | blob | BLOB, BINARY |

---

## 📋 Detailed Protocols

### Workflow: Fix Bug

1. **Read** — Read the relevant source files completely
2. **Search** — Check `audit.md` for related investigation reports
3. **Understand** — Trace the data flow: entry point → controller → service → model → DB → response
4. **Reproduce** — Identify the exact conditions that trigger the bug
5. **Root Cause** — Find the actual root cause, not just the symptom
6. **Fix** — Implement the minimal fix that addresses the root cause
7. **Verify** — Run `php -l`, test the fix, check edge cases
8. **Document** — Update `changelog.md` and `audit.md`

### Workflow: Add Feature

1. **Read** — Read README.md Section 15 "Common Tasks"
2. **Plan** — Identify all files that need changes
3. **Implement** — Follow existing patterns
4. **Test** — Verify the feature works end-to-end
5. **Verify** — Run `php -l` on PHP files, `npm run lint` on JS files
6. **Document** — Update `changelog.md` with full description

### Workflow: Refactor

1. **Read** — Read the entire file(s) to be refactored
2. **Understand** — Map all callers and dependencies
3. **Refactor** — Make changes that preserve external behavior
4. **Verify** — Run `php -l`. Verify no behavior change
5. **Document** — Update `changelog.md` with "Refactor:" prefix

### Workflow: Delete Feature

1. **Read** — Read all files that reference the feature
2. **Understand** — Map all dependencies
3. **Remove** — Delete the feature code and all references
4. **Update** — Update documentation, routes, menus
5. **Verify** — Run `php -l`. Test removing doesn't break other features
6. **Document** — Update `changelog.md` with "Removed:" prefix

### Workflow: Production Incident

1. **Assess** — Determine severity (P1 or P2)
2. **Stabilize** — If P1, implement immediate fix or rollback
3. **Fix** — Implement proper fix
4. **Verify** — Test in production. Monitor for 15 minutes
5. **Post-mortem** — Write `audit.md` section
6. **Document** — Update `changelog.md` with severity and timeline

**P1 SLA:** Fix within 15 minutes.
**P2 SLA:** Fix within 1 hour.

---

## 🤖 AI Agent Workflow

### End-to-End Workflow (16 Steps)

| Step | Action | Description |
|------|--------|-------------|
| 1 | **Receive Request** | AI receives task from user |
| 2 | **Read rules.md** | Understand the protocol |
| 3 | **Read Context** | Read relevant source files |
| 4 | **Identify Workflow** | fix bug / add feature / refactor |
| 5 | **Identify Severity** | P1–P4 |
| 6 | **Pull Latest** | `git pull origin master` |
| 7 | **Create Branch** | `git checkout -b <type>/<name>` |
| 8 | **Implement** | Make code changes |
| 9 | **Verify** | Run `php -l`, `npm run lint` |
| 10 | **Document** | Update changelog.md, audit.md |
| 11 | **Commit** | `git add` + `git commit` |
| 12 | **Push** | `git push` to remote |
| 13 | **Merge to Master** | `git checkout master` + `git merge` |
| 14 | **Push Master** | `git push origin master` |
| 15 | **Cleanup Branch** | Delete feature branch |
| 16 | **Report** | Report completion to user |

### Repository Management

| Operation | Permission |
|-----------|-----------|
| `git add`, `commit`, `push`, `checkout -b`, `merge`, `branch -d` | ✅ **Auto** |
| `git revert`, `reset`, `rebase` | 🔒 **User permission** |

---

## 🧠 Known Gotchas

### Gotcha 1: Entry Point
ONE entry point: `public/index.php`. Only modify files in `public/`, `app/`, `controllers/`, `routes/`, `views/`.

### Gotcha 2: Media Never Served Directly
All media requests go through PHP. Never serve media files directly.

### Gotcha 3: Background Workers
Always fire `arsip-hls-worker` directly. Don't rely on the job queue.

### Gotcha 4: localStorage Cannot Store File Objects
Always handle `file: null` gracefully in upload code.

### Gotcha 5: Slug Format is a Contract
Only lowercase alphanumeric and hyphens. No leading/trailing hyphens.

### Gotcha 6: CSS Load Order Conflict
Use `!important` on critical input/color overrides at the end of `style.css`.

### Gotcha 7: Assembly Validation Before DB Insert
Check file size, MIME type, and integrity before `$this->videoModel->create()`.

### Gotcha 8: Rate Limiting — Two Approaches
Use `rate_limit()` function for simple cases, `RateLimitMiddleware` for complex ones.

### Gotcha 9: Telegram Has Two Implementations
Use `TelegramNotifier` for web requests, `lib/Telegram.php` for CLI scripts.

### Gotcha 10: Dual Token API Paths
Use `?op=` JSON API path for new features. `?page=` is legacy fallback.

---

## 📐 Architecture Quick Reference

### Directory Structure

```
arsip-layar/
├── public/                    ← Document root (nginx)
│   ├── index.php              ← Front controller
│   ├── api.php                ← API entry point
│   └── assets/
│       ├── css/style.css      ← Main stylesheet
│       └── js/vue_enhance.js  ← Main frontend JS
├── app/                       ← Core application layer
│   ├── bootstrap.php          ← Init
│   ├── helpers.php            ← Pure functions
│   ├── Database/Connection.php
│   ├── Http/Request.php, Response.php
│   ├── Middleware/             ← Auth, CSRF, RateLimit
│   ├── Models/                ← 12 DB models
│   └── Services/              ← 8 business services
├── controllers/               ← 11 route handlers
├── routes/                    ← web.php, api.php, webhook.php
├── views/                     ← PHP templates
├── cli/                       ← CLI scripts
├── tests/Unit/                ← 10 test files (50 tests)
├── schema.sql                 ← Database schema
├── rules.md                   ← AI agent guidelines
├── README.md                  ← Technical reference
├── audit.md                   ← Investigation reports
└── changelog.md               ← Change history
```

### Important Patterns

1. **Auto-loading** — New files in `app/Models/`, `app/Services/`, `controllers/` auto-loaded via `glob()`
2. **Type strings** — Must match param count exactly
3. **Auth first** — `AuthMiddleware::requireAdmin()` FIRST for admin-only actions
4. **CSRF always** — `CsrfMiddleware::validate()` on ALL POST requests
5. **Settings** — Use `setting()` and `set_setting()` — NOT raw queries
6. **Logging** — Use `log_activity()` or `log_activity_diff()`
7. **Background tasks** — Fire `arsip-hls-worker` directly via `setsid nohup`
8. **API responses** — Always use `Response::json()`
9. **Views** — Plain PHP templates. Use `e()` for escaping
10. **CSS variables** — Use Shadcn CSS vars — not hardcoded colors

---

## 🔗 Document Relationship Map

```
rules.md (PRIMARY REFERENCE)
    │
    ├── README.md (Technical Reference)
    │   ├── Architecture
    │   ├── Database Schema
    │   ├── API Routes
    │   └── Security Measures
    │
    ├── audit.md (Investigation Reports)
    │   ├── Bug Investigations
    │   ├── Security Audits
    │   └── Lessons Learned
    │
    ├── changelog.md (Change History)
    │   └── All changes documented
    │
    └── AUTODEPLOY.md (Server Setup & Recovery)
        └── VPS configuration
```

---

*Last updated: August 21, 2026*
*Source: rules.md (full version at project root)*
