-- =============================================================
-- Guest Register - Database Schema (structure only, no data)
-- Database: guest_register (utf8mb4)
--
-- WARNING: Importing this file DELETES all existing tables and
-- their data in guest_register. Use it to set up or reset a
-- development database only. Import seed.sql afterwards.
-- =============================================================

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS card_assignments;
DROP TABLE IF EXISTS cards;
DROP TABLE IF EXISTS visits;
DROP TABLE IF EXISTS visitors;
DROP TABLE IF EXISTS departments;
DROP TABLE IF EXISTS staff;
SET FOREIGN_KEY_CHECKS = 1;

-- -------------------------------------------------------------
-- Staff accounts (FR-01, FR-02, FR-12)
-- -------------------------------------------------------------
CREATE TABLE staff (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name          VARCHAR(100) NOT NULL,
    email         VARCHAR(150) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role          ENUM('admin', 'receptionist', 'security') NOT NULL,
    is_active     TINYINT(1)   NOT NULL DEFAULT 1,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_staff_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -------------------------------------------------------------
-- Departments / hosts being visited (FR-03, FR-08, FR-11)
-- -------------------------------------------------------------
CREATE TABLE departments (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name       VARCHAR(100) NOT NULL,
    is_active  TINYINT(1)   NOT NULL DEFAULT 1,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_departments_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -------------------------------------------------------------
-- Visitors: one person, many visits over time (PRD 11.1)
-- -------------------------------------------------------------
CREATE TABLE visitors (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name      VARCHAR(150) NOT NULL,
    organization   VARCHAR(150) NULL,
    contact_number VARCHAR(20)  NULL,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_visitors_name (full_name),
    KEY idx_visitors_contact (contact_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -------------------------------------------------------------
-- Visits: each entry into the building (FR-03 to FR-09)
-- Active visit    = status 'active',    check_out_at NULL
-- Completed visit = status 'completed', check_out_at set
-- -------------------------------------------------------------
CREATE TABLE visits (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    reference      VARCHAR(20)  NOT NULL,
    visitor_id     INT UNSIGNED NOT NULL,
    department_id  INT UNSIGNED NOT NULL,
    host_name      VARCHAR(100) NULL,
    purpose        VARCHAR(255) NOT NULL,
    check_in_at    DATETIME     NOT NULL,
    check_out_at   DATETIME     NULL,
    status         ENUM('active', 'completed') NOT NULL DEFAULT 'active',
    registered_by  INT UNSIGNED NOT NULL,
    checked_out_by INT UNSIGNED NULL,
    notes          VARCHAR(500) NULL,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_visits_reference (reference),
    KEY idx_visits_status (status),
    KEY idx_visits_check_in (check_in_at),
    CONSTRAINT fk_visits_visitor      FOREIGN KEY (visitor_id)     REFERENCES visitors (id),
    CONSTRAINT fk_visits_department   FOREIGN KEY (department_id)  REFERENCES departments (id),
    CONSTRAINT fk_visits_registered   FOREIGN KEY (registered_by)  REFERENCES staff (id),
    CONSTRAINT fk_visits_checked_out  FOREIGN KEY (checked_out_by) REFERENCES staff (id),
    -- Business rule 5: check-out cannot be earlier than check-in
    CONSTRAINT chk_visits_time_order CHECK (check_out_at IS NULL OR check_out_at >= check_in_at),
    -- Status must match whether the visitor has checked out
    CONSTRAINT chk_visits_status CHECK (
        (status = 'active'    AND check_out_at IS NULL) OR
        (status = 'completed' AND check_out_at IS NOT NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -------------------------------------------------------------
-- Visitor ID cards (FR-05, FR-06, FR-10)
-- current_visit_id is UNIQUE, so one card can never be linked
-- to two visits at the same time (business rule 6).
-- -------------------------------------------------------------
CREATE TABLE cards (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    card_number      VARCHAR(20)  NOT NULL,
    status           ENUM('available', 'issued', 'lost', 'inactive') NOT NULL DEFAULT 'available',
    current_visit_id INT UNSIGNED NULL,
    updated_by       INT UNSIGNED NULL,
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_cards_number (card_number),
    UNIQUE KEY uq_cards_current_visit (current_visit_id),
    CONSTRAINT fk_cards_current_visit FOREIGN KEY (current_visit_id) REFERENCES visits (id),
    CONSTRAINT fk_cards_updated_by    FOREIGN KEY (updated_by)       REFERENCES staff (id),
    -- An issued card must point to a visit; any other card must not
    CONSTRAINT chk_cards_issued CHECK (
        (status = 'issued'  AND current_visit_id IS NOT NULL) OR
        (status <> 'issued' AND current_visit_id IS NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -------------------------------------------------------------
-- Card history: which card was given to which visit (FR-05, FR-06)
-- One card per visit; a card appears in many rows over time.
-- -------------------------------------------------------------
CREATE TABLE card_assignments (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    visit_id      INT UNSIGNED NOT NULL,
    card_id       INT UNSIGNED NOT NULL,
    issued_at     DATETIME     NOT NULL,
    issued_by     INT UNSIGNED NOT NULL,
    returned_at   DATETIME     NULL,
    return_status ENUM('returned', 'lost') NULL,
    received_by   INT UNSIGNED NULL,
    note          VARCHAR(255) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_assignments_visit (visit_id),
    KEY idx_assignments_card (card_id),
    CONSTRAINT fk_assignments_visit       FOREIGN KEY (visit_id)    REFERENCES visits (id),
    CONSTRAINT fk_assignments_card        FOREIGN KEY (card_id)     REFERENCES cards (id),
    CONSTRAINT fk_assignments_issued_by   FOREIGN KEY (issued_by)   REFERENCES staff (id),
    CONSTRAINT fk_assignments_received_by FOREIGN KEY (received_by) REFERENCES staff (id),
    CONSTRAINT chk_assignments_time_order CHECK (returned_at IS NULL OR returned_at >= issued_at),
    -- A return time and a return status are recorded together
    CONSTRAINT chk_assignments_return CHECK (
        (returned_at IS NULL     AND return_status IS NULL) OR
        (returned_at IS NOT NULL AND return_status IS NOT NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
