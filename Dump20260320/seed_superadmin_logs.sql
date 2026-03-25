-- Seed data for superadmin_logs table
INSERT INTO superadmin_logs (activity_type, action_details, username, admin_name, log_date, log_time) VALUES
('Superadmin Login', 'Superadmin logged in', 'admin', 'Super Admin', '2026-03-20', '08:00:00'),
('Tenant Registration', 'Registered new clinic', 'admin', 'Super Admin', '2026-03-20', '09:05:00'),
('Tenant Registration', 'Registered new clinic', 'admin', 'Super Admin', '2026-03-20', '09:15:00'),
('Tenant Registration', 'Registered new clinic', 'admin', 'Super Admin', '2026-03-20', '09:25:00'),
('Tenant Status Change', 'Changed tenant status', 'admin', 'Super Admin', '2026-03-20', '11:10:00'),
('Dashboard Access', 'Accessed superadmin dashboard', 'admin', 'Super Admin', '2026-03-20', '13:10:00'),
('Superadmin Logout', 'Superadmin logged out', 'admin', 'Super Admin', '2026-03-20', '17:30:00');
