-- Separate database for the test suite so tests run against real MySQL 8 (row locks matter).
CREATE DATABASE IF NOT EXISTS rental_testing;
GRANT ALL PRIVILEGES ON rental_testing.* TO 'rental'@'%';
