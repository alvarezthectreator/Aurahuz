USE `aurahuz`;

ALTER TABLE orders
  ADD COLUMN delivery_address TEXT NOT NULL AFTER delivery_location,
  ADD COLUMN approval_token_hash CHAR(64) DEFAULT NULL AFTER receipt_path,
  ADD COLUMN payment_submitted_at DATETIME DEFAULT NULL AFTER approval_token_hash,
  ADD COLUMN payment_approved_at DATETIME DEFAULT NULL AFTER payment_submitted_at;

ALTER TABLE order_items
  ADD COLUMN line_total DECIMAL(12,2) NOT NULL AFTER unit_price;