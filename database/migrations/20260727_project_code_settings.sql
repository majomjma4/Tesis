INSERT INTO system_settings(setting_key,setting_value) VALUES
('project_code_prefixes','{"thesis":"TIT","thesis_profile":"PFT","pis":"PIS","practice":"PRA","community":"VIN"}'),
('project_code_digits','3')
ON DUPLICATE KEY UPDATE setting_key=VALUES(setting_key);
