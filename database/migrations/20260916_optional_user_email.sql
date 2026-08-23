-- STD-PRF-01: email is optional while retaining uniqueness for registered values.
ALTER TABLE users MODIFY COLUMN email VARCHAR(190) NULL;
