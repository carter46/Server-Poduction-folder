-- Global transfer configuration on license_settings (database: u502532383_tranzitest)
-- Prefer relying on PHP globalTransferEnsureColumns(); run these only if needed.
-- Skip any statement that errors with "Duplicate column name".

ALTER TABLE license_settings ADD COLUMN otp_enabled TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE license_settings ADD COLUMN hard_token_enabled TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE license_settings ADD COLUMN hard_token VARCHAR(64) DEFAULT NULL;
ALTER TABLE license_settings ADD COLUMN default_transfer_status ENUM('SUCCESSFUL','PENDING','FAILED') NOT NULL DEFAULT 'SUCCESSFUL';
ALTER TABLE license_settings ADD COLUMN transfer_restriction TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE license_settings ADD COLUMN risky_transaction TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE license_settings ADD COLUMN nin_verification TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE license_settings ADD COLUMN log_status ENUM('full_logs','weak_logs','pending_request','post_no_debit','fixed_account') NOT NULL DEFAULT 'full_logs';
