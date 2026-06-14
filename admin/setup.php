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

global $langs, $user;

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

$error = 0;
$setupnotempty = 0;

// Load the FormSetup factory (Dolibarr v15+). Use the bundled backport on Dolibarr < 16.
if (!class_exists('FormSetup')) {
	if (floatval(DOL_VERSION) < 16.0) {
		require_once __DIR__.'/../backport/v16/core/class/html.formsetup.class.php';
	} else {
		require_once DOL_DOCUMENT_ROOT.'/core/class/html.formsetup.class.php';
	}
}

$formSetup = new FormSetup($db);

// Pages on which the POST search form is converted to GET (one relative path per line).
//
// The field is PROPOSED pre-filled with every core Dolibarr list page plus the generic
// "_list.php" suffix (which also covers custom/modulebuilder module lists). This is only a
// PROPOSAL: the module stays inert until an admin opens this page and SAVES. Remove the
// lines you do not want before saving.
//
// Important: a page must reserve action='list' for display only. Do not keep a list that
// saves data under action='list' (some custom/third-party modules do). Test before saving.
$item = $formSetup->newItem('FORMLISTOVERRIDE_FORCE_FORM_METHOD_GET_WHEN_SEARCH_ON_THIS_PAGES');
$item->helpText = $langs->trans('FORMLISTOVERRIDE_FORCE_FORM_METHOD_GET_WHEN_SEARCH_ON_THIS_PAGES_Tooltip');
$item->defaultFieldValue = <<<'EOT'
accountancy/bookkeeping/list.php
accountancy/customer/list.php
accountancy/expensereport/list.php
accountancy/supplier/list.php
adherents/list.php
adherents/subscription/list.php
asset/list.php
asset/model/list.php
bom/bom_list.php
bookcal/availabilities_list.php
bookcal/booking_list.php
bookcal/calendar_list.php
bookmarks/list.php
categories/categorie_list.php
comm/action/list.php
comm/mailing/list.php
comm/propal/list.php
commande/list.php
compta/bank/bankentries_list.php
compta/bank/list.php
compta/bank/various_payment/list.php
compta/cashcontrol/cashcontrol_list.php
compta/deplacement/list.php
compta/facture/invoicetemplate_list.php
compta/facture/list.php
compta/localtax/list.php
compta/paiement/cheque/list.php
compta/paiement/list.php
compta/prelevement/list.php
compta/prelevement/orders_list.php
compta/sociales/list.php
compta/tva/list.php
contact/list.php
contrat/list.php
contrat/services_list.php
cron/list.php
don/list.php
don/paiement/list.php
eventorganization/conferenceorbooth_list.php
eventorganization/conferenceorboothattendee_list.php
expedition/list.php
expensereport/list.php
expensereport/payment/list.php
fichinter/list.php
fourn/commande/list.php
fourn/facture/list.php
fourn/paiement/list.php
fourn/product/list.php
holiday/list.php
hrm/evaluation_list.php
hrm/job_list.php
hrm/position_list.php
hrm/skill_list.php
intracommreport/list.php
knowledgemanagement/knowledgerecord_list.php
loan/list.php
mrp/mo_list.php
opensurvey/list.php
partnership/partnership_list.php
product/inventory/list.php
product/list.php
product/stock/list.php
product/stock/movement_list.php
product/stock/productlot_list.php
product/stock/stocktransfer/stocktransfer_list.php
projet/list.php
projet/tasks/list.php
public/members/public_list.php
public/ticket/list.php
quickmemo/memo_list.php
reception/list.php
recruitment/recruitmentcandidature_list.php
recruitment/recruitmentjobposition_list.php
resource/list.php
salaries/list.php
societe/list.php
supplier_proposal/list.php
ticket/list.php
user/api_token/list.php
user/group/list.php
user/list.php
variants/list.php
webhook/target_list.php
webhook/triggerhistory_list.php
workstation/workstation_list.php
_list.php
EOT;
$item->setAsTextarea();

// Optional dry-run: log the target GET URL via dol_syslog() instead of redirecting.
// Lets an admin validate the behavior on their own install before enabling it for real.
$item = $formSetup->newItem('FORMLISTOVERRIDE_DRYRUN');
$item->helpText = $langs->trans('FORMLISTOVERRIDE_DRYRUN_Tooltip');
$item->setAsYesNo();

$setupnotempty += count($formSetup->items);


/*
 * Actions
 */

// For retrocompatibility Dolibarr < 15.0
if (versioncompare(explode('.', DOL_VERSION), array(15)) < 0 && $action == 'update' && !empty($user->admin)) {
	$formSetup->saveConfFromPost();
}

include DOL_DOCUMENT_ROOT.'/core/actions_setmoduleoptions.inc.php';


/*
 * View
 */

$form = new Form($db);

$help_url = '';
$page_name = "FormListOverrideSetup";

llxHeader('', $langs->trans($page_name), $help_url);

// Subheader
$linkback = '<a href="'.($backtopage ? $backtopage : DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans("BackToModuleList").'</a>';

print load_fiche_titre($langs->trans($page_name), $linkback, 'title_setup');

// Configuration header
$head = formlistoverrideAdminPrepareHead();
print dol_get_fiche_head($head, 'settings', $langs->trans($page_name), -1, "formlistoverride@formlistoverride");

// Setup page goes here
echo '<span class="opacitymedium">'.$langs->trans("FormListOverrideSetupPage").'</span><br><br>';

if ($action == 'edit') {
	print $formSetup->generateOutput(true);
	print '<br>';
} elseif (!empty($formSetup->items)) {
	print $formSetup->generateOutput();
	print '<div class="tabsAction">';
	print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?action=edit&token='.newToken().'">'.$langs->trans("Modify").'</a>';
	print '</div>';
} else {
	print '<br>'.$langs->trans("NothingToSetup");
}

if (empty($setupnotempty)) {
	print '<br>'.$langs->trans("NothingToSetup");
}

// Page end
print dol_get_fiche_end();

llxFooter();
$db->close();
