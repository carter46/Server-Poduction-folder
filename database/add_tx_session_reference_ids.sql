-- Session ID + Reference ID for bank transfer receipts.
-- Run on PRODUCTION before relying on persisted IDs.
-- Columns are nullable. PHP will not INSERT them until this has been applied.
-- Safe to re-run: each statement is ignored if the column already exists (MySQL 8.0+
-- does not support IF NOT EXISTS on ADD COLUMN in all versions, so verify after).

ALTER TABLE `uba_transactions`
  ADD COLUMN `session_id` VARCHAR(64) NULL AFTER `reference`,
  ADD COLUMN `reference_id` VARCHAR(64) NULL AFTER `session_id`;

ALTER TABLE `first_bank_transactions`
  ADD COLUMN `session_id` VARCHAR(64) NULL AFTER `reference`,
  ADD COLUMN `reference_id` VARCHAR(64) NULL AFTER `session_id`;

ALTER TABLE `zenith_bank_transactions`
  ADD COLUMN `session_id` VARCHAR(64) NULL AFTER `reference`,
  ADD COLUMN `reference_id` VARCHAR(64) NULL AFTER `session_id`;

ALTER TABLE `access_bank_transactions`
  ADD COLUMN `session_id` VARCHAR(64) NULL AFTER `reference`,
  ADD COLUMN `reference_id` VARCHAR(64) NULL AFTER `session_id`;

-- Verify:
-- SELECT table_name, column_name
-- FROM information_schema.columns
-- WHERE table_schema = DATABASE()
--   AND table_name IN (
--     'uba_transactions','first_bank_transactions',
--     'zenith_bank_transactions','access_bank_transactions'
--   )
--   AND column_name IN ('session_id','reference_id')
-- ORDER BY table_name, column_name;
