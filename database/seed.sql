-- =============================================================
-- Guest Register - Starter Data (invented test data only)
-- Import AFTER schema.sql. Never put real visitor data here.
-- =============================================================

-- First administrator account
-- Login:    admin@guestregister.local
-- Password: ChangeMe@123  (temporary - change it after first login)
INSERT INTO staff (name, email, password_hash, role) VALUES
('System Administrator', 'admin@guestregister.local',
 '$2y$10$yAAKi4Ee2wDrjVPgMkuxHO7ECIUwh5uQ4zqmpgo3P9hKeP76bNYVO', 'admin');

INSERT INTO departments (name) VALUES
('Administration'),
('Human Resources'),
('Finance'),
('Information Technology'),
('Operations'),
('Management Office');

INSERT INTO cards (card_number) VALUES
('V-001'), ('V-002'), ('V-003'), ('V-004'), ('V-005'),
('V-006'), ('V-007'), ('V-008'), ('V-009'), ('V-010');
