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
 * Hook contexts of the core list pages that join the extrafields table with the alias "ef"
 * and call the printFieldListWhere hook (Dolibarr 23.0 source, 58 lists). Extrafield filters
 * are only applied on these contexts: other pages with a "...list" context (product/reassortlot.php,
 * projet/tasks/time.php, opensurvey/list.php...) have no such join and would fail with
 * "Unknown column ef.xxx". Third-party lists can be added through the MULTIFILTER_EXTRAFIELDS_CONTEXTS
 * option (comma separated list of hook contexts).
 *
 * @return string[]
 */
function multifilterGetExtrafieldContexts()
{
	$contexts = array(
		'agendalist', 'assetlist', 'assetmodellist', 'bankaccountlist', 'banktransactionlist', 'bomlist',
		'bookcalavailabilitieslist', 'bookcalcalendarlist', 'categoriescategorielist', 'conferenceorboothattendeelist',
		'contactlist', 'contractlist', 'contractservicelist', 'emailcollectorlist', 'emailsenderprofilelist',
		'eventorganizationconferenceorboothlist', 'evaluationlist', 'expensereportlist', 'holidaylist', 'interventionlist',
		'intracommreportlist', 'inventorylist', 'invoicelist', 'invoicereclist', 'joblist', 'knowledgerecordlist',
		'memberlist', 'mrpmolist', 'orderlist', 'orderlistdetail', 'partnershippartnershiplist', 'paymentlist',
		'positionlist', 'product_lotlist', 'productattributelist', 'productservicelist', 'projectlist', 'propallist',
		'receptionlist', 'recruitmentcandidaturelist', 'recruitmentjobpositionlist', 'resourcelist', 'shipmentlist',
		'skilllist', 'stocklist', 'stockmovementlist', 'stocktransferlist', 'subscriptionlist', 'supplier_proposallist',
		'supplierinvoicelist', 'supplierorderlist', 'targetlist', 'tasklist', 'thirdpartylist', 'ticketlist', 'userlist',
		'webhooktriggerhistorylist', 'workstationlist',
	);
	$more = getDolGlobalString('MULTIFILTER_EXTRAFIELDS_CONTEXTS');
	if ($more !== '') {
		foreach (explode(',', $more) as $ctx) {
			$ctx = trim($ctx);
			if ($ctx !== '') {
				$contexts[] = $ctx;
			}
		}
	}
	return $contexts;
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
	$allownotdefined = getDolGlobalInt('MULTIFILTER_NOTDEFINED') ? true : false;
	$out = array();
	foreach ($values as $value) {
		$value = trim((string) $value);
		if ($value === '' || $value === '0' || $value === '-1') {
			continue;
		}
		if ($value === MULTIFILTER_NOTDEFINED_VALUE && !$allownotdefined) {
			continue; // "Not defined" disabled: ignore it even if it comes from a saved search or a hand-made URL
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
	global $db, $conf;

	if (!is_object($object) || empty($object->table_element)) {
		return array();
	}
	// In the ajax select2 mode, sellist search combos are remote-loaded and can't be swapped; select combos are unaffected
	$ajaxsellist = (!empty($conf->use_javascript_ajax) && getDolGlobalString('MAIN_EXTRAFIELDS_ENABLE_NEW_SELECT2'));

	require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
	$extrafields = new ExtraFields($db);
	$extrafields->fetch_name_optionals_label($object->table_element);

	$out = array();
	$attrs = $extrafields->attributes[$object->table_element];
	if (!empty($attrs['type'])) {
		foreach ($attrs['type'] as $key => $type) {
			if (!in_array($type, multifilterExtrafieldTypes())) {
				continue;
			}
			if ($type == 'sellist' && $ajaxsellist) {
				continue;
			}
			// Same visibility rule as the core list pages: abs(list) == 3 (never on lists) or list == 0 (never visible) means no search filter
			$list = isset($attrs['list'][$key]) ? (int) dol_eval((string) $attrs['list'][$key], 1, 1, '1') : 0;
			if ($list == 0 || abs($list) == 3) {
				continue;
			}
			$out[$key] = $type;
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
 * @param string   $type        'int' = integer foreign key (values cast to int, "not defined" = NULL or 0),
 *                              'sellist' = varchar key of a dictionary row (exact match),
 *                              'select' = varchar key of a fixed list; like the core search (natural_search mode 4),
 *                              the key is also matched inside a comma separated value in case the column holds several keys
 * @return string               " AND (...)" or '' if nothing to filter on
 */
function multifilterSqlCriteria($db, $field, $values, $type)
{
	$isint = ($type == 'int');
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
			$keys[] = $db->escape($value);
		}
	}

	$parts = array();
	if (count($keys)) {
		if ($type == 'select') {
			foreach ($keys as $key) {
				$likekey = $db->escapeforlike($key);
				$parts[] = "(".$field." = '".$key."' OR ".$field." LIKE '".$likekey.",%' OR ".$field." LIKE '%,".$likekey."' OR ".$field." LIKE '%,".$likekey.",%')";
			}
		} elseif ($isint) {
			$parts[] = $field." IN (".implode(',', $keys).")";
		} else {
			$parts[] = $field." IN ('".implode("','", $keys)."')";
		}
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
