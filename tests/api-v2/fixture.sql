-- CI fixture: API keys, clients, a tech, tickets, and the extension schema
-- (agreements + time_entries, matching the demo deployment exactly).
-- Test-only values, valid only inside the probe stack. Idempotent.

CREATE TABLE IF NOT EXISTS `agreements` (
  `agreement_id` int(11) NOT NULL AUTO_INCREMENT,
  `agreement_client_id` int(11) NOT NULL,
  `agreement_name` varchar(150) NOT NULL,
  `agreement_type` varchar(60) NOT NULL,
  `agreement_seats` int(11) NOT NULL DEFAULT 0,
  `agreement_mrr` decimal(15,2) NOT NULL,
  `agreement_start` date NOT NULL,
  `agreement_end` date NOT NULL,
  `agreement_status` varchar(20) NOT NULL DEFAULT 'Active',
  `agreement_created_at` datetime NOT NULL,
  PRIMARY KEY (`agreement_id`),
  KEY `idx_ag_client` (`agreement_client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `time_entries` (
  `entry_id` int(11) NOT NULL AUTO_INCREMENT,
  `entry_client_id` int(11) NOT NULL,
  `entry_ticket_id` int(11) DEFAULT NULL,
  `entry_tech_id` int(11) NOT NULL,
  `entry_hours` decimal(6,2) NOT NULL,
  `entry_billable` tinyint(1) NOT NULL DEFAULT 1,
  `entry_rate` decimal(10,2) NOT NULL,
  `entry_date` date NOT NULL,
  `entry_note` varchar(200) DEFAULT NULL,
  `entry_created_at` datetime NOT NULL,
  PRIMARY KEY (`entry_id`),
  KEY `idx_te_client` (`entry_client_id`),
  KEY `idx_te_ticket` (`entry_ticket_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DELETE FROM api_keys WHERE api_key_name LIKE 'ci-%';
DELETE FROM agreements WHERE agreement_id BETWEEN 9401 AND 9499;
DELETE FROM time_entries WHERE entry_id BETWEEN 9501 AND 9599;
DELETE FROM ticket_replies WHERE ticket_reply_ticket_id BETWEEN 9101 AND 9199;
DELETE FROM notifications WHERE notification_client_id IN (101, 102);
DELETE FROM tickets WHERE ticket_id BETWEEN 9101 AND 9199;
DELETE FROM users WHERE user_id = 901;
DELETE FROM contacts WHERE contact_id IN (801, 802);
DELETE FROM assets WHERE asset_id IN (701, 702);
DELETE FROM clients WHERE client_id IN (101, 102);

INSERT INTO api_keys (api_key_name, api_key_secret, api_key_decrypt_hash, api_key_expire, api_key_client_id) VALUES
('ci-all-clients', 'ci-key-allclients-000000000000001', '', '2099-12-31', 0),
('ci-client-101',  'ci-key-client101-0000000000000002', '', '2099-12-31', 101),
('ci-expired',     'ci-key-expired-000000000000000003', '', '2020-01-01', 0);

INSERT INTO clients (client_id, client_name, client_currency_code, client_net_terms) VALUES
(101, 'CI Test Client', 'USD', 30),
(102, 'CI Second Client', 'USD', 30);

INSERT INTO users (user_id, user_name, user_email, user_password) VALUES
(901, 'CI Tech', 'ci-tech@example.test', 'not-a-real-hash');

INSERT INTO contacts (contact_id, contact_name, contact_client_id) VALUES
(801, 'CI Contact A', 101),
(802, 'CI Contact B', 102);

INSERT INTO assets (asset_id, asset_type, asset_name, asset_make, asset_client_id) VALUES
(701, 'Laptop', 'CI-LT-001', 'CI Make', 101),
(702, 'Server', 'CI-SV-001', 'CI Make', 102);

INSERT INTO agreements
(agreement_id, agreement_client_id, agreement_name, agreement_type, agreement_seats,
 agreement_mrr, agreement_start, agreement_end, agreement_status, agreement_created_at) VALUES
(9401, 101, 'CI Managed Services', 'Managed',  25, 1500.00, '2026-01-01', '2026-12-31', 'Active',  NOW()),
(9402, 101, 'CI Legacy Support',   'Support',  10,  400.00, '2025-04-01', '2026-03-31', 'Expired', NOW()),
(9403, 102, 'CI Backup Plan',      'Backup',    5,  250.00, '2025-09-01', '2026-08-15', 'Active',  NOW());

INSERT INTO time_entries
(entry_id, entry_client_id, entry_ticket_id, entry_tech_id, entry_hours,
 entry_billable, entry_rate, entry_date, entry_note, entry_created_at) VALUES
(9501, 101, 9102, 901, 2.50, 1, 150.00, '2026-02-16', 'Tunnel diagnostics', NOW()),
(9502, 101, NULL, 901, 1.00, 0, 0.00,   '2026-01-20', 'Internal maintenance', NOW()),
(9503, 102, 9104, 901, 3.00, 1, 150.00, '2026-03-21', 'Backup job repair', NOW());

INSERT INTO tickets
(ticket_id, ticket_number, ticket_subject, ticket_details, ticket_priority, ticket_status,
 ticket_created_at, ticket_created_by, ticket_assigned_to, ticket_client_id) VALUES
(9101, 9101, 'Printer offline in accounting', 'Front office printer not responding.',      'Medium', 1, '2026-01-10 09:00:00', 1, 0,   101),
(9102, 9102, 'VPN drops daily',               'FortiGate tunnel renegotiates every day.', 'Medium', 2, '2026-02-15 10:30:00', 1, 901, 101),
(9103, 9103, 'Password reset',                'User locked out after vacation.',          'Low',    4, '2026-03-05 08:15:00', 1, 0,   101),
(9104, 9104, 'Server backup failing',         'Nightly job errors with code 0x80070005.', 'Medium', 2, '2026-03-20 22:00:00', 1, 901, 102),
(9105, 9105, 'New laptop setup',              'Onboarding device for new hire.',          'High',   1, '2026-07-01 13:45:00', 1, 0,   102);
