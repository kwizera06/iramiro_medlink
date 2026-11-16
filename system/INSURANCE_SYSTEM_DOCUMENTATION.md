# Insurance System Documentation - billing.php Handler

## Overview
The billing.php handler manages invoice creation, calculation, and payment tracking with special logic for insurance invoices that split costs between **patient copayment** and **insurance company portions**.

---

## 1. KEY INSURANCE CONCEPTS

### Copayment Percentage
- Defined in `patient_insurance` + `insurance_packages` tables
- Example: 20% copayment = Patient pays 20%, Insurance pays 80%
- Retrieved via `copayment_percentage` field

### Payment Types
```php
$paymentType = 'self-pay'    // Patient pays full amount
$paymentType = 'insurance'   // Split: patient (copay%) + insurance (100-copay%)
$paymentType = 'mixed'       // Combination of payment types
```

### Invoice Balance Split
When insurance is involved:
- **Patient Balance** = Total × (copayment_percentage / 100)
- **Insurance Balance** = Total - Patient Balance

Example:
- Invoice Total: 100,000 RWF
- Copayment: 20%
- Patient owes: 100,000 × 0.20 = 20,000 RWF
- Insurance owes: 100,000 × 0.80 = 80,000 RWF

---

## 2. HOW INVOICE CREATION WORKS

### When Insurance Invoice is Created (from POST action)

```php
// Line 965-967: Create invoice
$stmt = $pdo->prepare("INSERT INTO invoices 
    (invoice_no, visit_id, patient_id, payment_type, insurance_id, 
     discount, tax, subtotal, total, balance, status, notes, created_by, created_at) 
    VALUES (?, ?, ?, ?, ?, ?, ?, 0, 0, 0, 'draft', ?, ?, NOW())");

$stmt->execute([$invoiceNo, $visitId, $patientId, $paymentType, $insId, 
                $discount, $tax, $notes, $input['created_by']]);
```

**Key fields:**
- `payment_type`: Set to 'insurance' if insurance_id provided
- `insurance_id`: ID of the patient's insurance (company_id from patient_insurance)
- `balance`: Initially 0, recalculated after items added

### When Invoice Items are Added

```php
// Items added with:
$pdo->prepare("INSERT INTO invoice_items 
    (invoice_id, service_id, description, quantity, unit_price, discount, total, category, added_by, added_at) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())")
```

Then `recalcInvoice()` is called to:
1. Sum all invoice_items total → subtotal
2. Calculate: `total = subtotal - discount + tax`
3. Split balance based on copayment_percentage

---

## 3. INSURANCE BALANCE CALCULATION - recalcInvoice()

Located at **Line 18-62**

```php
function recalcInvoice(PDO $pdo, int $invoiceId): void {
    // Step 1: Sum all items
    $subtotal = SUM(invoice_items.total)
    
    // Step 2: Get invoice details
    $discount = invoices.discount
    $tax      = invoices.tax
    $total    = max(0, subtotal - discount + tax)
    
    // Step 3: Calculate balance (full amount due initially)
    $balance  = max(0, total - paid_amount)
    
    // Step 4: IF INSURANCE EXISTS, recalculate balance
    if ($insId) {
        $copayPct = [from patient_insurance + insurance_packages]
        
        if ($copayPct < 100) {
            // Only patient's copay portion is their balance
            $patientBalance = round(total * copayPct / 100, 2)
            UPDATE invoices SET balance = patientBalance
        }
        // Insurance portion is tracked separately in journal entries
    }
    
    // Step 5: Update journal entries to match new totals
    recalcJournalEntry()
}
```

### Copayment Lookup Logic
The system tries to find copayment_percentage in this order:

```php
// 1. First try: insurance_id is patient_insurance.id
$cpStmt = "SELECT ip.copayment_percentage 
           FROM patient_insurance pi 
           LEFT JOIN insurance_packages ip ON ip.id = pi.package_id 
           WHERE pi.id = ?"

// 2. Fallback: insurance_id is insurance_companies.id
$cpStmt2 = "SELECT ip.copayment_percentage 
            FROM patient_insurance pi 
            LEFT JOIN insurance_packages ip ON ip.id = pi.package_id 
            WHERE pi.patient_id = ? AND pi.company_id = ? AND pi.is_primary = 1 LIMIT 1"
```

---

## 4. JOURNAL ENTRY CREATION - recalcJournalEntry()

Located at **Line 65-113**

When insurance invoice total changes, journal entries are updated:

### Patient Copay Portion (Patient Receivable)
```
Dr: Patient Receivable Account (Account 7 + patient sub-account)
    Amount: invoice.balance (only patient's copay portion)
Cr: Revenue Account (Account 1007)
    Amount: invoice.balance
```

### Insurance Portion (Insurance Receivable)
```
Dr: Insurance Receivable Account (linked to insurance company)
    Amount: total - patient_portion
Cr: Revenue Account (Account 1007)
    Amount: total - patient_portion
```

**Example Journal Entry for 100,000 RWF invoice with 20% copay:**

```
Entry Date: TODAY
Description: Consultation charge from Invoice INV-20260813-0001

Lines:
  Dr Patient Receivable (Account 7)           20,000 RWF
  Dr Insurance Receivable (Account ACME Ins)  80,000 RWF
      Cr Revenue (Account 1007)                       100,000 RWF
```

---

## 5. INVOICES LIST WITH FILTERS - Line 398-446

### Payment Status Filtering

**Pending Status:**
```php
$where[] = "i.status IN ('issued','partial','draft')";

// Special logic: Exclude insurance invoices where patient already paid copay
$where[] = "NOT (
    i.payment_type = 'insurance'
    AND i.paid_amount >= ROUND(i.total * copayment_percentage / 100, 2)
)";
// This means: Show invoice as pending only if patient HASN'T paid their copay share
```

**Paid Status:**
```php
// Show only if balance is fully covered
// For insurance: check if patient paid their copay share AND total is paid
```

### Key Query (Line 398-446)

```sql
SELECT i.*, p.*, v.visit_no, v.visit_date, v.payment_type,
       company_name, package_name, copayment_percentage,
       REMAINING_BALANCE = GREATEST(0, 
           ROUND(i.total * copayment_percentage / 100, 2) - i.paid_amount
       )
FROM invoices i
LEFT JOIN patient_insurance pi ON pi.id = i.insurance_id
LEFT JOIN insurance_companies ic ON ic.id = pi.company_id
LEFT JOIN insurance_packages ip ON ip.id = pi.package_id
WHERE [filters applied]
```

---

## 6. RECALCULATE VISIT - POST action=recalculate_visit (Line 449-846)

This is the **most important action for insurance**.

### Purpose
When visit is finalized or insurance prices change, recalculate all invoices for that visit with current insurance prices from `insurance_procedure_prices` table.

### Flow

```
INPUT:
  - visit_id
  - patient_id

LOGIC:
1. Get patient's current PRIMARY insurance
   - patient_insurance (is_primary=1)
   - insurance_packages (copayment_percentage)

2. Load all insurance_procedure_prices for that insurance company
   - Maps service_id → price

3. For each invoice in visit:
   a. For each invoice_item:
      - If service_id exists in insurance_procedure_prices
      - Update unit_price to insurance price
      - Recalculate item.total = (qty × new_price) - discount
      - Count as "updated"
   
   b. Recalculate invoice totals
      - subtotal = SUM(invoice_items.total)
      - total = subtotal - discount + tax
      - Split balance by copayment%
      - Update status (issued/partial/paid)

4. Update journal entries to match new amounts
   - Scales debit/credit by ratio = new_total / old_total

OUTPUT:
  {
    "updated_invoices": N,
    "updated_items": M,
    "total_before": X,
    "total_after": Y,
    "company_id": insurance_company_id,
    "copayment_percentage": copay%
  }
```

### Example
```php
// Insurance Company: ACME Insurance
// Insurance Procedure Prices:
//   - Consultation: 50,000 RWF (vs Market 60,000)
//   - Lab Test: 15,000 RWF (vs Market 20,000)

// Before recalculation:
//   Item 1: Consultation 1 × 60,000 = 60,000
//   Item 2: Lab Test 1 × 20,000 = 20,000
//   Total: 80,000 RWF
//   Patient Balance (20% copay): 16,000 RWF

// After recalculation with insurance prices:
//   Item 1: Consultation 1 × 50,000 = 50,000 ← UPDATED
//   Item 2: Lab Test 1 × 15,000 = 15,000 ← UPDATED
//   Total: 65,000 RWF
//   Patient Balance (20% copay): 13,000 RWF
```

---

## 7. PAYMENT RECORDS

Each payment against an invoice creates a `payment_records` entry:

```sql
CREATE TABLE payment_records (
    id INT PRIMARY KEY,
    invoice_id INT,
    amount_paid DECIMAL(12,2),
    payment_method VARCHAR(50),      -- 'cash', 'card', 'check', etc.
    payment_date DATE,
    receipt_number VARCHAR(50),
    status VARCHAR(20),              -- 'completed', 'pending', 'written_off'
    created_at TIMESTAMP
);
```

### Status Logic in Invoices List (Line 420-435)

```php
// Get payment_records for invoice
$records = payment_records WHERE invoice_id = ?

// Use payment_records as source of truth (more reliable than invoice.paid_amount)
$paid_amount = SUM(amount_paid WHERE status='completed')
$unpaid_amount = SUM(amount_paid WHERE status='pending')
$written_off_amount = SUM(amount_paid WHERE status='written_off')

// Calculate remaining balance
$remaining = total - paid_amount - written_off_amount
```

---

## 8. INSURANCE TOP-UP CONCEPT (Not Yet Implemented)

Based on the existing settlement pattern in `/handlers/insurance/settlement.php`:

**Top-Up would be the REVERSE of Settlement:**

### Settlement (Current)
```
Insurance has a RECEIVABLE (debit balance)
Hospital settles by paying insurance back
Creates journal entries that REDUCE the receivable:
  Dr Loss Account → Cr Insurance Receivable
  Dr WHT Tax Account → Cr Insurance Receivable
  Dr Bank Account → Cr Insurance Receivable
```

### Top-Up (Proposed)
```
Insurance pre-pays by increasing credit balance on their account
Allows future invoices to be charged against this prepaid amount
Would create journal entries like:
  Dr Bank Account → Cr Insurance Receivable (when cash received)
  
Then when invoice is issued:
  - First check if insurance has prepaid balance
  - If yes, deduct from prepayment instead of adding to receivable
  - Update insurance_topups table tracking
```

---

## 9. KEY TABLES INVOLVED

| Table | Purpose |
|-------|---------|
| `invoices` | Main invoice record with payment_type, insurance_id, balance, paid_amount |
| `invoice_items` | Line items per invoice with category, unit_price, total |
| `patient_insurance` | Link between patient and insurance company with copayment_percentage |
| `insurance_companies` | Insurance company details |
| `insurance_packages` | Insurance packages with copayment_percentage rules |
| `insurance_procedure_prices` | Insurance-agreed pricing for services |
| `payment_records` | Payment transactions against invoices |
| `journal_entries` | Accounting records linked to invoices |
| `journal_entry_lines` | Debit/credit lines per journal entry |
| `chart_of_accounts` | Chart of accounts with linked_insurance_id, linked_client_id |

---

## 10. SUMMARY: Insurance Calculation Flow

```
1. Visit created with payment_type='insurance'
   ↓
2. Invoice created, linked to patient's insurance
   ↓
3. Invoice items added with market prices
   ↓
4. recalcInvoice() called
   ├─ Sum items → subtotal
   ├─ Calculate total = subtotal ± discount ± tax
   ├─ Split: patient_balance = total × copayment% / 100
   └─ recalcJournalEntry()
       ├─ Dr Patient Receivable (patient_balance)
       ├─ Dr Insurance Receivable (total - patient_balance)
       └─ Cr Revenue (total)
   ↓
5. When visit finalized: POST recalculate_visit
   ├─ Load insurance_procedure_prices for that company
   ├─ Update each item.unit_price → insurance price
   ├─ Recalculate totals & split
   └─ Update journal entries with new amounts
   ↓
6. Payments recorded via payment_records table
   ↓
7. Monthly settlement: POST /handlers/insurance/settlement.php
   ├─ Generate monthly invoice for insurance
   └─ Create journal entries to settle receivable (writeoff + WHT + payment)
```

