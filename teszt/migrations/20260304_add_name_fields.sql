-- Adatbázis módosítás (2026-03-04): Vezetéknév és Keresztnév mezők hozzáadása

USE szivhang_db;

-- 1. Új oszlopok hozzáadása a nickname után
ALTER TABLE users 
ADD COLUMN last_name VARCHAR(50) NOT NULL AFTER nickname,
ADD COLUMN first_name VARCHAR(50) NOT NULL AFTER last_name;

-- 2. Lecseréljük a meglévő, nem használt full_name mezőt, ha volt ilyen
ALTER TABLE users
DROP COLUMN full_name;

