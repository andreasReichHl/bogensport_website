-- Review existing users before running this on a non-empty database.
-- If two users share the same email prefix, set unique usernames manually before
-- adding the unique key.

ALTER TABLE users
  ADD COLUMN username VARCHAR(190) NULL AFTER id;

UPDATE users
SET username = LOWER(SUBSTRING_INDEX(email, '@', 1))
WHERE username IS NULL OR username = '';

ALTER TABLE users
  MODIFY username VARCHAR(190) NOT NULL,
  ADD UNIQUE KEY username (username);
