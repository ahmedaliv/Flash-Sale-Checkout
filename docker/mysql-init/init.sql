DROP USER IF EXISTS 'user'@'%';
CREATE DATABASE IF NOT EXISTS flash_sale;
CREATE DATABASE IF NOT EXISTS flash_sale_test;

CREATE USER 'user'@'%' IDENTIFIED BY 'secret';
GRANT ALL PRIVILEGES ON flash_sale.* TO 'user'@'%';
GRANT ALL PRIVILEGES ON flash_sale_test.* TO 'user'@'%';
FLUSH PRIVILEGES;