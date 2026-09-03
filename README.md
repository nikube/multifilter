# MultiFilter

Dolibarr module: multiselect and "Not defined" search filters on list pages.

Companion of upstream PR [Dolibarr#39093](https://github.com/Dolibarr/dolibarr/pull/39093), delivered as a module so it works today on Dolibarr 19 to 24 without touching the core.

## What it does

- **Payment modes / payment terms** filters of the customer invoices, customer orders, proposals and supplier invoices lists become multiselects.
- **Extrafields** of type *sellist* and *select* get a multiselect filter on every list page (67 core lists).
- A **"Not defined"** entry finds the records with no value set.
- The selection survives pagination, sorting, exports and the "restore last search" feature.

## How it works

No core file modified:

- the combo is swapped client-side (`js/multifilter.js`, injected by the `printCommonFooter` hook); the core field stays in the form, hidden and empty, so the core filter is neutral;
- the values are posted as `search_mf_<field>[]` and turned into SQL by the `printFieldListWhere` hook (`IN (...)`, plus `IS NULL / '' / 0` for "Not defined");
- `printFieldListSearchParam` propagates them to the pagination and export links;
- because the parameter names start with `search_`, Dolibarr saves and restores them with the other search criteria.

## Limits

- Lists must call the standard `printFieldListWhere` / `printFieldListSearchParam` hooks and join extrafields with the `ef` alias (this is the case for nearly all core lists).
- Extrafields rendered through the ajax select2 mode (`MAIN_EXTRAFIELDS_ENABLE_NEW_SELECT2`) are not supported.
- Custom lists of third-party modules are covered only if they use the same hooks and aliases.

## Setup

Home > Setup > Modules > MultiFilter: enable/disable each feature (payment fields, extrafields, "Not defined" entry, debug traces).

## License

GPL v3 or later.
