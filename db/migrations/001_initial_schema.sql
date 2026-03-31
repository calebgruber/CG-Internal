-- Migration 001: Initial schema (already in db/schema.sql)
-- This file exists so the migrations tracker records it.
-- If you ran setup.php, this was already applied.
-- Running this migration again is safe (uses IF NOT EXISTS / INSERT IGNORE).

INSERT IGNORE INTO migrations (name) VALUES ('001_initial_schema');
