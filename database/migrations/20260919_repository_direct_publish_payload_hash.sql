-- Adds semantic payload fingerprinting for direct Repository idempotency.
ALTER TABLE repository_direct_publish_requests
    ADD COLUMN payload_hash CHAR(64) NOT NULL AFTER request_token;
