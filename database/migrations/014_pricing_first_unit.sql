INSERT INTO settings (key_setting, value_setting, date_created_setting, date_updated_setting)
SELECT 'pricing_first_unit', '65000', CURDATE(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE key_setting = 'pricing_first_unit');

UPDATE settings SET value_setting = '60000'
WHERE key_setting = 'pricing_tier1_unit' AND value_setting IN ('60000', '65000');

UPDATE settings SET value_setting = '55000'
WHERE key_setting = 'pricing_tier2_unit';
