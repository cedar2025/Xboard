# Remove Obsolete Public HTML Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remove five obsolete public HTML entrypoints without changing the download page, AI support compatibility route, login entrypoint, or runtime admin assets.

**Architecture:** This is a static-file retirement only. Delete the five tracked files and rely on the existing deployment/Web server fallback for missing paths; do not add routes, redirects, SEO middleware, or robots rules.

**Tech Stack:** Laravel repository layout, static HTML under `public/`, Node.js built-in test runner, Git

---

### Task 1: Retire the five obsolete static pages

**Files:**
- Delete: `public/pricing.html`
- Delete: `public/privacy.html`
- Delete: `public/refund.html`
- Delete: `public/terms.html`
- Delete: `public/assets/admin/index.html`

- [ ] **Step 1: Verify the pre-change state**

Run:

```bash
for file in \
  public/pricing.html \
  public/privacy.html \
  public/refund.html \
  public/terms.html \
  public/assets/admin/index.html; do
  test -f "$file" || exit 1
done
```

Expected: exit code `0`, proving every targeted file exists before deletion.

- [ ] **Step 2: Delete only the approved files**

Use `apply_patch` with five `Delete File` sections for the exact paths listed above. Do not remove `public/assets/admin/`, because the real admin Blade view loads files from its `assets/` and `locales/` children.

- [ ] **Step 3: Verify the retired files are absent**

Run:

```bash
for file in \
  public/pricing.html \
  public/privacy.html \
  public/refund.html \
  public/terms.html \
  public/assets/admin/index.html; do
  test ! -e "$file" || exit 1
done
```

Expected: exit code `0`.

- [ ] **Step 4: Verify required pages and admin runtime assets remain**

Run:

```bash
for file in \
  public/download/index.html \
  resources/views/support_ai.blade.php \
  public/assets/admin/assets/index.js \
  public/assets/admin/assets/index.css \
  public/assets/admin/locales/zh-CN.js; do
  test -f "$file" || exit 1
done
```

Expected: exit code `0`.

- [ ] **Step 5: Run focused regression tests**

Run:

```bash
node --test \
  tests/app-download-rate-limit.test.js \
  tests/elephant-route-dashboard-actions.test.js \
  tests/prorated-renewal-order.test.js
```

Expected: all tests pass with zero failures, confirming the retained download page, AI support compatibility view, and admin runtime bundles still satisfy their existing checks.

- [ ] **Step 6: Audit the final Git diff**

Run:

```bash
git diff --check
git diff --name-status HEAD -- \
  public/pricing.html \
  public/privacy.html \
  public/refund.html \
  public/terms.html \
  public/assets/admin/index.html \
  public/download/index.html \
  resources/views/support_ai.blade.php \
  public/assets/admin/assets \
  public/assets/admin/locales
```

Expected: `git diff --check` exits `0`; the name-status output contains exactly five `D` entries for the approved files and no changes to retained paths.

- [ ] **Step 7: Commit the page retirement**

```bash
git add -- \
  public/pricing.html \
  public/privacy.html \
  public/refund.html \
  public/terms.html \
  public/assets/admin/index.html
git commit -m "chore: remove obsolete public pages"
```

Expected: one commit containing exactly the five file deletions.
