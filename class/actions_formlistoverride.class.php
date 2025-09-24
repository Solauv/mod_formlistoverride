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
 * \file    formlistoverride/class/actions_formlistoverride.class.php
 * \ingroup formlistoverride
 * \brief   Example hook overload.
 *
 * Put detailed description here.
 */

include_once DOL_DOCUMENT_ROOT.'/custom/formlistoverride/lib/formlistoverride.lib.php';

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
	 * @var array Errors
	 */
	public $errors = array();


	/**
	 * @var array Hook results. Propagated to $hookmanager->resArray for later reuse
	 */
	public $results = array();

	/**
	 * @var string String displayed by executeHook() immediately after return
	 */
	public $resprints;

	/**
	 * @var int		Priority of hook (50 is used if value is not defined)
	 */
	public $priority;


	/**
	 * Constructor
	 *
	 *  @param		DoliDB		$db      Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	public function checkContext($contextsToTest, $pageContextArray): bool
	{
		if (is_array($contextsToTest)) {
			return count(array_intersect($contextsToTest, $pageContextArray)) > 0;
		} else {
			return in_array($contextsToTest, $pageContextArray);
		}
	}

	/**
	 * Overloading the doActions function : replacing the parent's function with the one below
	 *
	 * @param   array           $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          $action         Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function addHtmlHeader($parameters, &$object, &$action, $hookmanager)
	{
		$retValue = 0;
		if ($this->checkContext('main', $hookmanager->contextarray)) {
			$pagesToOverride = formlistoverrideGetListOfPagesToOverrideForm();
			if (count($pagesToOverride)) {
				print "<script>const formListOverridePages = JSON.parse('".json_encode($pagesToOverride)."');</script>";
				print '<script defer src="'. dol_buildpath('/custom/formlistoverride/js/formListOverride.js', 1) . '"></script>';
				$retValue = 1;
			}
		}
		return $retValue;
	}
}
