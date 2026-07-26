-- CI fixture: API keys + one client. Test-only values, valid only inside the probe stack.

INSERT INTO api_keys (api_key_name, api_key_secret, api_key_decrypt_hash, api_key_expire, api_key_client_id) VALUES
('ci-all-clients', 'ci-key-allclients-000000000000001', '', '2099-12-31', 0),
('ci-client-101',  'ci-key-client101-0000000000000002', '', '2099-12-31', 101),
('ci-expired',     'ci-key-expired-000000000000000003', '', '2020-01-01', 0);

INSERT INTO clients (client_id, client_name, client_currency_code, client_net_terms) VALUES
(101, 'CI Test Client', 'USD', 30);
