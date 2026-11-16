<?php
// includes/numbering.php — one invoice-number generator for every code path (MON-08).
// Daily series INV-YYYYMMDD-NNNN. A named DB lock serialises two requests numbering at once; it is held on
// this connection until the request ends, i.e. until the invoice row is inserted. The UNIQUE index on
// invoices.invoice_no stays the last line of defence.
function next_invoice_no(PDO $pdo): string
{
    $pdo->query("SELECT GET_LOCK('invoice_no', 5)")->fetchColumn();
    $prefix = 'INV-' . date('Ymd') . '-';
    $q = $pdo->prepare("SELECT MAX(CAST(SUBSTRING(invoice_no, -4) AS UNSIGNED)) FROM invoices WHERE invoice_no LIKE ?");
    $q->execute([$prefix . '____']);
    return $prefix . str_pad((string)((int)$q->fetchColumn() + 1), 4, '0', STR_PAD_LEFT);
}

// Same idea for other documents (RFQ, PO, asset codes): $prefix + next number of the series in $table.$col.
// $table / $col are code constants, never user input.
if (!function_exists('next_doc_no')) {
function next_doc_no(PDO $pdo, string $table, string $col, string $prefix, int $pad = 4): string
{
    $pdo->query("SELECT GET_LOCK('docno_$table', 5)")->fetchColumn();
    $q = $pdo->prepare("SELECT MAX(CAST(SUBSTRING($col, ?) AS UNSIGNED)) FROM $table WHERE $col LIKE ?");
    $q->execute([strlen($prefix) + 1, $prefix . '%']);
    return $prefix . str_pad((string)((int)$q->fetchColumn() + 1), $pad, '0', STR_PAD_LEFT);
}
}
