-- CI fixture: API keys, clients, a tech, and tickets. Test-only values,
-- valid only inside the probe stack. Idempotent: deletes its own rows first.

DELETE FROM api_keys WHERE api_key_name LIKE 'ci-%';
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

INSERT INTO tickets
(ticket_id, ticket_number, ticket_subject, ticket_details, ticket_priority, ticket_status,
 ticket_created_at, ticket_created_by, ticket_assigned_to, ticket_client_id) VALUES
(9101, 9101, 'Printer offline in accounting', 'Front office printer not responding.',      'Medium', 1, '2026-01-10 09:00:00', 1, 0,   101),
(9102, 9102, 'VPN drops daily',               'FortiGate tunnel renegotiates every day.', 'Medium', 2, '2026-02-15 10:30:00', 1, 901, 101),
(9103, 9103, 'Password reset',                'User locked out after vacation.',          'Low',    4, '2026-03-05 08:15:00', 1, 0,   101),
(9104, 9104, 'Server backup failing',         'Nightly job errors with code 0x80070005.', 'Medium', 2, '2026-03-20 22:00:00', 1, 901, 102),
(9105, 9105, 'New laptop setup',              'Onboarding device for new hire.',          'High',   1, '2026-07-01 13:45:00', 1, 0,   102);
