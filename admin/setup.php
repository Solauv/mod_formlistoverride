<?php
/* Copyright (C) 2004-2017 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2025-2026	Solauv					<contact@solauv.fr>
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
 * \file    formlistoverride/admin/setup.php
 * \ingroup formlistoverride
 * \brief   FormListOverride setup page.
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME']; $tmp2 = realpath(__FILE__); $i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--; $j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
// Try main.inc.php using relative path
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Translate $langs
 * @var User $user
 */

// Libraries
require_once DOL_DOCUMENT_ROOT."/core/lib/admin.lib.php";
require_once '../lib/formlistoverride.lib.php';

// Translations
$langs->loadLangs(array("admin", "formlistoverride@formlistoverride"));

// Initialize a technical object to manage hooks of page. Note that conf->hooks_modules contains array of hook context
$hookmanager->initHooks(array('formlistoverridesetup', 'globalsetup'));

// Access control
if (!$user->admin) {
	accessforbidden();
}

// Parameters
$action = GETPOST('action', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');

$form = new Form($db);

$pagesConstName = 'FORMLISTOVERRIDE_FORCE_FORM_METHOD_GET_WHEN_SEARCH_ON_THIS_PAGES';
$acceptEmptyAction = (bool) getDolGlobalInt('FORMLISTOVERRIDE_ACCEPT_EMPTY_ACTION');


/*
 * Actions
 */

if ($action == 'detect') {
	$detection = formlistoverrideDetectAndStoreListPages($db);
	$nbCompatible = count(array_filter($detection['pages'], static function ($page) use ($acceptEmptyAction) {
		return formlistoverrideIsListPageUsable($page, $acceptEmptyAction);
	}));
	setEventMessages($langs->trans('FormListOverrideDetectionDone', $nbCompatible), null, 'mesgs');
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}

if ($action == 'update') {
	// Checked pages and free lines end up in the same constant, one entry per line:
	// that is what formlistoverrideGetListOfPagesToOverrideForm() reads.
	$entries = array();
	$checkedPages = GETPOST('pages', 'array');
	foreach (is_array($checkedPages) ? $checkedPages : array() as $page) {
		$entries[] = trim((string) $page);
	}
	foreach (preg_split('/[\r\n]+/', (string) GETPOST('extrapages', 'restricthtml')) as $page) {
		$entries[] = trim($page);
	}

	$validEntries = array();
	foreach (array_unique($entries) as $page) {
		if ($page === '') {
			continue;
		}
		if (formlistoverrideIsValidPageEntry($page)) {
			$validEntries[] = $page;
		} else {
			setEventMessages($langs->trans('FormListOverrideInvalidPage', $page), null, 'warnings');
		}
	}

	dolibarr_set_const($db, $pagesConstName, implode("\n", $validEntries), 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'FORMLISTOVERRIDE_DRYRUN', (int) GETPOST('FORMLISTOVERRIDE_DRYRUN', 'int'), 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'FORMLISTOVERRIDE_ACCEPT_EMPTY_ACTION', (int) GETPOST('FORMLISTOVERRIDE_ACCEPT_EMPTY_ACTION', 'int'), 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'FORMLISTOVERRIDE_MENU_PER_TAB', (int) GETPOST('FORMLISTOVERRIDE_MENU_PER_TAB', 'int'), 'chaine', 0, '', $conf->entity);

	setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}


/*
 * View
 */

// First visit: detect once so the page is usable at once. Later runs are on demand
// (button), after installing or upgrading a module.
$detection = formlistoverrideGetStoredListPages();
if ($detection === null) {
	$detection = formlistoverrideDetectAndStoreListPages($db);
}

// Usable pages grouped for display: core first, then one group per external module.
// Lists posting no action are offered only with the empty action option.
$groups = array();
$incompatible = array();
$emptyActionPages = array();
foreach ($detection['pages'] as $page) {
	if ($page['reason'] === 'emptyaction') {
		$emptyActionPages[] = $page['path'];
	}
	if (!formlistoverrideIsListPageUsable($page, $acceptEmptyAction)) {
		$incompatible[] = $page;
		continue;
	}
	if ($page['root'] === 'main') {
		$groupKey = 'main';
		$groupLabel = $langs->trans('FormListOverrideCoreLists');
	} else {
		$moduleDir = explode('/', $page['path'])[0];
		$groupKey = $page['root'].'/'.$moduleDir;
		$groupLabel = $langs->trans('FormListOverrideExternalModule', $moduleDir);
	}
	if (!isset($groups[$groupKey])) {
		$groups[$groupKey] = array('label' => $groupLabel, 'pages' => array());
	}
	$groups[$groupKey]['pages'][] = $page['path'];
}

$candidates = array();
foreach ($groups as $group) {
	$candidates = array_merge($candidates, $group['pages']);
}
list($checkedPages, $otherEntries) = formlistoverrideSplitConfiguredPages(formlistoverrideGetListOfPagesToOverrideForm(), $candidates);
$nbExternal = count($candidates) - (isset($groups['main']) ? count($groups['main']['pages']) : 0);

$help_url = '';
$page_name = "FormListOverrideSetup";

llxHeader('', $langs->trans($page_name), $help_url);

// Subheader
$linkback = '<a href="'.($backtopage ? $backtopage : DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans("BackToModuleList").'</a>';

print load_fiche_titre($langs->trans($page_name), $linkback, 'title_setup');

// Configuration header
$head = formlistoverrideAdminPrepareHead();
print dol_get_fiche_head($head, 'settings', $langs->trans($page_name), -1, "formlistoverride@formlistoverride");

print '<span class="opacitymedium">'.$langs->trans("FormListOverrideSetupPage").'</span><br><br>';

// Detection
print '<form method="POST" action="'.dol_escape_htmltag($_SERVER["PHP_SELF"]).'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="detect">';
print '<div class="info">';
print $langs->trans('FormListOverrideLastDetection', dol_print_date($detection['date'], 'dayhour'), count($candidates), $nbExternal);
print ' <input type="submit" class="button smallpaddingimp" value="'.dol_escape_htmltag($langs->trans('FormListOverrideDetectButton')).'">';
print '<br><span class="opacitymedium">'.$langs->trans('FormListOverrideDetectHelp').'</span>';
print '</div>';
print '</form>';

// Settings
print '<form method="POST" action="'.dol_escape_htmltag($_SERVER["PHP_SELF"]).'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="update">';

print '<div class="warning">'.$langs->trans('FormListOverrideWarningActionList').'</div>';

print '<table class="noborder centpercent">';

// First: it decides which lists are offered below.
print '<tr class="liste_titre"><td>'.$form->textwithpicto($langs->trans('FORMLISTOVERRIDE_ACCEPT_EMPTY_ACTION'), $langs->trans('FORMLISTOVERRIDE_ACCEPT_EMPTY_ACTION_Tooltip')).'</td></tr>';
print '<tr class="oddeven"><td>'.$form->selectyesno('FORMLISTOVERRIDE_ACCEPT_EMPTY_ACTION', (int) $acceptEmptyAction, 1);
print ' <span class="opacitymedium">'.$langs->trans('FormListOverrideAcceptEmptyActionCount', count($emptyActionPages)).'</span></td></tr>';

foreach ($groups as $groupKey => $group) {
	$groupId = 'flo'.dol_hash($groupKey, '3');
	print '<tr class="liste_titre"><td>'.dol_escape_htmltag($group['label']).' ('.count($group['pages']).')';
	print ' &nbsp; <a href="#" class="formlistoverride-checkall" data-group="'.$groupId.'" data-check="1">'.$langs->trans('FormListOverrideCheckAll').'</a>';
	print ' / <a href="#" class="formlistoverride-checkall" data-group="'.$groupId.'" data-check="0">'.$langs->trans('FormListOverrideUncheckAll').'</a>';
	print '</td></tr>';
	print '<tr class="oddeven"><td><div style="columns: 30em auto;">';
	foreach ($group['pages'] as $path) {
		$inputId = $groupId.'_'.dol_hash($path, '3');
		print '<div class="nowraponall"><input type="checkbox" class="formlistoverride-page" data-group="'.$groupId.'" name="pages[]" id="'.$inputId.'" value="'.dol_escape_htmltag($path).'"'.(in_array($path, $checkedPages, true) ? ' checked' : '').'>';
		print ' <label for="'.$inputId.'">'.dol_escape_htmltag($path);
		if (in_array($path, $emptyActionPages, true)) {
			print ' <span class="opacitymedium">('.$langs->trans('FormListOverrideNoActionPosted').')</span>';
		}
		print '</label></div>';
	}
	print '</div></td></tr>';
}

print '<tr class="liste_titre"><td>'.$form->textwithpicto($langs->trans('FormListOverrideExtraPages'), $langs->trans('FormListOverrideExtraPagesHelp')).'</td></tr>';
print '<tr class="oddeven"><td>';
print '<textarea name="extrapages" class="quatrevingtpercent" rows="'.max(3, count($otherEntries) + 1).'">'.dol_escape_htmltag(implode("\n", $otherEntries), 0, 1).'</textarea>';
print '</td></tr>';

print '<tr class="liste_titre"><td>'.$form->textwithpicto($langs->trans('FORMLISTOVERRIDE_DRYRUN'), $langs->trans('FORMLISTOVERRIDE_DRYRUN_Tooltip')).'</td></tr>';
print '<tr class="oddeven"><td>'.$form->selectyesno('FORMLISTOVERRIDE_DRYRUN', getDolGlobalInt('FORMLISTOVERRIDE_DRYRUN'), 1).'</td></tr>';

// Independent of the GET conversion: same module, other proof of concept.
print '<tr class="liste_titre"><td>'.$form->textwithpicto($langs->trans('FORMLISTOVERRIDE_MENU_PER_TAB'), $langs->trans('FORMLISTOVERRIDE_MENU_PER_TAB_Tooltip')).'</td></tr>';
print '<tr class="oddeven"><td>'.$form->selectyesno('FORMLISTOVERRIDE_MENU_PER_TAB', getDolGlobalInt('FORMLISTOVERRIDE_MENU_PER_TAB'), 1).'</td></tr>';
print '</table>';

print '<div class="center"><input type="submit" class="button button-save" value="'.dol_escape_htmltag($langs->trans('Save')).'"></div>';
print '</form>';

// Detected but unusable: shown so nobody wonders why a list is missing above.
if (!empty($incompatible)) {
	print '<br><details><summary>'.$langs->trans('FormListOverrideIncompatibleTitle', count($incompatible)).'</summary>';
	print '<span class="opacitymedium">'.$langs->trans('FormListOverrideIncompatibleHelp').'</span><ul>';
	foreach ($incompatible as $page) {
		$reasonKeys = array('nohook' => 'FormListOverrideReasonNoHook', 'emptyaction' => 'FormListOverrideReasonEmptyAction');
		$reasonKey = $reasonKeys[$page['reason']] ?? 'FormListOverrideReasonNoAction';
		print '<li>'.dol_escape_htmltag($page['path']).' &mdash; <span class="opacitymedium">'.$langs->trans($reasonKey).'</span></li>';
	}
	print '</ul></details>';
}

print '<script>
$(document).ready(function () {
	$(".formlistoverride-checkall").on("click", function (e) {
		e.preventDefault();
		$("input.formlistoverride-page[data-group=\'" + $(this).data("group") + "\']").prop("checked", $(this).data("check") == 1);
	});
});
</script>';

// Page end
print dol_get_fiche_end();

llxFooter();
$db->close();
