# MultiFilter

Dolibarr module: multiselect and "Not defined" search filters on list pages.

Companion of upstream PR [Dolibarr#39093](https://github.com/Dolibarr/dolibarr/pull/39093), delivered as a module so it works today on Dolibarr 19 to 24 without touching the core.

## What it does

- **Payment modes / payment terms** filters of the customer invoices, customer orders, proposals and supplier invoices lists become multiselects.
- **Extrafields** of type *sellist* and *select* get a multiselect filter on the core list pages that join the extrafields table (58 hook contexts known, see `multifilterGetExtrafieldContexts()`; third-party lists can be added from the setup page).
- A **"Not defined"** entry finds the records with no value set.
- The selection survives pagination, sorting, exports and the "restore last search" feature.

## How it works

No core file modified:

- the combo is swapped client-side (`js/multifilter.js`, injected by the `printCommonFooter` hook); the core field stays in the form, hidden and empty, so the core filter is neutral;
- the values are posted as `search_mf_<field>[]` and turned into SQL by the `printFieldListWhere` hook (`IN (...)`, plus `IS NULL / '' / 0` for "Not defined");
- `printFieldListSearchParam` propagates them to the pagination and export links;
- because the parameter names start with `search_`, Dolibarr saves and restores them with the other search criteria.

## Limits

- Extrafield filters are only applied on a whitelist of hook contexts whose SQL joins extrafields with the `ef` alias; a list outside the whitelist keeps its core single-choice filter. Add a third-party list with the `MULTIFILTER_EXTRAFIELDS_CONTEXTS` option (its query must join `..._extrafields as ef` and call the standard `printFieldListWhere` / `printFieldListSearchParam` hooks).
- Extrafields whose *list* attribute hides them from lists are ignored server-side as well.
- When javascript is off, no filter is applied at all (no hidden server-side criteria).
- Disabling the "Not defined" option also rejects the special value coming from a saved search or a hand-made URL.
- Sellist extrafields rendered through the ajax select2 mode (`MAIN_EXTRAFIELDS_ENABLE_NEW_SELECT2`) are skipped.

## Setup

Home > Setup > Modules > MultiFilter: enable/disable each feature (payment fields, extrafields, "Not defined" entry, debug traces).

## License

GPL v3 or later.
