# CHANGELOG FORMLISTOVERRIDE FOR [DOLIBARR ERP CRM](https://www.dolibarr.org)

CHANGELOG FORMLISTOVERRIDE FOR DOLIBARR ERP CRM

## 1.0.1
Fix: mass actions were swallowed on overridden pages (massaction guard used GETPOSTINT, which casts string actions to 0). Now uses GETPOST.
Fix: empty entries in the page list are filtered out; page matching is end-anchored (no more substring over-match).
Add: fail-safe guards — conversion is skipped for AJAX requests, file uploads, and strict CSRF policies.
Change: only empty values are stripped from the GET URL; a value typed as "-1" is kept.
Add: optional dry-run mode (constant FORMLISTOVERRIDE_DRYRUN) that logs the target URL instead of redirecting.
Fix: library loaded via dol_include_once (no hardcoded /custom/ path).
Clean: setup page reduced to the form + save (removed unused document/numbering boilerplate); pages proposal pre-filled.
Fix: real module description and translations; removed unused temp directory and dead JS.

## 1.0
Initial version
