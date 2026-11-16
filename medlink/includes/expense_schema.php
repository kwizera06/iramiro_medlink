<?php
// includes/expense_schema.php
// Expense Management support: its own tables (CREATE TABLE IF NOT EXISTS only) and the few ledger helpers the
// handler needs, written for medlink's journal_entries / journal_entry_lines / chart_of_accounts.
// Ported from Michel's finance_schema.php + finance_posting.php, reduced on purpose: no account-map table,
// no period-close table, no schema changes to existing medlink tables.

function ex_boot(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    $done = true;
    if ($pdo->query("SHOW TABLES LIKE 'expense_requests'")->fetch() && $pdo->query("SHOW TABLES LIKE 'expense_request_documents'")->fetch()) return;
    if ($pdo->inTransaction()) throw new RuntimeException('ex_boot must run outside a transaction');
    $pdo->exec("CREATE TABLE IF NOT EXISTS expense_categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        account_id INT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_by INT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_expense_category_name (name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS expense_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        expense_no VARCHAR(40) NOT NULL UNIQUE,
        facility_id INT NULL,
        department_id INT NULL,
        category_id INT NULL,
        expense_account_id INT NULL,
        description VARCHAR(255) NOT NULL,
        item_service VARCHAR(255) NULL,
        items_json TEXT NULL,
        quantity DECIMAL(15,3) NOT NULL DEFAULT 1,
        unit VARCHAR(40) NULL,
        unit_price DECIMAL(15,2) NOT NULL DEFAULT 0,
        tax DECIMAL(15,2) NOT NULL DEFAULT 0,
        discount DECIMAL(15,2) NOT NULL DEFAULT 0,
        total DECIMAL(15,2) NOT NULL DEFAULT 0,
        expense_date DATE NOT NULL,
        due_date DATE NULL,
        payee VARCHAR(150) NULL,
        payee_type VARCHAR(30) NULL,
        telephone VARCHAR(50) NULL,
        email VARCHAR(150) NULL,
        address VARCHAR(255) NULL,
        tin VARCHAR(80) NULL,
        invoice_reference VARCHAR(100) NULL,
        payment_method VARCHAR(30) NULL,
        paying_account_id INT NULL,
        amount_paid DECIMAL(15,2) NOT NULL DEFAULT 0,
        last_payment_date DATE NULL,
        last_payment_reference VARCHAR(100) NULL,
        comments TEXT NULL,
        status ENUM('draft','submitted','approved','rejected','posted','partially_paid','paid','cancelled') NOT NULL DEFAULT 'draft',
        created_by INT NULL,
        submitted_by INT NULL,
        approved_by INT NULL,
        approved_at DATETIME NULL,
        posted_at DATETIME NULL,
        rejected_reason VARCHAR(255) NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_exp_req_status (status), KEY idx_exp_req_date (expense_date), KEY idx_exp_req_dept (department_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS expense_request_payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        expense_request_id INT NOT NULL,
        payment_date DATE NOT NULL,
        amount DECIMAL(15,2) NOT NULL,
        pay_account_id INT NOT NULL,
        method VARCHAR(30) NULL,
        reference VARCHAR(100) NULL,
        created_by INT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_exp_req_pay (expense_request_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS expense_request_audit (
        id INT AUTO_INCREMENT PRIMARY KEY,
        expense_request_id INT NOT NULL,
        action VARCHAR(30) NOT NULL,
        from_status VARCHAR(30) NULL,
        to_status VARCHAR(30) NULL,
        comment VARCHAR(255) NULL,
        user_id INT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_exp_req_audit (expense_request_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS expense_request_documents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        expense_request_id INT NOT NULL,
        original_name VARCHAR(255) NOT NULL,
        stored_name VARCHAR(255) NOT NULL,
        mime_type VARCHAR(120) NULL,
        file_size INT NOT NULL DEFAULT 0,
        uploaded_by INT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_exp_req_doc (expense_request_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    if (!$pdo->query("SHOW COLUMNS FROM expense_requests LIKE 'supplier_id'")->fetch()) {
        $pdo->exec("ALTER TABLE expense_requests ADD COLUMN supplier_id INT NULL AFTER department_id, ADD KEY idx_exp_req_supplier (supplier_id)");
    }
    $pdo->exec("INSERT IGNORE INTO expense_categories (name) VALUES
        ('Utilities'), ('Premises'), ('Human resources'), ('Staff advances'),
        ('Missions and transport'), ('Maintenance'), ('Medical consumables'),
        ('Office supplies / cleaning'), ('Other')");
    $pdo->exec("INSERT IGNORE INTO expense_categories (name) VALUES
        ('Electricity'), ('Water'), ('Internet'), ('Rent'), ('Fuel'), ('Transport'),
        ('Security'), ('Staff training'), ('Equipment repairs'), ('Cleaning'),
        ('Office supplies'), ('Medical supplies')");
}

// Accounts the expense workflow posts against (looked up by chart_of_accounts.account_code)
function fin_acct(PDO $pdo, string $key): int {
    $codes = ['ap' => '2110'];
    $q = $pdo->prepare("SELECT id FROM chart_of_accounts WHERE account_code = ? LIMIT 1");
    $q->execute([$codes[$key] ?? $key]);
    $id = $q->fetchColumn();
    if (!$id) throw new RuntimeException("No account found for '$key' in the chart of accounts");
    return (int)$id;
}

// Cash / bank / mobile-money accounts (children of Cash & Cash Equivalents, id 3)
function fin_cash_accounts(PDO $pdo): array {
    return $pdo->query("SELECT id, account_code, account_name FROM chart_of_accounts WHERE parent_id = 3 AND status = 'active' ORDER BY account_code")->fetchAll();
}

// Post a balanced journal entry. $o: date, description, type, ref_id, lines [[account_id, debit, credit, desc]]. Joins an open transaction.
function fin_post(PDO $pdo, array $o): int {
    $date = substr((string)($o['date'] ?? date('Y-m-d')), 0, 10);
    $lines = [];
    foreach ($o['lines'] ?? [] as $l) {
        $d = round((float)($l['debit'] ?? 0), 2); $c = round((float)($l['credit'] ?? 0), 2);
        if ($d < 0 || $c < 0) throw new InvalidArgumentException('Negative journal amount');
        if ($d == 0 && $c == 0) continue;
        $lines[] = [(int)$l['account_id'], (string)($l['desc'] ?? ''), $d, $c];
    }
    if (count($lines) < 2) throw new InvalidArgumentException('A journal entry needs at least two lines');
    if (abs(array_sum(array_column($lines, 2)) - array_sum(array_column($lines, 3))) > 0.01) throw new InvalidArgumentException('Journal not balanced');
    $uid = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    $own = !$pdo->inTransaction();
    if ($own) $pdo->beginTransaction();
    try {
        $pdo->prepare("INSERT INTO journal_entries (entry_date, description, reference_type, reference_id, posted, posted_by, posted_at, created_by) VALUES (?,?,?,?,1,?,NOW(),?)")
            ->execute([$date, (string)($o['description'] ?? ''), $o['type'] ?? null, isset($o['ref_id']) ? (string)$o['ref_id'] : null, $uid, $uid]);
        $jeId = (int)$pdo->lastInsertId();
        $pdo->prepare("UPDATE journal_entries SET reference = ? WHERE id = ?")->execute([sprintf('%s-%s-%05d', ($o['type'] ?? '') === 'expense_payment' ? 'EPM' : 'EXP', date('Ymd', strtotime($date)), $jeId), $jeId]);
        $ins = $pdo->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, line_description, debit, credit) VALUES (?,?,?,?,?)");
        foreach ($lines as $l) $ins->execute([$jeId, $l[0], $l[1], $l[2], $l[3]]);
        if ($own) $pdo->commit();
        return $jeId;
    } catch (Throwable $e) {
        if ($own && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

// Delete the entries posted for a source record (type(s) + ref id)
function fin_unpost(PDO $pdo, $types, $refId, string $reason = 'Source record removed'): void {
    $types = (array)$types;
    $in = implode(',', array_fill(0, count($types), '?'));
    $q = $pdo->prepare("SELECT id FROM journal_entries WHERE reference_type IN ($in) AND reference_id = ?");
    $q->execute(array_merge($types, [(string)$refId]));
    foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $id) {
        $pdo->prepare("DELETE FROM journal_entry_lines WHERE journal_entry_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM journal_entries WHERE id = ?")->execute([$id]);
    }
}
