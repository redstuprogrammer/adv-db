-- Seed data for tenant_activity_logs table (privacy-safe: no names or personal details)
INSERT INTO tenant_activity_logs (tenant_id, activity_type, activity_description, activity_count, log_date, log_time) VALUES
(1, 'Patient Created', 'New patient added to records', 1, '2026-03-20', '10:05:00'),
(1, 'Appointment Scheduled', 'Appointment created in system', 1, '2026-03-20', '10:15:00'),
(1, 'Payment Received', 'Payment transaction processed', 1, '2026-03-20', '10:45:00'),
(1, 'Staff Member Added', 'New staff member registered', 1, '2026-03-20', '11:00:00'),
(2, 'Patient Created', 'New patient added to records', 2, '2026-03-20', '11:30:00'),
(2, 'Appointment Scheduled', 'Appointment created in system', 1, '2026-03-20', '12:00:00'),
(3, 'Patient Created', 'New patient added to records', 1, '2026-03-20', '13:15:00'),
(3, 'Appointment Scheduled', 'Appointment created in system', 3, '2026-03-20', '13:45:00'),
(1, 'Clinical Notes Added', 'Clinical notes recorded', 1, '2026-03-20', '14:10:00'),
(2, 'Payment Received', 'Payment transaction processed', 2, '2026-03-20', '14:30:00');
