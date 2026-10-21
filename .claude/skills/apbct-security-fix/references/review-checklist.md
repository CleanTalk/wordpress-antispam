# Pre-PR self-review

Derived from ~110 Copilot comments on 31 security PRs (≈40% accepted) and pentester re-tests.

## Must pass (these were real findings)

- [ ] Every sibling of the vulnerable pattern fixed or explicitly ruled out (AntiCrawler/AntiFlood, producer+verifier, AJAX+shortcode, JS src+bundles).
- [ ] Every new property / array key / class name exists with exact case (`$apbct->data['salt']`, `RateLimiterDto`), `global $apbct` declared inside functions.
- [ ] Escaping matches the output context, including fallback paths (old-WP fallbacks, `</script>` in inline JS, `esc_attr` in `data-*`, `esc_html()` not `esc_html__()` for variables).
- [ ] Sanitizer doesn't break valid input (percent-encoding, IPv6 hosts, `mailto:` links, legit forms) — a test for the legit case exists.
- [ ] Inputs normalized before comparison (case, trailing dot, URL decoding / canonical path).
- [ ] Return values of security-relevant writes checked (`set_transient`, `update_option`, DB insert).
- [ ] Public methods guard input types (`is_array`, `is_scalar`), `TT::` / `Cookie::getString()` used for typed reads.
- [ ] Auth/nonce/token check happens before any expensive or side-effecting work.
- [ ] Code runs on the lowest supported PHP.
- [ ] Tests use real payloads, assert new formulas, restore globals/statics.
- [ ] `composer test` incl. Psalm taint analysis and `npm run eslint` pass; prebuild bundles rebuilt and committed.
- [ ] No secret, payload or vuln wording in public commit/PR titles.

## Usually rejected — pre-empt in the PR body instead of coding

- Race conditions on transients / non-atomic one-time tokens for low-impact flows.
- Backward compatibility for invalidated tokens/cookies ("one block page / re-login, accepted").
- Defense-in-depth on values that are never output — say why they are not sinks.
- Hardening beyond the reported vector + its siblings (theoretical sanitizer bypasses) — backlog task.
- Docblock/grammar/indent nits, "not now" refactors.
