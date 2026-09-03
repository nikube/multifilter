<?php
/* Copyright (C) 2026 Anatole Conseil
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    lib/multifilter.lib.php
 * \ingroup multifilter
 * \brief   Registry of the core filters handled by MultiFilter + helpers
 */

/**
 * Special value posted by the multiselect to search records with no value set.
 */
define('MULTIFILTER_NOTDEFINED_VALUE', '-2');

/**
 * Registry of the core (non extrafield) single-select filters turned into multiselects.
 *
 * Keyed by hook context (the value given to $hookmanager->initHooks() by the list page).
 * For each context: the expected $object->element (sanity check) and the map
 * "name of the core search field" => "SQL column with its alias in the list query".
 *
 * The module posts its own values under search_mf_<name>[] (see js/multifilter.js),
 * leaves the core field empty so the core filter is neutral, and forges the SQL itself
 * in the printFieldListWhere hook.
 *
 * @return array<string,array{element:string,fields:array<string,string>}>
 */
function multifilterGetRegistry()
{
	return array(
		'invoicelist' => array(
			'element' => 'facture',
			'fields' => array(
				'search_paymentmode' => 'f.fk_mode_reglement',
				'search_paymentterms' => 'f.fk_cond_reglement',
			),
		),
		'orderlist' => array(
			'element' => 'commande',
			'fields' => array(
				'search_fk_mode_reglement' => 'c.fk_mode_reglement',
				'search_fk_cond_reglement' => 'c.fk_cond_reglement',
			),
		),
		'propallist' => array(
			'element' => 'propal',
			'fields' => array(
				'search_fk_mode_reglement' => 'p.fk_mode_reglement',
				'search_fk_cond_reglement' => 'p.fk_cond_reglement',
			),
		),
		'supplierinvoicelist' => array(
			'element' => 'invoice_supplier',
			'fields' => array(
				'search_paymentmode' => 'f.fk_mode_reglement',
				'search_paymentcond' => 'f.fk_cond_reglement',
			),
		),
	);
}

/**
 * Return the registry entry matching the current hook context, or null.
 *
 * @param string $context Hook context string ("ctx1:ctx2:...")
 * @param object $object  Current object of the list page (may be null)
 * @return array{element:string,fields:array<string,string>}|null
 */
function multifilterGetContextEntry($context, $object)
{
	$registry = multifilterGetRegistry();
	foreach (explode(':', (string) $context) as $ctx) {
		if (isset($registry[$ctx])) {
			if (is_object($object) && !empty($object->element) && $object->element != $registry[$ctx]['element']) {
				return null; // Not the list we expect for this context
			}
			return $registry[$ctx];
		}
	}
	return null;
}

/**
 * Name of the module parameter carrying the multiselect values of a core search field.
 * search_paymentmode -> search_mf_paymentmode, search_options_foo -> search_mf_options_foo.
 * Keeping the "search_" prefix makes Dolibarr save/restore it with the last search criteria.
 *
 * @param string $name Name of the core search field
 * @return string
 */
function multifilterParamName($name)
{
	return preg_replace('/^search_/', 'search_mf_', $name);
}

/**
 * Return the values posted for a multiselect filter (empty array if none, or if the
 * filters have been reset with the "remove filter" button).
 *
 * @param string $name Name of the core search field
 * @return string[]
 */
function multifilterGetSelection($name)
{
	if (multifilterIsReset()) {
		return array();
	}
	$values = GETPOST(multifilterParamName($name), 'array:alphanohtml');
	if (!is_array($values)) {
		return array();
	}
	$out = array();
	foreach ($values as $value) {
		$value = trim((string) $value);
		if ($value === '' || $value === '0' || $value === '-1') {
			continue;
		}
		$out[$value] = $value;
	}
	return array_values($out);
}

/**
 * Whether the current request is a "remove filters" submit of a list page.
 *
 * @return bool
 */
function multifilterIsReset()
{
	return (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha'));
}

/**
 * Types of extrafields handled by the module (single-choice combos in search filters).
 *
 * @return string[]
 */
function multifilterExtrafieldTypes()
{
	return array('sellist', 'select');
}

/**
 * Return the extrafields of the object that can be turned into multiselect filters:
 * key => type, for the types of multifilterExtrafieldTypes().
 *
 * @param object $object Current object of the list page
 * @return array<string,string>
 */
function multifilterGetExtrafieldTargets($object)
{
	global $db;

	if (!is_object($object) || empty($object->table_element)) {
		return array();
	}
	require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
	$extrafields = new ExtraFields($db);
	$extrafields->fetch_name_optionals_label($object->table_element);

	$out = array();
	if (!empty($extrafields->attributes[$object->table_element]['type'])) {
		foreach ($extrafields->attributes[$object->table_element]['type'] as $key => $type) {
			if (in_array($type, multifilterExtrafieldTypes())) {
				$out[$key] = $type;
			}
		}
	}
	return $out;
}

/**
 * Build the SQL criteria for a multiselect selection on a column.
 *
 * @param DoliDB   $db          Database handler
 * @param string   $field       SQL column with alias (ex: "f.fk_mode_reglement", "ef.myfield")
 * @param string[] $values      Selected values (may contain MULTIFILTER_NOTDEFINED_VALUE)
 * @param bool     $isint       True if the column is an integer foreign key (values cast to int, "not defined" = NULL or 0)
 * @return string               " AND (...)" or '' if nothing to filter on
 */
function multifilterSqlCriteria($db, $field, $values, $isint)
{
	$notdefined = false;
	$keys = array();
	foreach ($values as $value) {
		if ((string) $value === MULTIFILTER_NOTDEFINED_VALUE) {
			$notdefined = true;
		} elseif ($isint) {
			if ((int) $value > 0) {
				$keys[] = (int) $value;
			}
		} else {
			$keys[] = "'".$db->escape($value)."'";
		}
	}

	$parts = array();
	if (count($keys)) {
		$parts[] = $field." IN (".implode(',', $keys).")";
	}
	if ($notdefined) {
		if ($isint) {
			$parts[] = "(".$field." IS NULL OR ".$field." = 0)";
		} else {
			// An empty sellist/select value can be stored as NULL, '' or '0' depending on how the record was saved
			$parts[] = "(".$field." IS NULL OR ".$field." = '' OR ".$field." = '0')";
		}
	}
	if (!count($parts)) {
		return '';
	}
	return " AND (".implode(" OR ", $parts).")";
}
