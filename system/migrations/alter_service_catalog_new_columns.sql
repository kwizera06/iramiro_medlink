-- Migration: Redesign service_catalog table
-- Date: 2026-07-13
-- Description: Replace old columns with RHIC, Speciality, ICHI CODE, Procedure name, 
--              Primary/Secondary/Tertiary Health Facility

-- Add new columns
ALTER TABLE service_catalog
    ADD COLUMN rhic VARCHAR(100) NULL AFTER code,
    ADD COLUMN speciality VARCHAR(255) NULL AFTER rhic,
    ADD COLUMN ichi_code VARCHAR(100) NULL AFTER speciality,
    ADD COLUMN procedure_name VARCHAR(500) NULL AFTER ichi_code,
    ADD COLUMN primary_health_facility DECIMAL(12,2) DEFAULT 0.00 AFTER procedure_name,
    ADD COLUMN secondary_health_facility DECIMAL(12,2) DEFAULT 0.00 AFTER primary_health_facility,
    ADD COLUMN tertiary_health_facility DECIMAL(12,2) DEFAULT 0.00 AFTER secondary_health_facility;

-- Add indexes
ALTER TABLE service_catalog ADD INDEX idx_ichi_code (ichi_code);
ALTER TABLE service_catalog ADD INDEX idx_rhic (rhic);

-- Remove old columns
ALTER TABLE service_catalog DROP INDEX department_id;
ALTER TABLE service_catalog DROP INDEX code;
ALTER TABLE service_catalog
    DROP COLUMN name,
    DROP COLUMN category,
    DROP COLUMN department_id,
    DROP COLUMN unit_price,
    DROP COLUMN description,
    DROP COLUMN is_active;
ALTER TABLE service_catalog DROP COLUMN code;

-- Clean old data
DELETE FROM service_catalog WHERE procedure_name IS NULL;
