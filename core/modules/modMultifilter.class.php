<?php
/* Copyright (C) 2026 Anatole Conseil
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    core/modules/modMultifilter.class.php
 * \ingroup multifilter
 * \brief   Module descriptor for MultiFilter
 */

require_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 * Class modMultifilter
 *
 * Turns single-choice search filters of list pages (payment terms, payment
 * modes, sellist/select extrafields) into multiselect filters with an extra
 * "Not defined" entry. No core file modified: the combo is swapped client-side
 * and the SQL criteria is added through the printFieldListWhere hook.
 */
class modMultifilter extends DolibarrModules
{
	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $langs, $conf;

		$this->db = $db;

		// Anatole Conseil range — next free after dolilucca (185171)
		$this->numero = 185172;
		$this->rights_class = 'multifilter';
		$this->family = 'interface';
		$this->module_position = '90';
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = "MultiFilter - Multiselect and \"Not defined\" search filters on lists";
		$this->descriptionlong = "Search several payment terms, payment modes or extrafield values at once on list pages, and find the records where the value is not set. No core modification.";
		$this->editor_name = 'Anatole Conseil';
		$this->editor_url = '';
		$this->version = '0.1.1';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto = 'fa-filter';

		$this->module_parts = array(
			'hooks' => array('all'),
		);

		$this->dirs = array();
		$this->config_page_url = array('setup.php@multifilter');

		$this->hidden = false;
		$this->depends = array();
		$this->requiredby = array();
		$this->conflictwith = array();
		$this->langfiles = array('multifilter@multifilter');
		$this->phpmin = array(7, 4);
		$this->need_dolibarr_version = array(19, 0);
		$this->warnings_activation = array();
		$this->warnings_activation_ext = array();

		$this->const = array(
			1 => array('MULTIFILTER_PAYMENT', 'chaine', '1', 'Multiselect on payment terms and payment modes filters (invoices, orders, proposals, supplier invoices)', 0, 'current', 0),
			2 => array('MULTIFILTER_EXTRAFIELDS', 'chaine', '1', 'Multiselect on sellist and select extrafields filters (all lists)', 0, 'current', 0),
			3 => array('MULTIFILTER_NOTDEFINED', 'chaine', '1', 'Add a "Not defined" entry to search records with no value', 0, 'current', 0),
			4 => array('MULTIFILTER_DEBUG', 'chaine', '0', 'Log to browser console', 0, 'current', 0),
			5 => array('MULTIFILTER_EXTRAFIELDS_CONTEXTS', 'chaine', '', 'Extra hook contexts (comma separated) of third-party lists that join extrafields with the alias ef', 0, 'current', 0),
		);

		if (!isset($conf->multifilter)) {
			$conf->multifilter = new stdClass();
		}
		if (!isset($conf->multifilter->enabled)) {
			$conf->multifilter->enabled = 0;
		}
		$this->tabs = array();
		$this->dictionaries = array();
		$this->boxes = array();
		$this->cronjobs = array();
		$this->rights = array();
		$this->menu = array();
	}

	/**
	 * Function called when module is enabled.
	 *
	 * @param string $options Options when enabling module ('', 'noboxes')
	 * @return int 1 if OK, 0 if KO
	 */
	public function init($options = '')
	{
		$sql = array();
		return $this->_init($sql, $options);
	}

	/**
	 * Function called when module is disabled.
	 *
	 * @param string $options Options when disabling module ('', 'noboxes')
	 * @return int 1 if OK, 0 if KO
	 */
	public function remove($options = '')
	{
		$sql = array();
		return $this->_remove($sql, $options);
	}
}
