<?php
/* Copyright (C) 2026 Anatole Conseil
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    admin/setup.php
 * \ingroup multifilter
 * \brief   MultiFilter setup page
 */

$res = 0;
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res && file_exists("../../../../main.inc.php")) {
	$res = @include "../../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

global $conf, $db, $langs, $user;

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/ajax.lib.php';
require_once dol_buildpath('/multifilter/lib/multifilter.lib.php');

$langs->loadLangs(array('multifilter@multifilter', 'admin', 'bills', 'orders', 'propal', 'suppliers'));

if (!$user->admin) {
	accessforbidden();
}

$switches = array(
	'MULTIFILTER_PAYMENT' => 'MultifilterFeaturePayment',
	'MULTIFILTER_EXTRAFIELDS' => 'MultifilterFeatureExtrafields',
	'MULTIFILTER_NOTDEFINED' => 'MultifilterFeatureNotDefined',
	'MULTIFILTER_DEBUG' => 'MultifilterFeatureDebug',
);

llxHeader('', $langs->trans('MultifilterSetup'));

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans('MultifilterSetup'), $linkback, 'fa-filter');

print '<span class="opacitymedium">'.$langs->trans('MultifilterSetupIntro').'</span><br><br>';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans('Parameter').'</td><td class="center">'.$langs->trans('Value').'</td></tr>';
foreach ($switches as $const => $langkey) {
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans($langkey).'<br><span class="opacitymedium small">'.$const.'</span></td>';
	print '<td class="center">'.ajax_constantonoff($const).'</td>';
	print '</tr>';
}
print '</table>';

print '<br>';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans('MultifilterCoveredLists').'</td><td>'.$langs->trans('MultifilterCoveredFields').'</td></tr>';
$labels = array(
	'invoicelist' => $langs->trans('BillsCustomers'),
	'orderlist' => $langs->trans('CustomersOrders'),
	'propallist' => $langs->trans('Proposals'),
	'supplierinvoicelist' => $langs->trans('BillsSuppliers'),
);
foreach (multifilterGetRegistry() as $context => $entry) {
	print '<tr class="oddeven">';
	print '<td>'.(isset($labels[$context]) ? $labels[$context] : $context).' <span class="opacitymedium small">('.$context.')</span></td>';
	print '<td>'.$langs->trans('PaymentMode').', '.$langs->trans('PaymentConditions').'</td>';
	print '</tr>';
}
print '<tr class="oddeven">';
print '<td>'.$langs->trans('MultifilterAllLists').'</td>';
print '<td>'.$langs->trans('MultifilterExtrafieldsTypes').'</td>';
print '</tr>';
print '</table>';

print '<br><span class="opacitymedium">'.$langs->trans('MultifilterHint').'</span>';

llxFooter();
$db->close();
