# Sinks and grep recipes

Run from the plugin root; exclude `vendor`, `node_modules`, `js/prebuild`, `*.min.js`.

```bash
EX='--exclude-dir=vendor --exclude-dir=node_modules --exclude-dir=prebuild --exclude=*.min.js'

# JS DOM sinks
grep -rnE 'innerHTML|outerHTML|insertAdjacentHTML|\.html\(|\.append\(|document\.write|atob\(' $EX js/src

# PHP: decode after escape, page-wide replacement, raw output
grep -rnE 'html_entity_decode|wp_specialchars_decode|htmlspecialchars_decode|strtr\(|str_replace\(.*(\{|%%)' $EX --include=*.php lib inc cleantalk.php templates
grep -rnE "echo .*\\\$(_GET|_POST|_REQUEST|_SERVER|_COOKIE)|Server::get\('(REQUEST_URI|HTTP_HOST|SCRIPT_URL|HTTP_REFERER)" $EX --include=*.php lib inc

# Placeholders the plugin substitutes into HTML
grep -rnoE '\{[A-Z_]{4,}\}|%%[A-Z_]+%%' $EX lib inc templates | sort -u

# Secrets reaching output / cookies / mail
grep -rnE 'user_token|api_key|apikey|md5\(\s*\$apbct->api_key' $EX --include=*.php lib inc cleantalk.php \
  | grep -E 'localize|echo|printf|Cookie::set|setcookie|wp_mail|mail_|href|url|message|body'

# Redirect targets
grep -rnE 'wp_redirect|header\(.Location|href=.\{?REQUEST_URI|window\.location' $EX lib inc js/src templates

# Auth: AJAX/REST handlers and remote calls
grep -rnE "add_action\('wp_ajax_(nopriv_)?|register_rest_route|permission_callback" $EX --include=*.php lib inc cleantalk.php

# SQL & unserialize
grep -rnE '\$wpdb->(query|get_results|get_var|get_row|get_col)\(' $EX --include=*.php lib inc | grep -v prepare
grep -rnE '(^|[^_])unserialize\(' $EX --include=*.php lib inc
```

## Known hot spots

Public CVEs 2026 (CVE-2026-8071, CVE-2026-103424) and most internal reports hit these areas:

- `lib/Cleantalk/ApbctWP/ContactsEncoder/*` + `cleantalk/contacts-encoder` lib — decode AJAX, shortcodes.
- `lib/Cleantalk/ApbctWP/Firewall/*` die pages and `diePage()` of SFW/AntiFlood/AntiCrawler.
- `lib/Cleantalk/ApbctWP/RemoteCalls.php` — token check, `$sensitiveData`, rate limit (CVE-2026-1490, CVE-2024-10542).
- `inc/cleantalk-admin.php` — localized admin data, who can open the page.
