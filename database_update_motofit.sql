-- Safe schema/data sync for existing RBJ databases.
-- Compatible with older motofit_db schemas used by this project.

CREATE DATABASE IF NOT EXISTS motofit_db;
USE motofit_db;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL,
    contact_number VARCHAR(30) DEFAULT NULL,
    password VARCHAR(255) NOT NULL,
    is_verified TINYINT(1) NOT NULL DEFAULT 0,
    sms_verified_at DATETIME NULL DEFAULT NULL,
    role ENUM('user', 'admin', 'superadmin') DEFAULT 'user',
    profile_picture VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS site_content (
    content_key VARCHAR(100) PRIMARY KEY,
    content_value TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    customization TEXT,
    status ENUM('pending', 'in_progress', 'completed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_orders_user (user_id),
    CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS feedback (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    feedback TEXT NOT NULL,
    rating INT DEFAULT 5,
    status ENUM('submitted', 'approved', 'rejected') DEFAULT 'submitted',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_feedback_user (user_id),
    CONSTRAINT fk_feedback_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_notifications_user (user_id),
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS favorites (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    customization_name VARCHAR(255) NOT NULL,
    customization_details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_favorites_user (user_id),
    CONSTRAINT fk_favorites_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS reviews (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    order_id INT UNSIGNED NOT NULL,
    rating INT DEFAULT 5,
    review_text TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_reviews_user (user_id),
    INDEX idx_reviews_order (order_id),
    CONSTRAINT fk_reviews_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_reviews_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS support_messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('open', 'in_progress', 'resolved', 'closed') DEFAULT 'open',
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    admin_response TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_support_user (user_id),
    CONSTRAINT fk_support_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS live_chat_messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    sender_role ENUM('user', 'admin') NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    delivered_at TIMESTAMP NULL DEFAULT NULL,
    seen_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_live_chat_user (user_id),
    INDEX idx_live_chat_unread (user_id, sender_role, is_read),
    CONSTRAINT fk_live_chat_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS customization_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    category VARCHAR(100),
    base_price DECIMAL(10,2) DEFAULT 0.00,
    image_path VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS order_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    template_id INT NULL,
    customizations TEXT,
    quantity INT DEFAULT 1,
    price DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_order_items_order (order_id),
    INDEX idx_order_items_template (template_id),
    CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_order_items_template FOREIGN KEY (template_id) REFERENCES customization_templates(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cart (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    template_id INT NOT NULL,
    customizations TEXT,
    quantity INT DEFAULT 1,
    price DECIMAL(10,2) NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cart_user (user_id),
    INDEX idx_cart_template (template_id),
    UNIQUE KEY unique_cart_item (user_id, template_id, customizations(255)),
    CONSTRAINT fk_cart_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_cart_template FOREIGN KEY (template_id) REFERENCES customization_templates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('credit_card', 'paypal', 'bank_transfer', 'cash_on_delivery') DEFAULT 'credit_card',
    transaction_id VARCHAR(255),
    status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_payments_order (order_id),
    INDEX idx_payments_user (user_id),
    CONSTRAINT fk_payments_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_payments_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS payment_proofs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    payment_id INT DEFAULT NULL,
    user_id INT NOT NULL,
    payment_channel VARCHAR(40) NOT NULL,
    reference_number VARCHAR(120) DEFAULT NULL,
    proof_path VARCHAR(255) DEFAULT NULL,
    status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
    admin_notes VARCHAR(255) DEFAULT NULL,
    verified_by INT DEFAULT NULL,
    verified_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_payment_proofs_order (order_id),
    INDEX idx_payment_proofs_user (user_id),
    INDEX idx_payment_proofs_status (status),
    CONSTRAINT fk_payment_proofs_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_payment_proofs_payment FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE SET NULL,
    CONSTRAINT fk_payment_proofs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS shop_vouchers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    voucher_type ENUM('free_shipping', 'fixed_discount') NOT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    min_spend DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    start_at DATETIME NULL DEFAULT NULL,
    end_at DATETIME NULL DEFAULT NULL,
    usage_limit INT NULL DEFAULT NULL,
    used_count INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO shop_vouchers (code, name, voucher_type, amount, min_spend, is_active)
VALUES
('rbj_freeship', 'RBJ Free Shipping', 'free_shipping', 0.00, 0.00, 1),
('rbj_discount_100', 'RBJ Discount 100', 'fixed_discount', 100.00, 0.00, 1);

CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    image_path VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS product_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    is_primary BOOLEAN DEFAULT FALSE,
    alt_text VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_product_images_template (template_id),
    CONSTRAINT fk_product_images_template FOREIGN KEY (template_id) REFERENCES customization_templates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Legacy compatibility updates for older motofit_db layouts.
ALTER TABLE users ADD COLUMN IF NOT EXISTS contact_number VARCHAR(30) DEFAULT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS sms_verified_at DATETIME NULL DEFAULT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS is_verified TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_picture VARCHAR(255) DEFAULT NULL;
ALTER TABLE users MODIFY COLUMN role ENUM('user','admin','superadmin') DEFAULT 'user';

ALTER TABLE orders ADD COLUMN IF NOT EXISTS customization TEXT;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS status ENUM('pending', 'in_progress', 'completed') DEFAULT 'pending';
ALTER TABLE orders MODIFY COLUMN product VARCHAR(100) NULL DEFAULT NULL;
ALTER TABLE orders MODIFY COLUMN status ENUM('pending', 'in-progress', 'in_progress', 'completed') DEFAULT 'pending';
UPDATE orders SET customization = product WHERE customization IS NULL AND product IS NOT NULL;
UPDATE orders SET status = 'in_progress' WHERE status = 'in-progress';
ALTER TABLE orders MODIFY COLUMN status ENUM('pending', 'in_progress', 'completed') DEFAULT 'pending';

ALTER TABLE feedback ADD COLUMN IF NOT EXISTS feedback TEXT;
ALTER TABLE feedback ADD COLUMN IF NOT EXISTS rating INT DEFAULT 5;
ALTER TABLE feedback ADD COLUMN IF NOT EXISTS status ENUM('submitted', 'approved', 'rejected') DEFAULT 'submitted';
ALTER TABLE feedback MODIFY COLUMN message TEXT NULL;
UPDATE feedback SET feedback = message WHERE feedback IS NULL AND message IS NOT NULL;

ALTER TABLE support_messages ADD COLUMN IF NOT EXISTS priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium';
ALTER TABLE live_chat_messages ADD COLUMN IF NOT EXISTS delivered_at TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE live_chat_messages ADD COLUMN IF NOT EXISTS seen_at TIMESTAMP NULL DEFAULT NULL;

-- Core seed data
INSERT IGNORE INTO users (username, email, password, role)
VALUES ('admin', 'admin@motofit.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

INSERT IGNORE INTO site_content (content_key, content_value) VALUES
('hero_title', 'Design Your Dream Motorcycle Seat'),
('hero_subtitle', 'Premium Quality Motorcycle Accessories'),
('hero_description', 'Customize your seat, explore past designs, and see what our customers love.'),
('features_title', 'Why Riders Choose RBJ'),
('features_description', 'Premium materials, precise customization, and reliable support built for daily riders and enthusiasts.'),
('about_title', 'About RBJ Accessories'),
('about_content', 'RBJ Accessories delivers custom motorcycle seat craftsmanship focused on comfort, durability, and design that reflects your style.'),
('contact_title', 'Get In Touch'),
('contact_info', 'Email: info@motofit.com\nPhone: +1 (555) 123-4567\nAddress: 123 Motorcycle Lane, Bike City, BC 12345\nHours: Mon-Fri 9AM-6PM, Sat 10AM-4PM');

-- Add RBJ catalog products only when they are not already present.
INSERT INTO customization_templates (name, description, category, base_price, is_active, created_at)
SELECT p.name, p.description, p.category, p.base_price, p.is_active, NOW()
FROM (
    SELECT 'STELLAR SEAT COVER | UNIVERSAL SEAT COVER' AS name, 'Stellar design motorcycle seat cover perfect for universal fit motorcycles' AS description, 'seat_covers' AS category, 950.00 AS base_price, 1 AS is_active
    UNION ALL SELECT 'FULL SMOKE INDO SEAT COVER | UNIVERSAL SEAT', 'Full smoke design Indo seat cover for universal motorcycle seats', 'seat_covers', 880.00, 1
    UNION ALL SELECT 'PLAIN INDO COVER WITH SPLIT CHECKERED', 'Plain Indo cover with split checkered pattern design', 'seat_covers', 480.00, 1
    UNION ALL SELECT 'CLEAR COVER FOR MOTOR SEAT | UNIVERSAL SEAT', 'Clear protective cover for motor seat with universal fit', 'seat_covers', 250.00, 1
    UNION ALL SELECT 'FULL SMOKE FLAME WITH LINING | INDO SEAT COVER', 'Full smoke flame design with lining for Indo seat', 'seat_covers', 980.00, 1
    UNION ALL SELECT 'BRIDE DARK EDITION SEAT COVER | UNIVERSAL', 'Bride dark edition seat cover for universal motorcycles', 'seat_covers', 650.00, 1
    UNION ALL SELECT 'INFINITY KNOT WITH FLAME INDO CONCEPT', 'Infinity knot design with flame pattern Indo concept', 'seat_covers', 900.00, 1
    UNION ALL SELECT 'INFINITY KNOT X FLAME INDO UNIVERSAL SEAT', 'Infinity knot X flame design for Indo universal seats', 'seat_covers', 850.00, 1
    UNION ALL SELECT 'RACING SEAT SMOKE CARBON', 'Racing seat with smoke carbon finish', 'seat_covers', 3500.00, 1
    UNION ALL SELECT 'INDO SEAT COVER WITH BANDANA EXTENSION', 'Indo seat cover with bandana extension design', 'seat_covers', 650.00, 1
    UNION ALL SELECT 'HALF SMOKE HALF F1 CARBON UNIVERSAL IND', 'Half smoke half F1 carbon design for universal Indo', 'seat_covers', 850.00, 1
    UNION ALL SELECT 'DARUMA VERSION 3 UNIVERSAL SEAT COVER', 'Daruma version 3 design for universal motorcycle seats', 'seat_covers', 850.00, 1
    UNION ALL SELECT 'RACING SEAT INFINITY KNOT WITH FLAME AND', 'Racing seat with infinity knot and flame pattern', 'seat_covers', 850.00, 1
    UNION ALL SELECT 'INDO CONCEPT SEATS | NEW DESIGN', 'Indo concept seats with new modern design', 'seat_covers', 3300.00, 1
    UNION ALL SELECT 'BRAND NEW INDO SEAT ASSEMBLY', 'Brand new Indo seat assembly complete package', 'seat_covers', 3350.00, 1
    UNION ALL SELECT 'INFINITY KNOT WITH SIDE FLAME EXTENSION IND', 'Infinity knot design with side flame extension Indo', 'seat_covers', 785.00, 1
    UNION ALL SELECT 'DARUMA DOLL SEAT COVER | RBJ ACCESSORIES', 'Daruma doll design seat cover RBJ accessories', 'seat_covers', 950.00, 1
    UNION ALL SELECT 'NEW INFINITY KNOT | INDO CONCEPT SEAT COVER', 'New infinity knot design Indo concept seat cover', 'seat_covers', 650.00, 1
    UNION ALL SELECT 'INDO SEAT COVER PLAIN SAND | INDO CONCEPT', 'Indo seat cover plain sand color Indo concept', 'seat_covers', 420.00, 1
    UNION ALL SELECT 'INDO SEAT COVER ORIGINAL 3D', 'Indo seat cover original 3D design', 'seat_covers', 550.00, 1
    UNION ALL SELECT 'INFINITY KNOT WITH PAISLEY EXTENSION', 'Infinity knot design with paisley extension', 'seat_covers', 650.00, 1
    UNION ALL SELECT 'DARUMA UNIVERSAL SEAT COVER', 'Daruma design universal motorcycle seat cover', 'seat_covers', 400.00, 1
    UNION ALL SELECT 'NEW MOTOR SEAT COVER | UNIVERSAL SEAT COVER', 'New motor seat cover with universal fit', 'seat_covers', 880.00, 1
    UNION ALL SELECT 'DARUMA VERSION 2 UNIVERSAL SEAT COVER', 'Daruma version 2 design universal seat cover', 'seat_covers', 950.00, 1
    UNION ALL SELECT 'INDO CONCEPT UNIVERSAL SEAT COVER', 'Indo concept design for universal motorcycle seats', 'seat_covers', 250.00, 1
    UNION ALL SELECT 'YAYAMANIN UNIVERSAL SEAT COVER | RBJ', 'Yayamanin design universal seat cover by RBJ', 'seat_covers', 550.00, 1
    UNION ALL SELECT 'INFINITY KNOT 3D F1 LIHA TEXTURE | INDO SEAT', 'Infinity knot 3D F1 Liha texture Indo seat cover', 'seat_covers', 550.00, 1
    UNION ALL SELECT 'BRAND NEW INDO SEAT | ICPH', 'Brand new Indo seat ICPH model', 'seat_covers', 3350.00, 1
    UNION ALL SELECT 'SKULL PAISLEY SEAT COVER | UNIVERSAL SEAT', 'Skull paisley design universal motorcycle seat cover', 'seat_covers', 750.00, 1
    UNION ALL SELECT 'BRIDE UNIVERSAL SEAT COVER', 'Bride design universal motorcycle seat cover', 'seat_covers', 850.00, 1
) AS p
WHERE NOT EXISTS (
    SELECT 1
    FROM customization_templates t
    WHERE t.name = p.name AND COALESCE(t.category, '') = COALESCE(p.category, '')
);
