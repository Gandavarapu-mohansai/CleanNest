create database cleannest_db;

use cleannest_db;

# user table

CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(15),
    role VARCHAR(20) DEFAULT 'customer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

DESCRIBE users;

# services table

CREATE TABLE services (
    service_id INT AUTO_INCREMENT PRIMARY KEY,
    service_name VARCHAR(100) NOT NULL,
    description VARCHAR(500),
    price DECIMAL(10,2) NOT NULL,
    status VARCHAR(20) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

DESCRIBE services;

#bhk_packages table

CREATE TABLE bhk_packages (
    package_id INT AUTO_INCREMENT PRIMARY KEY,
    package_name VARCHAR(50) NOT NULL,
    bhk_type INT NOT NULL UNIQUE,
    price DECIMAL(10,2) NOT NULL,
    description VARCHAR(500),
    status VARCHAR(20) DEFAULT 'active'
);

DESCRIBE bhk_packages;

#package_services table

CREATE TABLE package_services (
    package_service_id INT AUTO_INCREMENT PRIMARY KEY,
    package_id INT NOT NULL,
    service_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,

    FOREIGN KEY (package_id)
        REFERENCES bhk_packages(package_id),

    FOREIGN KEY (service_id)
        REFERENCES services(service_id)
);

DESCRIBE package_services;

#booking table

CREATE TABLE bookings (
    booking_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    package_id INT NULL,
    booking_date DATE NOT NULL,
    booking_time TIME NOT NULL,
    address VARCHAR(500) NOT NULL,
    home_size VARCHAR(20),
    total_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status VARCHAR(30) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (package_id) REFERENCES bhk_packages(package_id)
);


DESCRIBE bookings;

-- Payments table
CREATE TABLE payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    payment_method VARCHAR(30) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_status VARCHAR(30) DEFAULT 'pending',
    transaction_reference VARCHAR(100) DEFAULT NULL,
    payment_date DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    rejection_reason VARCHAR(255) DEFAULT NULL,

    FOREIGN KEY (booking_id)
        REFERENCES bookings(booking_id)
);

-- Contact messages table
CREATE TABLE contact_messages (
    message_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    mobile VARCHAR(15) NOT NULL,
    subject VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    admin_reply TEXT,
    reply_status VARCHAR(20) DEFAULT 'Pending',
    replied_at DATETIME DEFAULT NULL
);

-- Reviews table
CREATE TABLE reviews (
    review_id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    rating INT NOT NULL,
    feedback TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY (booking_id),

    FOREIGN KEY (booking_id)
        REFERENCES bookings(booking_id)
);

#booking_service table

CREATE TABLE booking_services (
    booking_service_id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    service_id INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    quantity INT NOT NULL DEFAULT 1,

    FOREIGN KEY (booking_id)
        REFERENCES bookings(booking_id),

    FOREIGN KEY (service_id)
        REFERENCES services(service_id)
);

DESCRIBE booking_services;

show tables;

INSERT INTO services (service_name, description, price)
VALUES
('Bathroom Cleaning', 'Deep cleaning of bathrooms', 800),
('Kitchen Cleaning', 'Cleaning of kitchen surfaces and appliances', 1200),
('Bedroom Cleaning', 'Complete bedroom cleaning', 700),
('Hall / Living Room Cleaning', 'Complete cleaning of hall and living room', 700),
('Dining Room Cleaning', 'Cleaning of dining table, chairs and floor', 500),
('Balcony Cleaning', 'Cleaning of balcony floors and railings', 400),
('Pooja Room Cleaning', 'Cleaning of pooja room and shelves', 300),
('Study Room Cleaning', 'Cleaning of study room, furniture and shelves', 400),
('Windows & Glass Cleaning', 'Cleaning of windows and glass doors', 500),
('Terrace Cleaning', 'Cleaning of terrace floors and surrounding areas', 1500),
('AC & Fan Cleaning', 'Cleaning of air conditioners and ceiling fans', 750),
('Laundry & Ironing', 'Laundry and ironing services', 80);

Select * from services;

INSERT INTO bhk_packages
(package_name, bhk_type, price, description)
VALUES
('1 BHK Cleaning', 1, 1500, 'Includes 1 bedroom, kitchen, hall and 1 bathroom'),
('2 BHK Cleaning', 2, 2500, 'Includes 2 bedrooms, kitchen, hall and 2 bathrooms'),
('3 BHK Cleaning', 3, 3500, 'Includes 3 bedrooms, kitchen, hall and 3 bathrooms'),
('4 BHK Cleaning', 4, 4500, 'Includes 4 bedrooms, kitchen, hall and 4 bathrooms');

SELECT * FROM bhk_packages;

USE CLEANNEST_DB;

INSERT INTO package_services
(package_id, service_id, quantity)
VALUES
(1, 3, 1),
(1, 2, 1),
(1, 4, 1),
(1, 1, 1),

(2, 3, 2),
(2, 2, 1),
(2, 4, 1),
(2, 1, 2),

(3, 3, 3),
(3, 2, 1),
(3, 4, 1),
(3, 1, 3),

(4, 3, 4),
(4, 2, 1),
(4, 4, 1),
(4, 1, 4);

SELECT * FROM package_services;

CREATE TABLE password_resets (
    reset_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    reset_token VARCHAR(64) NOT NULL,
    expiry_time DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON DELETE CASCADE
);






 

 
