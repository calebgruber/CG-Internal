-- Migration 002: Add phone field to users (if missed in initial schema)
ALTER TABLE users
    MODIFY COLUMN phone VARCHAR(30) DEFAULT NULL AFTER display_name;

-- Add indexes for frequently-queried columns
CREATE INDEX IF NOT EXISTS idx_users_email ON users (email);
CREATE INDEX IF NOT EXISTS idx_users_role  ON users (role);
