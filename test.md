# MedLink – Handler Module Test Scenarios

25 modules = 25 folders under `medlink/handlers/`. Top-level handlers (`activity-log`, `deletion-requests`, `edit-requests`, `global-search`, `documents`) are folded into the module they serve (noted per module).

## 0. Setup (do once)

**Test users** (one per role): `admin`, `receptionist`, `nurse`, `doctor`, `lab_tech`, `pharmacist`, `cashier`, `finance_mgr`, `hr_mgr`, `store_keeper`, plus one user with **no** permissions.
**Seed data**: 1 department, 1 ward + room + 2 beds, 1 insurance company + package, 1 drug with stock, 1 lab test, 1 service in catalog, 1 supplier, 1 employee.
**Tools**: browser DevTools (Network tab), phpMyAdmin, `medlink/logs` / Apache `error.log` open in a tail.

**Every scenario also checks (the "universal 6")**
1. HTTP status is right (200/201 ok, 400 validation, 401 not logged in, 403 no permission, 404 missing) and body is JSON `{success,message,data}`.
2. No PHP warning/notice/stack trace in response or `error.log`.
3. Wrong role → 403, logged-out → 401 (try the URL directly).
4. Required field blank / wrong type / negative number / 10k-char string → clean 400, no 500.
5. DB row matches what the UI shows (check the table, not just the screen).
6. Action appears in activity log (`activity-log.php`) with correct user, entity, timestamp.

Also per module: **double-click submit** (no duplicate row), **replay the same request** (idempotent or rejected), **SQL-injection string `' OR 1=1 --` in every text/search param** (no error, no leak).

Format: **Flow** (happy path, in order) → **Negative** → **Verify**.

---

## 1. auth  (`auth/login, logout, register, checkAuth, getUserProfile, check-block-status`)
**Flow**: register new user → (admin activates if required) → login → `checkAuth` returns user → `getUserProfile` returns own data → logout → `checkAuth` now 401.
**Negative**: wrong password ×N → account blocked, `check-block-status` says blocked, correct password still refused; duplicate email/username on register; weak password; login while blocked shows right message; session cookie unusable after logout.
**Verify**: password stored hashed; failed-attempt counter resets after success; no password/hash in any response.

## 2. appointments  (`appointments/appointments.php`: day, doctors, reminders…)
**Flow**: receptionist books follow-up for patient with doctor + date/time → appears in `day` view → doctor sees it → patient arrives (mark arrived) → new visit created and linked to source visit → status completed.
**Negative**: past date; doctor double-booked same slot; unknown patient; cancel then try to mark arrived; reminders endpoint returns only upcoming, not cancelled.
**Verify**: `appointments.visit_id` set on arrival, `source_visit_no` correct, reminders not duplicated.

## 3. approvals  (`approvals/approvals.php`: list, count, detail, decide)
**Flow**: create a thing needing approval (e.g. expense, leave, disposal) as user A → approver's inbox `count` increments → `list` shows it → `detail` shows requester + payload → approve → source record moves to approved, `count` decrements, requester notified.
**Negative**: requester approves own item (must be blocked if rule says so); approve twice; non-approver calls `decide` → 403; reject without reason.
**Verify**: decision stored with approver id + time; source module status matches approval status.

## 4. assets  (`asset-management`, `asset-ledger`)
**Flow**: create category → add asset (via GRN line or manual) → capitalize → assign to employee → depreciate (period run) → revalue → schedule maintenance → report fault → create work order → start → record → verify → close fault → transfer (request → decide → complete) → dispose (request → decide).
**Negative**: depreciate same period twice; dispose already-disposed asset; assign asset already assigned; transfer to same department; dispose with open work order; negative revaluation below zero.
**Verify**: ledger journal entries balance (debit = credit) after capitalize/depreciate/dispose; net book value = cost − accumulated depreciation; employee clearance (`clearance`) lists unreturned assets.

## 5. consultation  (`consult`, `consult_enhanced`, `diagnoses`, `queue`)
**Flow**: patient in queue → doctor `called` → `in_consultation` → add vitals → record diagnosis → prescription item → lab order → imaging order → lab/imaging result returns → complete → disposition one of `treated | follow_up | referred | admitted | discharged | done`.
**Negative**: complete with no diagnosis (per rule); order for patient not in consultation; edit/delete order item after it is paid/dispensed; reopen completed consult by non-doctor; two doctors call same patient.
**Verify**: queue status transitions follow order (no jump `called`→`done` skipping rules); order items create billing lines; diagnosis recurrence flag works for repeat dx; lab consumption confirm/reverse restores stock.

## 6. departments  (`departments/portal`, `department-forms`)
**Flow**: patient assigned to department → `pending_visits` shows it → `call_next` → `start_consultation` → fill dynamic form (`form_response`/`submit_form_response`) → `complete_consultation` → `history` and `reports` show it.
**Negative**: submit form missing required `fields`; call_next on empty queue; wrong-department user sees other dept's queue; edit submitted response.
**Verify**: dashboard counts equal queue rows; form answers saved per field; doctor-by-dept list excludes inactive doctors.

## 7. documents  (`documents/medical-documents`, top-level `documents.php`)
**Flow**: generate document (e.g. medical certificate/referral) → `next_ref` gives unique reference → document saved → `verify` with ref returns valid.
**Negative**: verify wrong/tampered ref; two simultaneous `next_ref` calls (must not collide); generate for patient without a visit.
**Verify**: ref numbers sequential, unique; printed content matches stored data.

## 8. expenses  (`expenses/expense-management.php`)
**Flow**: create category + supplier → `save` draft expense with upload → `submit` → approver `approve` → `pay` from cash account → appears in `list`/`view` as paid.
**Negative**: pay before approve; pay more than cash balance; edit after paid; reject without reason; cancel paid expense; upload disallowed file type (.php, .exe) and oversized file; deactivate a category in use.
**Verify**: cash account balance reduced exactly by amount; journal entry posted; rejected/cancelled never reach ledger.

## 9. finance  (`finance-ops`, `chart-of-accounts`, `invoices`, `invoices-enhanced`, `payment-records`, `payment-reports`, `receivables`, `consultation-fees`, `department-payment-config`, `finance-close`)
**Flow**: create invoice from visit orders → record payment (cash, then partial, then rest) → receivable ages correctly → post manual journal (`record-journal`) → bank reconciliation (`bank_rec_save` → `bank_rec_match` → `bank_rec_post_line` → `bank_rec_complete`) → trial/`balance_sheet` → period close.
**Negative**: overpay invoice; payment on cancelled invoice; unbalanced journal; post into closed period; delete matched bank-rec line; complete bank rec with unmatched difference; change consultation fee mid-visit.
**Verify**: `balance_sheet` Assets = Liabilities + Equity; invoice paid+balance = total; payment report totals = sum of `payment-records`; closed period rejects any write.

## 10. hr  (`employees`, `attendance`, `leave`, `payroll`, `contracts`, `departments`, `documents`, `licenses`, `performance`, `permissions`, `shifts`, `hr_enhanced`)
**Flow**: add employee (`new_employee`) → contract → shift → clock in/out (attendance) → request leave → approve → payroll run for month → payslip → performance review → license with expiry.
**Negative**: leave overlapping existing leave; leave beyond balance; approve own leave; payroll run twice same month; attendance clock-out before clock-in; duplicate national ID/employee no; expired license alert; terminated employee still on payroll.
**Verify**: leave balance decremented once; payroll gross − deductions = net; attendance hours vs shift; permissions change takes effect on next request.

## 11. insurance  (`companies`, `coverage-types`, `packages`, `settlement`)
**Flow**: create company → coverage type → package with price list/co-pay % → attach patient → bill visit (insurer share + patient share) → create `settlement` batch → mark settled.
**Negative**: package without company; co-pay >100%; bill uncovered service; settle same claim twice; delete company with open claims; expired policy.
**Verify**: insurer share + patient share = invoice total; settlement total = sum of claims; settled claims drop from receivable.

## 12. inventory  (`inventory-management`, `-v2`, `workspace`, `drug-reports`)
**Flow**: add item → receive stock (batch + expiry) → adjust → transfer between stores → dispense decrements → low-stock and expiry reports.
**Negative**: negative stock; expired batch dispensed (FEFO should pick earliest valid); transfer more than on hand; same item twice with different units; v1 vs v2 endpoints disagree on quantity.
**Verify**: on-hand = Σ receipts − Σ issues; `drug-reports` match raw stock table; movement history complete.

## 13. lab  (`lab/lab.php`)
**Flow**: doctor lab order → appears in lab queue → collect sample → enter result → validate/release → doctor sees result → consumables deducted.
**Negative**: enter result before sample collected; `lab_no_sample` path (rejected sample) reflected in doctor view; edit released result without audit; result value out of range flagged; unpaid order processed (if rule requires payment).
**Verify**: result linked to correct order item; abnormal flag correct vs reference range; consumption reduces lab store.

## 14. notifications  (`notifications/notifications.php`)
**Flow**: trigger events (new approval, lab result, low stock) → target user sees unread badge → open → marked read → mark-all-read.
**Negative**: user A reads user B's notification by id (must 403/404); notification for deleted entity; huge unread count paging.
**Verify**: counts per user correct; no notification to the actor themselves unless intended.

## 15. patients  (`patients`, `visits`, `billing`, `locations`)
**Flow**: register patient (province→district→sector→cell→village cascade) → create visit → bill → search patient → edit demographics → merge/duplicate check.
**Negative**: duplicate phone/national ID; future DOB; invalid location combo (cell not in sector); open second visit while one active; edit patient with locked visit; delete patient with visits (use deletion-request flow, `deletion-requests.php`) and edit locked data via `edit-requests.php`.
**Verify**: patient_no unique and sequential; location cascade returns only children of parent; deletion/edit requests need approver and are logged.

## 16. payments  (`payments/afrirem.php`)
**Flow**: patient with Afrirem cover → bill → Afrirem share computed → submit claim → see in `afriremlogs` → reconcile payment.
**Negative**: claim above limit; claim for expired cover; duplicate claim; payment callback replay.
**Verify**: limit remaining decreases once; claim and invoice totals reconcile.

## 17. pharmacy  (`pharmacy/pharmacy.php`)
**Flow**: prescription arrives → pharmacist verifies → dispense (batch auto-picked) → stock down → patient billed → partial dispense then remainder.
**Negative**: dispense without payment (if rule); dispense > prescribed; interacting/allergy drug (warning); expired batch; out-of-stock → backorder; re-dispense already dispensed item.
**Verify**: dispensed qty ≤ prescribed; stock movement row per dispense; returns restore exact batch.

## 18. procedures  (`procedures/procedures.php`)
**Flow**: doctor orders procedure → scheduled → performed (staff, time, notes) → billed → complete.
**Negative**: perform without order; perform twice; cancel after performed; missing consumables; order for inactive service.
**Verify**: bill line matches service catalog price; consumables deducted; status chain ordered→scheduled→done only.

## 19. procurement  (`procurement-ops`, `procurement-ext`)
**Flow**: purchase request → approval → PO to supplier → GRN (goods received, partial then full) → invoice match → payment.
**Negative**: GRN > PO qty; PO without approval; receive on cancelled PO; supplier invoice price ≠ PO price (3-way match flags); duplicate supplier invoice number.
**Verify**: stock increases by GRN qty only; PO status partial/complete correct; payable posted once.

## 20. reports  (`general`, `hmis`, `hmis-dx-map`, `performance`)
**Flow**: run each report for a known seeded date range → compare to manual count; export; change filters (department, date, gender, age band).
**Negative**: from > to date; empty range → empty result not error; huge range performance (<10s); user without report permission.
**Verify**: HMIS totals = sum of components; each diagnosis maps to exactly one HMIS category (`hmis-dx-map`, no unmapped); report numbers = raw table counts.

## 21. requests  (`requests/request-management.php`, top-level `edit-requests`, `deletion-requests`)
**Flow**: staff raise edit/delete request with reason → approver sees in `approval-inbox` → approve → change applied (or rejected → untouched) → requester notified.
**Negative**: request on locked/closed record; approve own request; approve twice; request with no reason; request for non-existent id.
**Verify**: approved delete is soft/hard per policy; audit log keeps old value.

## 22. requisitions  (`requisitions/requisitions.php`)
**Flow**: department creates `internal-requisition` (items from `consumable_items`) → `submit` → `approve` → store `issue` (partial/full) → `verification` by requester.
**Negative**: issue > approved qty; issue > store stock; reject without reason; edit after approved; requester approves own; verify before issue.
**Verify**: store stock down, department stock up; status chain submitted→approved→issued→verified; `my-requests` shows only own.

## 23. settings  (`hospital`, `menus`, `rbac`, `roles`, `users`, `user-permissions`, `user-sessions`, `service-catalog`, `vital-signs`)
**Flow**: create role → assign permissions → create user with role → login as that user → menu shows only allowed items → change permission → effect on next request → kill session in `user-sessions` → user forced out → add service to catalog with insurance price.
**Negative**: user opens URL for a menu hidden from them (still 403 server-side); delete role in use; remove last admin; duplicate service code; negative price; `check_permission` for unknown permission; vital-sign out-of-range thresholds.
**Verify**: permissions enforced in handler, not just menu; `get_access_log` records changes; catalog price reflects in new orders but not old invoices.

## 24. ward  (`ward/ward-management.php`)
**Flow**: create ward → room → beds → set pricing → `request_admission` from consultation → `pending_admissions` → `assign_bed` → `active_admissions` → `transfer` bed/room → `discharge` → bed becomes available.
**Negative**: assign occupied bed; assign bed under maintenance; delete ward/room with active patient; discharge twice; transfer to same bed; admit already-admitted patient.
**Verify**: `occupancy_stats` = occupied/total beds; daily bed charge accrues and stops at discharge; bed state machine consistent.

## 25. workflow  (`workflow/workflow.php`)
**Flow**: `transfer_create` (patient transfer) → `transfer_step` (each stage in order) → `transfer_decide` approve → `transfer_costs` → `transfer_history`; `closure_list` → `closure_close` (visit closure) → `cost_services` finalized.
**Negative**: skip a step; step out of order; `transfer_cancel` after completed; close visit with unpaid invoice or open orders; decide by non-authorized role; close already closed.
**Verify**: step log ordered with actor/time; closed visit is read-only everywhere (consultation, pharmacy, lab reject new orders); transfer costs added to invoice once.

---

## Cross-module end-to-end (run last)
1. Register patient → visit → consult → lab + drug + procedure → lab result → dispense → invoice → insurance split → payment → admit to ward → discharge → close visit → HMIS report includes it.
2. Procure drug (PO→GRN) → inventory up → requisition to pharmacy → dispense → stock consistent end to end.
3. Employee onboard → shift → attendance → leave → payroll → expense for payroll payment → finance `balance_sheet` balances.

## Bug log template
| # | Module | Scenario step | Expected | Actual | Status code | Severity | Fixed? |
|---|--------|--------------|----------|--------|-------------|----------|--------|

---

# Playwright run protocol (background, low-token, in-scope)

Node 24 and Playwright 1.65 are available. No `package.json` or config exists yet, so create tests under `tests/e2e/` only.

## Scope rules (hard)
- Test only the 25 modules above. One spec file per module: `tests/e2e/NN-module.spec.js`.
- **Never edit app code** (`medlink/**`) during a test run. A failing test gets logged in the bug table, not fixed on the spot.
- No new features, no refactors, no extra dependencies beyond `@playwright/test`.
- Use the seeded test DB/users from section 0. Never run against real patient data.
- API-first: call handlers with `request` (`/medlink/handlers/<mod>/<file>.php?action=...`) for the happy path and negatives. Use the browser only where the UI flow is the point (login, queue, ward bed assign).

## Token-saving rules
1. **Run in the background, never watch it.** Start with `run_in_background`, and don't poll. Read only the final summary.
2. **Minimal reporters.** `--reporter=dot,json` with the JSON going to `tests/e2e/out/results.json`. Never use the `list` or `html` reporter in the transcript.
3. **Read failures only.** Extract failed tests with a script (below), never `cat` the whole report. The test-runner agent (haiku) should do this.
4. **No screenshots, video or trace on pass.** Config: `screenshot:'only-on-failure'`, `trace:'retain-on-failure'`, `video:'off'`. Don't read images unless a failure can't be explained from text.
5. **One module at a time** by the spec file name (`npx playwright test 05-`). Don't rerun the whole suite after each fix. Rerun only the failed file (`--last-failed`).
6. **Cap output.** Truncate error messages to the first 15 lines. Don't print response bodies except for failing asserts.
7. **Stop after 2 failures of the same test.** Log it in the bug table and move on.
8. **Reuse login.** One `globalSetup` logs in each role and saves `storageState` to `tests/e2e/.auth/<role>.json`, so no login steps per test.

## Setup (once)
```bash
cd /c/xampp/htdocs/Direca/iramiro
npm init -y && npm i -D @playwright/test && npx playwright install chromium
```
`playwright.config.js` essentials: `baseURL: 'http://localhost/Direca/iramiro'`, `workers: 4`, `retries: 0`, `reporter: [['dot'],['json',{outputFile:'tests/e2e/out/results.json'}]]`, `use:{screenshot:'only-on-failure',trace:'retain-on-failure',video:'off',headless:true}`.
Add `node_modules/`, `tests/e2e/out/` and `tests/e2e/.auth/` to `.gitignore`.

## Run (background)
```bash
npx playwright test --reporter=dot,json  # whole suite; launch with run_in_background
npx playwright test 05-                  # one module
npx playwright test --last-failed        # rerun failures only
```

## Failure summary (what to read; ~1 line per failure)
```bash
node -e "const r=require('./tests/e2e/out/results.json');const f=[];(function w(s){(s.specs||[]).forEach(p=>p.tests.forEach(t=>{const x=t.results.at(-1);if(x.status!=='passed'&&x.status!=='skipped')f.push(p.file+' | '+p.title+' | '+(x.error?.message||'').split('\n')[0].slice(0,160))}));(s.suites||[]).forEach(w)})({suites:r.suites});console.log(r.stats.expected+' pass, '+r.stats.unexpected+' fail');f.forEach(l=>console.log(l))"
```

## Spec template per scenario
Each `test()` title = `<module> › <Flow|Negative|Verify> › <short step>`, so a failure line maps straight to a section of this file. Each `test()` asserts the universal 6 (status, JSON shape, no PHP warning text like `Warning:`/`Fatal error`/`Stack trace`, role denial, DB row via a read endpoint).

## Order and gating
Run in this order, and stop to log bugs between groups: auth (1) → settings (23) → patients (15) → the rest in file order → cross-module E2E last. If auth fails, nothing else is meaningful, so stop the run.

## Done criteria
- All 25 spec files exist and run headless without manual input.
- `results.json` has 0 unexpected failures, or each failure has a row in the bug log.
- The final message to the user is at most 10 lines: pass/fail counts plus the failure lines from the summary script.
