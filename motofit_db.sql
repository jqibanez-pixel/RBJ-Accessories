-- Database: motofit_db
-- Create database
CREATE DATABASE IF NOT EXISTS motofit_db;
USE motofit_db;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    contact_number VARCHAR(30) DEFAULT NULL,
    password VARCHAR(255) NOT NULL,
    is_verified TINYINT(1) NOT NULL DEFAULT 0,
    sms_verified_at DATETIME NULL DEFAULT NULL,
    role ENUM('user', 'admin') DEFAULT 'user',
    profile_picture VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Site content table (for admin-managed homepage/about/contact content)
CREATE TABLE IF NOT EXISTS site_content (
    content_key VARCHAR(100) PRIMARY KEY,
    content_value TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Orders table
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    customization TEXT,
    status ENUM('pending', 'in_progress', 'completed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Feedback table
CREATE TABLE IF NOT EXISTS feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    feedback TEXT NOT NULL,
    rating INT CHECK (rating >= 1 AND rating <= 5),
    status ENUM('submitted', 'approved', 'rejected') DEFAULT 'submitted',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Notifications table
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Favorites/Wishlist table
CREATE TABLE IF NOT EXISTS favorites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    customization_name VARCHAR(255) NOT NULL,
    customization_details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Reviews/Ratings table
CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    order_id INT NOT NULL,
    rating INT CHECK (rating >= 1 AND rating <= 5),
    review_text TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (order_id) REFERENCES orders(id)
);

-- Support/Messages table
CREATE TABLE IF NOT EXISTS support_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('open', 'in_progress', 'resolved', 'closed') DEFAULT 'open',
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    admin_response TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);


-- Live chat messages table
CREATE TABLE IF NOT EXISTS live_chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    sender_role ENUM('user', 'admin') NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    delivered_at TIMESTAMP NULL DEFAULT NULL,
    seen_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_live_chat_user (user_id),
    INDEX idx_live_chat_unread (user_id, sender_role, is_read),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Customization templates table
CREATE TABLE IF NOT EXISTS customization_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    category VARCHAR(100),
    base_price DECIMAL(10,2) DEFAULT 0.00,
    image_path VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Order items table (for multiple items per order)
CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    template_id INT,
    customizations TEXT,
    quantity INT DEFAULT 1,
    price DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (template_id) REFERENCES customization_templates(id)
);

-- Shopping cart table
CREATE TABLE IF NOT EXISTS cart (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    template_id INT NOT NULL,
    customizations TEXT,
    quantity INT DEFAULT 1,
    price DECIMAL(10,2) NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (template_id) REFERENCES customization_templates(id) ON DELETE CASCADE,
    UNIQUE KEY unique_cart_item (user_id, template_id, customizations(255))
);

-- Payment transactions table
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    user_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('credit_card', 'paypal', 'bank_transfer', 'cash_on_delivery') DEFAULT 'credit_card',
    transaction_id VARCHAR(255),
    status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Payment proof uploads for QR payments (GCash/GoTyme)
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
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Admin-managed shop vouchers
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
);

-- Product categories table
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    image_path VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Product images table
CREATE TABLE IF NOT EXISTS product_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    is_primary BOOLEAN DEFAULT FALSE,
    alt_text VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (template_id) REFERENCES customization_templates(id) ON DELETE CASCADE
);

-- Insert default admin user
INSERT IGNORE INTO users (username, email, password, role) VALUES
('admin', 'admin@motofit.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Insert sample users
INSERT IGNORE INTO users (username, email, password, role) VALUES
('john_doe', 'john@example.com', '$2y$10$iPacztPfepIhtLUIqOzeyuBjy9sWhxjjDx98UcrDAYJgncZm.YgT6', 'user'),
('jane_smith', 'jane@example.com', '$2y$10$iPacztPfepIhtLUIqOzeyuBjy9sWhxjjDx98UcrDAYJgncZm.YgT6', 'user'),
('mike_wilson', 'mike@example.com', '$2y$10$iPacztPfepIhtLUIqOzeyuBjy9sWhxjjDx98UcrDAYJgncZm.YgT6', 'user');

-- Insert default site content
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

-- Insert sample customization templates
INSERT IGNORE INTO customization_templates (name, description, category, base_price, image_path, is_active) VALUES
('Sport Bike Seat', 'High-performance seat for sport bikes with enhanced grip and comfort', 'seats', 299.99, 'templates/sport_seat.jpg', 1),
('Cruiser Comfort Seat', 'Luxury comfort seat for cruiser motorcycles with gel padding', 'seats', 399.99, 'templates/cruiser_seat.jpg', 1),
('Touring Backrest', 'Adjustable backrest for long-distance touring bikes', 'backrests', 149.99, 'templates/touring_backrest.jpg', 1),
('Custom Handlebar Grips', 'Ergonomic grips with vibration dampening technology', 'grips', 79.99, 'templates/custom_grips.jpg', 1),
('LED Light Kit', 'Complete LED lighting upgrade kit for better visibility', 'lighting', 199.99, 'templates/led_kit.jpg', 1);

-- Insert sample orders (using admin user_id = 1 for demo purposes)
INSERT IGNORE INTO orders (user_id, customization, status, created_at) VALUES
(1, 'Custom sport bike seat with red leather and racing stripes', 'completed', '2024-01-15 10:30:00'),
(1, 'Touring backrest with premium padding and chrome finish', 'in_progress', '2024-01-20 14:45:00'),
(1, 'LED light kit installation with custom wiring harness', 'pending', '2024-01-25 09:15:00'),
(1, 'Custom handlebar grips with heated elements', 'completed', '2024-02-01 16:20:00');

-- Insert sample notifications (using admin user_id = 1 for demo purposes)
INSERT IGNORE INTO notifications (user_id, message, is_read, created_at) VALUES
(1, 'Your order #1 has been completed and is ready for pickup!', 0, '2024-01-16 11:00:00'),
(1, 'Your order #2 is now in progress. Estimated completion: 3-5 business days.', 0, '2024-01-21 09:30:00'),
(1, 'Welcome to MotoFit! Start customizing your perfect ride today.', 1, '2024-01-25 10:00:00'),
(1, 'Thank you for your recent order! Don\'t forget to leave a review.', 0, '2024-02-02 12:00:00');

-- Insert sample favorites (using admin user_id = 1 for demo purposes)
INSERT IGNORE INTO favorites (user_id, customization_name, customization_details, created_at) VALUES
(1, 'Racing Red Sport Seat', 'Custom sport seat with red alcantara material and yellow stitching', '2024-01-10 15:30:00'),
(1, 'Luxury Cruiser Setup', 'Premium cruiser seat with tan leather and comfort gel padding', '2024-01-18 20:45:00'),
(1, 'LED Performance Kit', 'Complete LED upgrade with daytime running lights and turn signals', '2024-01-26 14:20:00');

-- Insert sample reviews (using admin user_id = 1 and order_id = 1 for demo purposes)
INSERT IGNORE INTO reviews (user_id, order_id, rating, review_text, created_at) VALUES
(1, 1, 5, 'Absolutely love my new sport seat! The quality is outstanding and the comfort during long rides is incredible. Highly recommend!', '2024-01-17 13:20:00'),
(1, 4, 4, 'Great grips, very comfortable and the heating feature works perfectly in cold weather. Only minor issue with installation but support helped.', '2024-02-03 10:15:00');

-- Insert sample support messages (using admin user_id = 1 for demo purposes)
INSERT IGNORE INTO support_messages (user_id, subject, message, status, priority, admin_response, created_at, updated_at) VALUES
(1, 'Installation Question', 'Hi, I need help with installing the custom seat on my Honda CBR. Do you provide installation guides?', 'resolved', 'medium', 'Hello! Yes, we provide detailed installation guides for all our products. You can find them in your order confirmation email or download them from our website. If you need further assistance, feel free to ask!', '2024-01-18 16:45:00', '2024-01-19 09:30:00'),
(1, 'Order Status Update', 'Could you please provide an update on my order #2? It\'s been a week since I placed it.', 'in_progress', 'low', 'Thank you for your patience. Your order is currently in the customization phase and should be completed within the next 2-3 days. We\'ll send you an update once it\'s ready.', '2024-01-22 11:20:00', '2024-01-22 14:15:00'),
(1, 'Product Recommendation', 'I\'m looking for accessories for my touring bike. What would you recommend for long trips?', 'open', 'medium', NULL, '2024-01-27 08:30:00', '2024-01-27 08:30:00');
