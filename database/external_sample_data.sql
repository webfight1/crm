-- Sample data for external companies database
-- This file creates the necessary tables and sample data for testing

USE ettevotted;

-- Create companies table
CREATE TABLE IF NOT EXISTS companies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    registrikood VARCHAR(255),
    phone VARCHAR(255),
    email VARCHAR(255),
    website VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create company_emails table
CREATE TABLE IF NOT EXISTS company_emails (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    email VARCHAR(255) NOT NULL,
    type VARCHAR(50) DEFAULT 'primary',
    is_primary BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
);

-- Create company_phones table
CREATE TABLE IF NOT EXISTS company_phones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    phone VARCHAR(255) NOT NULL,
    type VARCHAR(50) DEFAULT 'primary',
    is_primary BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
);

-- Create company_www table
CREATE TABLE IF NOT EXISTS company_www (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    website VARCHAR(255) NOT NULL,
    type VARCHAR(50) DEFAULT 'primary',
    is_primary BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
);

-- Insert sample companies
INSERT INTO companies (name, registrikood, phone, email, website) VALUES
('AS Tallinna Vesi', '10257326', '+372 606 2200', 'info@tallinnavesi.ee', 'https://tallinnavesi.ee'),
('Eesti Energia AS', '10421629', '+372 715 2222', 'info@energia.ee', 'https://energia.ee'),
('Telia Eesti AS', '10234957', '+372 640 6000', 'info@telia.ee', 'https://telia.ee'),
('Swedbank AS', '10060701', '+372 888 3333', 'info@swedbank.ee', 'https://swedbank.ee'),
('SEB Pank AS', '10004252', '+372 665 5100', 'info@seb.ee', 'https://seb.ee'),
('Maxima Eesti OÜ', '10068799', '+372 605 4400', 'info@maxima.ee', 'https://maxima.ee'),
('Rimi Eesti Food AS', '10263574', '+372 605 4500', 'info@rimi.ee', 'https://rimi.ee'),
('Selver AS', '10393952', '+372 740 5000', 'info@selver.ee', 'https://selver.ee'),
('Bolt Technology OÜ', '14532901', '+372 634 7300', 'support@bolt.eu', 'https://bolt.eu'),
('Skype Technologies OÜ', '10353349', '+372 640 9900', 'info@skype.com', 'https://skype.com');

-- Insert additional emails
INSERT INTO company_emails (company_id, email, type, is_primary) VALUES
(1, 'klienditeenindus@tallinnavesi.ee', 'customer_service', FALSE),
(1, 'press@tallinnavesi.ee', 'press', FALSE),
(2, 'klienditeenindus@energia.ee', 'customer_service', FALSE),
(2, 'press@energia.ee', 'press', FALSE),
(3, 'klienditeenindus@telia.ee', 'customer_service', FALSE),
(4, 'support@swedbank.ee', 'support', FALSE),
(5, 'support@seb.ee', 'support', FALSE);

-- Insert additional phones
INSERT INTO company_phones (company_id, phone, type, is_primary) VALUES
(1, '+372 606 2201', 'customer_service', FALSE),
(2, '+372 715 2223', 'customer_service', FALSE),
(3, '+372 640 6001', 'customer_service', FALSE),
(4, '+372 888 3334', 'customer_service', FALSE),
(5, '+372 665 5101', 'customer_service', FALSE);

-- Insert additional websites
INSERT INTO company_www (company_id, website, type, is_primary) VALUES
(1, 'https://klienditeenindus.tallinnavesi.ee', 'customer_portal', FALSE),
(2, 'https://klienditeenindus.energia.ee', 'customer_portal', FALSE),
(3, 'https://klienditeenindus.telia.ee', 'customer_portal', FALSE),
(4, 'https://internetipank.swedbank.ee', 'online_banking', FALSE),
(5, 'https://internetipank.seb.ee', 'online_banking', FALSE);
