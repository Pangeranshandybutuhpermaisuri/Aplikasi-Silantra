-- MySQL schema & routines for SiLantra
-- Database: silantra

CREATE DATABASE IF NOT EXISTS silantra
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE silantra;

-- Services master
CREATE TABLE IF NOT EXISTS services (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code         VARCHAR(10) NOT NULL,
  name         VARCHAR(100) NOT NULL,
  description  VARCHAR(255) NULL,
  is_active    TINYINT(1) NOT NULL DEFAULT 1,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_services_code (code)
) ENGINE=InnoDB;

INSERT INTO services (code, name)
VALUES ('A', 'Layanan Administrasi'),
       ('B', 'Layanan Informasi / Pengaduan'),
       ('C', 'Layanan Prioritas'),
       ('BU', 'Layanan Badan Usaha')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Users
CREATE TABLE IF NOT EXISTS users (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name     VARCHAR(150) NOT NULL,
  username      VARCHAR(100) NULL,
  nik           VARCHAR(32) NULL,
  phone         VARCHAR(32) NULL,
  email         VARCHAR(150) NULL,
  password_hash VARCHAR(255) NULL,
  date_of_birth DATE NULL,
  gender        ENUM('M','F') NULL,
  address       VARCHAR(255) NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_nik (nik),
  UNIQUE KEY uq_users_username (username),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_name (full_name)
) ENGINE=InnoDB;

INSERT INTO users (full_name, nik, username, phone, email)
VALUES
  ('Budi Santoso', '3276010101010001', 'budi', '081234567890', 'budi@example.com'),
  ('Siti Aminah',  '3276010202020002', 'siti', '081298765432', 'siti@example.com'),
  ('Agus Salim',   '3276010303030003', 'agus', '081212341234', 'agus@example.com'),
  ('Dewi Lestari', '3276010404040004', 'dewi', '081377788899', 'dewi@example.com')
ON DUPLICATE KEY UPDATE
  full_name = VALUES(full_name),
  username  = COALESCE(VALUES(username), username),
  phone     = VALUES(phone),
  email     = VALUES(email);

-- Queue tickets
CREATE TABLE IF NOT EXISTS queue_tickets (
  id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  visit_date     DATE NOT NULL,
  service_id     BIGINT UNSIGNED NOT NULL,
  user_id        BIGINT UNSIGNED NULL,
  ticket_no_int  INT UNSIGNED NOT NULL,
  ticket_code    VARCHAR(20) NOT NULL,
  status         ENUM('waiting','called','served','no_show','cancelled') NOT NULL DEFAULT 'waiting',
  counter_no     VARCHAR(10) NULL,
  notes          VARCHAR(255) NULL,
  called_at      DATETIME NULL,
  served_at      DATETIME NULL,
  finished_at    DATETIME NULL,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_qt_service FOREIGN KEY (service_id) REFERENCES services(id),
  CONSTRAINT fk_qt_user    FOREIGN KEY (user_id)    REFERENCES users(id),
  UNIQUE KEY uq_queue_per_day (service_id, visit_date, ticket_no_int),
  KEY idx_queue_lookup (visit_date, service_id, status, ticket_no_int),
  KEY idx_ticket_code (ticket_code)
) ENGINE=InnoDB;

-- Per service per day counters
CREATE TABLE IF NOT EXISTS queue_counters (
  service_id  BIGINT UNSIGNED NOT NULL,
  visit_date  DATE NOT NULL,
  last_number INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (service_id, visit_date),
  CONSTRAINT fk_qc_service FOREIGN KEY (service_id) REFERENCES services(id)
) ENGINE=InnoDB;

-- Views for receipts and listings
CREATE OR REPLACE VIEW v_queue_tickets_detail AS
SELECT
  qt.id              AS ticket_id,
  qt.ticket_code,
  qt.ticket_no_int,
  qt.visit_date,
  qt.status,
  qt.counter_no,
  qt.created_at,
  qt.called_at,
  qt.served_at,
  qt.finished_at,
  s.id               AS service_id,
  s.code             AS service_code,
  s.name             AS service_name,
  u.id               AS user_id,
  u.full_name,
  u.username,
  u.nik,
  u.phone,
  u.email
FROM queue_tickets qt
JOIN services s ON s.id = qt.service_id
LEFT JOIN users u ON u.id = qt.user_id;

DELIMITER $$

-- Create ticket
CREATE PROCEDURE sp_create_queue_ticket (
  IN p_service_code VARCHAR(10),
  IN p_user_id BIGINT UNSIGNED,
  IN p_counter_no VARCHAR(10)
)
BEGIN
  DECLARE v_service_id BIGINT UNSIGNED;
  DECLARE v_visit_date DATE;
  DECLARE v_next_no INT UNSIGNED;
  DECLARE v_ticket_code VARCHAR(20);
  DECLARE v_code VARCHAR(10);

  SET v_visit_date = CURDATE();

  SELECT id, code INTO v_service_id, v_code
  FROM services
  WHERE code = p_service_code AND is_active = 1
  LIMIT 1;

  IF v_service_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Service code tidak ditemukan/aktif';
  END IF;

  INSERT INTO queue_counters(service_id, visit_date, last_number)
  VALUES (v_service_id, v_visit_date, 1)
  ON DUPLICATE KEY UPDATE last_number = LAST_INSERT_ID(last_number + 1);

  SET v_next_no = LAST_INSERT_ID();
  SET v_ticket_code = CONCAT(v_code, '-', LPAD(v_next_no, 3, '0'));

  INSERT INTO queue_tickets (
    visit_date, service_id, user_id, ticket_no_int, ticket_code, status, counter_no
  ) VALUES (
    v_visit_date, v_service_id, p_user_id, v_next_no, v_ticket_code, 'waiting', p_counter_no
  );

  SELECT qt.id, qt.ticket_code, qt.ticket_no_int, s.name AS service_name, qt.visit_date,
         qt.status, qt.counter_no, qt.created_at,
         u.full_name, u.phone
  FROM queue_tickets qt
  LEFT JOIN users u ON u.id = qt.user_id
  JOIN services s ON s.id = qt.service_id
  WHERE qt.service_id = v_service_id
    AND qt.visit_date = v_visit_date
    AND qt.ticket_no_int = v_next_no
  LIMIT 1;
END$$

-- Call next ticket (update to called)
CREATE PROCEDURE sp_call_next_ticket (
  IN p_service_code VARCHAR(10),
  IN p_counter_no VARCHAR(10)
)
BEGIN
  DECLARE v_service_id BIGINT UNSIGNED;
  DECLARE v_visit_date DATE;
  DECLARE v_target_id BIGINT UNSIGNED;

  SET v_visit_date = CURDATE();

  SELECT id INTO v_service_id
  FROM services
  WHERE code = p_service_code AND is_active = 1
  LIMIT 1;

  IF v_service_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Service code tidak ditemukan/aktif';
  END IF;

  SELECT id INTO v_target_id
  FROM queue_tickets
  WHERE service_id = v_service_id
    AND visit_date = v_visit_date
    AND status = 'waiting'
  ORDER BY ticket_no_int ASC
  LIMIT 1;

  IF v_target_id IS NULL THEN
    SIGNAL SQLSTATE '02000' SET MESSAGE_TEXT = 'Tidak ada tiket waiting';
  END IF;

  UPDATE queue_tickets
  SET status = 'called', called_at = NOW(), counter_no = p_counter_no
  WHERE id = v_target_id;

  SELECT *
  FROM v_queue_tickets_detail
  WHERE ticket_id = v_target_id
  LIMIT 1;
END$$

-- Update ticket status
CREATE PROCEDURE sp_update_ticket_status (
  IN p_ticket_id BIGINT UNSIGNED,
  IN p_status ENUM('served','no_show','cancelled')
)
BEGIN
  IF p_status = 'served' THEN
    UPDATE queue_tickets
      SET status = 'served', served_at = IFNULL(served_at, NOW()), finished_at = NOW()
    WHERE id = p_ticket_id;
  ELSEIF p_status = 'no_show' THEN
    UPDATE queue_tickets
      SET status = 'no_show', finished_at = NOW()
    WHERE id = p_ticket_id;
  ELSEIF p_status = 'cancelled' THEN
    UPDATE queue_tickets
      SET status = 'cancelled', finished_at = NOW()
    WHERE id = p_ticket_id;
  END IF;

  SELECT * FROM v_queue_tickets_detail WHERE ticket_id = p_ticket_id;
END$$

-- Lookup NIK (autofill)
CREATE PROCEDURE sp_lookup_nik (
  IN p_nik VARCHAR(32)
)
BEGIN
  SELECT id AS user_id, full_name, username, nik, phone, email
  FROM users
  WHERE nik = p_nik
  LIMIT 1;
END$$

-- Upsert user by NIK and return row
CREATE PROCEDURE sp_upsert_user_by_nik (
  IN p_nik VARCHAR(32),
  IN p_full_name VARCHAR(150),
  IN p_username VARCHAR(100),
  IN p_phone VARCHAR(32),
  IN p_email VARCHAR(150),
  IN p_password_hash VARCHAR(255)
)
BEGIN
  DECLARE v_user_id BIGINT UNSIGNED;

  IF p_nik IS NULL OR p_nik = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'NIK wajib diisi untuk upsert';
  END IF;

  SELECT id INTO v_user_id FROM users WHERE nik = p_nik LIMIT 1;

  IF v_user_id IS NULL THEN
    INSERT INTO users (full_name, nik, username, phone, email, password_hash)
    VALUES (p_full_name, p_nik, NULLIF(p_username,''), NULLIF(p_phone,''), NULLIF(p_email,''), NULLIF(p_password_hash,''));
    SET v_user_id = LAST_INSERT_ID();
  ELSE
    UPDATE users
      SET full_name = COALESCE(NULLIF(p_full_name,''), full_name),
          username  = COALESCE(NULLIF(p_username,''), username),
          phone     = COALESCE(NULLIF(p_phone,''), phone),
          email     = COALESCE(NULLIF(p_email,''), email),
          password_hash = COALESCE(NULLIF(p_password_hash,''), password_hash),
          updated_at = NOW()
    WHERE id = v_user_id;
  END IF;

  SELECT id AS user_id, full_name, username, nik, phone, email
  FROM users
  WHERE id = v_user_id
  LIMIT 1;
END$$

-- Receipt by ticket id
CREATE PROCEDURE sp_get_receipt_by_ticket_id (
  IN p_ticket_id BIGINT UNSIGNED
)
BEGIN
  SELECT * FROM v_queue_tickets_detail WHERE ticket_id = p_ticket_id LIMIT 1;
END$$

-- Receipt by ticket code
CREATE PROCEDURE sp_get_receipt_by_code (
  IN p_ticket_code VARCHAR(20)
)
BEGIN
  SELECT * FROM v_queue_tickets_detail WHERE ticket_code = p_ticket_code LIMIT 1;
END$$

-- List today waiting per service
CREATE PROCEDURE sp_list_today_waiting (
  IN p_service_code VARCHAR(10)
)
BEGIN
  SELECT *
  FROM v_queue_tickets_detail
  WHERE service_code = p_service_code
    AND visit_date = CURDATE()
    AND status = 'waiting'
  ORDER BY ticket_no_int ASC;
END$$

-- List today called/served/no_show per service (for monitoring)
CREATE PROCEDURE sp_list_today_progress (
  IN p_service_code VARCHAR(10)
)
BEGIN
  SELECT *
  FROM v_queue_tickets_detail
  WHERE service_code = p_service_code
    AND visit_date = CURDATE()
    AND status IN ('called','served','no_show','cancelled')
  ORDER BY ticket_no_int ASC;
END$$

DELIMITER ;
