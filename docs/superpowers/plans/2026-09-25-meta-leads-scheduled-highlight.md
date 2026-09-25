# Meta Leads Scheduled Highlight Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** On the Meta Leads admin page, visually flag leads that already became an appointment (`status = 'converted'`) with a subtle green row/card background and a "Programat <date>" badge, and add a new "Programați" tab that filters to exactly those leads.

**Architecture:** `status` and `converted_at` already exist on `ea_meta_leads` (migration 093) and are already populated by `Meta_leads_model::mark_converted()` — nothing to add there. `search_calls()` gains an optional `status` filter (independent of the existing `call_status` filter) so the new tab can select `status = 'converted'` regardless of `call_status`. The highlight itself is presentation-only: every rendered row/card already carries `status`/`converted_at` (selected via `ml.*`), so the JS just conditionally adds Bootstrap's built-in `table-success` (desktop `<tr>`) / `bg-success-subtle` (mobile `.card`) classes and a badge — no new column, no backend change needed for the highlight.

**Tech Stack:** PHP 8 / CodeIgniter 3, Bootstrap 5.3.8 (confirmed via `package.json`; dark mode via `<html data-bs-theme="dark">` in `application/views/layouts/backend_layout.php:2`), jQuery, no build step for `assets/js`, no SCSS edit needed (`table-success`/`bg-success-subtle` are already compiled into `assets/css/themes/default.css` and are dark-mode aware by design).

**Spec:** user's pasted requirements in this conversation (Romanian) — no separate spec file.

**Testing note (deviation from the default TDD step shape):** this codebase has no PHPUnit harness for DB-touching models/controllers/views, and no JS test framework for the plain jQuery `assets/js` pages (only `tests/Unit/Helper/*.php` — pure helpers — are unit-tested). This is the same documented, pre-existing condition as the prior Meta Leads plans in this repo (`docs/superpowers/plans/2026-09-25-meta-leads-procedure-column.md`). Verification here follows the same substitute: `php -l` / `node --check` per file, plus one live Playwright pass against the local docker-compose stack at the end.

## Global Constraints

- No schema change, no new migration — `ea_meta_leads.status` (`'new'|'converted'`) and `ea_meta_leads.converted_at` already exist (migration `093_create_meta_leads_table.php`) and are already written by `Meta_leads_model::mark_converted()`.
- The 5 existing call-status tab buttons (`De sunat` / `Nu a răspuns` / `Revine` / `Nu e interesat` / `Toate`) keep their exact markup, attributes (`data-call-status`), and click/render behavior unchanged — the new tab is additive, not a replacement or a rename.
- The green highlight (row/card background + badge) must render for every `status = 'converted'` lead regardless of which tab is currently selected — it is not conditional on the new "Programați" tab being active.
- Use only Bootstrap classes already compiled into this app's stylesheet: `table-success` on the desktop `<tr>`, `bg-success-subtle` on the mobile `.card`, `badge bg-success` for the badge (same class the existing `has_appointment` badge already uses at `assets/js/pages/meta_leads.js:312`). No SCSS file is touched, no `gulp styles` rebuild is required.
- Badge date format: `dd.mm.yyyy` (date only, no time) — distinct from the existing `formatDatetime()` helper, which includes `HH:mm`.

## Review Focus

- A lead with `status = 'converted'` that still carries its original `call_status` (e.g. never updated to something else after conversion) must still show the green highlight and badge when it appears under any of the 5 existing call-status tabs, not only under "Toate" or "Programați" — the highlight is independent of the active tab.
- `converted_at` is unexpectedly `NULL` on a `status = 'converted'` row (defensive case — `mark_converted()` always sets both together, but the renderer must not crash or show "Invalid date"): the badge's date formatter must fall back to `—`.
- The "Programați" tab combined with the keyword search box and/or the procedure filter dropdown must still narrow correctly — these are independent `WHERE` clauses in `search_calls()`, not mutually exclusive with the new `status` filter.
- Switching from "Programați" to any existing call-status tab (or back to "Toate") must fully clear the `status` filter, not leave both a `call_status` and a `status = 'converted'` filter applied at once.
- Zero leads currently have `status = 'converted'` in a given data set — selecting "Programați" must render the existing empty state (`render([])` / `#meta-leads-empty`), not error.

---

## File Structure

- `application/models/Meta_leads_model.php` — modified. `search_calls()` gains an optional `$status` filter param.
- `application/controllers/Meta_leads.php` — modified. `search_calls()` reads/validates a `status` request field.
- `application/language/romanian/translations_lang.php`, `application/language/english/translations_lang.php` — modified. Two new lang keys (`meta_leads_scheduled_tab`, `meta_leads_scheduled_badge`).
- `application/views/pages/meta_leads.php` — modified. One new button in the existing `#meta-leads-call-filter` button group.
- `assets/js/http/meta_leads_http_client.js` — modified. `searchCalls()` gains a `status` param.
- `assets/js/pages/meta_leads.js` — modified. New `currentStatusFilter` state, generalized `onFilterClick`/`renderFilterState`, `load()` passes the status filter, new `scheduledBadgeHtml()`/`formatDate()` helpers, row/card templates get the conditional highlight class + badge.

---

### Task 1: `Meta_leads_model::search_calls()` — add the `status` filter

**Files:**
- Modify: `application/models/Meta_leads_model.php:145-213` (the `search_calls()` method)

**Interfaces:**
- Produces: `search_calls(?string $call_status = null, string $keyword = '', int $limit = 200, int $offset = 0, ?string $form_id = null, ?string $status = null): array` — consumed by Task 2 (controller) and Task 6 (JS).

- [ ] **Step 1: Edit the method**

Replace the existing `search_calls()` method with:

```php
    /**
     * Search meta leads for the internal call workflow, optionally filtered by
     * call_status, form_id and/or status. Each row is enriched with a
     * has_appointments count (whether the linked customer has any
     * appointment), the assigned user's name, and procedure (the
     * clinic-facing name for the lead's form_id, from
     * META_LEAD_FORM_PROCEDURES, falling back to the raw form_id when it
     * isn't in that mapping).
     *
     * The status filter is independent of call_status: it selects on the
     * lead's lifecycle column ('new'/'converted'), not the internal call
     * workflow column, so it can be combined with keyword/form_id or used on
     * its own for the "Programați" (scheduled) tab.
     *
     * Leads are ordered strictly newest-first (received_at DESC), with no
     * call_status prioritization.
     *
     * @param string|null $call_status One of the allowed call statuses, null for all.
     * @param string $keyword
     * @param int $limit
     * @param int $offset
     * @param string|null $form_id Only leads from this form_id, null for all.
     * @param string|null $status Only 'new' or 'converted', null for all.
     *
     * @return array
     */
    public function search_calls(
        ?string $call_status = null,
        string $keyword = '',
        int $limit = 200,
        int $offset = 0,
        ?string $form_id = null,
        ?string $status = null,
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

        if ($status !== null && in_array($status, ['new', 'converted'], true)) {
            $this->db->where('ml.status', $status);
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

```bash
export MSYS_NO_PATHCONV=1
WINPATH=$(pwd -W)
docker run --rm -v "${WINPATH}:/app" -w //app php:8.2-cli php -l application/models/Meta_leads_model.php
```

Expected: `No syntax errors detected in application/models/Meta_leads_model.php`.

- [ ] **Step 3: Commit**

```bash
git add application/models/Meta_leads_model.php
git commit -m "feat(meta-leads): add status filter to search_calls"
```

---

### Task 2: `Meta_leads` controller — read the `status` filter

**Files:**
- Modify: `application/controllers/Meta_leads.php:121-151` (the `search_calls()` method)

**Interfaces:**
- Consumes: `Meta_leads_model::search_calls()`'s new `$status` param (Task 1).
- Produces: `POST meta_leads/search_calls` now accepts an optional `status` field (only `'converted'` has a UI path, but `'new'` is also honored since the model already validates against both). Consumed by Task 6 (JS).

- [ ] **Step 1: Edit the method**

Change:

```php
            $form_id = trim((string) request('form_id', ''));
            $form_id = $form_id !== '' ? $form_id : null;

            json_response(
                array_values($this->meta_leads_model->search_calls($call_status, $keyword, $limit, $offset, $form_id)),
            );
```

to:

```php
            $form_id = trim((string) request('form_id', ''));
            $form_id = $form_id !== '' ? $form_id : null;

            $status = request('status');

            if (!in_array($status, ['new', 'converted'], true)) {
                $status = null;
            }

            json_response(
                array_values(
                    $this->meta_leads_model->search_calls($call_status, $keyword, $limit, $offset, $form_id, $status),
                ),
            );
```

- [ ] **Step 2: Verify**

```bash
export MSYS_NO_PATHCONV=1
WINPATH=$(pwd -W)
docker run --rm -v "${WINPATH}:/app" -w //app php:8.2-cli php -l application/controllers/Meta_leads.php
```

Expected: `No syntax errors detected in application/controllers/Meta_leads.php`.

- [ ] **Step 3: Commit**

```bash
git add application/controllers/Meta_leads.php
git commit -m "feat(meta-leads): read status filter in search_calls controller action"
```

---

### Task 3: Language keys

**Files:**
- Modify: `application/language/romanian/translations_lang.php:462`
- Modify: `application/language/english/translations_lang.php:462`

**Interfaces:**
- Produces: `lang('meta_leads_scheduled_tab')`, `lang('meta_leads_scheduled_badge')` — consumed by Task 4 (view) and Task 6 (JS).

- [ ] **Step 1: Add the Romanian keys**

In `application/language/romanian/translations_lang.php`, right after line 462 (`$lang['meta_leads_all_procedures'] = 'Toate procedurile';`), add:

```php
$lang['meta_leads_scheduled_tab'] = 'Programați';
$lang['meta_leads_scheduled_badge'] = 'Programat';
```

- [ ] **Step 2: Add the English keys**

In `application/language/english/translations_lang.php`, right after the equivalent `$lang['meta_leads_all_procedures'] = 'All procedures';` line, add:

```php
$lang['meta_leads_scheduled_tab'] = 'Scheduled';
$lang['meta_leads_scheduled_badge'] = 'Scheduled';
```

- [ ] **Step 3: Verify**

```bash
export MSYS_NO_PATHCONV=1
WINPATH=$(pwd -W)
docker run --rm -v "${WINPATH}:/app" -w //app php:8.2-cli php -l application/language/romanian/translations_lang.php
docker run --rm -v "${WINPATH}:/app" -w //app php:8.2-cli php -l application/language/english/translations_lang.php
```

Expected: `No syntax errors detected ...` for both.

- [ ] **Step 4: Commit**

```bash
git add application/language/romanian/translations_lang.php application/language/english/translations_lang.php
git commit -m "feat(meta-leads): add scheduled tab/badge translation keys"
```

---

### Task 4: View — add the "Programați" tab button

**Files:**
- Modify: `application/views/pages/meta_leads.php:18-37` (the `#meta-leads-call-filter` button group)

**Interfaces:**
- Consumes: lang keys from Task 3.
- Produces: a 6th button in `#meta-leads-call-filter`, marked with `data-status="converted"` (distinct from the existing 5 buttons' `data-call-status`) — consumed by Task 6 (JS).

- [ ] **Step 1: Add the button**

Change:

```php
            <div class="btn-group flex-wrap" role="group" id="meta-leads-call-filter">
                <button type="button" class="btn btn-outline-primary" data-call-status="de sunat">
                    <?= lang('call_status_de_sunat') ?>
                </button>
                <button type="button" class="btn btn-outline-primary" data-call-status="nu a raspuns">
                    <?= lang('call_status_nu_a_raspuns') ?>
                </button>
                <button type="button" class="btn btn-outline-primary" data-call-status="revine">
                    <?= lang('call_status_revine') ?>
                </button>
                <button type="button" class="btn btn-outline-primary" data-call-status="nu e interesat">
                    <?= lang('call_status_nu_e_interesat') ?>
                </button>
                <button type="button" class="btn btn-outline-primary" data-call-status="toate">
                    <?= lang('meta_leads_all') ?>
                </button>
            </div>
```

to:

```php
            <div class="btn-group flex-wrap" role="group" id="meta-leads-call-filter">
                <button type="button" class="btn btn-outline-primary" data-call-status="de sunat">
                    <?= lang('call_status_de_sunat') ?>
                </button>
                <button type="button" class="btn btn-outline-primary" data-call-status="nu a raspuns">
                    <?= lang('call_status_nu_a_raspuns') ?>
                </button>
                <button type="button" class="btn btn-outline-primary" data-call-status="revine">
                    <?= lang('call_status_revine') ?>
                </button>
                <button type="button" class="btn btn-outline-primary" data-call-status="nu e interesat">
                    <?= lang('call_status_nu_e_interesat') ?>
                </button>
                <button type="button" class="btn btn-outline-primary" data-call-status="toate">
                    <?= lang('meta_leads_all') ?>
                </button>
                <button type="button" class="btn btn-outline-primary" data-status="converted">
                    <?= lang('meta_leads_scheduled_tab') ?>
                </button>
            </div>
```

- [ ] **Step 2: Verify**

```bash
export MSYS_NO_PATHCONV=1
WINPATH=$(pwd -W)
docker run --rm -v "${WINPATH}:/app" -w //app php:8.2-cli php -l application/views/pages/meta_leads.php
```

Expected: `No syntax errors detected in application/views/pages/meta_leads.php`.

- [ ] **Step 3: Commit**

```bash
git add application/views/pages/meta_leads.php
git commit -m "feat(meta-leads): add Programați tab button to the view"
```

---

### Task 5: HTTP client — `searchCalls()` gains a `status` param

**Files:**
- Modify: `assets/js/http/meta_leads_http_client.js:88-110` (the `searchCalls()` function)

**Interfaces:**
- Produces: `App.Http.MetaLeads.searchCalls(callStatus, keyword, limit, offset, formId, status)` (new 6th param) — consumed by Task 6.

- [ ] **Step 1: Edit the function**

Change:

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

to:

```javascript
    function searchCalls(callStatus = null, keyword = '', limit = 200, offset = 0, formId = null, status = null) {
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

        if (status) {
            data.status = status;
        }

        return $.post(url, data);
    }
```

- [ ] **Step 2: Verify**

```bash
node --check assets/js/http/meta_leads_http_client.js
```

Expected: no output (exit code 0).

- [ ] **Step 3: Commit**

```bash
git add assets/js/http/meta_leads_http_client.js
git commit -m "feat(meta-leads): add status param to searchCalls HTTP client"
```

---

### Task 6: Page JS — new tab state, row/card highlight, badge, live verification

**Files:**
- Modify: `assets/js/pages/meta_leads.js`

**Interfaces:**
- Consumes: `App.Http.MetaLeads.searchCalls(..., status)` (Task 5); the new button `[data-status="converted"]` (Task 4); lang keys (Task 3). Each lead row already carries `status` and `converted_at` (unchanged columns, already selected via `ml.*`).

- [ ] **Step 1: Add the `currentStatusFilter` state**

Change:

```javascript
    let currentCallStatus = ALL_CALL_STATUS;
    let noteModal = null;
    let leadCache = {};
```

to:

```javascript
    let currentCallStatus = ALL_CALL_STATUS;
    let currentStatusFilter = null;
    let noteModal = null;
    let leadCache = {};
```

- [ ] **Step 2: Generalize `onFilterClick` and `renderFilterState` to handle the new button**

Change:

```javascript
    function onFilterClick(event) {
        currentCallStatus = $(event.currentTarget).data('call-status') || ALL_CALL_STATUS;
        renderFilterState();
        load();
    }

    function renderFilterState() {
        $callFilter.find('button').each(function () {
            const active = ($(this).data('call-status') || ALL_CALL_STATUS) === currentCallStatus;
            $(this).toggleClass('btn-primary', active).toggleClass('btn-outline-primary', !active);
        });
    }
```

to:

```javascript
    function onFilterClick(event) {
        const $btn = $(event.currentTarget);
        const statusFilter = $btn.data('status');

        if (statusFilter) {
            currentStatusFilter = statusFilter;
            currentCallStatus = ALL_CALL_STATUS;
        } else {
            currentStatusFilter = null;
            currentCallStatus = $btn.data('call-status') || ALL_CALL_STATUS;
        }

        renderFilterState();
        load();
    }

    function renderFilterState() {
        $callFilter.find('button').each(function () {
            const $btn = $(this);
            const statusFilter = $btn.data('status');

            const active = statusFilter
                ? currentStatusFilter === statusFilter
                : currentStatusFilter === null && ($btn.data('call-status') || ALL_CALL_STATUS) === currentCallStatus;

            $btn.toggleClass('btn-primary', active).toggleClass('btn-outline-primary', !active);
        });
    }
```

- [ ] **Step 3: Pass the status filter into `load()`**

Change:

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

to:

```javascript
    function load() {
        const keyword = $keyword.val().trim();
        const callStatus =
            currentStatusFilter === null && currentCallStatus !== ALL_CALL_STATUS ? currentCallStatus : null;
        const formId = $procedureFilter.val() || null;

        App.Http.MetaLeads.searchCalls(callStatus, keyword, 200, 0, formId, currentStatusFilter)
            .done((leads) => render(leads || []))
            .fail(() => render([]));
    }
```

- [ ] **Step 4: Render the highlight class and badge**

Change `renderTableRow()` and `renderCard()`:

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
```

to:

```javascript
    function renderTableRow(lead) {
        const rowClass = lead.status === 'converted' ? 'table-success' : '';

        return `
            <tr class="${rowClass}">
                <td>${nameAndPhoneHtml(lead)}${scheduledBadgeHtml(lead)}</td>
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
        const cardClass = lead.status === 'converted' ? ' bg-success-subtle' : '';

        return `
            <div class="card mb-3${cardClass}">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <div class="fw-bold">${nameHtml(lead)}${scheduledBadgeHtml(lead)}</div>
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

    function scheduledBadgeHtml(lead) {
        if (lead.status !== 'converted') {
            return '';
        }

        return ` <span class="badge bg-success ms-1">${lang('meta_leads_scheduled_badge')} ${escapeHtml(formatDate(lead.converted_at))}</span>`;
    }
```

- [ ] **Step 5: Add the `formatDate()` helper (date-only, dd.mm.yyyy)**

Add this function near `formatDatetime()`:

```javascript
    function formatDate(value) {
        if (!value) {
            return '—';
        }

        const parsed = moment(value);

        if (!parsed.isValid()) {
            return '—';
        }

        return parsed.format('DD.MM.YYYY');
    }
```

- [ ] **Step 6: Verify with `node --check`**

```bash
node --check assets/js/pages/meta_leads.js
```

Expected: no output (exit code 0).

- [ ] **Step 7: Live verification (Playwright against the local docker-compose stack)**

Bring the stack up (same as the prior Meta Leads plan's verification):

```bash
export MSYS_NO_PATHCONV=1
docker compose up -d mysql php-fpm nginx
```

Wait for MySQL, then seed test data covering every Review Focus item: one lead with `status = 'converted'` + a `converted_at` timestamp and `call_status = 'de sunat'` (to prove the highlight shows under a non-"Toate"/non-"Programați" tab too), one plain `status = 'new'` lead for contrast, and re-use (or create) a throwaway admin login.

In the browser:
1. Load `meta_leads` with the "Toate" tab active — confirm the converted lead's row has a visibly different (green-tinted) background and shows a "Programat DD.MM.YYYY" badge next to its name; the `new` lead has neither.
2. Switch to the `de sunat` call-status tab (the converted lead's own `call_status`) — confirm the converted lead still appears there with the same highlight/badge (Review Focus item 1).
3. Click the new "Programați" tab — confirm the list narrows to exactly the converted lead(s); type a keyword that doesn't match it — confirm the list narrows further to zero and the existing empty state renders (Review Focus items 3 and 5).
4. Click back to "Toate" — confirm the full list returns (Review Focus item 4: the status filter is fully cleared, not left combined with a stale call_status).
5. Resize to mobile width (390px) — confirm the card shows the `bg-success-subtle` background and the same badge.
6. Check the browser console — 0 errors, 0 warnings.

Clean up the seeded test data and the throwaway admin afterward; stop the containers if they weren't already running before this session (check `docker compose ps` before `up`, exactly as the prior plan's verification did).

- [ ] **Step 8: Commit**

```bash
git add assets/js/pages/meta_leads.js
git commit -m "feat(meta-leads): highlight converted leads and wire the Programați tab"
```

---

## Self-Review Notes

- **Spec coverage:** green background on desktop rows (Task 6, `table-success`) ✓; green background on mobile cards (Task 6, `bg-success-subtle`) ✓; "Programat" badge with `converted_at` as dd.mm.yyyy (Task 6, `scheduledBadgeHtml()`/`formatDate()`) ✓; new "Programați" tab next to the existing ones, filtering `status = 'converted'` (Tasks 1, 2, 4, 6) ✓; existing tabs unchanged (Task 4 only appends a 6th button; Task 6's `onFilterClick`/`renderFilterState` generalization is verified against the existing 5 buttons' exact prior behavior in Step 7 item 4) ✓; no schema change, no migration (confirmed: `status`/`converted_at` already exist per migration 093, no migration file in this plan) ✓.
- **Placeholder scan:** every step has literal code; re-read confirms no "TBD"/"similar to Task N" placeholders.
- **Type consistency:** `search_calls(?string $call_status, string $keyword, int $limit, int $offset, ?string $form_id, ?string $status)` matches between Task 1 (model) and Task 2 (controller call site); JS `searchCalls(callStatus, keyword, limit, offset, formId, status)` (Task 5) matches the call in Task 6 Step 3's `load()`; the view's `data-status="converted"` (Task 4) matches the JS's `$btn.data('status')` reads (Task 6 Step 2).
- **Review Focus:** item 1 (highlight independent of active tab) has a dedicated live-check step (Task 6 Step 7 item 2); item 2 (`converted_at` NULL) is handled by `formatDate()`'s `!value`/`isValid()` guards (Task 6 Step 5); item 3 (status filter + keyword/procedure combine) has a dedicated live-check step (Task 6 Step 7 item 3); item 4 (switching tabs clears the status filter) is structural in `onFilterClick` (Task 6 Step 2) and has a dedicated live-check step (Task 6 Step 7 item 4); item 5 (empty state) reuses the existing `render([])` branch untouched by this plan and is covered in the same live-check step.

## Post-implementation correction

This plan's Tech Stack and Global Constraints sections (lines 9 and 20) claimed `table-success` is "dark-mode aware by design" and that no SCSS edit/gulp rebuild would be needed. **That claim was wrong** and was caught by the final whole-branch review, not by the live verification in Task 6 Step 7 (which checked the class was present and the row was visibly green, but not that it was legible).

The actual situation, confirmed by reading the compiled `assets/css/themes/default.css`: `.table-success` bakes fixed light RGB values (`--bs-table-bg: rgb(214.4, 243.8, 221.8)`, `--bs-table-color: #000`, etc.) at Sass build time, and this Bootstrap 5.3.8 build has **no** `[data-bs-theme=dark] .table-success` override anywhere in the compiled output. Only the separate `-subtle`/`-emphasis` utility family (e.g. `.bg-success-subtle`, which the mobile card already used) is genuinely dark-aware — it was a mistake to assume the older contextual `.table-*` classes shared that property.

**Fix applied post-review:** added a scoped override in `assets/css/backend.scss` (`#meta-leads-page .table-success { --bs-table-bg: var(--bs-success-bg-subtle); ... }`), matching this same file's pre-existing "Reports page table contrast fix" pattern, then ran `npx gulp styles` to recompile `assets/css/backend.css` (gitignored, generated at deploy time by the Dockerfile's `assets-builder` stage — only the `.scss` source is committed). Verified live: desktop row and mobile card now show the same subtle dark green, hover state doesn't regress to the light color, and all inner text (muted labels, phone link, buttons) stays legible.

**Lesson for future plans on this codebase:** before asserting a Bootstrap utility class is "dark-mode aware," grep the actually-compiled CSS in `assets/css/themes/*.css` for a `[data-bs-theme=dark]` override of that exact selector — don't infer it from the Bootstrap version number or from a different, similarly-named class (e.g. `-subtle` vs. the plain contextual class) being confirmed dark-aware.
