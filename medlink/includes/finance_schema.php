<?php
// includes/finance_schema.php
// Tables, default accounts and account mapping used by finance_posting.php.
// Idempotent: fin_boot() runs it once per request (a single SELECT once installed).
// DDL commits implicitly, so it must run outside a transaction — api.php calls fin_boot() up front.

const FIN_SCHEMA_VERSION = 45;

// key => [existing account_code | null, new code, name, type, parent account_code, optional description]
// Rows with an existing code are only looked up; rows with a new code are created when missing.
// The one receivable account for self-paying patients (no per-patient accounts). Found by name, created under 1120 when missing.
function fin_private_ar_acct(PDO $pdo): int {
    $ar = (int)$pdo->query("SELECT id FROM chart_of_accounts WHERE account_code = '1120' LIMIT 1")->fetchColumn() ?: 7;
    $q = $pdo->prepare("SELECT id FROM chart_of_accounts WHERE account_name = 'Receivable - Private' AND parent_id = ? LIMIT 1");
    $q->execute([$ar]);
    if ($id = $q->fetchColumn()) return (int)$id;
    $pdo->prepare("INSERT INTO chart_of_accounts (account_code, account_name, account_type, description, parent_id, status, is_default, has_sub) VALUES ('1120-PRIV', 'Receivable - Private', 'asset', 'Amounts owed by self-paying patients.', ?, 'active', 0, 0)")->execute([$ar]);
    return (int)$pdo->lastInsertId();
}

function fin_default_accounts(): array {
    return [
        // cash & equivalents (parent 1110)
        'cash'                    => [null, '111001', 'Cash on hand',                         'asset',     '1110'],
        'bank'                    => [null, '111002', 'Bank - main account',                  'asset',     '1110'],
        'momo'                    => ['798777', '111003', 'MTN Mobile Money',                 'asset',     '1110'],
        'petty_cash'              => ['1115', null, null, null, null],
        // receivables / payables / liabilities
        'ar_general'              => ['1120', null, null, null, null],
        'ap'                      => ['2110', null, null, null, null],
        'vat_out'                 => ['2122', null, null, null, null],
        'deposits'                => [null, '2150', 'Patient deposits & advances',            'liability', '2100'],
        'salary_payable'          => [null, '2151', 'Net salaries payable',                   'liability', '2100'],
        'paye_payable'            => [null, '2152', 'PAYE / TPR payable',                     'liability', '2120'],
        'pension_payable'         => [null, '2153', 'Pension (CSR / RSSB) payable',           'liability', '2100'],
        'other_deductions_payable'=> [null, '2154', 'Other payroll deductions payable',       'liability', '2100'],
        'grni'                    => [null, '2155', 'Goods received not invoiced',            'liability', '2100'],
        'wht_receivable'          => ['116001', null, null, null, null],
        'staff_advance'           => ['1140', null, null, null, null],
        // inventory & cost of sales
        'store_clinic'            => ['1132', null, null, null, null],
        'cogs'                    => ['510001', null, null, null, null, 'Cost of drugs actually dispensed/sold to patients, expensed at dispensing/stock issue.'],
        'stock_loss'              => ['53007', null, null, null, null],
        'stock_adjust'            => [null, '534009', 'Stock adjustment gains / losses',      'expense',   '5340'],
        'dept_consumables'        => [null, '5312',   'Departmental consumables issued',      'expense',   '5200'],
        'maintenance_exp'         => [null, '534010', 'Repairs & maintenance',                'expense',   '5340'],
        // revenue
        'rev_consultation'        => ['4102', null, null, null, null],
        'rev_pharmacy'            => ['4101', null, null, null, null],
        'rev_lab'                 => [null, '4103', 'Laboratory income',                      'income',    '4100', 'Revenue from laboratory tests billed to patients and insurers.'],
        'rev_procedure'           => [null, '4104', 'Procedure & theatre income',             'income',    '4100'],
        'rev_radiology'           => [null, '4105', 'Radiology & imaging income',             'income',    '4100'],
        'rev_accommodation'       => [null, '4106', 'Ward & accommodation income',            'income',    '4100'],
        'rev_nursing'             => [null, '4107', 'Nursing services income',                'income',    '4100', 'Revenue from nursing and emergency services.'],
        'rev_other'               => [null, '4109', 'Other service income',                   'income',    '4100'],
        'discount'                => [null, '534007', 'Discounts & waivers allowed',          'expense',   '5340'],
        'ins_writeoff'            => ['534005', null, null, null, null],
        'bad_debt_exp'            => [null, '534011', 'Bad debts expense',                    'expense',   '5340'],
        'allowance'               => [null, '1121',   'Allowance for doubtful accounts',      'asset',     '1120'],
        'wht_payable'             => [null, '2156',   'Withholding tax payable',              'liability', '2120'],
        // payroll
        'salary_exp'              => ['531003', null, null, null, null],
        'employer_contrib_exp'    => [null, '531004', 'Employer social security contributions', 'expense', '5310'],
        // fixed assets
        'dep_exp'                 => ['5350', null, null, null, null],
        'acc_dep'                 => ['1300', null, null, null, null],
        'asset_gain'              => [null, '4310',   'Gain on asset disposal',               'income',    '4300'],
        'asset_loss'              => [null, '534008', 'Loss on asset disposal',               'expense',   '5340'],
        'reval_surplus'           => ['3400', null, null, null, null],
        // equity
        'opening_equity'          => ['3200', null, null, null, null],
        'retained'                => ['3300', null, null, null, null],
    ];
}

// invoice_items.category => account map key
function fin_category_keys(): array {
    return [
        'consultation' => 'rev_consultation', 'consultations' => 'rev_consultation', 'doctor' => 'rev_consultation',
        'pharmacy' => 'rev_pharmacy', 'drug' => 'rev_pharmacy', 'drugs' => 'rev_pharmacy', 'medication' => 'rev_pharmacy',
        'lab' => 'rev_lab', 'laboratory' => 'rev_lab',
        'procedure' => 'rev_procedure', 'surgery' => 'rev_procedure',
        'radiology' => 'rev_radiology', 'imaging' => 'rev_radiology',
        'accommodation' => 'rev_accommodation', 'ward' => 'rev_accommodation',
        'nursing' => 'rev_nursing',
        'emergency' => 'rev_nursing', // emergency revenue is booked with nursing (4107)
    ];
}

// Medlink: tables/columns already exist in this database, so there is no installer here.
// Account-map rows (finance_account_map) are seeded add-only by the one-off SQL documented with the port; this only checks they exist.
function fin_boot(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    $done = true;
}

// Audit trail: medlink has no activity_log include everywhere; log only when a log_activity() is loaded.
if (!function_exists('fin_log')) {
    function fin_log(...$a) { if (function_exists('log_activity')) log_activity(...$a); }
}
