CREATE DATABASE IF NOT EXISTS `aurahuz` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `aurahuz`;

CREATE TABLE IF NOT EXISTS admins (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY admins_email_unique (email)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS products (
  id VARCHAR(100) NOT NULL,
  storefront VARCHAR(40) NOT NULL DEFAULT 'aurahuz',
  name VARCHAR(180) NOT NULL,
  short_description TEXT NOT NULL,
  description TEXT NOT NULL,
  benefits JSON NOT NULL,
  ingredients TEXT NOT NULL,
  price DECIMAL(12,2) NOT NULL,
  old_price DECIMAL(12,2) DEFAULT NULL,
  image VARCHAR(255) NOT NULL,
  tag VARCHAR(80) DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY products_active_created_idx (is_active, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS bank_accounts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  bank_name VARCHAR(120) NOT NULL,
  account_name VARCHAR(180) NOT NULL,
  account_number VARCHAR(80) NOT NULL,
  instructions TEXT NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS orders (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_code VARCHAR(40) NOT NULL,
  storefront VARCHAR(40) NOT NULL DEFAULT 'aurahuz',
  customer_name VARCHAR(180) NOT NULL,
  customer_email VARCHAR(190) NOT NULL,
  customer_phone VARCHAR(50) NOT NULL,
  delivery_location TEXT NOT NULL,
  total DECIMAL(12,2) NOT NULL,
  payment_method VARCHAR(40) NOT NULL DEFAULT 'bank_transfer',
  payment_status ENUM('awaiting_payment','payment_pending_confirmation','payment_approved','payment_declined') NOT NULL DEFAULT 'awaiting_payment',
  receipt_path VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY orders_code_unique (order_code),
  KEY orders_email_idx (customer_email),
  KEY orders_status_idx (payment_status, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS order_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id BIGINT UNSIGNED NOT NULL,
  product_id VARCHAR(100) NOT NULL,
  product_name VARCHAR(180) NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  unit_price DECIMAL(12,2) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY order_items_order_idx (order_id),
  CONSTRAINT order_items_order_fk FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS newsletter_subscribers (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  email VARCHAR(190) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY newsletter_email_unique (email)
) ENGINE=InnoDB;

INSERT INTO bank_accounts (bank_name, account_name, account_number, instructions, is_active)
SELECT 'Your Bank', 'Aurahuz Fashion Store', '0123456789', 'Use your order code as the transfer reference, then contact support with proof of payment.', 1
WHERE NOT EXISTS (SELECT 1 FROM bank_accounts);

INSERT INTO products (id, storefront, name, short_description, description, benefits, ingredients, price, old_price, image, tag)
VALUES
('woman-lace-lingerie', 'aurahuz', 'Woman lace lingerie', 'Elegant lace lingerie for a confident look.', 'A stylish one-piece lace design with a flattering silhouette.', JSON_ARRAY('Comfortable fit', 'Statement lace detail'), 'Lace textile', 25000.00, 30000.00, 'img/imgi_5_b9ffrjjqkm.jpg', '17% OFF'),
('3d-alien-men-s-slides', 'aurahuz', '3D alien men\'s slides', 'Statement slides with a sculpted silhouette.', 'Bold everyday slides designed for comfort and personality.', JSON_ARRAY('Lightweight feel', 'Everyday styling'), 'EVA', 82000.00, 90000.00, 'img/imgi_2_w44lwux42l.jpg', NULL),
('automatic-rebound-ab-roller', 'aurahuz', 'Automatic Rebound Ab Roller', 'A compact fitness essential.', 'A rebound ab roller designed for controlled home workouts.', JSON_ARRAY('Stable movement', 'Compact storage'), 'Steel and EVA', 48000.00, 55000.00, 'img/imgi_6_mkyxtj9t4k.jpg', NULL),
('sunglasses', 'aurahuz', 'Sunglasses', 'Everyday eyewear with a polished finish.', 'A versatile pair for completing your everyday looks.', JSON_ARRAY('Lightweight frame', 'Easy styling'), 'Acetate', 25000.00, 30000.00, 'img/imgi_8_7lkf2u199w.jpg', NULL),
('luxury-rhinestone-body-chain', 'aurahuz', 'Luxury Rhinestone Body Chain', 'A polished finishing detail for evening looks.', 'A statement body chain designed to layer over your favorite silhouettes.', JSON_ARRAY('Adjustable styling', 'Crystal finish'), 'Rhinestone alloy', 20000.00, 25000.00, 'img/imgi_4_8t12erdbvw.jpg', NULL),
('luxe-spiral-tassel-necklace', 'aurahuz', 'Luxe Spiral Tassel Necklace', 'A sculptural necklace for standout styling.', 'An elegant tassel necklace that adds movement and shine.', JSON_ARRAY('Layering piece', 'Polished finish'), 'Metal alloy', 25000.00, 35000.00, 'img/imgi_3_shbfbfgkft.jpg', NULL),
('celeste-crystal-cut-out-dress', 'aurahuz', 'Celeste Crystal Cut-Out Dress', 'A statement evening silhouette.', 'A confident cut-out dress finished with crystal-inspired details.', JSON_ARRAY('Evening ready', 'Statement silhouette'), 'Polyester blend', 35000.00, 40000.00, 'img/imgi_1_5vukrz8ghq.jpg', NULL),
('polarized-sunglasses', 'aurahuz', 'Polarized Sunglasses', 'Classic eyewear with a modern edge.', 'Polarized sunglasses for bright days and effortless styling.', JSON_ARRAY('Polarized lenses', 'Comfort fit'), 'Acetate', 25000.00, 30000.00, 'img/imgi_7_hthejwzb8i.jpg', NULL),
('fashion-sunglasses', 'aurahuz', 'Sunglasses', 'A bold accessory for everyday looks.', 'A modern frame that adds character to any outfit.', JSON_ARRAY('Lightweight frame', 'Modern shape'), 'Acetate', 10000.00, 15000.00, 'img/imgi_9_tfv3xw6xjf.jpg', NULL),
('luxury-baroque-pearl-jewelry', 'aurahuz', 'Luxury Baroque Pearl Jewelry', 'Soft pearl details with a statement finish.', 'A luminous jewelry piece designed for special occasions and gifting.', JSON_ARRAY('Pearl detail', 'Gift-ready styling'), 'Baroque pearl alloy', 20000.00, 25000.00, 'img/imgi_10_7lpiy4p5ra.jpg', NULL)
,
('fitness-ab-roller', 'fitness', 'Automatic Rebound Ab Roller', 'A compact core-training essential.', 'A rebound ab roller designed for controlled home workouts.', JSON_ARRAY('Stable movement', 'Compact storage'), 'Steel and EVA', 48000.00, 55000.00, 'img/imgi_6_mkyxtj9t4k.jpg', 'BEST SELLER'),
('fitness-performance-slides', 'fitness', 'Performance Recovery Slides', 'Comfort-first slides for post-workout recovery.', 'Lightweight recovery slides for easy movement before and after training.', JSON_ARRAY('Soft footbed', 'Everyday recovery'), 'EVA', 28000.00, 35000.00, 'img/imgi_2_w44lwux42l.jpg', NULL),
('fitness-training-set', 'fitness', 'Sculpt Training Set', 'A flexible set for focused training days.', 'A versatile training set designed for movement, layering, and repeat wear.', JSON_ARRAY('Flexible fit', 'Breathable feel'), 'Performance blend', 42000.00, 50000.00, 'img/imgi_1_5vukrz8ghq.jpg', NULL)
ON DUPLICATE KEY UPDATE name = VALUES(name), price = VALUES(price), old_price = VALUES(old_price), image = VALUES(image);
