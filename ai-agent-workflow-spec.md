# Spec: AI Agent Workflow — VPS Server & Repository Management

**Purpose:** Add a comprehensive workflow section to `rules.md` that describes how AI agents work in the VPS server codebase and manage the Git repository automatically.

---

## 1. Overview

### 1.1 What This Section Does
Defines the complete end-to-end workflow for AI agents working in the Arsip Layar codebase on this VPS server. Covers:
- Receiving and processing user requests
- Working in the codebase
- Verifying changes
- Managing the Git repository (branch, commit, push, merge)
- Handling edge cases (errors, conflicts, rollbacks)

### 1.2 Where to Add
Add as a new top-level section in `rules.md` after "📋 Detailed Protocols" and before "🧠 Known Gotchas & Lessons Learned".

### 1.3 Section Title
**"🤖 AI Agent Workflow — VPS Server & Repository"**

---

## 2. Content Requirements

### 2.1 Section Structure

The section should contain:

1. **Overview** — Brief description of the workflow
2. **End-to-End Flow Table** — Markdown table showing each step
3. **Repository Management Rules** — How AI manages Git automatically
4. **Edge Cases** — How to handle errors, conflicts, rollbacks
5. **Current VPS Configuration** — Server details and Git config

### 2.2 End-to-End Flow Table

Format: Markdown table with columns:
- Step number
- Action
- Description
- When to do it
- Notes/details

**Steps to include:**

| Step | Action | Description | When |
|------|--------|-------------|------|
| 1 | Receive Request | AI receives task from user | Always |
| 2 | Read rules.md | Understand the protocol | Always |
| 3 | Read Context | Read relevant source files, README, audit, changelog | Always |
| 4 | Identify Workflow | Determine: fix bug, add feature, refactor, etc. | Always |
| 5 | Identify Severity | Classify P1-P4 | Always |
| 6 | Pull Latest | `git pull origin master` | When needed (AI decides) |
| 7 | Create Branch | `git checkout -b <type>/<name>` | For feature work |
| 8 | Implement | Make code changes | Always |
| 9 | Verify | Run `php -l`, `npm run lint`, tests | Always |
| 10 | Document | Update changelog.md, audit.md | Always |
| 11 | Commit | `git add` + `git commit` | Always |
| 12 | Push | `git push` | Always |
| 13 | Merge to Master | `git checkout master` + `git merge` | After feature complete |
| 14 | Push Master | `git push origin master` | After merge |
| 15 | Cleanup Branch | Delete feature branch | After merge |
| 16 | Report | Report completion to user | Always |

### 2.3 Repository Management Rules

**Key principle:** AI agent manages the repository FULLY AUTOMATICALLY. No user confirmation needed for:
- `git add`
- `git commit`
- `git push`
- `git checkout -b` (create branch)
- `git merge`
- `git branch -d` (delete branch)

**User permission required for:**
- `git revert` (reverting changes)
- `git reset` (destructive)
- `git rebase` (rewrites history)

**AI decides when to:**
- Pull from remote
- Create feature branch
- Commit changes
- Push to remote
- Merge and cleanup

### 2.4 Edge Cases

| Scenario | AI Action |
|----------|-----------|
| Push fails (remote changed) | Pull, resolve conflict, push again |
| Merge conflict | Resolve conflict, test, commit, push |
| User requests rollback | Ask for confirmation, then `git revert` |
| PHP syntax error after commit | Fix error, amend commit, force push (if not shared) |
| ESLint errors | Fix errors before commit |
| Broken feature branch | Delete branch, start fresh |
| Multiple features in progress | Use separate feature branches for each |
| Production incident | Create hotfix branch, fix, merge to master, push |

### 2.5 VPS Configuration

| Setting | Value |
|---------|-------|
| Server | Linux VPS |
| App Root | `/var/www/arsip-layar` |
| Document Root | `/var/www/arsip-layar/public` |
| Git User | `Xboxchillz` |
| Git Email | `alvin.krisdianto69@gmail.com` |
| SSH Key | `~/.ssh/id_ed25519` |
| Remote | `git@github.com:frnshaxor/Xboxchillz.git` |
| Default Branch | `master` |
| Web Server | Nginx |
| PHP | 8.5.4 (FPM) |
| Database | MySQL/MariaDB |

---

## 3. Implementation Steps

### Step 1: Create the new section in rules.md

Add after "📋 Detailed Protocols" section:

```markdown
## 🤖 AI Agent Workflow — VPS Server & Repository

> AI agents work directly on this VPS server and manage the repository automatically.
> Follow this workflow for ALL tasks.

### End-to-End Workflow

| Step | Action | Description | When |
|------|--------|-------------|------|
| 1 | Receive Request | AI receives task from user | Always |
| 2 | Read rules.md | Understand the protocol | Always |
| 3 | Read Context | Read relevant source files, README, audit, changelog | Always |
| 4 | Identify Workflow | Determine: fix bug, add feature, refactor, etc. | Always |
| 5 | Identify Severity | Classify P1-P4 | Always |
| 6 | Pull Latest | `git pull origin master` | When needed |
| 7 | Create Branch | `git checkout -b <type>/<name>` | For feature work |
| 8 | Implement | Make code changes | Always |
| 9 | Verify | Run `php -l`, `npm run lint`, tests | Always |
| 10 | Document | Update changelog.md, audit.md | Always |
| 11 | Commit | `git add` + `git commit` | Always |
| 12 | Push | `git push` | Always |
| 13 | Merge to Master | `git checkout master` + `git merge` | After feature complete |
| 14 | Push Master | `git push origin master` | After merge |
| 15 | Cleanup Branch | Delete feature branch | After merge |
| 16 | Report | Report completion to user | Always |

### Repository Management

**AI agent manages the repository FULLY AUTOMATICALLY:**

| Operation | Permission | Notes |
|-----------|-----------|-------|
| `git add` | ✅ Auto | AI stages only relevant files |
| `git commit` | ✅ Auto | AI commits after successful work |
| `git push` | ✅ Auto | AI pushes after commit |
| `git checkout -b` | ✅ Auto | AI creates feature branches |
| `git merge` | ✅ Auto | AI merges feature branches to master |
| `git branch -d` | ✅ Auto | AI deletes merged branches |
| `git pull` | ✅ Auto | AI pulls when needed |
| `git revert` | 🔒 User permission | Reverting affects production |
| `git reset` | 🔒 User permission | Destructive — can lose work |
| `git rebase` | 🔒 User permission | Rewrites history |

### Edge Cases

| Scenario | AI Action |
|----------|-----------|
| Push fails (remote changed) | Pull, resolve conflict, push again |
| Merge conflict | Resolve conflict, test, commit, push |
| User requests rollback | Ask for confirmation, then `git revert` |
| PHP syntax error after commit | Fix error, amend commit, push |
| ESLint errors | Fix errors before commit |
| Broken feature branch | Delete branch, start fresh |
| Multiple features | Use separate feature branches |
| Production incident | Create hotfix branch, fix, merge, push |

### Current VPS Configuration

| Setting | Value |
|---------|-------|
| App Root | `/var/www/arsip-layar` |
| Git User | `Xboxchillz` |
| Git Email | `alvin.krisdianto69@gmail.com` |
| Remote | `git@github.com:frnshaxor/Xboxchillz.git` |
| Default Branch | `master` |
| Web Server | Nginx (port 80) |
| PHP | 8.5.4 (FPM) |
| Database | MySQL/MariaDB |
```

### Step 2: Update changelog.md

Add entry documenting the new workflow section.

### Step 3: Update cross-references

Update the Cross-Reference Index at the end of rules.md to include the new section.

---

## 4. Verification Checklist

- [ ] New section added to rules.md after "Detailed Protocols"
- [ ] End-to-end workflow table has 16 steps
- [ ] Repository management rules clearly define auto vs permission required
- [ ] Edge cases table covers 8 scenarios
- [ ] VPS configuration table has correct values
- [ ] changelog.md updated with new entry
- [ ] Cross-reference index updated
- [ ] No existing content broken
- [ ] File passes markdown linting (if available)

---

## 5. Example Flow

**User request:** "Fix poster tidak muncul di watch page"

**AI agent flow:**

1. ✅ Receive request
2. ✅ Read rules.md (this file)
3. ✅ Read relevant files: `views/pages/watch.php`, `controllers/MediaController.php`, `app/Services/MediaService.php`
4. ✅ Identify workflow: Fix Bug
5. ✅ Identify severity: P2 (High)
6. ✅ Pull latest: `git pull origin master` (if needed)
7. ✅ Create branch: `git checkout -b fix/poster-not-loading`
8. ✅ Implement: Find and fix the bug
9. ✅ Verify: `php -l`, test poster loads
10. ✅ Document: Update changelog.md
11. ✅ Commit: `git add .` + `git commit -m "fix(media): perbaiki poster tidak muncul di watch page"`
12. ✅ Push: `git push -u origin fix/poster-not-loading`
13. ✅ Merge: `git checkout master` + `git merge fix/poster-not-loading`
14. ✅ Push master: `git push origin master`
15. ✅ Cleanup: `git branch -d fix/poster-not-loading` + `git push origin --delete fix/poster-not-loading`
16. ✅ Report: "Poster bug fixed. Commit: abc1234"
