<?php
/* Copyright (C) 2025-2026	Solauv				<contact@solauv.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    formlistoverride/lib/formlistoverride.lib.php
 * \ingroup formlistoverride
 * \brief   Library files with common functions for FormListOverride
 */

/**
 * Prepare admin pages header
 *
 * @return array
 */
function formlistoverrideAdminPrepareHead()
{
	global $langs, $conf;

	$langs->load("formlistoverride@formlistoverride");

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath("/formlistoverride/admin/setup.php", 1);
	$head[$h][1] = $langs->trans("Settings");
	$head[$h][2] = 'settings';
	$h++;

	/*
	$head[$h][0] = dol_buildpath("/formlistoverride/admin/myobject_extrafields.php", 1);
	$head[$h][1] = $langs->trans("ExtraFields");
	$head[$h][2] = 'myobject_extrafields';
	$h++;
	*/

	$head[$h][0] = dol_buildpath("/formlistoverride/admin/about.php", 1);
	$head[$h][1] = $langs->trans("About");
	$head[$h][2] = 'about';
	$h++;

	// Show more tabs from modules
	// Entries must be declared in modules descriptor with line
	//$this->tabs = array(
	//	'entity:+tabname:Title:@formlistoverride:/formlistoverride/mypage.php?id=__ID__'
	//); // to add new tab
	//$this->tabs = array(
	//	'entity:-tabname:Title:@formlistoverride:/formlistoverride/mypage.php?id=__ID__'
	//); // to remove a tab
	complete_head_from_modules($conf, $langs, null, $head, $h, 'formlistoverride@formlistoverride');

	complete_head_from_modules($conf, $langs, null, $head, $h, 'formlistoverride@formlistoverride', 'remove');

	return $head;
}

/**
 * Get the list of pages on which the POST search form must be converted to GET.
 * The pages are stored in the module constant, one page (relative path) per line.
 * Empty entries are filtered out so an empty needle can never match every page.
 *
 * @return string[] List of page paths (e.g. '/comm/propal/list.php')
 */
function formlistoverrideGetListOfPagesToOverrideForm(): array
{
	$listOfPagesToOverride = [];

	$pagesParamValue = getDolGlobalString('FORMLISTOVERRIDE_FORCE_FORM_METHOD_GET_WHEN_SEARCH_ON_THIS_PAGES');
	if ($pagesParamValue !== '') {
		$pagesParamValue = preg_replace("/[\n\r]/", '@', $pagesParamValue);
		$pagesParamValue = preg_replace("/@+/", '@', $pagesParamValue);

		$listOfPagesToOverride = array_values(array_filter(
			array_map('trim', explode('@', $pagesParamValue)),
			static function ($page) {
				return $page !== '';
			}
		));
	}

	return $listOfPagesToOverride;
}

/**
 * Tell whether a configured entry is acceptable: a relative page path or suffix,
 * without '..' nor characters a script path does not need.
 *
 * @param  string $page Entry (e.g. 'comm/propal/list.php' or '_list.php')
 * @return bool
 */
function formlistoverrideIsValidPageEntry(string $page): bool
{
	return (bool) preg_match('/^[A-Za-z0-9_\-\.\/]+$/', $page) && strpos($page, '..') === false;
}

/**
 * Scan directories for list pages and tell which ones the module can act on.
 *
 * A candidate is a '*list*.php' file (templates excluded) whose search form posts
 * 'formfilteraction'. It is compatible when its form also posts action=list (literally
 * or through an expression giving 'list') and when the page calls the doActions hook:
 * without either, the conversion can never trigger there.
 *
 * Directories that are themselves one of the scanned roots are skipped when met inside
 * another root (htdocs/custom inside htdocs), so each page is listed once, under its root.
 *
 * @param  array<string,string> $roots Root key => absolute directory, e.g. $conf->file->dol_document_root
 * @return array<int,array{path:string,root:string,compatible:bool,reason:string}> Sorted by root order then path
 */
function formlistoverrideScanListPages(array $roots): array
{
	$skippedNames = array('custom', 'includes', 'vendor', 'node_modules', '.git', 'install', 'test', 'tests');
	$realRoots = array();
	foreach ($roots as $key => $dir) {
		$real = realpath($dir);
		if ($real !== false && is_dir($real)) {
			$realRoots[$key] = $real;
		}
	}

	$pages = array();
	foreach ($realRoots as $rootKey => $rootDir) {
		$otherRoots = array_diff($realRoots, array($rootDir));
		$filter = static function ($file, $key, $iterator) use ($skippedNames, $otherRoots, $rootDir) {
			if ($iterator->hasChildren()) {
				$path = $file->getPathname();
				return !in_array($file->getFilename(), $skippedNames, true)
					&& !in_array($path, $otherRoots, true)
					&& $path !== $rootDir.'/modulebuilder/template';
			}
			return true;
		};
		$iterator = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
			new RecursiveDirectoryIterator($rootDir, FilesystemIterator::SKIP_DOTS),
			$filter
		));

		$found = array();
		foreach ($iterator as $file) {
			$name = $file->getFilename();
			if (!preg_match('/list[^\/]*\.php$/', $name) || preg_match('/\.tpl\.php$/', $name)) {
				continue;
			}
			$content = (string) @file_get_contents($file->getPathname());
			if (!preg_match('/name=["\']formfilteraction["\']/', $content)) {
				continue;
			}

			// action=list, or an expression yielding it: value="'.($action == 'edit' ? 'update' : 'list').'"
			$postsActionList = false;
			if (preg_match_all('/name=["\']action["\']\s+value="([^"]*)"/', $content, $matches)) {
				foreach ($matches[1] as $value) {
					if (preg_match('/\blist\b/', $value)) {
						$postsActionList = true;
						break;
					}
				}
			}
			$callsDoActions = (bool) preg_match('/executeHooks\(\s*["\']doActions["\']/', $content);

			$reason = '';
			if (!$postsActionList) {
				$reason = 'noaction';
			} elseif (!$callsDoActions) {
				$reason = 'nohook';
			}
			$path = str_replace('\\', '/', substr($file->getPathname(), strlen($rootDir) + 1));
			$found[$path] = array('path' => $path, 'root' => (string) $rootKey, 'compatible' => ($reason === ''), 'reason' => $reason);
		}
		ksort($found);
		$pages = array_merge($pages, array_values($found));
	}

	return $pages;
}

/**
 * Detect list pages on this install (core and every alternative root) and store the result.
 *
 * @param  DoliDB $db Database handler
 * @return array{date:int,pages:array<int,array{path:string,root:string,compatible:bool,reason:string}>}
 */
function formlistoverrideDetectAndStoreListPages($db): array
{
	global $conf;

	$detection = array(
		'date' => dol_now(),
		'pages' => formlistoverrideScanListPages($conf->file->dol_document_root),
	);
	dolibarr_set_const($db, 'FORMLISTOVERRIDE_DETECTED_PAGES', json_encode($detection), 'chaine', 0, '', $conf->entity);

	return $detection;
}

/**
 * Get the last stored detection.
 *
 * @return array{date:int,pages:array<int,array{path:string,root:string,compatible:bool,reason:string}>}|null Null if never run
 */
function formlistoverrideGetStoredListPages(): ?array
{
	$detection = json_decode(getDolGlobalString('FORMLISTOVERRIDE_DETECTED_PAGES'), true);
	if (!is_array($detection) || !isset($detection['pages']) || !is_array($detection['pages'])) {
		return null;
	}

	return $detection;
}

/**
 * Split configured entries between those shown as checkboxes and those kept in the free text field.
 *
 * @param  string[] $configured Configured entries, in their stored order
 * @param  string[] $candidates Paths offered as checkboxes
 * @return array{0:string[],1:string[]} [checked candidates, other entries]
 */
function formlistoverrideSplitConfiguredPages(array $configured, array $candidates): array
{
	$checked = array_values(array_intersect($configured, $candidates));
	$others = array_values(array_diff($configured, $candidates));

	return array($checked, $others);
}

/**
 * Convert a POST search-list request into a GET one when it is safe to do so.
 *
 * The conversion is attempted only for a genuine search submit:
 * - the page context ends with 'list'
 * - request parameter 'action' is 'list'
 * - request parameter 'formfilteraction' is 'list'
 * - request method is POST
 * - it is not a mass action
 * - the current script is one of the pages selected in the module setup
 *
 * Fail-safe philosophy: in any doubtful case the function returns and lets
 * Dolibarr fall back to its standard POST behavior. Not redirecting is always
 * safe (it is the current default everyone already lives with).
 *
 * @param  string $context  The current page context (typically $hookmanager->contextarray[0])
 * @param  string $action   The current action
 * @return void
 */
function formlistoverrideConvertPostSearchListRequestToGetIfPossible($context, $action)
{
	// --- Fail-safe guards: never convert in these cases ---

	// Form carrying a file upload: a POST is mandatory, $_FILES cannot be moved to GET.
	if (!empty($_FILES)) {
		return;
	}

	// List loaded through AJAX: a header redirect would reload the wrong frame/context.
	if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])
		&& strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
		return;
	}

	// No CSRF guard here: whatever the MAIN_SECURITY_CSRF_WITH_TOKEN level, the GET
	// URL is built so that the core accepts it without token (see
	// formlistoverrideBuildGetUrlFromPost()).

	// --- Qualify a genuine search submit ---

	if (preg_match('/list$/', $context)
		&& $action == 'list'
		&& GETPOST('formfilteraction') == 'list'
		&& $_SERVER['REQUEST_METHOD'] == 'POST'
		&& !GETPOST('massaction') // exclude mass actions: their values are strings, so use GETPOST (NOT GETPOSTINT)
	) {
		$pagesToOverride = formlistoverrideGetListOfPagesToOverrideForm();

		foreach ($pagesToOverride as $page) {
			// End-anchored match: tolerant about the install prefix (/custom, sub-dir, ...)
			// but strict that the running script path ends exactly with the configured page.
			if ($page !== '' && substr($_SERVER['SCRIPT_NAME'], -strlen($page)) === $page) {
				formlistoverrideRedirectPostSearchListToGet();
				return; // page matched: redirect attempted (or skipped by a guard), stop here
			}
		}
	}
}

/**
 * Build the GET URL equivalent to a POST search, acceptable by the core without token.
 *
 * MAIN_SECURITY_CSRF_WITH_TOKEN >= 3 (the default since Dolibarr 24, 2 before) requires a token on
 * every GET carrying an 'action' or a 'massaction', and 'list' is not in the core list of
 * legitimate GET actions. At that level 'action' is therefore left out: list pages treat a
 * missing action as a display. Below 3, 'action=list' is kept so the page behaves exactly as
 * on the POST (e.g. societe/list.php does not jump to the card when it finds one result).
 *
 * @param  array<string,mixed> $postData   Submitted search data (typically $_POST)
 * @param  string              $scriptName Path of the list page (typically $_SERVER['SCRIPT_NAME'])
 * @param  int                 $csrfLevel  Value of MAIN_SECURITY_CSRF_WITH_TOKEN
 * @return string                          URL to redirect to
 */
function formlistoverrideBuildGetUrlFromPost(array $postData, string $scriptName, int $csrfLevel): string
{
	// Remove technical data that must not appear in the GET URL.
	unset($postData['massaction']);
	unset($postData['confirmmassactioninvisible']);
	unset($postData['token']);
	if ($csrfLevel >= 3) {
		unset($postData['action']);
	}

	// Remove only truly empty string values (an empty filter means "no filter").
	// We deliberately DO NOT strip '-1': it can be a value typed by the user in a
	// search field, and list pages already treat the "-1 = all" sentinel on their own.
	$postData = array_filter($postData, static function ($value) {
		return !(is_string($value) && $value === '');
	});

	$url = $scriptName;
	$queryString = http_build_query($postData);
	if ($queryString !== '') {
		$url .= '?' . $queryString;
	}

	return $url;
}

/**
 * Build the equivalent GET URL from the current POST search and redirect to it.
 *
 * Redirection happens only if the resulting URL stays within a safe length;
 * otherwise the function returns and the standard POST behavior is kept.
 *
 * @return void
 */
function formlistoverrideRedirectPostSearchListToGet()
{
	$newLocationUrl = formlistoverrideBuildGetUrlFromPost($_POST, $_SERVER['SCRIPT_NAME'], getDolGlobalInt('MAIN_SECURITY_CSRF_WITH_TOKEN'));

	// Only redirect (GET) if the URL stays within a safe length; otherwise fall back
	// to the standard POST behavior (fail-safe).
	$maxUrlLength = 2000;
	if (strlen($newLocationUrl) <= $maxUrlLength) {
		// Optional dry-run: log the URL that would be used, without redirecting.
		// Enable with: set the constant FORMLISTOVERRIDE_DRYRUN to 1.
		if (getDolGlobalInt('FORMLISTOVERRIDE_DRYRUN')) {
			dol_syslog('FormListOverride DRYRUN would redirect to: ' . $newLocationUrl, LOG_DEBUG);
			return;
		}

		header('Location: ' . $newLocationUrl);
		exit();
	}
}
