---
name: apbct-security-fix
description: Fix a security report (CVE / "Vulnerability inside CleanTalk.org" / bug bounty / automated WP.org review) in the CleanTalk Anti-Spam WordPress plugin (apbct) — stored/DOM XSS, open redirect, api_key/user_token disclosure, CSRF, auth bypass, DoS, SQLi, object injection. Use when given a vulnerability report, PoC or doBoard security task for this repo, or when asked to verify/triage such a report.
---

# APBCT security fix

Built from ~25 security tasks resolved Apr–Oct 2026 (doBoard project 64), their PRs and the Copilot review
threads on them. The recurring failure is **not** "couldn't fix" — it is "fixed the reported line, pentester
bypassed it via a sibling path". Most of this skill exists to prevent that second round.

Case history with task/PR ids: [references/case-history.md](references/case-history.md).
Sinks and grep recipes: [references/sinks.md](references/sinks.md).
Pre-PR self-review (what Copilot and the pentester caught before): [references/review-checklist.md](references/review-checklist.md).

## 0. Input

A report from the pentest department usually has: minimal attacker role, victim, "how it works"
with `file:line`, data chain source → sink, PoC (curl / UI steps), recommendations. Reports are often written
with AI help — treat line numbers and the named source as hypotheses, not facts.

## 1. Triage (write the verdict down before coding)

1. Locate every `file:line` from the report in the **current** code (`git log -L` if it moved). Reports have
   pointed at the wrong source before (Drupal 49374: report blamed Helper, real sink was RemoteCalls).
2. Build the real chain: who controls the input (anonymous / subscriber / contributor / author / admin / CleanTalk
   cloud / DB), where it is stored, where it is rendered, who sees it.
3. Decide the class:
   - **Real, exploitable** → fix now.
   - **Requires api_key, DB write access or cloud MITM** → not a CVE; defense-in-depth fix only if cheap, else
     backlog. State the reasoning explicitly (see 50457 W10, 52113 in case history).
   - **Duplicate** of an open task → say which.
4. Also note what an AI recommendation in the report gets wrong. Previous reports suggested "sanitize the saved
   form" (breaks forms) and "replace outerHTML with innerHTML" (does not fix anything).

## 2. Reproduce as a test first

- Turn the PoC payload into a PHPUnit test (`tests/ApbctWP/<mirrors lib path>/...`, base `ApbctTestCase`)
  that fails on the current code. Use the **real** payload (`'onanimationstart=`, `<img onerror>`, `javascript:`,
  `//evil.com`, ReDoS pattern) — a test without a dangerous payload proves nothing (#815).
- For JS sinks without a JS test harness, document a manual repro (page, settings to enable, payload).
- Note the settings the bug needs (e.g. `forms__force_protection`, contacts encoder on, native cookies) — the
  re-tester needs them.

## 3. Fix rules by class

**XSS (PHP output)**
- Escape at the sink for the exact context: text → `esc_html`, attribute → `esc_attr`, URL → `esc_url`
  (add `mailto`/`tel` to protocols when needed), inline JS → `wp_json_encode`/`esc_js`, `</script>` in inline
  scripts. Plugin wrappers: `Cleantalk\ApbctWP\Escape::escHtml/escAttr/escUrl/escKses/escKsesPreset`.
- Never `html_entity_decode()` / `wp_specialchars_decode()` / `strtr` back after escaping. Never "escape then
  decode to restore markup".
- Template placeholders (`{REQUEST_URI}` etc.) — escape the value when substituting; placeholders must not be
  predictable or replaceable from author content.
- Values from the DB are attacker-controlled (WP core keeps quotes in `comment_author_email` etc.).

**XSS (JS / DOM)**
- No `innerHTML`, `outerHTML`, `insertAdjacentHTML`, jQuery `.html()`/`.append(string)` with server or page
  data. Use `textContent`, `createElement` + `setAttribute`, `.text()`.
- If HTML really must be inserted: strict allowlist sanitizer — check `href`, `src`, `action`, `formaction`,
  `xlink:href` protocols; case-normalize tag/attr names; cover SVG/MathML/`style`; test each.
- **Provenance**: anything the plugin later decodes from the page (e.g. `data-original-string`) can be forged
  by a contributor in post content (KSES allows `div`, `class`, `data-*`). Sanitize the decoded value on every
  output path.
- Edit only `js/src/*.js`, then rebuild (see §5).

**Secret disclosure (api_key, md5(api_key), user_token)**
- Never send to the frontend, cookies, URLs, or emails that can reach non-admins (`wp_localize_script` on
  pages contributors can open, notification emails).
- Derived values must be salted: `md5/sha256($apbct->api_key . $apbct->data['salt'])` — the property is
  `$apbct->data['salt']`, not `$apbct->salt`.
- Mask new key-like fields in `RemoteCalls::$sensitiveData`.
- If the link/feature is unused (ask for analytics), removing it is a valid fix (58993).
- Scan the whole codebase for the same secret, not only the reported spot (see sinks.md).

**Open redirect**: validate same host (`wp_validate_redirect($url, home_url())`) or build the URL from the
request path only; escape the result for the HTML context too.

**Auth / CSRF / remote calls**
- Order: capability + nonce/token check **first**, then any expensive work (DNS lookups, unzip, DB writes,
  HTTP). A PR was rejected for doing reverse DNS before the token check (838).
- Static tokens are replayable — prefer action/time-bound tokens; rate-limit per IP, not globally (50328, uses
  `cleantalk/rate-limiter`).
- `permission_callback => '__return_true'` only with a comment why the route is public.

**DoS / resource**: limits on size/count/time, cleanup in `finally`, rate limit 429.

**SQL**: `$wpdb->prepare` for every value, cast ints, handle empty `IN ()`. **Unserialize**: `allowed_classes => false`.

**Regex from data**: escape delimiters, check `preg_last_error()` for backtrack/recursion limits (804).

Keep functionality: fail-open vs fail-closed must not be the "fix"; legitimate forms, `mailto:` links, encoder
output must still work — add a test for the legit case next to the attack case.

## 4. Variant analysis (mandatory)

Before calling it done, fix **all** instances of the pattern, not just the reported one:
- the same helper/function in sibling modules (AntiCrawler ↔ AntiFlood ↔ SFW;
  AJAX decode ↔ shortcode decode);
- producer **and** verifier of a token/hash (cookie set, check, die page, remote call);
- the twin feature in Security plugin (spbct, `security-malware-firewall`) — open a follow-up task, don't edit it here;
- shared libs `lib/Cleantalk/Common/*` — upstream repo first (`_sfw`, `_remote_calls`, `contacts-encoder`,
  `rate-limiter`), then composer update; `checkLibrariesChanges.yml` fails otherwise.
Use the grep recipes in [references/sinks.md](references/sinks.md). List found siblings in the PR body: fixed /
not reachable (why) / follow-up task.

## 5. Build & checks

- JS: `npx gulp compress-js` regenerates `js/prebuild/apbct-public-bundle*.js` (8 variants) and `js/*.min.js`
  + maps — commit them. `npm run eslint`. React parts: `npm run build`.
- PHP: `composer test` = PHPUnit (needs WP test DB, `tests/wp-test-setup.sh`), PHPCS + PHPCompatibility,
  Psalm, **Psalm `--taint-analysis`** (run it — it has caught real issues and will flag your new sinks).
- Lowest supported PHP: check `ServerRequirementsChecker` / `readme.txt` before using `random_bytes`, `??`,
  typed props, etc.
- Tests: restore globals and statics in `tearDown` (`$apbct`, `$_COOKIE`, `$_SERVER`, `Cookie` caches) —
  `tests/bootstrap.php` loads the plugin once.

## 6. Self-review

Go through [references/review-checklist.md](references/review-checklist.md) line by line. Copilot reviews
every PR in this repo; anything on that list it will flag, and an unfixed real finding costs a review round.

## 7. Deliverables

- Branch from `fix` (urgent/security) or `dev`, name `<topic>.<initials>` / `<topic>_<initials>`.
- Commit/PR title: `Fix. <Module>. <neutral description>.` — the repo is public: no PoC, payload or
  "XSS in X" wording in titles/commits/PR body until the release is out.
- PR body: doBoard task link; threat model in one line; what was changed at the sink; siblings checked; accepted
  risks (e.g. "old pass cookies invalidated — one block page, accepted", "race on transient accepted").
- doBoard comment (Russian) for the re-tester: what was fixed, settings to enable, how to verify, zip/branch.
  Fixes go to the reporter for re-test **before** merge.
- Out of scope findings → separate backlog task; new CVE-class findings → tell the user immediately.
- When fixing several reports at once: one commit per report, then a combined variant-analysis pass.
