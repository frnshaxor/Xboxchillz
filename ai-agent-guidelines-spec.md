# Spec: AI Agent Guidelines (`rules.md`)

**Purpose:** Create a single `rules.md` file at project root that serves as the authoritative guide for ALL AI agents working in the Arsip Layar codebase. This file connects to `README.md`, `audit.md`, and `changelog.md` as its source of truth.

---

## 1. Overview

### 1.1 What This File Is
A comprehensive, self-contained protocol document that every AI agent MUST read before performing ANY work in this codebase. It consolidates rules, workflows, verification procedures, and lessons learned from the three reference documents.

### 1.2 What This File Is NOT
- NOT a replacement for README.md, audit.md, or changelog.md — it REFERENCES them
- NOT a tutorial — it's a protocol/checklist
- NOT optional — compliance is mandatory

### 1.3 Relationship to Existing Docs

| Document | Role | When AI Reads It |
|----------|------|------------------|
| `rules.md` (NEW) | Quick reference + protocol | **BEFORE every task** |
| `README.md` | Technical reference (architecture, patterns, gotchas) | When needed for context |
| `audit.md` | Investigation reports (bugs, security, features) | When debugging or implementing related features |
| `changelog.md` | Change history | When documenting changes, checking what was done before |

---

## 2. File Structure (Target Format)

The file should follow a **Hybrid format**: checklist at top (quick reference), detailed protocol below.

```
# Arsip Layar — AI Agent Guidelines

## ⚡ Quick Reference (Checklist)
  - Pre-task checklist
  - Post-task checklist
  - Severity levels
  - Override protocol

## 🔒 Non-Negotiable Rules (NEVER override)
  - Security rules
  - Documentation rules

## 📋 Detailed Protocols
  ### Workflow: Fix Bug
  ### Workflow: Add Feature
  ### Workflow: Refactor
  ### Workflow: Delete Feature
  ### Workflow: Production Incident
  ### Workflow: Rollback
  ### Workflow: Breaking Change

## 🧠 Known Gotchas & Lessons Learned
  - From audit.md Section 9 (HLS Status Bug)
  - From audit.md Section 12 (Bulk Upload)
  - From audit.md Section 13 (Slug Validation)
  - From audit.md Section 16 (Upload Pipeline Bugs)
  - From README.md Section 10 (Known Issues & Gotchas)

## 📐 Architecture Quick Reference
  - Directory structure (abbreviated)
  - Important patterns (from README.md Section 15)
  - Common tasks → files to modify (from README.md Section 15)
```

---

## 3. Content Requirements

### 3.1 Quick Reference Checklist

**Pre-Task Checklist (AI MUST complete before starting ANY work):**
```markdown
- [ ] Read `rules.md` (this file) — understand the protocol
- [ ] Read `README.md` — understand architecture and patterns
- [ ] Read `audit.md` — check for related findings/lessons
- [ ] Read `changelog.md` — check what was done before
- [ ] Identify which workflow applies (fix bug / add feature / etc.)
- [ ] Identify severity level (P1-P4)
- [ ] Confirm no breaking changes (or get user override)
```

**Post-Task Checklist (AI MUST complete after finishing ANY work):**
```markdown
- [ ] All modified files pass `php -l`
- [ ] ESLint passes (if frontend changes): `npm run lint`
- [ ] No debug code left (`var_dump`, `error_log`, `dd()`)
- [ ] `changelog.md` updated with new entry
- [ ] `audit.md` updated (if new findings discovered)
- [ ] `README.md` updated (if architecture changed)
- [ ] Cross-references added (changelog ↔ audit)
- [ ] Backup created (if major changes)
- [ ] Type strings match param counts (if DB changes)
```

### 3.2 Severity Levels

Define clear severity levels with SLAs:

| Level | Name | Description | SLA | Example |
|-------|------|-------------|-----|---------|
| P1 | Critical | Site down, data loss, security breach | Fix within 15 minutes | SQL injection, production outage |
| P2 | High | Feature broken, user-facing bug | Fix within 1 hour | Upload broken, payment failed |
| P3 | Medium | Minor bug, UI glitch | Fix within 24 hours | Typo, color mismatch |
| P4 | Low | Enhancement, optimization | Fix when convenient | Performance improvement, code cleanup |

### 3.3 Override Protocol

**All rules CAN be overridden by user request**, but AI agent MUST:
1. **Explain briefly but in detail** what rule is being overridden
2. **Explain the risks** of overriding (what could go wrong)
3. **Get explicit confirmation** from user before proceeding
4. **Document the override** in changelog.md entry

**Exception:** Rules marked as 🔒 NON-NEGOTIABLE cannot be overridden even by user request. AI must refuse and explain why.

### 3.4 Non-Negotiable Rules (🔒 NEVER override)

These rules CANNOT be overridden by user request. AI must refuse and explain:

1. **Security (Rule 5):** Never output user input without `e()` escaping, never use raw SQL, never store secrets in code
2. **Documentation (Rule 7):** Always update changelog.md after every implementation
3. **Syntax Check (Rule 4):** Always run `php -l` on modified PHP files
4. **No Debug Code:** Never leave `var_dump`, `error_log`, `dd()` in production files

### 3.5 Workflows

Each workflow should have:
1. **Trigger:** When to use this workflow
2. **Steps:** Numbered list of actions
3. **Files to check/modify:** Based on README.md Section 15
4. **Verification:** Specific checks for this workflow
5. **Documentation:** What to update in changelog.md and audit.md

**Workflows to include:**

#### Fix Bug
- Trigger: User reports a bug or AI discovers one
- Steps: Read → Understand → Reproduce → Fix → Verify → Document
- Special: Check audit.md for related investigation reports

#### Add Feature
- Trigger: User requests new functionality
- Steps: Read → Plan → Implement → Test → Verify → Document
- Special: Check README.md Section 15 for "Common Tasks" mapping

#### Refactor
- Trigger: User requests code improvement without changing behavior
- Steps: Read → Understand → Refactor → Verify no behavior change → Document
- Special: Run full test suite if available

#### Delete Feature
- Trigger: User requests removal of functionality
- Steps: Read → Understand dependencies → Remove → Update references → Document
- Special: Check for orphaned references in other files

#### Production Incident
- Trigger: Bug affecting live users
- Steps: Assess severity → Fix immediately → Verify → Post-mortem → Document
- Special: Follow P1/P2 SLA

#### Rollback
- Trigger: Recent change caused problems
- Steps: Identify change → Revert → Verify → Document reason
- Special: Use git to identify and revert specific commits

#### Breaking Change
- Trigger: User requests change that affects existing functionality
- Steps: Assess impact → Warn user → Get confirmation → Implement → Document migration
- Special: Must get explicit user override confirmation

### 3.6 Known Gotchas & Lessons Learned

Extract and summarize from audit.md:

**From Section 9 (HLS Status Bug):**
- Job queue without consumer is worse than no queue
- Fire-and-forget + immediate markDone is unreliable
- Always run background workers synchronously or add verification

**From Section 12 (Bulk Upload):**
- localStorage cannot store File objects — handle `file: null` gracefully
- Error state needs TTL — auto-clear stale errors
- Retry mechanisms need limits — prevent infinite loops
- Abort should be atomic — await server response

**From Section 13 (Slug Validation):**
- Slug generation must produce media-delivery-compatible slugs
- Defense in depth matters — generator and consumer should both be correct
- Test with edge-case titles (special chars at start)

**From Section 16 (Upload Pipeline Bugs):**
- Assembly validation before DB insert — prevent corrupt files
- Health checks should cover temp storage
- Race conditions in async operations need flags/locks

**From README.md Section 10 (Known Issues):**
- Two entry points (legacy vs new) — only `public/index.php` is used by nginx
- Media never served directly by nginx — always through PHP
- Background tasks use `setsid nohup` — not job queue
- CSRF for API calls uses FormData with csrf field
- Settings cache is in-memory — `set_setting()` updates immediately

### 3.7 Architecture Quick Reference

**Abbreviated directory structure** (from README.md Section 3):
```
public/          → Document root (nginx)
app/             → Core application (auto-loaded)
  Models/        → 11 DB models
  Services/      → 8 business logic services
  controllers/   → 11 route handlers
  Middleware/     → Auth, CSRF, RateLimit
views/           → PHP templates
routes/          → web.php, api.php, webhook.php
cli/             → CLI scripts (health_check, run_jobs)
media/           → Video storage (denied by nginx)
```

**Important patterns** (from README.md Section 15):
1. New files in Models/, Services/, controllers/ are auto-loaded via glob()
2. Type strings must match param count exactly
3. Controllers call AuthMiddleware::requireAdmin() first for admin routes
4. CSRF validated on ALL POST requests
5. Settings use setting()/set_setting() — not raw queries
6. Logging uses log_activity() — not raw inserts
7. Background tasks fire arsip-hls-worker directly via setsid nohup
8. API responses always use Response::json()
9. Views are plain PHP — use e() for escaping

**Common tasks → files to modify** (from README.md Section 15):

| Task | Files to Modify |
|------|-----------------|
| Add new admin tab | `views/admin/index.php` + `vue_enhance.js` + `routes/api.php` + new Controller |
| Add new page | `routes/web.php` + new Controller + new View |
| Add new API endpoint | `routes/api.php` + new Controller method |
| Add new model | `app/Models/NewModel.php` (auto-loaded) |
| Add new service | `app/Services/NewService.php` (auto-loaded) |
| Modify video upload | `app/Services/VideoUpload.php` + `controllers/VideoController.php` |
| Modify chunked upload | `routes/api.php`, `controllers/VideoController.php`, `app/Services/VideoUpload.php` |
| Modify video player | `views/pages/watch.php` + `public/assets/js/vue_enhance.js` |
| Modify theme colors | `public/assets/css/style.css` (CSS custom properties) |
| Add Shadcn component | Create HTML using CSS vars in relevant view |
| Add database column | `schema.sql` + `ALTER TABLE` in migration |

---

## 4. Writing Guidelines

### 4.1 Tone
- Direct and actionable — no fluff
- Use imperative mood ("Read this", "Run that", "Never do X")
- Bold for emphasis on critical rules

### 4.2 Format
- Use markdown headers for sections
- Use tables for structured data
- Use code blocks for commands and templates
- Use emoji prefixes for severity: 🔒 (non-negotiable), 🔴 (critical), 🟠 (high), 🟡 (medium), 🟢 (low)

### 4.3 Length Target
- Quick Reference section: ~1 page (scannable)
- Detailed Protocols: ~3-4 pages
- Gotchas & Lessons: ~1-2 pages
- Architecture Quick Reference: ~1 page
- **Total: ~6-8 pages**

### 4.4 Cross-References
Every section should reference the source document:
- `[README.md Section X.Y]` for architecture/patterns
- `[audit.md Section X]` for investigation reports
- `[changelog.md YYYY-MM-DD]` for change history

---

## 5. Implementation Steps

1. **Create `rules.md` at project root** with the structure defined in Section 2
2. **Populate Quick Reference checklist** from Section 3.1
3. **Define severity levels** from Section 3.2
4. **Write override protocol** from Section 3.3
5. **List non-negotiable rules** from Section 3.4
6. **Write all 7 workflows** from Section 3.5
7. **Extract gotchas** from audit.md and README.md (Section 3.6)
8. **Add architecture quick reference** from Section 3.7
9. **Add cross-references** to source documents throughout
10. **Update README.md** to reference `rules.md` in Section 1 (Strict Rules) and Section 15 (Quick Reference)
11. **Update changelog.md** with new entry documenting creation of rules.md

---

## 6. Acceptance Criteria

- [ ] File is named `rules.md` and placed at project root
- [ ] Written in English (consistent with technical docs)
- [ ] Hybrid format: checklist at top, detailed protocol below
- [ ] Covers ALL 7 workflows (fix bug, add feature, refactor, delete feature, production incident, rollback, breaking change)
- [ ] Includes severity levels (P1-P4) with SLAs
- [ ] Override protocol allows user override with AI explanation
- [ ] Non-negotiable rules clearly marked with 🔒
- [ ] Gotchas extracted from audit.md (Sections 9, 12, 13, 16)
- [ ] Gotchas extracted from README.md (Section 10)
- [ ] Architecture quick reference from README.md (Sections 3, 15)
- [ ] Cross-references to README.md, audit.md, changelog.md throughout
- [ ] README.md updated to reference rules.md
- [ ] changelog.md updated with creation entry
- [ ] File passes markdown linting (if available)
