<?php
// includes/procurement_schema.php — Procurement & Supplier Management pages, supplier documents/status, proforma invoices, GRN discrepancy follow-up and its sidebar.
// Idempotent; called from procurement.php and the procurement handlers.

// The Procurement sidebar: one menu item per page; the tabs inside a page are the ?sub= values listed here (first one is the landing tab).
const PC_PAGES = [
    ['Suppliers',            'fas fa-truck',            ['suppliers', 'supplier-docs', 'performance']],
    ['Sourcing',             'fas fa-balance-scale',    ['approved-requests', 'sourcing', 'evaluation', 'awards']],
    ['Contracts',            'fas fa-file-signature',   ['contracts']],
    ['Orders & Receiving',   'fas fa-file-invoice',     ['purchase-orders', 'expected-deliveries', 'goods-received', 'discrepancies']],
    ['Invoices & Payments',  'fas fa-money-check-alt',  ['proformas', 'supplier-invoices', 'invoice-exceptions', 'payment-status']],
];

// Visible Procurement and Supplier menu, in the requested workflow order.
const PC_SIDEBAR = [
    ['Suppliers',               'fas fa-truck',             'procurement.php?module=procurement&sub=suppliers'],
    ['Request for quotations',  'fas fa-file-alt',           'procurement.php?module=procurement&sub=sourcing'],
    ["Quotation's evaluation", 'fas fa-clipboard-check',    'procurement.php?module=procurement&sub=evaluation'],
    ['Notifications',          'fas fa-bell',               'procurement.php?module=procurement&sub=awards'],
    ['Contracts',              'fas fa-file-signature',     'procurement.php?module=procurement&sub=contracts'],
    ['Purchase Orders',        'fas fa-file-invoice',        'procurement.php?module=procurement&sub=purchase-orders'],
    ['Good Received Note',     'fas fa-dolly',              'inventory.php?module=inventory&sub=goods-receiving'],
    ['Good Received Report',   'fas fa-clipboard-list',     'procurement.php?module=procurement&sub=goods-received'],
    ['Proforma invoice',       'fas fa-file-invoice-dollar', 'procurement.php?module=procurement&sub=proformas'],
    ['Invoice',                'fas fa-receipt',             'procurement.php?module=procurement&sub=supplier-invoices'],
];

// finance-ops actions the procurement pages call (contracts and supplier bills moved here from Finance). Paying a supplier stays with Finance: it is not listed.
const PC_FINANCE_ACTIONS = ['contracts', 'contract_save', 'suppliers_payable', 'supplier_invoices', 'uninvoiced_grns', 'supplier_invoice', 'supplier_invoice_void'];

// true when two ?sub= values are tabs of the same Procurement page
function pc_same_page(string $a, string $b): bool {
    foreach (PC_PAGES as [, , $subs]) if (in_array($a, $subs, true) && in_array($b, $subs, true)) return true;
    return false;
}

// Trimmed for medlink: no ALTER on suppliers (columns + the 'suspended' status already exist) and no sidebar rebuild
// (medlink's Procurement and Supplier menu rows are managed directly in nav_menu_items).
function pc_migrate(PDO $pdo, ?callable $log = null): void {
    static $done = false;
    if ($done) return;
    $done = true;
    $pdo->exec("CREATE TABLE IF NOT EXISTS supplier_documents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        supplier_id INT NOT NULL,
        doc_type VARCHAR(40) NOT NULL,
        title VARCHAR(150) NOT NULL,
        original_name VARCHAR(255) NOT NULL,
        stored_name VARCHAR(80) NOT NULL,
        mime_type VARCHAR(80) NULL,
        file_size INT NULL,
        expiry_date DATE NULL,
        verified_by INT NULL,
        verified_at DATETIME NULL,
        uploaded_by INT NULL,
        uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_sd_supplier (supplier_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS supplier_proformas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        proforma_number VARCHAR(60) NOT NULL,
        supplier_id INT NOT NULL,
        pr_id INT NULL,
        po_id INT NULL,
        issue_date DATE NOT NULL,
        valid_until DATE NULL,
        amount DECIMAL(15,2) NOT NULL,
        status ENUM('pending','accepted','rejected') NOT NULL DEFAULT 'pending',
        decision_note VARCHAR(255) NULL,
        notes VARCHAR(500) NULL,
        original_name VARCHAR(255) NULL,
        stored_name VARCHAR(80) NULL,
        mime_type VARCHAR(80) NULL,
        created_by INT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        decided_by INT NULL,
        decided_at DATETIME NULL,
        UNIQUE KEY uq_proforma (supplier_id, proforma_number),
        KEY idx_pf_po (po_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // follow-up on a delivery problem (rejected / damaged / short): open until someone records how it ended
    $pdo->exec("CREATE TABLE IF NOT EXISTS grn_discrepancy_notes (
        grn_item_id INT NOT NULL PRIMARY KEY,
        status ENUM('open','resolved') NOT NULL DEFAULT 'open',
        note VARCHAR(500) NULL,
        updated_by INT NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
