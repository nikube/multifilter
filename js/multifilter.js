/* Copyright (C) 2026 Anatole Conseil
 * MultiFilter — swaps single-choice search combos of list pages for multiselects.
 * Configuration is injected by the printCommonFooter hook (window.multifilterConfig).
 */
(function ($) {
	'use strict';

	var cfg = window.multifilterConfig;
	if (!cfg || !$ || !cfg.fields) {
		return;
	}

	function log() {
		if (cfg.debug && window.console) {
			console.log.apply(console, ['[multifilter]'].concat(Array.prototype.slice.call(arguments)));
		}
	}

	function isIgnoredValue(v) {
		return v === '' || v === '0' || v === '-1';
	}

	function swap(name, def) {
		// Core search combos: single select (sellist) or, since Dolibarr 19, native multiselect "name[]" (select)
		var $orig = $('select[name="' + name + '"], select[name="' + name + '[]"]').first();
		if (!$orig.length) {
			log('field not found', name);
			return;
		}

		var selected = (def.selected || []).map(String);
		var origValue = $orig.val();
		var origValues = $.isArray(origValue) ? origValue : (origValue === null ? [] : [origValue]);
		origValues = origValues.map(String).filter(function (v) { return !isIgnoredValue(v); });
		// A value coming from an old link or a saved search on the core field: import it, then neutralize the core filter
		if (!selected.length && origValues.length) {
			selected = origValues;
		}

		var $new = $('<select multiple="multiple"></select>')
			.attr('name', def.param + '[]')
			.attr('id', def.param)
			.addClass('flat multifilter minwidth100');

		if (cfg.notdefined) {
			$('<option></option>').attr('value', cfg.notdefinedValue).text(cfg.notdefinedLabel).appendTo($new);
		}
		$orig.find('option').each(function () {
			var v = String(this.value);
			if (isIgnoredValue(v) || v === cfg.notdefinedValue) {
				return;
			}
			$('<option></option>').attr('value', v).text($(this).text()).appendTo($new);
		});
		$new.val(selected);

		// Hide the core combo (and its select2 widget if any) and post it empty
		if ($orig.data('select2')) {
			try { $orig.select2('destroy'); } catch (e) { /* ignore */ }
		}
		$orig.next('.select2-container').remove();
		$orig.val('').hide();
		$orig.after($new);

		if ($.fn.select2) {
			$new.select2({
				width: '100%',
				minimumInputLength: 0,
				closeOnSelect: false,
				dropdownAutoWidth: true,
				placeholder: '',
				language: (window.select2arrayoflanguage || undefined)
			});
			// Keep the widget compact inside the filter row
			$new.next('.select2-container').css({'min-width': '100px'});
		}
		log('swapped', name, selected);
	}

	$(function () {
		$.each(cfg.fields, function (name, def) {
			swap(name, def);
		});
	});
})(window.jQuery);
