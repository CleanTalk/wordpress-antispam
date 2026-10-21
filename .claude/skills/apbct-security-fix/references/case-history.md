# Case history (Apr–Oct 2026)

Lessons only; ids are doBoard tasks (`https://app.doboard.com/1/task/<id>`) and GitHub PRs.

| Task / PR | Issue | What happened | Lesson |
|---|---|---|---|
| 50334 / #816, #815 | Stored DOM XSS: contacts encoder decodes author-supplied `data-original-string`, JS inserts via innerHTML | First fix escaped AJAX decode; pentester bypassed via `[apbct_skip_encoding]` shortcode path; second round shared one sanitizer (`strip_tags` + `wp_kses` allowlist `a[href,title,target,rel]`, protocols http/https/mailto/tel) for AJAX **and** shortcode | Same decoded value has several output paths — sanitize in one shared function used by all. base64+str_rot13 "secret" leaks to front, it's not a protection (51992) |
| 52113 / #838 | Auto review: unsanitized AJAX HTML in int-protection bundle; remote plugin install | 1st version: sanitizer missed `form[action]=javascript:`; DNS lookup ran before token check | Allowlist must check every URL-bearing attribute; auth before expensive ops; rebuild all prebuild bundles |
| 58993 / #907 | `user_token` in `ctCommentsScreen` localized to contributors | Link removed (analytics: <0.3% clicks). Pentester then found the same token in a notification email | A secret leak is a codebase-wide grep, not one line |
| 50322 / #813 | md5(api_key) = remote call token exposed via cookie test `check_value` | Salt added unconditionally; Copilot caught `$apbct->salt` (wrong) → `$apbct->data['salt']` | Verify property names; unit-test the new formula |
| 50457 / #805 | Batch from AI audit: debug RC leaks hoster key; raw SQL in `SFW::updateLog`; pass cookie forgeable | `api_key` added to `$sensitiveData`; all values via `prepare`; salt in `ct_sfw_pass_key` (all IPs see block page once — accepted). Alt-sessions unserialize declined: needs DB access | Write accept/decline reasoning per item |
| 48216 / #804 | AI audit: CSV injection, insecure RNG, ReDoS in UA templates, raw unserialize | Email-enumeration item was a false positive (no `email_exists` there). Copilot: same regex fix missing in AntiFlood | Check sibling modules |
| 50328 / #824 | Unauth remote calls DoS via `last_call` cooldown | Ported `cleantalk/rate-limiter` (10/60s), **per IP**; many Copilot nits in shared libs | Rate limits per IP |
| 49374, 50463 (Drupal) | Reflected XSS via SCRIPT_URL in block pages | Fix in shared `_remote_calls`/`_sfw` broke `RemoteCalls::check()` (lost return) — caught by re-tester | Shared-lib edits need regression tests of the normal path; escape `{REQUEST_URI}` in die pages |
| 48536 (Drupal) | API message in die page through `Xss::filter` | Tag+attr allowlist, like `wp_kses` | Filtering tags ≠ escaping attributes |
| 55465 (spbct) / spbc #723 | `.html()` with AJAX data + endpoint returned other users' rows | Two fixes: `.text()` and filter by user/IP | Fix both the sink and the data-scope bug |
| 49956 (spbct) | DoS via subscriber archive upload | cap+nonce, 5 scans/10 min → 429 | Auth first, then limits |
| 55234 / #897, spbc #739 | `$_GET['access'] === api_key` bypass leaks key in access.log | One-time emailed token; Copilot: check `set_transient` result, PHP 5.6 compat | Check storage return values; race conditions on transients accepted |

Process notes from these threads:
- Reporter re-tests on a zip/branch before merge; budget for one bypass round.
- Copilot: 1–2 rounds are enough ("на один круг обработал, замечания дельные"); respond to each thread with fixed / not actual + reason.
- Mark decisions between sprints in the task (what moved, what's left).
- Pentest priority: XSS = high; unauth secret leak = high; open redirect = medium but WPScan may register CVE.
