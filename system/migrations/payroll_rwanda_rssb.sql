-- Rwanda Payroll Migration: Add full RSSB/PAYE breakdown columns
-- Safe version: uses IF NOT EXISTS workaround via information_schema checks

DROP PROCEDURE IF EXISTS AddColumnIfMissing;

DELIMITER $$
CREATE PROCEDURE AddColumnIfMissing(
    IN tbl    VARCHAR(64),
    IN col    VARCHAR(64),
    IN def    TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tbl AND COLUMN_NAME = col
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', tbl, '` ADD COLUMN `', col, '` ', def);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$
DELIMITER ;

-- payroll_records: salary components
CALL AddColumnIfMissing('payroll_records', 'communication_allowance',   'DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER transport_allowance');
CALL AddColumnIfMissing('payroll_records', 'overtime_pay',              'DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER communication_allowance');
CALL AddColumnIfMissing('payroll_records', 'bonus',                     'DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER overtime_pay');
CALL AddColumnIfMissing('payroll_records', 'other_benefits',            'DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER bonus');
CALL AddColumnIfMissing('payroll_records', 'employee_rssb',             'DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER tpr_tax');
CALL AddColumnIfMissing('payroll_records', 'loan_deduction',            'DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER employee_rssb');
CALL AddColumnIfMissing('payroll_records', 'other_deductions',          'DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER loan_deduction');
CALL AddColumnIfMissing('payroll_records', 'employer_rssb_pension',     'DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER other_deductions');
CALL AddColumnIfMissing('payroll_records', 'employer_ohi',              'DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER employer_rssb_pension');
CALL AddColumnIfMissing('payroll_records', 'employer_cost',             'DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER employer_ohi');
CALL AddColumnIfMissing('payroll_records', 'approved_by',               'INT NULL DEFAULT NULL AFTER net_pay');
CALL AddColumnIfMissing('payroll_records', 'approved_at',               'DATETIME NULL DEFAULT NULL AFTER approved_by');
CALL AddColumnIfMissing('payroll_records', 'approval_notes',            'TEXT NULL DEFAULT NULL AFTER approved_at');
CALL AddColumnIfMissing('payroll_records', 'finance_recorded',          'TINYINT(1) NOT NULL DEFAULT 0 AFTER approval_notes');
CALL AddColumnIfMissing('payroll_records', 'finance_recorded_by',       'INT NULL DEFAULT NULL AFTER finance_recorded');
CALL AddColumnIfMissing('payroll_records', 'finance_recorded_at',       'DATETIME NULL DEFAULT NULL AFTER finance_recorded_by');
CALL AddColumnIfMissing('payroll_records', 'finance_reference',         'VARCHAR(80) NULL DEFAULT NULL AFTER finance_recorded_at');

-- employees: salary component defaults
CALL AddColumnIfMissing('employees', 'housing_allowance',       'DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER gross_salary');
CALL AddColumnIfMissing('employees', 'transport_allowance',     'DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER housing_allowance');
CALL AddColumnIfMissing('employees', 'communication_allowance', 'DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER transport_allowance');
CALL AddColumnIfMissing('employees', 'loan_deduction',          'DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER mutuelle_amount');
CALL AddColumnIfMissing('employees', 'other_deductions',        'DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER loan_deduction');

-- Update status ENUM to include 'approved'
ALTER TABLE payroll_records
    MODIFY COLUMN status ENUM('draft','processed','approved','paid') NOT NULL DEFAULT 'draft';

-- payroll_approvals audit table
CREATE TABLE IF NOT EXISTS payroll_approvals (
    id               INT           NOT NULL AUTO_INCREMENT,
    payroll_batch_id VARCHAR(30)   NOT NULL COMMENT 'pay_period YYYY-MM',
    payroll_record_id INT          NULL,
    action           ENUM('submitted','approved','rejected','paid','finance_recorded') NOT NULL,
    actor_id         INT           NULL,
    actor_name       VARCHAR(160)  NULL,
    actor_role       VARCHAR(100)  NULL,
    notes            TEXT          NULL,
    created_at       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_batch  (payroll_batch_id),
    INDEX idx_record (payroll_record_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP PROCEDURE IF EXISTS AddColumnIfMissing;
