<?php
/* Copyright (C) 2025-2026	Solauv					<contact@solauv.fr>
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
 * \file    formlistoverride/class/actions_formlistoverride.class.php
 * \ingroup formlistoverride
 * \brief   Hook overload for FormListOverride.
 */

// Load the module library. dol_include_once resolves the module path whatever the
// alternative root is (/custom or other), so we do NOT hardcode '/custom/'.
dol_include_once('/formlistoverride/lib/formlistoverride.lib.php');

/**
 * Class ActionsFormListOverride
 */
class ActionsFormListOverride
{
	/**
	 * @var DoliDB Database handler.
	 */
	public $db;

	/**
	 * @var string Error code (or message)
	 */
	public $error = '';

	/**
	 * @var string[] Errors
	 */
	public $errors = array();

	/**
	 * @var array<string,mixed> Hook results. Propagated to $hookmanager->resArray for later reuse
	 */
	public $results = array();

	/**
	 * @var string String displayed by executeHook() immediately after return
	 */
	public $resprints;

	/**
	 * @var int Priority of hook (50 is used if value is not defined)
	 */
	public $priority;

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Overloading the doActions function : replacing the parent's function with the one below
	 *
	 * @param   array<string,mixed> $parameters Hook metadatas (context, etc...)
	 * @param   CommonObject    $object         The object to process
	 * @param   string          $action         Current action
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             Negative on error, 0 on success, 1 to replace standard code
	 */
	public function doActions($parameters, &$object, &$action, $hookmanager)
	{
		if (function_exists('formlistoverrideConvertPostSearchListRequestToGetIfPossible')
			&& !empty($hookmanager->contextarray)) {
			formlistoverrideConvertPostSearchListRequestToGetIfPossible($hookmanager->contextarray, $action);
		}
		if (getDolGlobalInt('FORMLISTOVERRIDE_MENU_PER_TAB') && function_exists('formlistoverrideMenuPerTabWatchRedirect')) {
			formlistoverrideMenuPerTabWatchRedirect();
		}

		return 0;
	}

	/**
	 * Called in top_htmlhead(), on every page with a header (Dolibarr 16 to 24), once the core
	 * has read mainmenu/leftmenu. Starts adding the menu section to the links and forms of the
	 * page, when the menu is kept per browser tab.
	 *
	 * @param   array<string,mixed> $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject|null   $object         Not used
	 * @param   string              $action         Current action
	 * @param   HookManager         $hookmanager    Hook manager
	 * @return  int                                 0
	 */
	public function addHtmlHeader($parameters, &$object, &$action, $hookmanager)
	{
		if (getDolGlobalInt('FORMLISTOVERRIDE_MENU_PER_TAB') && function_exists('formlistoverrideMenuPerTabStartPage')) {
			formlistoverrideMenuPerTabStartPage();
		}

		return 0;
	}
}
