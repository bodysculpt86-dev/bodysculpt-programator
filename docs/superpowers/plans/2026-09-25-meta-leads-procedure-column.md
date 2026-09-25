# Meta Leads Procedure Column Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Show a human-readable "Procedura" column/row on the Meta Leads admin page, derived purely from the existing `form_id` — no schema change, no ingestion change.

**Architecture:** A single PHP constant array maps the 3 known `form_id`s to a procedure name. `Meta_leads_model::search_calls()` resolves each row's `procedure` from that constant (falling back to the raw `form_id` when unmapped) and also accepts an optional `form_id` filter. The view renders the filter `<option>`s server-side directly from the same constant (no second copy of the mapping, no AJAX call for filter options). The JS just displays the already-resolved `lead.procedure` field.

**Tech Stack:** PHP 8 / CodeIgniter 3 (Easy!Appointments fork), jQuery + Bootstrap 5, no build step for `assets/js`.

**Spec:** user request pasted in this conversation (Romanian, final scope after two rounds of narrowing — no DB column, no `ea_meta_forms` table, no label admin, no `Webhooks_make.php` changes).

**Testing note (deviation from the default TDD step shape):** this codebase has no PHPUnit harness for DB-touching models/controllers (`tests/` only covers pure helpers). This plan follows that convention: each step's verification is `php -l` / `node --check` plus a manual browser/curl check, matching how the existing `search_calls()` filter UI shipped.

## Global Constraints

- No new migration, no new table, no new column.
- No changes to `Webhooks_make.php` or any ingestion path — Make does not send a form name, so there is nothing to ingest.
- The `form_id -> procedure` mapping is defined in exactly one place and reused everywhere (backend resolution and the filter dropdown both read the same PHP array — never duplicated into JS).
- Unmapped `form_id` → display the raw `form_id` (never blank, never an error).

## Review Focus

- **A lead's `form_id` is `''` (empty string) or missing** — must fall back to displaying `form_id` (i.e. an empty string), not throw a PHP warning on array access. Covered in Task 2.
- **The `form_id` filter is applied and no lead matches** — the existing "no records" UI state must still render correctly (already handled by `render()`'s `!leads.length` branch; verified in Task 4).
- **A `form_id` outside the 3 known keys is selected via URL/manual POST (not through the dropdown)** — `search_calls()` must still filter correctly by raw `form_id` equality even though it has no dropdown entry; the fallback display still resolves to that `form_id`. Covered in Task 2.

---

## File Structure

- `application/config/constants.php` — modified. Add `META_LEAD_FORM_PROCEDURES` constant.
- `application/models/Meta_leads_model.php` — modified. `search_calls()` gains a `$form_id` filter param and resolves `procedure` per row.
- `application/controllers/Meta_leads.php` — modified. `search_calls()` reads `form_id` from the request.
- `application/language/romanian/translations_lang.php`, `application/language/english/translations_lang.php` — modified. Two new lang keys.
- `application/views/pages/meta_leads.php` — modified. New "Procedura" desktop column header; new filter `<select>` rendered server-side from the constant.
- `assets/js/http/meta_leads_http_client.js` — modified. `searchCalls()` gains a `formId` param.
- `assets/js/pages/meta_leads.js` — modified. Renders the "Procedura" column/row; wires the filter `<select>`.

---

### Task 1: Define the `form_id -> procedure` mapping

**Files:**
- Modify: `application/config/constants.php`

**Interfaces:**
- Produces: `META_LEAD_FORM_PROCEDURES` (array, `form_id => procedure name`), consumed by Task 2 (model resolution) and Task 5 (view filter options).

- [ ] **Step 1: Add the constant**

In `application/config/constants.php`, right after the `LDAP_DEFAULT_FIELD_MAPPING` block (line 136) and before the `Webhook Actions` section comment, add:

```php
/*
|--------------------------------------------------------------------------
| Meta Lead Ads Form -> Procedure Mapping
|--------------------------------------------------------------------------
|
| Meta's Instant Form names are internal and unclear, so the Meta Leads
| admin page shows a clinic-controlled procedure name instead. A form_id
| not present here falls back to displaying the raw form_id.
|
*/
const META_LEAD_FORM_PROCEDURES = [
    '2552388718563534' => 'Micropigmentare',
    '33734214119557107' => 'Masaj',
    '708851772252922' => 'Criolipoliza + HIFU',
];
```

- [ ] **Step 2: Verify**

```
php -l application/config/constants.php
```

Expected: `No syntax errors detected ...`.

- [ ] **Step 3: Commit**

```bash
git add application/config/constants.php
git commit -m "feat(meta-leads): add form_id -> procedure mapping constant"
```

---

### Task 2: `Meta_leads_model::search_calls()` — resolve `procedure`, add `form_id` filter

**Files:**
- Modify: `application/models/Meta_leads_model.php:160-194` (the `search_calls()` method)

**Interfaces:**
- Consumes: `META_LEAD_FORM_PROCEDURES` (Task 1).
- Produces: `search_calls(?string $call_status = null, string $keyword = '', int $limit = 200, int $offset = 0, ?string $form_id = null): array` — each row now also carries a `procedure` string. Consumed by Task 3 (controller) and Task 6 (JS render).

- [ ] **Step 1: Edit the method**

Replace the existing `search_calls()` method (lines 160–194) with:

```php
    /**
     * Search meta leads for the internal call workflow, optionally filtered by
     * call_status and/or form_id. Each row is enriched with a has_appointments
     * count (whether the linked customer has any appointment), the assigned
     * user's name, and procedure (the clinic-facing name for the lead's
     * form_id, from META_LEAD_FORM_PROCEDURES, falling back to the raw
     * form_id when it isn't in that mapping).
     *
     * Leads are ordered strictly newest-first (received_at DESC), with no
     * call_status prioritization.
     *
     * @param string|null $call_status One of the allowed call statuses, null for all.
     * @param string $keyword
     * @param int $limit
     * @param int $offset
     * @param string|null $form_id Only leads from this form_id, null for all.
     *
     * @return array
     */
    public function search_calls(
        ?string $call_status = null,
        string $keyword = '',
        int $limit = 200,
        int $offset = 0,
        ?string $form_id = null,
    ): array {
        $this->db
            ->select(
                "ml.*, " .
                    "COUNT(a.id) AS has_appointments, " .
                    "MAX(CONCAT_WS(' ', u.first_name, u.last_name)) AS assigned_to_name",
                false,
            )
            ->from('meta_leads ml')
            ->join('appointments a', 'a.id_users_customer = ml.customer_id AND a.is_unavailability = 0', 'left')
            ->join('users u', 'u.id = ml.assigned_to', 'left');

        if ($keyword !== '') {
            $this->db
                ->group_start()
                ->like('ml.first_name', $keyword)
                ->or_like('ml.last_name', $keyword)
                ->or_like('CONCAT_WS(" ", ml.first_name, ml.last_name)', $keyword, 'both', false)
                ->or_like('ml.email', $keyword)
                ->or_like('ml.phone_number', $keyword)
                ->group_end();
        }

        $allowed = ['de sunat', 'nu a raspuns', 'revine', 'nu e interesat'];

        if ($call_status !== null && in_array($call_status, $allowed, true)) {
            $this->db->where('ml.call_status', $call_status);
        }

        if ($form_id !== null && $form_id !== '') {
            $this->db->where('ml.form_id', $form_id);
        }

        $this->db->group_by('ml.id');
        $this->db->order_by('ml.received_at', 'DESC');

        $leads = $this->db->limit($limit, $offset)->get()->result_array();

        foreach ($leads as &$lead) {
            $lead['procedure'] = META_LEAD_FORM_PROCEDURES[$lead['form_id'] ?? ''] ?? (string) ($lead['form_id'] ?? '');
        }

        return $leads;
    }
```

- [ ] **Step 2: Verify**

```
php -l application/models/Meta_leads_model.php
```

Expected: `No syntax errors detected ...`.

Against a local/dev DB, confirm the mapping and the fallback both resolve correctly (adjust the `form_id` values to match real rows in your dev data, or run against production read-replica data if that's what's available):

```sql
-- Sanity-check the raw data the fallback/mapping depend on:
SELECT DISTINCT form_id FROM ea_meta_leads;
```

Then load the page once Task 6 is wired up (or temporarily add a `var_dump` in a scratch script) to confirm: rows with `form_id = '2552388718563534'` resolve to `procedure = 'Micropigmentare'`, and rows with any other `form_id` resolve to `procedure = <that form_id>` (not an empty string, not a PHP warning in the logs).

- [ ] **Step 3: Commit**

```bash
git add application/models/Meta_leads_model.php
git commit -m "feat(meta-leads): resolve procedure from form_id and add form_id filter to search_calls"
```

---

### Task 3: `Meta_leads` controller — read `form_id` filter

**Files:**
- Modify: `application/controllers/Meta_leads.php:106-131` (the `search_calls()` method)

**Interfaces:**
- Consumes: `Meta_leads_model::search_calls()`'s new `$form_id` param (Task 2).
- Produces: `POST meta_leads/search_calls` now accepts an optional `form_id` field. Consumed by Task 6 (JS).

- [ ] **Step 1: Edit the method**

Change:

```php
            $keyword = trim((string) request('keyword', ''));
            $call_status = request('call_status');

            $allowed = ['de sunat', 'nu a raspuns', 'revine', 'nu e interesat'];

            if (!in_array($call_status, $allowed, true)) {
                $call_status = null;
            }

            $limit = (int) request('limit', 200);
            $offset = (int) request('offset', 0);

            json_response(array_values($this->meta_leads_model->search_calls($call_status, $keyword, $limit, $offset)));
```

to:

```php
            $keyword = trim((string) request('keyword', ''));
            $call_status = request('call_status');

            $allowed = ['de sunat', 'nu a raspuns', 'revine', 'nu e interesat'];

            if (!in_array($call_status, $allowed, true)) {
                $call_status = null;
            }

            $limit = (int) request('limit', 200);
            $offset = (int) request('offset', 0);

            $form_id = trim((string) request('form_id', ''));
            $form_id = $form_id !== '' ? $form_id : null;

            json_response(
                array_values($this->meta_leads_model->search_calls($call_status, $keyword, $limit, $offset, $form_id)),
            );
```

- [ ] **Step 2: Verify**

```
php -l application/controllers/Meta_leads.php
```

Expected: `No syntax errors detected ...`.

With an authenticated admin session, confirm via the browser network tab (once Task 6 is wired up) that selecting a procedure in the new filter sends `form_id=2552388718563534` (etc.) in the `search_calls` POST body and the response only contains leads with that `form_id`.

- [ ] **Step 3: Commit**

```bash
git add application/controllers/Meta_leads.php
git commit -m "feat(meta-leads): read form_id filter in search_calls controller action"
```

---

### Task 4: Language keys

**Files:**
- Modify: `application/language/romanian/translations_lang.php:460`
- Modify: `application/language/english/translations_lang.php:460`

**Interfaces:**
- Produces: `lang('meta_leads_procedure')`, `lang('meta_leads_all_procedures')` — consumed by Task 5 (view) and Task 6 (JS).

- [ ] **Step 1: Add the Romanian keys**

In `application/language/romanian/translations_lang.php`, right after line 460 (`$lang['meta_leads_form_answers'] = 'Răspunsuri formular';`), add:

```php
$lang['meta_leads_procedure'] = 'Procedura';
$lang['meta_leads_all_procedures'] = 'Toate procedurile';
```

- [ ] **Step 2: Add the English keys**

In `application/language/english/translations_lang.php`, right after the equivalent `$lang['meta_leads_form_answers'] = 'Form answers';` line, add:

```php
$lang['meta_leads_procedure'] = 'Procedure';
$lang['meta_leads_all_procedures'] = 'All procedures';
```

- [ ] **Step 3: Verify**

```
php -l application/language/romanian/translations_lang.php
php -l application/language/english/translations_lang.php
```

Expected: `No syntax errors detected ...` for both.

- [ ] **Step 4: Commit**

```bash
git add application/language/romanian/translations_lang.php application/language/english/translations_lang.php
git commit -m "feat(meta-leads): add procedure column/filter translation keys"
```

---

### Task 5: View — "Procedura" column header, filter `<select>`

**Files:**
- Modify: `application/views/pages/meta_leads.php`

**Interfaces:**
- Consumes: `META_LEAD_FORM_PROCEDURES` (Task 1), lang keys (Task 4).
- Produces: DOM element `#meta-leads-procedure-filter` (a `<select>`, options rendered server-side) — consumed by Task 6 (JS).

- [ ] **Step 1: Add the procedure filter `<select>` next to the existing filters**

Change the filters row:

```php
        <div class="col-12 col-md-4 ms-md-auto">
            <div class="input-group">
                <input type="text" id="meta-leads-keyword" class="form-control"
                       placeholder="<?= lang('type_to_filter_meta_leads') ?>">
                <button type="button" id="meta-leads-filter" class="btn btn-outline-secondary">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </div>
    </div>
```

to:

```php
        <div class="col-12 col-md-auto">
            <select id="meta-leads-procedure-filter" class="form-select">
                <option value=""><?= lang('meta_leads_all_procedures') ?></option>
                <?php foreach (META_LEAD_FORM_PROCEDURES as $form_id => $procedure): ?>
                    <option value="<?= html_escape($form_id) ?>"><?= html_escape($procedure) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-12 col-md-4 ms-md-auto">
            <div class="input-group">
                <input type="text" id="meta-leads-keyword" class="form-control"
                       placeholder="<?= lang('type_to_filter_meta_leads') ?>">
                <button type="button" id="meta-leads-filter" class="btn btn-outline-secondary">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </div>
    </div>
```

(`html_escape()` is CodeIgniter core's global output-escaping function, defined in `system/core/Common.php` — always available with no autoload/helper-load step needed. Confirmed no competing escaping helper is used elsewhere in `application/views`, so this is the correct choice.)

- [ ] **Step 2: Add the "Procedura" desktop column**

Change the desktop table header:

```php
                <tr>
                    <th style="width: 15%"><?= lang('meta_leads_name') ?></th>
                    <th style="width: 28%"><?= lang('meta_leads_form_answers') ?></th>
                    <th style="width: 10%"><?= lang('meta_leads_received_at') ?></th>
                    <th style="width: 12%"><?= lang('call_status') ?></th>
                    <th style="width: 13%"><?= lang('assigned_to') ?></th>
                    <th style="width: 16%"><?= lang('call_note') ?></th>
                    <th style="width: 6%"><?= lang('meta_leads_actions') ?></th>
                </tr>
```

to:

```php
                <tr>
                    <th style="width: 14%"><?= lang('meta_leads_name') ?></th>
                    <th style="width: 10%"><?= lang('meta_leads_procedure') ?></th>
                    <th style="width: 22%"><?= lang('meta_leads_form_answers') ?></th>
                    <th style="width: 9%"><?= lang('meta_leads_received_at') ?></th>
                    <th style="width: 11%"><?= lang('call_status') ?></th>
                    <th style="width: 12%"><?= lang('assigned_to') ?></th>
                    <th style="width: 16%"><?= lang('call_note') ?></th>
                    <th style="width: 6%"><?= lang('meta_leads_actions') ?></th>
                </tr>
```

- [ ] **Step 3: Verify**

```
php -l application/views/pages/meta_leads.php
```

Expected: `No syntax errors detected ...`.

Load `meta_leads` in the browser (admin session) and confirm: the filters row shows the call-status buttons, a new "Toate procedurile" / "All procedures" `<select>` populated with exactly the 3 procedure names (view source to confirm the `<option value="2552388718563534">Micropigmentare</option>` shape), the keyword box; the desktop table header shows a new "Procedura" column between "Nume" and "Răspunsuri formular".

- [ ] **Step 4: Commit**

```bash
git add application/views/pages/meta_leads.php
git commit -m "feat(meta-leads): add procedure column header and filter select to the view"
```

---

### Task 6: JS — HTTP client `formId` param, render "Procedura" column/row, wire the filter

**Files:**
- Modify: `assets/js/http/meta_leads_http_client.js`
- Modify: `assets/js/pages/meta_leads.js`

**Interfaces:**
- Consumes: DOM element `#meta-leads-procedure-filter` (Task 5); each lead row now carries `procedure` (Task 2).
- Produces: `App.Http.MetaLeads.searchCalls(callStatus, keyword, limit, offset, formId)` (new 5th param).

- [ ] **Step 1: Extend `searchCalls()` in the HTTP client**

In `assets/js/http/meta_leads_http_client.js`, change:

```javascript
    function searchCalls(callStatus = null, keyword = '', limit = 200, offset = 0) {
        const url = App.Utils.Url.siteUrl('meta_leads/search_calls');

        const data = {
            csrf_token: vars('csrf_token'),
            keyword,
            limit,
            offset,
        };

        // Omit call_status entirely for "All" (null) so the backend sees no
        // filter. Sending `call_status: undefined` relies on jQuery serializing
        // undefined to an empty string; omitting the key is unambiguous.
        if (callStatus) {
            data.call_status = callStatus;
        }

        return $.post(url, data);
    }
```

to:

```javascript
    function searchCalls(callStatus = null, keyword = '', limit = 200, offset = 0, formId = null) {
        const url = App.Utils.Url.siteUrl('meta_leads/search_calls');

        const data = {
            csrf_token: vars('csrf_token'),
            keyword,
            limit,
            offset,
        };

        // Omit call_status entirely for "All" (null) so the backend sees no
        // filter. Sending `call_status: undefined` relies on jQuery serializing
        // undefined to an empty string; omitting the key is unambiguous.
        if (callStatus) {
            data.call_status = callStatus;
        }

        if (formId) {
            data.form_id = formId;
        }

        return $.post(url, data);
    }
```

- [ ] **Step 2: Verify**

```
node --check assets/js/http/meta_leads_http_client.js
```

Expected: no output (exit code 0).

- [ ] **Step 3: Cache the new filter element, pass its value into `load()`**

In `assets/js/pages/meta_leads.js`, change:

```javascript
    const $callFilter = $('#meta-leads-call-filter');
    const $keyword = $('#meta-leads-keyword');
    const $filter = $('#meta-leads-filter');
    const $tableBody = $('#meta-leads-table-body');
    const $cards = $('#meta-leads-cards');
    const $empty = $('#meta-leads-empty');
```

to:

```javascript
    const $callFilter = $('#meta-leads-call-filter');
    const $keyword = $('#meta-leads-keyword');
    const $filter = $('#meta-leads-filter');
    const $tableBody = $('#meta-leads-table-body');
    const $cards = $('#meta-leads-cards');
    const $empty = $('#meta-leads-empty');
    const $procedureFilter = $('#meta-leads-procedure-filter');
```

Change `bindEvents()`:

```javascript
    function bindEvents() {
        $callFilter.on('click', 'button', onFilterClick);
        $filter.on('click', load);
        $keyword.on('keyup', (event) => {
            if (event.key === 'Enter') {
                load();
            }
        });

        $tableBody.on('change', '.meta-leads-call-status', onStatusChange);
        $cards.on('change', '.meta-leads-call-status', onStatusChange);

        $tableBody.on('click', '[data-action="open-note"]', onOpenNote);
        $cards.on('click', '[data-action="open-note"]', onOpenNote);

        $tableBody.on('click', '[data-action="delete"]', onDeleteClick);
        $cards.on('click', '[data-action="delete"]', onDeleteClick);
    }
```

to:

```javascript
    function bindEvents() {
        $callFilter.on('click', 'button', onFilterClick);
        $filter.on('click', load);
        $keyword.on('keyup', (event) => {
            if (event.key === 'Enter') {
                load();
            }
        });

        $procedureFilter.on('change', load);

        $tableBody.on('change', '.meta-leads-call-status', onStatusChange);
        $cards.on('change', '.meta-leads-call-status', onStatusChange);

        $tableBody.on('click', '[data-action="open-note"]', onOpenNote);
        $cards.on('click', '[data-action="open-note"]', onOpenNote);

        $tableBody.on('click', '[data-action="delete"]', onDeleteClick);
        $cards.on('click', '[data-action="delete"]', onDeleteClick);
    }
```

Change `load()`:

```javascript
    function load() {
        const keyword = $keyword.val().trim();
        const callStatus = currentCallStatus === ALL_CALL_STATUS ? null : currentCallStatus;

        App.Http.MetaLeads.searchCalls(callStatus, keyword, 200, 0)
            .done((leads) => render(leads || []))
            .fail(() => render([]));
    }
```

to:

```javascript
    function load() {
        const keyword = $keyword.val().trim();
        const callStatus = currentCallStatus === ALL_CALL_STATUS ? null : currentCallStatus;
        const formId = $procedureFilter.val() || null;

        App.Http.MetaLeads.searchCalls(callStatus, keyword, 200, 0, formId)
            .done((leads) => render(leads || []))
            .fail(() => render([]));
    }
```

- [ ] **Step 4: Render the "Procedura" column and card row**

Change `renderTableRow()` and `renderCard()`:

```javascript
    function renderTableRow(lead) {
        return `
            <tr>
                <td>${nameAndPhoneHtml(lead)}</td>
                <td>${formAnswersHtml(lead)}</td>
                <td>${relativeTimeHtml(lead.received_at)}</td>
                <td>${statusSelectHtml(lead)}</td>
                <td>${assignedToHtml(lead)} ${appointmentBadgeHtml(lead)}</td>
                <td>${noteDisplayHtml(lead)}</td>
                <td class="text-center">
                    <button type="button" class="btn btn-outline-danger btn-sm" data-action="delete" data-id="${lead.id}" title="${lang('meta_leads_delete')}">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>`;
    }

    function renderCard(lead) {
        return `
            <div class="card mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <div class="fw-bold">${nameHtml(lead)}</div>
                            <div class="text-muted small">${relativeTimeHtml(lead.received_at)}</div>
                        </div>
                        <button type="button" class="btn btn-outline-danger btn-sm" data-action="delete" data-id="${lead.id}">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>

                    ${phoneButtonHtml(lead)}

                    <div class="mb-2">${formAnswersHtml(lead)}</div>

                    <div class="mb-2">${statusSelectHtml(lead)}</div>

                    <div class="mb-2 small">
                        ${assignedToHtml(lead)} ${appointmentBadgeHtml(lead)}
                    </div>

                    ${noteDisplayHtml(lead)}
                </div>
            </div>`;
    }
```

to:

```javascript
    function renderTableRow(lead) {
        return `
            <tr>
                <td>${nameAndPhoneHtml(lead)}</td>
                <td>${procedureHtml(lead)}</td>
                <td>${formAnswersHtml(lead)}</td>
                <td>${relativeTimeHtml(lead.received_at)}</td>
                <td>${statusSelectHtml(lead)}</td>
                <td>${assignedToHtml(lead)} ${appointmentBadgeHtml(lead)}</td>
                <td>${noteDisplayHtml(lead)}</td>
                <td class="text-center">
                    <button type="button" class="btn btn-outline-danger btn-sm" data-action="delete" data-id="${lead.id}" title="${lang('meta_leads_delete')}">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>`;
    }

    function renderCard(lead) {
        return `
            <div class="card mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <div class="fw-bold">${nameHtml(lead)}</div>
                            <div class="text-muted small">${relativeTimeHtml(lead.received_at)}</div>
                            <div class="text-muted small">${lang('meta_leads_procedure')}: ${procedureHtml(lead)}</div>
                        </div>
                        <button type="button" class="btn btn-outline-danger btn-sm" data-action="delete" data-id="${lead.id}">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>

                    ${phoneButtonHtml(lead)}

                    <div class="mb-2">${formAnswersHtml(lead)}</div>

                    <div class="mb-2">${statusSelectHtml(lead)}</div>

                    <div class="mb-2 small">
                        ${assignedToHtml(lead)} ${appointmentBadgeHtml(lead)}
                    </div>

                    ${noteDisplayHtml(lead)}
                </div>
            </div>`;
    }

    function procedureHtml(lead) {
        return escapeHtml(lead.procedure || lead.form_id || '—');
    }
```

- [ ] **Step 5: Verify**

```
node --check assets/js/pages/meta_leads.js
```

Expected: no output (exit code 0).

Then, in the browser (admin session, `meta_leads` page):
1. Confirm the desktop table's "Procedura" column shows "Micropigmentare" / "Masaj" / "Criolipoliza + HIFU" for leads with the 3 known `form_id`s, and shows the raw `form_id` for any lead whose `form_id` isn't one of those three.
2. Confirm the mobile card (resize below the `md` breakpoint) shows a "Procedura: ..." line with the same value.
3. Pick a procedure in the "Toate procedurile" dropdown — the list narrows to only leads with that `form_id` (or shows the "no records" state if none match); reset to "Toate procedurile" and confirm the full list returns.
4. Confirm the keyword search and call-status filters still work unchanged (regression check — `load()` now also reads `$procedureFilter.val()` but must not break the existing filters when the procedure filter is left at "Toate procedurile").

- [ ] **Step 6: Commit**

```bash
git add assets/js/http/meta_leads_http_client.js assets/js/pages/meta_leads.js
git commit -m "feat(meta-leads): render procedure column/row and wire the procedure filter"
```

---

## Self-Review Notes

- **Spec coverage:** mapping defined in one place (Task 1) ✓; fallback to `form_id` (Task 2) ✓; desktop column (Task 5, Task 6) ✓; mobile card row (Task 6) ✓; filter next to existing filters (Task 5, Task 6) ✓; no migration, no `Webhooks_make.php` change, no new table (confirmed absent from every task above) ✓.
- **Placeholder scan:** every step has literal code; re-read confirms no "TBD"/"similar to Task N" placeholders.
- **Type consistency:** `search_calls(?string $call_status, string $keyword, int $limit, int $offset, ?string $form_id)` matches between Task 2 (model) and Task 3 (controller call site); JS `searchCalls(callStatus, keyword, limit, offset, formId)` (Task 6 Step 1) matches the call in Task 6 Step 3's `load()`.
- **Review Focus:** empty/missing `form_id` fallback covered in Task 2 Step 2; empty-filter-result UI state covered in Task 6 Step 5 item 3 (reuses the already-existing `render()` empty-state branch, no new code needed there); an unmapped `form_id` passed directly as a filter value (bypassing the dropdown) is handled by the same `ml.form_id = ?` equality check used for the 3 known values — no special-casing needed, confirmed by re-reading Task 2's `search_calls()` body.
