INSERT INTO admin_logs (tenant_id, admin_name, activity_type, action_details, username, user_role, log_date, log_time) VALUES
(1, 'Super Admin', 'Tenant Registration', 'Registered clinic Yes', 'amiel', 'superadmin', '2026-03-20', '09:05:00'),
(2, 'Super Admin', 'Tenant Registration', 'Registered clinic Gold''s Best', 'gold', 'superadmin', '2026-03-20', '09:15:00'),
(3, 'Super Admin', 'Tenant Registration', 'Registered clinic MiniACE', 'gabriel', 'superadmin', '2026-03-20', '09:25:00'),
(1, 'Tenant Owner', 'Tenant Login', 'Clinic logged in', 'amiel', 'tenant_owner', '2026-03-20', '10:00:00'),
(1, 'Tenant Owner', 'Tenant Logout', 'Clinic logged out', 'amiel', 'tenant_owner', '2026-03-20', '10:20:00'),
(2, 'Super Admin', 'Tenant Status Change', 'Set status inactive', 'admin', 'superadmin', '2026-03-20', '11:10:00'),
(3, 'Tenant Owner', 'Tenant Login', 'Clinic logged in', 'gabriel', 'tenant_owner', '2026-03-20', '12:25:00'),
(3, 'Tenant Owner', 'Tenant Logout', 'Clinic logged out', 'gabriel', 'tenant_owner', '2026-03-20', '12:45:00'),
(1, 'Super Admin', 'Audit Log View', 'Viewed audit log page', 'amiel', 'superadmin', '2026-03-20', '13:10:00'),
(2, 'Super Admin', 'Audit Log View', 'Viewed audit log page', 'admin', 'superadmin', '2026-03-20', '13:15:00');
