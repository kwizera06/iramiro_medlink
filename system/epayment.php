<?php
// public/epayment.php
require_once __DIR__ . '/includes/header.php';

// Get sub-module parameter and action
$subModule = isset($_GET['sub']) ? $_GET['sub'] : 'dashboard';
$action = isset($_GET['action']) ? $_GET['action'] : 'list';

// Page title mapping
$subModuleTitles = [
    'dashboard' => 'Payment Dashboard',
    'payments' => 'Payment Transactions',
    'methods' => 'Payment Methods',
    'reconcile' => 'Payment Reconciliation'
];

$currentTitle = isset($subModuleTitles[$subModule]) ? $subModuleTitles[$subModule] : 'ePayment';
?>

<!-- Page Header -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
    <div>
        <h2 style="font-size: 24px; font-weight: 600; color: var(--gray-900);"><?php echo $currentTitle; ?></h2>
        <p style="color: var(--gray-500); font-size: 14px; margin-top: 4px;">
            <?php 
            $descriptions = [
                'dashboard' => 'ePayment dashboard and payment overview',
                'payments' => 'Process and manage patient payments',
                'methods' => 'Configure payment methods and gateways',
                'reconcile' => 'Reconcile payment transactions'
            ];
            echo isset($descriptions[$subModule]) ? $descriptions[$subModule] : 'ePayment Management';
            ?>
        </p>
    </div>
    <div style="display: flex; gap: 12px; flex-wrap: wrap;">
        <?php if ($action == 'list' && $subModule == 'payments'): ?>
        <button onclick="window.location.href='epayment.php?module=finance&sub=payments&action=add'" style="padding: 10px 20px; background: var(--primary-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer; display: flex; align-items: center; gap: 8px;">
            <i class="fas fa-plus"></i> New Payment
        </button>
        <?php endif; ?>
        <?php if ($action != 'list'): ?>
        <button onclick="window.location.href='epayment.php?module=finance&sub=<?php echo $subModule; ?>'" style="padding: 10px 20px; background: white; color: var(--gray-700); border: 1px solid var(--gray-200); border-radius: 8px; font-weight: 500; cursor: pointer; display: flex; align-items: center; gap: 8px;">
            <i class="fas fa-arrow-left"></i> Back
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- ============================================
ePAYMENT DASHBOARD
============================================ -->
<?php if ($subModule == 'dashboard'): ?>

<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <!-- Dashboard Stats -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div style="padding: 20px; background: linear-gradient(135deg, #2563eb, #60a5fa); border-radius: 10px; color: white;">
            <div style="font-size: 13px; opacity: 0.9;">Today's Revenue</div>
            <div style="font-size: 28px; font-weight: 700;">$0</div>
            <div style="font-size: 12px; opacity: 0.8; margin-top: 4px;">0 transactions</div>
        </div>
        <div style="padding: 20px; background: linear-gradient(135deg, #22c55e, #86efac); border-radius: 10px; color: white;">
            <div style="font-size: 13px; opacity: 0.9;">This Week</div>
            <div style="font-size: 28px; font-weight: 700;">$0</div>
            <div style="font-size: 12px; opacity: 0.8; margin-top: 4px;">0 transactions</div>
        </div>
        <div style="padding: 20px; background: linear-gradient(135deg, #f59e0b, #fcd34d); border-radius: 10px; color: white;">
            <div style="font-size: 13px; opacity: 0.9;">This Month</div>
            <div style="font-size: 28px; font-weight: 700;">$0</div>
            <div style="font-size: 12px; opacity: 0.8; margin-top: 4px;">0 transactions</div>
        </div>
        <div style="padding: 20px; background: linear-gradient(135deg, #8b5cf6, #c4b5fd); border-radius: 10px; color: white;">
            <div style="font-size: 13px; opacity: 0.9;">Pending Payments</div>
            <div style="font-size: 28px; font-weight: 700;">0</div>
            <div style="font-size: 12px; opacity: 0.8; margin-top: 4px;">Awaiting confirmation</div>
        </div>
    </div>

    <!-- Payment Method Distribution -->
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
        <div style="padding: 20px; background: var(--gray-50); border-radius: 8px; min-height: 250px;">
            <h4 style="font-size: 14px; font-weight: 600; color: var(--gray-700); margin-bottom: 16px;">Revenue Trend</h4>
            <div style="text-align: center; padding: 60px 20px; color: var(--gray-400);">
                <div style="font-size: 48px; margin-bottom: 12px;">
                    <i class="fas fa-chart-line"></i>
                </div>
                <p>Revenue chart will appear here</p>
                <p style="font-size: 12px;">Data will be populated when available</p>
            </div>
        </div>
        <div style="padding: 20px; background: var(--gray-50); border-radius: 8px; min-height: 250px;">
            <h4 style="font-size: 14px; font-weight: 600; color: var(--gray-700); margin-bottom: 16px;">Payment Methods</h4>
            <div style="text-align: center; padding: 40px 20px; color: var(--gray-400);">
                <div style="font-size: 48px; margin-bottom: 12px;">
                    <i class="fas fa-chart-pie"></i>
                </div>
                <p>No payment data</p>
            </div>
            <div style="margin-top: 12px;">
                <div style="display: flex; justify-content: space-between; font-size: 13px; padding: 4px 0;">
                    <span style="color: var(--gray-500);">Cash</span>
                    <span style="font-weight: 500;">0%</span>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 13px; padding: 4px 0;">
                    <span style="color: var(--gray-500);">Card</span>
                    <span style="font-weight: 500;">0%</span>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 13px; padding: 4px 0;">
                    <span style="color: var(--gray-500);">Mobile Money</span>
                    <span style="font-weight: 500;">0%</span>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 13px; padding: 4px 0;">
                    <span style="color: var(--gray-500);">Insurance</span>
                    <span style="font-weight: 500;">0%</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div style="margin-top: 24px; padding-top: 16px; border-top: 1px solid var(--gray-200);">
        <h4 style="font-size: 14px; font-weight: 600; color: var(--gray-700); margin-bottom: 12px;">Quick Actions</h4>
        <div style="display: flex; gap: 12px; flex-wrap: wrap;">
            <button onclick="window.location.href='epayment.php?module=finance&sub=payments&action=add'" style="padding: 10px 20px; background: var(--primary-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">
                <i class="fas fa-credit-card"></i> Process Payment
            </button>
            <button style="padding: 10px 20px; background: var(--success-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">
                <i class="fas fa-file-invoice"></i> Generate Invoice
            </button>
            <button style="padding: 10px 20px; background: var(--warning-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">
                <i class="fas fa-sync"></i> Reconcile
            </button>
            <button style="padding: 10px 20px; background: var(--secondary-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">
                <i class="fas fa-file-export"></i> Export Report
            </button>
        </div>
    </div>
</div>

<!-- ============================================
PAYMENT TRANSACTIONS
============================================ -->
<?php elseif ($subModule == 'payments'): ?>

<?php if ($action == 'add'): ?>
<!-- PROCESS PAYMENT FORM -->
<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <h3 style="font-size: 18px; font-weight: 600; color: var(--gray-900); margin-bottom: 20px;">
        <i class="fas fa-credit-card" style="color: var(--primary-color);"></i> Process Payment
    </h3>

    <!-- Payment Progress Steps -->
    <div style="display: flex; justify-content: center; gap: 0; margin-bottom: 30px; position: relative;">
        <div style="display: flex; align-items: center; gap: 0; flex: 1;">
            <div style="display: flex; flex-direction: column; align-items: center; flex: 1;">
                <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--primary-color); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; z-index: 1;">1</div>
                <span style="font-size: 12px; margin-top: 4px; color: var(--primary-color); font-weight: 500;">Patient Info</span>
            </div>
            <div style="flex: 1; height: 2px; background: var(--primary-color);"></div>
            <div style="display: flex; flex-direction: column; align-items: center; flex: 1;">
                <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--gray-200); color: var(--gray-500); display: flex; align-items: center; justify-content: center; font-weight: 600;">2</div>
                <span style="font-size: 12px; margin-top: 4px; color: var(--gray-500);">Payment Details</span>
            </div>
            <div style="flex: 1; height: 2px; background: var(--gray-200);"></div>
            <div style="display: flex; flex-direction: column; align-items: center; flex: 1;">
                <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--gray-200); color: var(--gray-500); display: flex; align-items: center; justify-content: center; font-weight: 600;">3</div>
                <span style="font-size: 12px; margin-top: 4px; color: var(--gray-500);">Confirmation</span>
            </div>
        </div>
    </div>

    <form>
        <!-- Step 1: Patient Information -->
        <div style="margin-bottom: 24px;">
            <h4 style="font-size: 15px; font-weight: 600; color: var(--gray-900); margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-user" style="color: var(--primary-color);"></i> Patient Information
            </h4>
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px;">
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                        Patient <span style="color: var(--danger-color);">*</span>
                    </label>
                    <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                        <option value="">Search/Select Patient</option>
                    </select>
                </div>
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                        Invoice Number
                    </label>
                    <input type="text" placeholder="e.g., INV-2024-001" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
                </div>
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                        Amount Due
                    </label>
                    <input type="number" placeholder="0.00" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
                </div>
            </div>
        </div>

        <!-- Step 2: Payment Details -->
        <div style="margin-bottom: 24px;">
            <h4 style="font-size: 15px; font-weight: 600; color: var(--gray-900); margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-credit-card" style="color: var(--success-color);"></i> Payment Details
            </h4>
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px;">
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                        Payment Method <span style="color: var(--danger-color);">*</span>
                    </label>
                    <select id="paymentMethod" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;" onchange="togglePaymentFields()">
                        <option value="">Select Method</option>
                        <option value="cash">Cash</option>
                        <option value="card">Card (Credit/Debit)</option>
                        <option value="mobile-money">Mobile Money</option>
                        <option value="bank-transfer">Bank Transfer</option>
                        <option value="insurance">Insurance</option>
                    </select>
                </div>
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                        Amount <span style="color: var(--danger-color);">*</span>
                    </label>
                    <input type="number" placeholder="0.00" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
                </div>
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                        Payment Date <span style="color: var(--danger-color);">*</span>
                    </label>
                    <input type="datetime-local" value="<?php echo date('Y-m-d\TH:i'); ?>" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
                </div>
            </div>

            <!-- Card Details (shown when card selected) -->
            <div id="cardFields" style="display: none; margin-top: 16px; padding: 16px; background: var(--gray-50); border-radius: 8px;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div>
                        <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                            Card Number <span style="color: var(--danger-color);">*</span>
                        </label>
                        <input type="text" placeholder="**** **** **** ****" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                            Cardholder Name <span style="color: var(--danger-color);">*</span>
                        </label>
                        <input type="text" placeholder="John Doe" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                            Expiry Date <span style="color: var(--danger-color);">*</span>
                        </label>
                        <input type="text" placeholder="MM/YY" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                            CVV <span style="color: var(--danger-color);">*</span>
                        </label>
                        <input type="text" placeholder="***" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
                    </div>
                </div>
            </div>

            <!-- Mobile Money Details (shown when mobile money selected) -->
            <div id="mobileMoneyFields" style="display: none; margin-top: 16px; padding: 16px; background: var(--gray-50); border-radius: 8px;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div>
                        <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                            Mobile Money Provider <span style="color: var(--danger-color);">*</span>
                        </label>
                        <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                            <option value="">Select Provider</option>
                            <option value="mtn">MTN Mobile Money</option>
                            <option value="airtel">Airtel Money</option>
                            <option value="tigo">Tigo Cash</option>
                            <option value="vodafone">Vodafone Cash</option>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                            Phone Number <span style="color: var(--danger-color);">*</span>
                        </label>
                        <input type="tel" placeholder="+250 78X XXX XXX" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                            Transaction Reference
                        </label>
                        <input type="text" placeholder="Enter transaction reference" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                            Status
                        </label>
                        <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                            <option value="completed">Completed</option>
                            <option value="pending">Pending</option>
                            <option value="failed">Failed</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Insurance Details (shown when insurance selected) -->
            <div id="insuranceFields" style="display: none; margin-top: 16px; padding: 16px; background: var(--gray-50); border-radius: 8px;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div>
                        <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                            Insurance Provider <span style="color: var(--danger-color);">*</span>
                        </label>
                        <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                            <option value="">Select Provider</option>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                            Policy Number <span style="color: var(--danger-color);">*</span>
                        </label>
                        <input type="text" placeholder="Enter policy number" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                            Authorization Code
                        </label>
                        <input type="text" placeholder="Enter authorization code" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                            Claim Amount
                        </label>
                        <input type="number" placeholder="0.00" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
                    </div>
                </div>
            </div>
        </div>

        <!-- Step 3: Confirmation -->
        <div style="margin-bottom: 24px;">
            <h4 style="font-size: 15px; font-weight: 600; color: var(--gray-900); margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-check-circle" style="color: var(--success-color);"></i> Payment Summary
            </h4>
            <div style="padding: 16px; background: var(--gray-50); border-radius: 8px;">
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px;">
                    <div>
                        <span style="font-size: 12px; color: var(--gray-500);">Patient</span>
                        <div style="font-weight: 600; margin-top: 4px;">Not selected</div>
                    </div>
                    <div>
                        <span style="font-size: 12px; color: var(--gray-500);">Amount</span>
                        <div style="font-weight: 600; margin-top: 4px;">$0.00</div>
                    </div>
                    <div>
                        <span style="font-size: 12px; color: var(--gray-500);">Method</span>
                        <div style="font-weight: 600; margin-top: 4px;">Not selected</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div style="display: flex; gap: 12px; padding-top: 16px; border-top: 1px solid var(--gray-200);">
            <button type="submit" style="flex: 1; padding: 12px; background: var(--success-color); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                <i class="fas fa-check"></i> Process Payment
            </button>
            <button type="submit" style="flex: 1; padding: 12px; background: var(--primary-color); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                <i class="fas fa-print"></i> Process & Print Receipt
            </button>
            <button type="reset" style="padding: 12px 24px; background: white; color: var(--gray-700); border: 1px solid var(--gray-200); border-radius: 8px; font-weight: 500; cursor: pointer;">
                <i class="fas fa-undo"></i> Reset
            </button>
        </div>
    </form>
</div>

<script>
function togglePaymentFields() {
    const method = document.getElementById('paymentMethod').value;
    document.getElementById('cardFields').style.display = method === 'card' ? 'block' : 'none';
    document.getElementById('mobileMoneyFields').style.display = method === 'mobile-money' ? 'block' : 'none';
    document.getElementById('insuranceFields').style.display = method === 'insurance' ? 'block' : 'none';
}
</script>

<?php else: ?>
<!-- PAYMENT TRANSACTIONS LIST -->
<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <!-- Filters -->
    <div style="display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 20px; padding: 16px; background: var(--gray-50); border-radius: 8px;">
        <input type="text" placeholder="Search by patient or transaction ID..." style="flex: 1; min-width: 200px; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
        <select style="padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
            <option value="">All Methods</option>
            <option value="cash">Cash</option>
            <option value="card">Card</option>
            <option value="mobile-money">Mobile Money</option>
            <option value="bank-transfer">Bank Transfer</option>
            <option value="insurance">Insurance</option>
        </select>
        <select style="padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
            <option value="">All Status</option>
            <option value="completed">Completed</option>
            <option value="pending">Pending</option>
            <option value="failed">Failed</option>
            <option value="refunded">Refunded</option>
        </select>
        <button style="padding: 10px 20px; background: var(--primary-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">
            <i class="fas fa-search"></i> Search
        </button>
        <button onclick="window.location.href='epayment.php?module=finance&sub=payments&action=add'" style="padding: 10px 20px; background: var(--success-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">
            <i class="fas fa-plus"></i> New Payment
        </button>
    </div>

    <!-- Empty State -->
    <div style="text-align: center; padding: 60px 20px;">
        <div style="font-size: 64px; color: var(--gray-300); margin-bottom: 16px;">
            <i class="fas fa-credit-card"></i>
        </div>
        <h3 style="font-size: 20px; font-weight: 600; color: var(--gray-600); margin-bottom: 8px;">No Payment Transactions</h3>
        <p style="color: var(--gray-400); margin-bottom: 20px;">Process a payment to start tracking transactions.</p>
        <button onclick="window.location.href='epayment.php?module=finance&sub=payments&action=add'" style="padding: 12px 24px; background: var(--primary-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">
            <i class="fas fa-plus"></i> First Payment
        </button>
    </div>
</div>
<?php endif; ?>

<!-- ============================================
PAYMENT METHODS
============================================ -->
<?php elseif ($subModule == 'methods'): ?>

<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h3 style="font-size: 16px; font-weight: 600; color: var(--gray-900);">Payment Methods</h3>
            <p style="font-size: 13px; color: var(--gray-500);">Configure payment methods and gateways</p>
        </div>
        <button onclick="window.location.href='epayment.php?module=finance&sub=methods&action=add'" style="padding: 10px 20px; background: var(--primary-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">
            <i class="fas fa-plus"></i> Add Method
        </button>
    </div>

    <!-- Payment Methods Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px;">
        <!-- Cash -->
        <div style="padding: 20px; border: 2px solid var(--gray-200); border-radius: 8px; text-align: center;">
            <div style="font-size: 40px; color: var(--success-color);">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <h4 style="font-weight: 600; margin-top: 8px;">Cash</h4>
            <p style="font-size: 13px; color: var(--gray-500);">Cash payments accepted at counter</p>
            <span style="display: inline-block; margin-top: 8px; padding: 4px 12px; background: #d1fae5; color: #065f46; border-radius: 12px; font-size: 12px;">Active</span>
            <button style="display: block; width: 100%; margin-top: 12px; padding: 8px; background: none; border: 1px solid var(--gray-200); border-radius: 6px; cursor: pointer; font-size: 13px;">
                Configure
            </button>
        </div>

        <!-- Card -->
        <div style="padding: 20px; border: 2px solid var(--gray-200); border-radius: 8px; text-align: center;">
            <div style="font-size: 40px; color: var(--primary-color);">
                <i class="fas fa-credit-card"></i>
            </div>
            <h4 style="font-weight: 600; margin-top: 8px;">Card Payments</h4>
            <p style="font-size: 13px; color: var(--gray-500);">Credit/Debit card payments</p>
            <span style="display: inline-block; margin-top: 8px; padding: 4px 12px; background: #d1fae5; color: #065f46; border-radius: 12px; font-size: 12px;">Active</span>
            <button style="display: block; width: 100%; margin-top: 12px; padding: 8px; background: none; border: 1px solid var(--gray-200); border-radius: 6px; cursor: pointer; font-size: 13px;">
                Configure
            </button>
        </div>

        <!-- Mobile Money -->
        <div style="padding: 20px; border: 2px solid var(--gray-200); border-radius: 8px; text-align: center;">
            <div style="font-size: 40px; color: var(--warning-color);">
                <i class="fas fa-mobile-alt"></i>
            </div>
            <h4 style="font-weight: 600; margin-top: 8px;">Mobile Money</h4>
            <p style="font-size: 13px; color: var(--gray-500);">MTN, Airtel, Tigo, Vodafone</p>
            <span style="display: inline-block; margin-top: 8px; padding: 4px 12px; background: #d1fae5; color: #065f46; border-radius: 12px; font-size: 12px;">Active</span>
            <button style="display: block; width: 100%; margin-top: 12px; padding: 8px; background: none; border: 1px solid var(--gray-200); border-radius: 6px; cursor: pointer; font-size: 13px;">
                Configure
            </button>
        </div>

        <!-- Insurance -->
        <div style="padding: 20px; border: 2px solid var(--gray-200); border-radius: 8px; text-align: center;">
            <div style="font-size: 40px; color: var(--secondary-color);">
                <i class="fas fa-shield-alt"></i>
            </div>
            <h4 style="font-weight: 600; margin-top: 8px;">Insurance</h4>
            <p style="font-size: 13px; color: var(--gray-500);">Insurance claims and billing</p>
            <span style="display: inline-block; margin-top: 8px; padding: 4px 12px; background: #d1fae5; color: #065f46; border-radius: 12px; font-size: 12px;">Active</span>
            <button style="display: block; width: 100%; margin-top: 12px; padding: 8px; background: none; border: 1px solid var(--gray-200); border-radius: 6px; cursor: pointer; font-size: 13px;">
                Configure
            </button>
        </div>
    </div>
</div>

<!-- ============================================
PAYMENT RECONCILIATION
============================================ -->
<?php elseif ($subModule == 'reconcile'): ?>

<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h3 style="font-size: 16px; font-weight: 600; color: var(--gray-900);">Payment Reconciliation</h3>
            <p style="font-size: 13px; color: var(--gray-500);">Reconcile payment transactions</p>
        </div>
        <button style="padding: 10px 20px; background: var(--success-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">
            <i class="fas fa-sync"></i> Run Reconciliation
        </button>
    </div>

    <!-- Reconciliation Stats -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div style="padding: 16px; background: var(--gray-50); border-radius: 8px;">
            <div style="font-size: 12px; color: var(--gray-500);">Total Processed</div>
            <div style="font-size: 24px; font-weight: 700; color: var(--primary-color);">$0</div>
        </div>
        <div style="padding: 16px; background: var(--gray-50); border-radius: 8px;">
            <div style="font-size: 12px; color: var(--gray-500);">Total Reconciled</div>
            <div style="font-size: 24px; font-weight: 700; color: var(--success-color);">$0</div>
        </div>
        <div style="padding: 16px; background: var(--gray-50); border-radius: 8px;">
            <div style="font-size: 12px; color: var(--gray-500);">Discrepancy</div>
            <div style="font-size: 24px; font-weight: 700; color: var(--danger-color);">$0</div>
        </div>
        <div style="padding: 16px; background: var(--gray-50); border-radius: 8px;">
            <div style="font-size: 12px; color: var(--gray-500);">Reconciliation Status</div>
            <div style="font-size: 16px; font-weight: 600; color: var(--success-color);">Fully Reconciled</div>
        </div>
    </div>

    <!-- Reconciliation List -->
    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: var(--gray-50); border-bottom: 2px solid var(--gray-200);">
                    <th style="text-align: left; padding: 12px 16px; font-size: 12px; font-weight: 600; color: var(--gray-500); text-transform: uppercase;">Date</th>
                    <th style="text-align: left; padding: 12px 16px; font-size: 12px; font-weight: 600; color: var(--gray-500); text-transform: uppercase;">Period</th>
                    <th style="text-align: right; padding: 12px 16px; font-size: 12px; font-weight: 600; color: var(--gray-500); text-transform: uppercase;">Total Amount</th>
                    <th style="text-align: right; padding: 12px 16px; font-size: 12px; font-weight: 600; color: var(--gray-500); text-transform: uppercase;">Reconciled</th>
                    <th style="text-align: right; padding: 12px 16px; font-size: 12px; font-weight: 600; color: var(--gray-500); text-transform: uppercase;">Difference</th>
                    <th style="text-align: left; padding: 12px 16px; font-size: 12px; font-weight: 600; color: var(--gray-500); text-transform: uppercase;">Status</th>
                    <th style="text-align: right; padding: 12px 16px; font-size: 12px; font-weight: 600; color: var(--gray-500); text-transform: uppercase;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr style="border-bottom: 1px solid var(--gray-100);">
                    <td style="padding: 12px 16px; color: var(--gray-600);">2024-01-31</td>
                    <td style="padding: 12px 16px; color: var(--gray-600);">January 2024</td>
                    <td style="padding: 12px 16px; text-align: right; font-weight: 500;">$0.00</td>
                    <td style="padding: 12px 16px; text-align: right; font-weight: 500;">$0.00</td>
                    <td style="padding: 12px 16px; text-align: right; font-weight: 500; color: var(--success-color);">$0.00</td>
                    <td style="padding: 12px 16px;">
                        <span style="padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 500; background: #d1fae5; color: #065f46;">Reconciled</span>
                    </td>
                    <td style="padding: 12px 16px; text-align: right;">
                        <button style="padding: 6px 12px; background: none; border: 1px solid var(--gray-200); border-radius: 6px; font-size: 13px; cursor: pointer; color: var(--primary-color);">
                            <i class="fas fa-eye"></i>
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Empty State -->
    <div style="text-align: center; padding: 40px 20px; margin-top: 20px;">
        <div style="font-size: 48px; color: var(--gray-300); margin-bottom: 12px;">
            <i class="fas fa-check-double"></i>
        </div>
        <p style="color: var(--gray-500);">No reconciliation records yet</p>
        <button style="margin-top: 12px; padding: 10px 20px; background: var(--primary-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">
            <i class="fas fa-sync"></i> Run First Reconciliation
        </button>
    </div>
</div>

<?php endif; ?>

<?php
require_once __DIR__ . '/includes/footer.php';
?>