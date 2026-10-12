-- READ ONLY: run in the teammate's existing Alona database.
SELECT DATABASE() AS selected_database, VERSION() AS mysql_version;
SHOW TABLES;
SHOW CREATE TABLE users;
SHOW CREATE TABLE areas;
SHOW CREATE TABLE migrations;
SELECT migration, batch FROM migrations WHERE migration IN ('2026_10_12_000001_create_partner_applications_table', '2026_10_12_000002_add_city_codes_to_service_areas', '2026_10_12_000003_add_address_geography_to_users') ORDER BY migration;
SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND ((TABLE_NAME = 'users' AND COLUMN_NAME IN ('id','address_geography','rejection_reason')) OR (TABLE_NAME = 'areas' AND COLUMN_NAME = 'city_code') OR TABLE_NAME = 'partner_applications');
-- Manual SQL is only for a baseline with users, areas and migrations already present,
-- none of these three migration entries, no partner_applications table,
-- no areas.city_code, and no users.address_geography.
