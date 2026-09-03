<?php
/* Copyright (C) 2026 Anatole Conseil
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    class/actions_multifilter.class.php
 * \ingroup multifilter
 * \brief   Hook handlers for the MultiFilter module
 */

require_once dol_buildpath('/multifilter/lib/multifilter.lib.php');

/**
 * Class ActionsMultifilter
 */
class ActionsMultifilter
{
	/**
	 * @var DoliDB Database handler
	 */
	public $db;

	/**
	 * @var string Error string
	 */
	public $error = '';

	/**
	 * @var string[] Errors
	 */
	public $errors = array();

	/**
	 * @var string String printed right after the hook
	 */
	public $resprints = '';

	/**
	 * @var array<string,array> Fields computed per hook context, cached for the whole request.
	 * The selection must be read early (printFieldListWhere): when printCommonFooter runs, llxFooter
	 * has already moved the saved search criteria out of the session, so GETPOST() can no longer
	 * restore them for restore_lastsearch_values=1.
	 */
	private static $fieldsCache = array();

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
	 * Return the fields handled on the current page: name of the core search field =>
	 * array('sql' => column with alias, 'int' => bool, 'selected' => posted values).
	 *
	 * @param string $context Hook context
	 * @param object $object  Current object of the list page
	 * @return array<string,array{sql:string,int:bool,selected:string[]}>
	 */
	private function getFields($context, $object)
	{
		// Key on the list context only: the full context string grows during the request (dao/lib contexts
		// are added after the list initHooks), so it differs between printFieldListWhere and printCommonFooter.
		$cachekey = $this->listContext($context).'|'.(is_object($object) && !empty($object->table_element) ? $object->table_element : '');
		if (isset(self::$fieldsCache[$cachekey])) {
			return self::$fieldsCache[$cachekey];
		}

		$fields = array();

		if (getDolGlobalInt('MULTIFILTER_PAYMENT')) {
			$entry = multifilterGetContextEntry($context, $object);
			if ($entry) {
				foreach ($entry['fields'] as $name => $sqlfield) {
					$fields[$name] = array('sql' => $sqlfield, 'int' => true, 'selected' => multifilterGetSelection($name));
				}
			}
		}

		if (getDolGlobalInt('MULTIFILTER_EXTRAFIELDS')) {
			foreach (multifilterGetExtrafieldTargets($object) as $key => $type) {
				$name = 'search_options_'.$key;
				$fields[$name] = array('sql' => 'ef.'.$key, 'int' => false, 'selected' => multifilterGetSelection($name));
			}
		}

		self::$fieldsCache[$cachekey] = $fields;
		return $fields;
	}

	/**
	 * Return the first list context ("...list") of the hook context string, or '' if none.
	 *
	 * @param string $context Hook context
	 * @return string
	 */
	private function listContext($context)
	{
		foreach (explode(':', (string) $context) as $ctx) {
			if (preg_match('/list$/', $ctx)) {
				return $ctx;
			}
		}
		return '';
	}

	/**
	 * Whether the current hook context is a list page we want to handle.
	 *
	 * @param string $context Hook context
	 * @return bool
	 */
	private function isListContext($context)
	{
		return ($this->listContext($context) !== '');
	}

	/**
	 * printFieldListWhere hook — appends the SQL criteria of the multiselect filters.
	 *
	 * @param array       $parameters  Hook metadata (context, etc...)
	 * @param object      $object      Current object
	 * @param string      $action      Current action
	 * @param HookManager $hookmanager Hook manager
	 * @return int 0 = OK and continue, <0 = error
	 */
	public function printFieldListWhere($parameters, &$object, &$action, $hookmanager)
	{
		$context = isset($parameters['context']) ? $parameters['context'] : '';
		if (!$this->isListContext($context)) {
			return 0;
		}

		$sql = '';
		foreach ($this->getFields($context, $object) as $name => $def) {
			if (!count($def['selected'])) {
				continue;
			}
			$sql .= multifilterSqlCriteria($this->db, $def['sql'], $def['selected'], $def['int']);
		}

		if ($sql !== '') {
			if (getDolGlobalInt('MULTIFILTER_DEBUG')) {
				dol_syslog('multifilter: context='.$context.' where='.$sql, LOG_DEBUG);
			}
			$this->resprints = $sql;
		}

		return 0;
	}

	/**
	 * printFieldListSearchParam hook — keeps the multiselect values into the pagination/sort/export links.
	 *
	 * @param array       $parameters  Hook metadata (context, etc...)
	 * @param object      $object      Current object
	 * @param string      $action      Current action
	 * @param HookManager $hookmanager Hook manager
	 * @return int 0 = OK and continue, <0 = error
	 */
	public function printFieldListSearchParam($parameters, &$object, &$action, $hookmanager)
	{
		$context = isset($parameters['context']) ? $parameters['context'] : '';
		if (!$this->isListContext($context)) {
			return 0;
		}

		$param = '';
		foreach ($this->getFields($context, $object) as $name => $def) {
			$paramname = multifilterParamName($name);
			foreach ($def['selected'] as $value) {
				$param .= '&'.$paramname.'[]='.urlencode($value);
			}
		}

		if ($param !== '') {
			$this->resprints = $param;
		}

		return 0;
	}

	/**
	 * printCommonFooter hook — injects the script that swaps the combos for multiselects on list pages.
	 *
	 * @param array       $parameters  Hook metadata (context, etc...)
	 * @param object      $object      Current object (null for this hook, we use the global one)
	 * @param string      $action      Current action
	 * @param HookManager $hookmanager Hook manager
	 * @return int 0 = OK and continue, <0 = error
	 */
	public function printCommonFooter($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $langs;

		if (empty($conf->use_javascript_ajax)) {
			return 0;
		}
		$context = isset($parameters['context']) ? $parameters['context'] : '';
		if (!$this->isListContext($context)) {
			return 0;
		}

		$listobject = $object;
		if (!is_object($listobject)) {
			global $object;
			$listobject = $object;
		}

		$fields = array();
		foreach ($this->getFields($context, $listobject) as $name => $def) {
			$fields[$name] = array('param' => multifilterParamName($name), 'selected' => $def['selected']);
		}
		if (!count($fields)) {
			return 0;
		}

		$langs->load('multifilter@multifilter');

		$jsfile = dol_buildpath('/multifilter/js/multifilter.js', 0);
		$mtime = file_exists($jsfile) ? filemtime($jsfile) : 0;
		$jsurl = dol_buildpath('/multifilter/js/multifilter.js', 1).'?v='.$mtime;

		print "\n".'<!-- MultiFilter -->'."\n";
		print '<script nonce="'.getNonce().'">window.multifilterConfig = '.json_encode(array(
			'fields' => $fields,
			'notdefined' => getDolGlobalInt('MULTIFILTER_NOTDEFINED') ? 1 : 0,
			'notdefinedValue' => MULTIFILTER_NOTDEFINED_VALUE,
			'notdefinedLabel' => '- '.$langs->transnoentitiesnoconv('MultifilterNotDefined').' -',
			'debug' => getDolGlobalInt('MULTIFILTER_DEBUG') ? 1 : 0,
		)).';</script>'."\n";
		print '<script nonce="'.getNonce().'" src="'.$jsurl.'"></script>'."\n";

		return 0;
	}
}
