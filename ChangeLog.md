# ChangeLog

## 0.1.1 (2026-09-03)

- Extrafield filters restricted to a whitelist of list contexts that join extrafields with the `ef` alias (no more risk of "Unknown column ef.xxx" on pages like reassortlot, tasks time or surveys); new option MULTIFILTER_EXTRAFIELDS_CONTEXTS to add third-party lists.
- Extrafields hidden from lists (list attribute 0 or 3) and sellist in ajax select2 mode are ignored server-side.
- "Not defined" disabled: the special value is now rejected server-side too.
- No server-side filter when javascript is off (the multiselects can't be shown).
- Select extrafields: keys also matched inside comma separated values, like the core search.
- Select2 direction no longer forced to LTR.

## 0.1.0 (2026-09-03)

- Initial version.
- Payment modes and payment terms filters become multiselects on customer invoices, orders, proposals and supplier invoices lists.
- Sellist and select extrafields filters become multiselects on every list page.
- "Not defined" entry to search records with no value set.
- Selection kept through pagination, sorting, exports and "restore last search".
