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

function formlistoverrideGetListOfPagesToOverrideForm(): array
{
	$listOfPagesToOverride = [];

	$pagesParamValue = getDolGlobalString('FORMLISTOVERRIDE_FORCE_FORM_METHOD_GET_WHEN_SEARCH_ON_THIS_PAGES');
	if ($pagesParamValue !== '') {
		$pagesParamValue = preg_replace("/[\n\r]/", '@', $pagesParamValue);
		$pagesParamValue = preg_replace("/@+/", '@', $pagesParamValue);
		$listOfPagesToOverride = explode('@', $pagesParamValue);
	}

	return $listOfPagesToOverride;
}

function formlistoverrideConvertPostSearchListRequestToGetIfPossible($context, $action)
{
	$pagesToOverride = formlistoverrideGetListOfPagesToOverrideForm();

	if (preg_match('/list$/', $context) && $action == 'list' && GETPOST('formfilteraction') == 'list' && $_SERVER['REQUEST_METHOD'] == 'POST') {
		// Detect if it is a simple search (not an massaction)
		if (GETPOSTINT('massaction') == 0) {
			foreach ($pagesToOverride as $page) {
				if ( strstr($_SERVER['SCRIPT_NAME'], $page) !== false) {
					formlistoverrideRedirectPostSearchListToGet();
				}
			}
		}
	}
}

function formlistoverrideRedirectPostSearchListToGet()
{
	$postData = $_POST;

	// Remove useless data
	unset($postData['massaction']);
	unset($postData['confirmmassactioninvisible']);
	unset($postData['token']);

	// Remove parameters with default value
	$postData = array_filter($postData, function ($value) {
		if ($value == '' || $value == '-1') {
			return false;
		}
		return true;
	});

	$newLocationUrl = $_SERVER['SCRIPT_NAME'];
	$queryString = http_build_query($postData);
	if ($queryString !== '') {
		$newLocationUrl .= '?' . $queryString;
	}

	$maxUrlLength = 2000;
	if (strlen($newLocationUrl) <= $maxUrlLength) {
		header('Location: ' . $newLocationUrl);
		exit();
	}
}

