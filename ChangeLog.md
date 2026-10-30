# CHANGELOG FORMLISTOVERRIDE FOR [DOLIBARR ERP CRM](https://www.dolibarr.org)

CHANGELOG FORMLISTOVERRIDE FOR DOLIBARR ERP CRM

## Unreleased
Add: option "Accept searches without action" (FORMLISTOVERRIDE_ACCEPT_EMPTY_ACTION, off by default). Some lists post no action with their search (users, contacts, agenda, scheduled jobs, cheque deposits): with the option, such a search is converted too, and the detection offers these lists. A request carrying another action is never converted.
Fix: the list context was read from the first hook context only. An object built by the page can push its own context in front (new User() in user/list.php gives 'userdao'), so the page was not recognized as a list. Every context is now checked.
Change: the stored detection carries a format version; a detection stored by an older version is run again automatically.
Add: setup page lists the compatible list pages as checkboxes, grouped by core and external module, with check/uncheck all. A free text field keeps extra pages and suffixes (e.g. _list.php). The configuration constant is unchanged: existing setups are kept.
Add: "Detect lists" button scanning the core and every custom root. A list is compatible when its search form posts action=list and the page calls the doActions hook; detected but incompatible lists are shown with the reason. Detection runs once at the first visit.
Change: invalid entries (characters outside a script path, '..') are rejected on save. The page no longer uses FormSetup.
Clean: removed the FormSetup backport for Dolibarr < 16 (never loaded: the module requires 16).
Fix: the module did nothing with the default CSRF level (2 up to Dolibarr 23, 3 since 24): conversion was skipped from level 2. The guard is removed; at level >= 3 'action' is left out of the GET URL, the only parameter the core requires a token for.

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
