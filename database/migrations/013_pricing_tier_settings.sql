-- Precios por tramos en checkout web (Admin → Ajustes → Precios por cantidad)
INSERT INTO settings (key_setting, value_setting, date_created_setting, date_updated_setting)
SELECT 'pricing_tiered_enabled', '1', CURDATE(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE key_setting = 'pricing_tiered_enabled');

INSERT INTO settings (key_setting, value_setting, date_created_setting, date_updated_setting)
SELECT 'pricing_tier1_qty', '2', CURDATE(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE key_setting = 'pricing_tier1_qty');

INSERT INTO settings (key_setting, value_setting, date_created_setting, date_updated_setting)
SELECT 'pricing_tier1_unit', '60000', CURDATE(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE key_setting = 'pricing_tier1_unit');

INSERT INTO settings (key_setting, value_setting, date_created_setting, date_updated_setting)
SELECT 'pricing_tier2_unit', '55000', CURDATE(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE key_setting = 'pricing_tier2_unit');
