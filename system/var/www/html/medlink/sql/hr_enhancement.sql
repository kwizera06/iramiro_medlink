-- =============================================================
-- HR ENHANCEMENT MODULE - Complete Employee Management Schema
-- =============================================================

-- 1. EMPLOYEE EMERGENCY CONTACTS
CREATE TABLE IF NOT EXISTS employee_emergency_contacts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    contact_name VARCHAR(120) NOT NULL,
    relationship VARCHAR(60) NOT NULL,
    phone VARCHAR(30) NOT NULL,
    alt_phone VARCHAR(30) NULL,
    email VARCHAR(150) NULL,
    address TEXT NULL,
    is_primary TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    INDEX idx_emp_contacts (employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. EMPLOYEE DEPENDENTS
CREATE TABLE IF NOT EXISTS employee_dependents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    relationship VARCHAR(60) NOT NULL,
    date_of_birth DATE NULL,
    gender ENUM('male','female','other') NULL,
    is_disabled TINYINT(1) DEFAULT 0,
    is_student TINYINT(1) DEFAULT 0,
    is_beneficiary TINYINT(1) DEFAULT 1,
    national_id VARCHAR(50) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    INDEX idx_emp_dependents (employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. EMPLOYEE QUALIFICATIONS (Academic)
CREATE TABLE IF NOT EXISTS employee_qualifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    qualification_type VARCHAR(60) NOT NULL COMMENT 'Degree, Diploma, Certificate, etc.',
    qualification_name VARCHAR(200) NOT NULL,
    institution_name VARCHAR(200) NOT NULL,
    country VARCHAR(100) DEFAULT 'Rwanda',
    start_date DATE NULL,
    completion_date DATE NULL,
    graduation_date DATE NULL,
    grade VARCHAR(50) NULL,
    is_highest TINYINT(1) DEFAULT 0,
    document_path VARCHAR(500) NULL,
    verified TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    INDEX idx_emp_quals (employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. EMPLOYEE PROFESSIONAL CERTIFICATIONS / REGISTRATIONS
CREATE TABLE IF NOT EXISTS employee_certifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    certification_name VARCHAR(200) NOT NULL,
    certifying_body VARCHAR(150) NOT NULL,
    certificate_number VARCHAR(100) NULL,
    issue_date DATE NULL,
    expiry_date DATE NULL,
    is_renewable TINYINT(1) DEFAULT 1,
    reminder_days INT DEFAULT 30,
    document_path VARCHAR(500) NULL,
    status ENUM('active','expired','expiring','revoked') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    INDEX idx_emp_certs (employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. EMPLOYEE CONTRACTS
CREATE TABLE IF NOT EXISTS employee_contracts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    contract_number VARCHAR(50) NOT NULL,
    contract_type ENUM('permanent','fixed-term','temporary','internship','volunteer') NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NULL,
    probation_end_date DATE NULL,
    job_title VARCHAR(150) NOT NULL,
    job_grade VARCHAR(50) NULL,
    department_id INT NULL,
    reporting_to INT NULL,
    gross_salary DECIMAL(15,2) DEFAULT 0.00,
    housing_allowance DECIMAL(15,2) DEFAULT 0.00,
    transport_allowance DECIMAL(15,2) DEFAULT 0.00,
    risk_allowance DECIMAL(15,2) DEFAULT 0.00,
    shift_allowance DECIMAL(15,2) DEFAULT 0.00,
    other_allowances DECIMAL(15,2) DEFAULT 0.00,
    work_location VARCHAR(200) NULL,
    working_hours VARCHAR(100) NULL,
    is_active TINYINT(1) DEFAULT 1,
    contract_document VARCHAR(500) NULL,
    signed_by_employee TINYINT(1) DEFAULT 0,
    signed_by_employer TINYINT(1) DEFAULT 0,
    signed_date DATE NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    INDEX idx_emp_contracts (employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. EMPLOYMENT HISTORY
CREATE TABLE IF NOT EXISTS employee_employment_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    employer_name VARCHAR(200) NOT NULL,
    job_title VARCHAR(150) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NULL,
    is_current TINYINT(1) DEFAULT 0,
    monthly_salary DECIMAL(15,2) NULL,
    reason_for_leaving TEXT NULL,
    responsibilities TEXT NULL,
    supervisor_name VARCHAR(120) NULL,
    supervisor_phone VARCHAR(30) NULL,
    can_contact TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    INDEX idx_emp_history (employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. EMPLOYEE BANK DETAILS
CREATE TABLE IF NOT EXISTS employee_bank_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    bank_name VARCHAR(150) NOT NULL,
    branch_name VARCHAR(150) NULL,
    branch_code VARCHAR(20) NULL,
    account_number VARCHAR(50) NOT NULL,
    account_name VARCHAR(150) NOT NULL,
    account_type ENUM('savings','current','salary') DEFAULT 'salary',
    currency VARCHAR(3) DEFAULT 'RWF',
    is_primary TINYINT(1) DEFAULT 1,
    swift_code VARCHAR(20) NULL,
    iban VARCHAR(50) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    INDEX idx_emp_banks (employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. PROFESSIONAL MEMBERSHIPS
CREATE TABLE IF NOT EXISTS employee_professional_memberships (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    organization_name VARCHAR(200) NOT NULL,
    membership_number VARCHAR(80) NULL,
    membership_type VARCHAR(80) NULL COMMENT 'Full, Associate, Fellow, etc.',
    issue_date DATE NULL,
    expiry_date DATE NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    INDEX idx_emp_memberships (employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. CAREER PROGRESSION
CREATE TABLE IF NOT EXISTS employee_career_progression (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    change_type ENUM('promotion','transfer','demotion','role-change','grade-change','secondment') NOT NULL,
    previous_position VARCHAR(150) NULL,
    new_position VARCHAR(150) NOT NULL,
    previous_department_id INT NULL,
    new_department_id INT NULL,
    previous_grade VARCHAR(50) NULL,
    new_grade VARCHAR(50) NULL,
    previous_salary DECIMAL(15,2) NULL,
    new_salary DECIMAL(15,2) NULL,
    effective_date DATE NOT NULL,
    reason TEXT NOT NULL,
    approved_by INT NULL,
    approval_date DATE NULL,
    status ENUM('pending','approved','implemented') DEFAULT 'approved',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    INDEX idx_emp_career (employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================================
-- VACANCY MANAGEMENT
-- =============================================================

-- 10. VACANCY REQUESTS
CREATE TABLE IF NOT EXISTS vacancy_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_number VARCHAR(30) NOT NULL UNIQUE,
    request_date DATE NOT NULL,
    facility VARCHAR(200) DEFAULT 'Main Hospital',
    department_id INT NULL,
    unit_section VARCHAR(150) NULL,
    position_title VARCHAR(200) NOT NULL,
    num_positions INT NOT NULL DEFAULT 1,
    employment_type ENUM('permanent','contract','temporary','volunteer','internship') NOT NULL,
    job_category VARCHAR(100) NULL,
    job_grade VARCHAR(50) NULL,
    reporting_position VARCHAR(150) NULL,
    proposed_start_date DATE NULL,
    justification TEXT NOT NULL,
    budget_available TINYINT(1) DEFAULT 0,
    funding_source VARCHAR(200) NULL,
    required_qualifications TEXT NULL,
    required_experience TEXT NULL,
    required_certifications TEXT NULL,
    required_skills TEXT NULL,
    job_description TEXT NULL,
    job_responsibilities TEXT NULL,
    work_location VARCHAR(200) NULL,
    priority ENUM('low','medium','high','urgent') DEFAULT 'medium',
    status ENUM('draft','submitted','under-review','returned-for-correction','pending-budget','pending-hr','pending-management','approved','rejected','closed','cancelled') DEFAULT 'draft',
    requested_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    INDEX idx_vacancy_status (status),
    INDEX idx_vacancy_date (request_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 11. VACANCY APPROVAL WORKFLOW
CREATE TABLE IF NOT EXISTS vacancy_approvals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vacancy_request_id INT NOT NULL,
    approver_id INT NULL,
    approver_role VARCHAR(100) NOT NULL,
    approval_order INT NOT NULL,
    status ENUM('pending','approved','rejected','returned','delegated') DEFAULT 'pending',
    comments TEXT NULL,
    action_date DATETIME NULL,
    delegated_to INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vacancy_request_id) REFERENCES vacancy_requests(id) ON DELETE CASCADE,
    INDEX idx_vacancy_approval (vacancy_request_id, approval_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 12. VACANCY SUPPORTING DOCUMENTS
CREATE TABLE IF NOT EXISTS vacancy_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vacancy_request_id INT NOT NULL,
    document_name VARCHAR(200) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT NULL,
    uploaded_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vacancy_request_id) REFERENCES vacancy_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================================
-- SHIFT & DUTY ROSTER
-- =============================================================

-- 13. SHIFT TYPES
CREATE TABLE IF NOT EXISTS shift_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) NOT NULL UNIQUE,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    hours DECIMAL(4,2) NOT NULL,
    category ENUM('regular','night','emergency','on-call','weekend','holiday') DEFAULT 'regular',
    allowance_rate DECIMAL(10,2) DEFAULT 0.00,
    requires_relief TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 14. DUTY ROSTERS (weekly/monthly schedules)
CREATE TABLE IF NOT EXISTS duty_rosters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    roster_name VARCHAR(150) NOT NULL,
    department_id INT NULL,
    unit_name VARCHAR(150) NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    roster_type ENUM('weekly','biweekly','monthly','rotation','custom') DEFAULT 'monthly',
    status ENUM('draft','published','active','completed') DEFAULT 'draft',
    created_by INT NULL,
    published_date DATETIME NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 15. ROSTER SHIFT ASSIGNMENTS
CREATE TABLE IF NOT EXISTS roster_shift_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    roster_id INT NOT NULL,
    employee_id INT NOT NULL,
    shift_type_id INT NOT NULL,
    shift_date DATE NOT NULL,
    start_time TIME NULL,
    end_time TIME NULL,
    location VARCHAR(200) NULL COMMENT 'ICU, Theatre, Ward, etc.',
    is_overtime TINYINT(1) DEFAULT 0,
    is_swap TINYINT(1) DEFAULT 0,
    swapped_with INT NULL,
    status ENUM('scheduled','confirmed','completed','absent','cancelled','swapped') DEFAULT 'scheduled',
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (roster_id) REFERENCES duty_rosters(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (shift_type_id) REFERENCES shift_types(id) ON DELETE CASCADE,
    INDEX idx_roster_assign (roster_id, shift_date),
    INDEX idx_emp_shifts (employee_id, shift_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================================
-- TRAINING & PROFESSIONAL DEVELOPMENT
-- =============================================================

-- 16. TRAINING PROGRAMS
CREATE TABLE IF NOT EXISTS training_programs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT NULL,
    training_type ENUM('workshop','seminar','course','online','certification','conference','cpd','in-house') NOT NULL,
    category VARCHAR(100) NULL,
    provider_name VARCHAR(200) NULL,
    venue VARCHAR(200) NULL,
    start_date DATE NULL,
    end_date DATE NULL,
    duration_hours DECIMAL(6,1) NULL,
    credits_hours DECIMAL(5,1) NULL COMMENT 'CPD points',
    cost DECIMAL(15,2) DEFAULT 0.00,
    funding_source VARCHAR(200) NULL,
    max_participants INT NULL,
    status ENUM('planned','open','ongoing','completed','cancelled') DEFAULT 'planned',
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 17. TRAINING ENROLLMENTS
CREATE TABLE IF NOT EXISTS training_enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    training_id INT NOT NULL,
    employee_id INT NOT NULL,
    enrollment_date DATE NOT NULL,
    completion_date DATE NULL,
    status ENUM('enrolled','in-progress','completed','dropped','no-show') DEFAULT 'enrolled',
    score DECIMAL(5,2) NULL,
    feedback TEXT NULL,
    evaluation_score DECIMAL(5,2) NULL,
    certificate_issued TINYINT(1) DEFAULT 0,
    certificate_path VARCHAR(500) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (training_id) REFERENCES training_programs(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    INDEX idx_training_emp (employee_id),
    INDEX idx_training_prog (training_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================================
-- DISCIPLINARY MANAGEMENT
-- =============================================================

-- 18. DISCIPLINARY CASES
CREATE TABLE IF NOT EXISTS disciplinary_cases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_number VARCHAR(30) NOT NULL UNIQUE,
    employee_id INT NOT NULL,
    incident_date DATE NULL,
    reported_by INT NULL,
    incident_type ENUM('misconduct','absenteeism','insubordination','negligence','fraud','harassment','theft','violation-of-policy','other') NOT NULL,
    description TEXT NOT NULL,
    severity ENUM('minor','moderate','serious','grave') DEFAULT 'moderate',
    status ENUM('reported','investigating','hearing-scheduled','hearing-completed','appeal','resolved','closed') DEFAULT 'reported',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    INDEX idx_disc_emp (employee_id),
    INDEX idx_disc_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 19. DISCIPLINARY ACTIONS
CREATE TABLE IF NOT EXISTS disciplinary_actions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    action_type ENUM('verbal-warning','written-warning','final-warning','suspension','salary-deduction','demotion','termination','reinstatement','other') NOT NULL,
    action_date DATE NOT NULL,
    description TEXT NOT NULL,
    duration_days INT NULL COMMENT 'For suspensions',
    amount DECIMAL(15,2) NULL COMMENT 'For salary deductions',
    issued_by INT NULL,
    documents_path TEXT NULL,
    status ENUM('pending','issued','appealed','enforced','overturned') DEFAULT 'issued',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES disciplinary_cases(id) ON DELETE CASCADE,
    INDEX idx_disc_action (case_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 20. DISCIPLINARY HEARINGS
CREATE TABLE IF NOT EXISTS disciplinary_hearings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    hearing_date DATETIME NOT NULL,
    venue VARCHAR(200) NULL,
    panel_members TEXT NULL,
    employee_representation TEXT NULL,
    minutes TEXT NULL,
    outcome TEXT NULL,
    decision ENUM('no-case','warning','suspension','demotion','termination','other') NULL,
    status ENUM('scheduled','held','adjourned','cancelled') DEFAULT 'scheduled',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES disciplinary_cases(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================================
-- COMMUNICATION MANAGEMENT
-- =============================================================

-- 21. ANNOUNCEMENTS
CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    announcement_type ENUM('general','urgent','emergency','departmental','event','policy') DEFAULT 'general',
    priority ENUM('low','normal','high','critical') DEFAULT 'normal',
    target_department_id INT NULL,
    target_role VARCHAR(100) NULL,
    posted_by INT NULL,
    start_date DATETIME NULL,
    end_date DATETIME NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (target_department_id) REFERENCES departments(id) ON DELETE SET NULL,
    INDEX idx_announce_active (is_active, start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 22. INTERNAL MESSAGES
CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    subject VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    priority ENUM('low','normal','high','urgent') DEFAULT 'normal',
    channel ENUM('internal','email','sms','push') DEFAULT 'internal',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS message_recipients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    employee_id INT NULL,
    department_id INT NULL,
    is_read TINYINT(1) DEFAULT 0,
    read_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    INDEX idx_msg_recipient (employee_id, is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 23. SURVEYS
CREATE TABLE IF NOT EXISTS surveys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT NULL,
    target_department_id INT NULL,
    start_date DATETIME NULL,
    end_date DATETIME NULL,
    is_anonymous TINYINT(1) DEFAULT 0,
    status ENUM('draft','open','closed') DEFAULT 'draft',
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS survey_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    survey_id INT NOT NULL,
    question_text TEXT NOT NULL,
    question_type ENUM('text','rating','multiple-choice','checkbox','yes-no') NOT NULL,
    options JSON NULL,
    is_required TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0,
    FOREIGN KEY (survey_id) REFERENCES surveys(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS survey_responses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    survey_id INT NOT NULL,
    question_id INT NOT NULL,
    employee_id INT NULL,
    response_text TEXT NULL,
    response_value INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (survey_id) REFERENCES surveys(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES survey_questions(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================================
-- TASK & PERFORMANCE MANAGEMENT EXTENSION
-- =============================================================

-- 24. EMPLOYEE GOALS (Yearly/Quarterly)
CREATE TABLE IF NOT EXISTS employee_goals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    goal_title VARCHAR(200) NOT NULL,
    description TEXT NULL,
    goal_type ENUM('annual','quarterly','monthly','project') DEFAULT 'annual',
    kpi_measure VARCHAR(255) NULL,
    target_value VARCHAR(100) NULL,
    weight DECIMAL(5,2) DEFAULT 0 COMMENT 'Percentage weight',
    start_date DATE NULL,
    target_date DATE NULL,
    status ENUM('not-started','in-progress','achieved','not-achieved','cancelled') DEFAULT 'not-started',
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    INDEX idx_emp_goals (employee_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 25. EMPLOYEE TASKS
CREATE TABLE IF NOT EXISTS employee_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    task_name VARCHAR(200) NOT NULL,
    description TEXT NULL,
    priority ENUM('low','medium','high','critical') DEFAULT 'medium',
    due_date DATE NULL,
    assigned_by INT NULL,
    status ENUM('pending','in-progress','completed','overdue','cancelled') DEFAULT 'pending',
    completion_date DATETIME NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    INDEX idx_emp_tasks (employee_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 26. PERFORMANCE IMPROVEMENT PLANS (PIP)
CREATE TABLE IF NOT EXISTS performance_improvement_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    review_period VARCHAR(30) NOT NULL,
    issue_area TEXT NOT NULL,
    improvement_goals TEXT NOT NULL,
    action_steps TEXT NOT NULL,
    support_provided TEXT NULL,
    review_interval ENUM('weekly','biweekly','monthly','quarterly') DEFAULT 'monthly',
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('active','completed','extended','terminated') DEFAULT 'active',
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    INDEX idx_pip_emp (employee_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default shift types
INSERT IGNORE INTO shift_types (name, code, start_time, end_time, hours, category, allowance_rate) VALUES
('Morning Shift', 'MORNING', '07:00', '15:00', 8.00, 'regular', 0),
('Afternoon Shift', 'AFTERNOON', '15:00', '23:00', 8.00, 'regular', 0),
('Night Shift', 'NIGHT', '23:00', '07:00', 8.00, 'night', 15000),
('Emergency Day', 'EMER-DAY', '08:00', '20:00', 12.00, 'emergency', 20000),
('Emergency Night', 'EMER-NIGHT', '20:00', '08:00', 12.00, 'emergency', 30000),
('ICU Morning', 'ICU-MORN', '07:00', '15:00', 8.00, 'regular', 5000),
('ICU Night', 'ICU-NIGHT', '23:00', '07:00', 8.00, 'night', 20000),
('On-Call 24h', 'ONCALL-24', '07:00', '07:00', 24.00, 'on-call', 25000),
('Weekend Morning', 'WKND-MORN', '07:00', '15:00', 8.00, 'weekend', 10000),
('Weekend Night', 'WKND-NIGHT', '23:00', '07:00', 8.00, 'weekend', 20000),
('Holiday Duty', 'HOLIDAY', '08:00', '16:00', 8.00, 'holiday', 25000),
('Theatre Morning', 'THEATRE-M', '06:30', '14:30', 8.00, 'regular', 8000),
('Theatre On-Call', 'THEATRE-OC', '14:30', '06:30', 16.00, 'on-call', 25000);

-- Insert default leave types if not exist
INSERT IGNORE INTO leave_types (name, code, days_allowed, is_paid, carry_forward, description) VALUES
('Annual Leave', 'ANNUAL', 24, 1, 0, 'Regular annual leave entitlement'),
('Sick Leave', 'SICK', 30, 1, 0, 'Medical leave with doctor note required'),
('Maternity Leave', 'MATERNITY', 90, 1, 0, 'Maternity leave for female employees'),
('Paternity Leave', 'PATERNITY', 4, 1, 0, 'Paternity leave for male employees'),
('Compassionate Leave', 'COMPASSION', 5, 1, 0, 'Bereavement and family emergency leave'),
('Study Leave', 'STUDY', 30, 0, 0, 'Educational leave for professional development'),
('Unpaid Leave', 'UNPAID', 30, 0, 0, 'Leave without pay'),
('Sabbatical', 'SABBATICAL', 365, 0, 0, 'Extended leave for research or personal development');
EOF
echo "Schema file created at: /var/www/html/medlink/sql/hr_enhancement.sql"