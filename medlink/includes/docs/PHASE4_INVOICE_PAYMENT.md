# Phase 4: Invoice & Payment Integration - Implementation Guide

## Overview

Phase 4 implements a comprehensive invoice and payment integration system for the patient management workflow. This includes:

1. **Consultation Fee Management** - Configure and manage fees by department and consultation type
2. **Enhanced Invoice Generation** - Automatically include consultation fees in invoices
3. **Payment Recording & Tracking** - Track all patient payments with detailed allocation and reconciliation
4. **Department-Specific Payment Workflows** - Configure payment methods and policies per department
5. **Payment Reporting & Analytics** - Comprehensive payment reports and invoicing statistics

## Database Schema

### New Tables Created

#### 1. `consultation_fees`
Manages consultation fee pricing for departments.

**Columns:**
- `id` - Primary key
- `department_id` - Department reference
- `consultation_type` - Type: 'initial', 'follow_up', 'emergency', 'specialist'
- `base_fee` - Self-pay fee amount
- `insurance_fee` - Insurance covered fee (usually lower)
- `emergency_surcharge` - Additional fee for emergency consultations
- `effective_from` - Start date for this fee rate
- `effective_to` - End date for this fee rate
- `is_active` - Whether this fee is currently active
- `created_by`, `created_at`, `updated_at` - Audit fields

**Usage:** When a consultation is completed, the appropriate fee is retrieved and added to the invoice.

#### 2. `payment_records`
Comprehensive payment tracking with full audit trail.

**Columns:**
- `id` - Primary key
- `invoice_id` - Invoice being paid
- `patient_id` - Patient making payment
- `department_id` - Department receiving payment
- `amount_paid` - Payment amount
- `payment_method` - 'cash', 'card', 'bank_transfer', 'check', 'insurance', 'mobile_money', 'mixed'
- `payment_date`, `payment_time` - When payment was made
- `receipt_number` - Unique receipt identifier
- `reference_number` - External reference (transaction ID, check number, etc.)
- `bank_name`, `check_number`, `card_last_four` - Payment method details
- `status` - 'pending', 'completed', 'failed', 'reversed', 'dispute'
- `created_by`, `verified_by` - User audit trail
- `verified_at` - When payment was verified

**Usage:** Every payment is recorded here for complete traceability.

#### 3. `payment_allocations`
Links payments to specific invoice items for detailed reconciliation.

**Columns:**
- `payment_record_id` - Payment reference
- `invoice_item_id` - Item being paid
- `consultation_id` - Optional consultation reference
- `allocated_amount` - How much of the payment applies to this item
- `allocation_note` - Reason for allocation

**Usage:** Supports partial payments and multi-item invoices with precise tracking.

#### 4. `department_payment_config`
Department-specific payment policies and workflows.

**Columns:**
- `department_id` - Department reference
- `payment_methods` - JSON array of enabled payment methods
- `allows_credit` - Whether department allows patient credit
- `credit_limit` - Maximum credit per patient
- `requires_deposit` - Whether advance deposit is required
- `deposit_percentage` - Percentage of service cost to pre-collect
- `requires_approval_above` - Amount requiring approval before processing
- `approval_role` - Role needed for approval
- `auto_invoice_on_checkout` - Automatically create invoice when patient checks out
- `invoice_prefix` - Department-specific invoice number prefix

**Usage:** Different departments can have different payment policies (e.g., ER may require deposit, OPD may not).

#### 5. `payment_disputes`
Manage payment disputes and corrections.

**Columns:**
- `payment_record_id` - Payment in dispute
- `dispute_type` - Type of dispute
- `dispute_amount` - Amount in dispute
- `description` - Details of dispute
- `status` - 'open', 'investigating', 'resolved', 'rejected'
- `resolution` - How the dispute was resolved
- `resolved_by`, `resolved_at` - Resolution audit trail

**Usage:** Handle payment corrections and disputes with full audit.

#### 6. `payment_summary`
Daily aggregated payment statistics for reporting.

**Columns:**
- `summary_date` - Date of summary
- `department_id` - Department
- `payment_method` - Payment method
- `total_payments` - Total amount
- `payment_count` - Number of transactions
- `completed_amount`, `failed_amount`, `reversed_amount` - By status

**Usage:** Pre-aggregated data for fast dashboard reporting.

#### 7. `invoice_adjustments`
Track invoice modifications like discounts and surcharges.

**Columns:**
- `invoice_id` - Invoice being adjusted
- `adjustment_type` - 'discount', 'surcharge', 'credit', 'reversal', 'correction'
- `amount` - Adjustment amount
- `reason` - Why adjustment was made
- `approved_by`, `approved_at` - Approval audit trail

**Usage:** Support invoice corrections with full traceability.

#### 8. `consultation_fee_history`
Historical record of consultation fee changes for auditing.

**Columns:**
- `consultation_id` - Consultation reference
- `original_fee` - Fee at time of consultation
- `adjusted_fee` - If fee was changed
- `adjustment_reason` - Why fee changed
- `adjusted_by`, `adjusted_at` - Adjustment audit trail

**Usage:** Track all fee changes for auditing and compliance.

### Modified Tables

#### `invoices`
Added columns:
- `consultation_fee_included` - Whether consultation fee is included
- `consultation_fee_amount` - Amount of consultation fee
- `department_id` - Associated department
- `payment_workflow_status` - Workflow state: 'pending_approval', 'approved', 'payment_due', 'payment_pending', 'completed'

#### `consultations`
Added columns:
- `consultation_fee_id` - Reference to applicable fee structure
- `fee_amount` - Actual fee charged
- `fee_status` - 'pending', 'invoiced', 'paid', 'waived'

#### `visits`
Added columns:
- `consultation_completed_at` - When consultation was completed

## API Endpoints

### Consultation Fees Management
**Base URL:** `/handlers/finance/consultation-fees.php`

#### GET - List Consultation Fees
```
GET /handlers/finance/consultation-fees.php?page=1&limit=25&active=1
```

**Query Parameters:**
- `page` - Page number (default: 1)
- `limit` - Results per page (default: 25)
- `department_id` - Filter by department
- `type` - Filter by consultation type (initial, follow_up, emergency, specialist)
- `active` - Show only active fees (default: true)

**Response:**
```json
{
    "success": true,
    "data": {
        "fees": [
            {
                "id": 1,
                "department_id": 1,
                "consultation_type": "initial",
                "base_fee": 150.00,
                "insurance_fee": 100.00,
                "emergency_surcharge": 50.00,
                "effective_from": "2026-01-01",
                "effective_to": null,
                "is_active": 1,
                "department_name": "OPD"
            }
        ],
        "pagination": {...}
    }
}
```

#### GET - Get Current Fee for Department
```
GET /handlers/finance/consultation-fees.php?action=current&department_id=1&type=initial
```

#### POST - Create Consultation Fee
```
POST /handlers/finance/consultation-fees.php
Content-Type: application/json

{
    "department_id": 1,
    "consultation_type": "initial",
    "base_fee": 200.00,
    "insurance_fee": 130.00,
    "emergency_surcharge": 60.00,
    "effective_from": "2026-07-08",
    "created_by": 5
}
```

#### PUT - Update Consultation Fee
```
PUT /handlers/finance/consultation-fees.php?id=1
Content-Type: application/json

{
    "base_fee": 210.00,
    "insurance_fee": 140.00
}
```

#### DELETE - Delete Consultation Fee
```
DELETE /handlers/finance/consultation-fees.php?id=1
```

### Enhanced Invoice Management
**Base URL:** `/handlers/finance/invoices-enhanced.php`

#### GET - Get Single Invoice with Consultation Fee
```
GET /handlers/finance/invoices-enhanced.php?id=123
```

**Response includes:**
- Invoice items
- Consultation fee amount
- Payment records
- Payment allocations

#### POST - Create Invoice with Consultation Fee
```
POST /handlers/finance/invoices-enhanced.php
Content-Type: application/json

{
    "visit_id": 456,
    "patient_id": 789,
    "department_id": 1,
    "payment_type": "self_pay",
    "include_consultation_fee": true,
    "consultation_type": "initial",
    "items": [
        {
            "description": "Lab Tests",
            "category": "lab",
            "quantity": 1,
            "unit_price": 500.00
        }
    ],
    "created_by": 5
}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "id": 1,
        "invoice_no": "INV-20260708-0001",
        "subtotal": 500.00,
        "consultation_fee_amount": 200.00,
        "total": 700.00,
        "status": "issued"
    }
}
```

#### PUT - Update Invoice Consultation Fee
```
PUT /handlers/finance/invoices-enhanced.php?id=123
Content-Type: application/json

{
    "action": "update_consultation_fee",
    "consultation_fee_amount": 150.00
}
```

### Payment Recording
**Base URL:** `/handlers/finance/payment-records.php`

#### GET - List Payments
```
GET /handlers/finance/payment-records.php?invoice_id=123&status=completed&payment_method=cash
```

**Query Parameters:**
- `invoice_id` - Filter by invoice
- `patient_id` - Filter by patient
- `department_id` - Filter by department
- `status` - Filter by status
- `payment_method` - Filter by method
- `date_from`, `date_to` - Date range
- `page`, `limit` - Pagination

#### GET - Get Single Payment
```
GET /handlers/finance/payment-records.php?id=999
```

**Response includes:**
- Payment details
- Payment allocations
- Allocated items

#### POST - Record Payment
```
POST /handlers/finance/payment-records.php
Content-Type: application/json

{
    "invoice_id": 123,
    "patient_id": 789,
    "department_id": 1,
    "amount_paid": 1000.00,
    "payment_method": "cash",
    "payment_date": "2026-07-08",
    "payment_time": "14:30:00",
    "notes": "Payment in full",
    "created_by": 5,
    "allocations": [
        {
            "invoice_item_id": 456,
            "amount": 500.00,
            "note": "Lab tests payment"
        },
        {
            "invoice_item_id": 457,
            "amount": 500.00,
            "note": "Consultation fee"
        }
    ]
}
```

#### PUT - Reverse Payment
```
PUT /handlers/finance/payment-records.php?id=999
Content-Type: application/json

{
    "action": "reverse"
}
```

#### PUT - Verify Payment
```
PUT /handlers/finance/payment-records.php?id=999
Content-Type: application/json

{
    "action": "verify",
    "verified_by": 5
}
```

### Department Payment Configuration
**Base URL:** `/handlers/finance/department-payment-config.php`

#### GET - Get Department Configuration
```
GET /handlers/finance/department-payment-config.php?action=by_department&department_id=1
```

#### POST - Create Configuration
```
POST /handlers/finance/department-payment-config.php
Content-Type: application/json

{
    "department_id": 1,
    "payment_methods": ["cash", "card", "insurance"],
    "allows_credit": false,
    "requires_deposit": true,
    "deposit_percentage": 30,
    "auto_invoice_on_checkout": true,
    "invoice_prefix": "OPD"
}
```

#### PUT - Update Configuration
```
PUT /handlers/finance/department-payment-config.php?id=1
Content-Type: application/json

{
    "payment_methods": ["cash", "card", "bank_transfer", "insurance"],
    "requires_deposit": false
}
```

### Payment Reports
**Base URL:** `/handlers/finance/payment-reports.php`

#### Daily Payment Summary
```
GET /handlers/finance/payment-reports.php?report=daily_summary&date=2026-07-08&department_id=1
```

#### Department Payment Statistics
```
GET /handlers/finance/payment-reports.php?report=department_stats&date_from=2026-06-08&date_to=2026-07-08
```

#### Invoice Status Report
```
GET /handlers/finance/payment-reports.php?report=invoice_status&department_id=1
```

**Response includes:**
- Status breakdown (issued, partial, paid, etc.)
- Overdue invoices list

#### Payment Tracking Report
```
GET /handlers/finance/payment-reports.php?report=payment_tracking&invoice_id=123
```

#### Consultation Fee Report
```
GET /handlers/finance/payment-reports.php?report=consultation_fees&date_from=2026-06-08&date_to=2026-07-08
```

## Integration Points

### From Consultation Flow
1. When consultation is marked completed, store `fee_amount` and `fee_status`
2. Link to applicable `consultation_fee` record
3. Include fee in invoice generation

### From Invoice Generation
1. Check if consultation fee should be included
2. Retrieve applicable consultation fee based on:
   - Department
   - Consultation type (initial/follow-up)
   - Payment type (self-pay vs insurance)
3. Add fee to invoice items
4. Recalculate invoice totals

### From Payment Processing
1. Create payment record with full details
2. Auto-allocate payment to invoice items
3. Update invoice balance and status
4. Update daily payment summary
5. If payment completes invoice, mark as paid

### Department Dashboard Integration
1. Display daily payment summary from `payment_summary` table
2. Show pending payments from invoices with status 'issued' or 'partial'
3. Display overdue invoices (30+ days)
4. Show payment trends and method breakdown
5. Link to detailed payment records and invoices

## Usage Examples

### Example 1: Complete Invoice Workflow with Consultation Fee

```javascript
// 1. Create consultation
POST /handlers/consultation/consult.php
{
    "visit_id": 1,
    "patient_id": 100,
    "department_id": 5,
    "status": "completed",
    ...consultation data...
}

// 2. Create invoice with consultation fee
POST /handlers/finance/invoices-enhanced.php
{
    "visit_id": 1,
    "patient_id": 100,
    "department_id": 5,
    "include_consultation_fee": true,
    "items": [...]
}

// 3. Record payment
POST /handlers/finance/payment-records.php
{
    "invoice_id": 50,
    "patient_id": 100,
    "amount_paid": 1500.00,
    "payment_method": "cash",
    "allocations": [...]
}
```

### Example 2: Configure Department Payment Workflow

```javascript
POST /handlers/finance/department-payment-config.php
{
    "department_id": 1,
    "payment_methods": ["cash", "card", "insurance"],
    "allows_credit": false,
    "requires_deposit": true,
    "deposit_percentage": 25,
    "requires_approval_above": 5000.00,
    "approval_role": "finance_manager"
}
```

### Example 3: Generate Payment Reports

```javascript
// Daily summary
GET /handlers/finance/payment-reports.php?report=daily_summary&date=2026-07-08

// Department statistics
GET /handlers/finance/payment-reports.php?report=department_stats&date_from=2026-06-08&date_to=2026-07-08

// Invoice tracking
GET /handlers/finance/payment-reports.php?report=payment_tracking&invoice_id=123
```

## Workflow States

### Invoice Payment Workflow Status
- `pending_approval` - Awaiting approval for processing
- `approved` - Approved for payment processing
- `payment_due` - Payment is due from patient
- `payment_pending` - Awaiting payment from patient/insurance
- `completed` - Invoice fully paid

### Consultation Fee Status
- `pending` - Consultation fee not yet billed
- `invoiced` - Fee included in invoice
- `paid` - Fee has been paid
- `waived` - Fee was waived

### Payment Status
- `pending` - Payment recorded but not verified
- `completed` - Payment verified and processed
- `failed` - Payment failed
- `reversed` - Payment was reversed
- `dispute` - Payment is under dispute

## Key Features

✓ **Automatic Consultation Fee Calculation** - Based on department, type, and payment method
✓ **Multi-Method Payment Support** - Cash, card, bank transfer, check, insurance, mobile money
✓ **Payment Allocation** - Detailed tracking of how payments apply to invoice items
✓ **Dispute Management** - Handle payment disputes with full audit trail
✓ **Department-Specific Policies** - Each department can have different payment workflows
✓ **Real-Time Reporting** - Daily summaries and comprehensive analytics
✓ **Audit Trail** - Complete tracking of all transactions and modifications
✓ **Partial Payments** - Support for partial invoice payments
✓ **Automatic Balance Updates** - Invoice status updated automatically as payments are received

## Next Steps

1. **Frontend Integration** - Create UI for payment recording and reporting
2. **Queue Integration** - Link payment workflows to department queues
3. **Dashboard Reports** - Add payment widgets to department dashboards
4. **Email Notifications** - Send payment receipts and reminders
5. **Reconciliation** - Implement bank reconciliation features
