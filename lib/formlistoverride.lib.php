<?php
/* Copyright (C) 2025 SuperAdmin
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

	// Strict CSRF policy (token also enforced on GET): a token-less GET would be rejected.
	if (getDolGlobalInt('MAIN_SECURITY_CSRF_WITH_TOKEN') >= 2) {
		return;
	}

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
 * Build the equivalent GET URL from the current POST search and redirect to it.
 *
 * Redirection happens only if the resulting URL stays within a safe length;
 * otherwise the function returns and the standard POST behavior is kept.
 *
 * @return void
 */
function formlistoverrideRedirectPostSearchListToGet()
{
	$postData = $_POST;

	// Remove technical data that must not appear in the GET URL.
	unset($postData['massaction']);
	unset($postData['confirmmassactioninvisible']);
	unset($postData['token']);

	// Remove only truly empty string values (an empty filter means "no filter").
	// We deliberately DO NOT strip '-1': it can be a value typed by the user in a
	// search field, and list pages already treat the "-1 = all" sentinel on their own.
	$postData = array_filter($postData, static function ($value) {
		return !(is_string($value) && $value === '');
	});

	$newLocationUrl = $_SERVER['SCRIPT_NAME'];
	$queryString = http_build_query($postData);
	if ($queryString !== '') {
		$newLocationUrl .= '?' . $queryString;
	}

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
