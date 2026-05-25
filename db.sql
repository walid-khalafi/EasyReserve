-- ایجاد دیتابیس اگر وجود ندارد
CREATE DATABASE IF NOT EXISTS visits
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_general_ci;

-- انتخاب دیتابیس
USE visits;

-- ایجاد جدول رزروها
CREATE TABLE IF NOT EXISTS reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,

    full_name VARCHAR(200) NOT NULL,
    national_id VARCHAR(10) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    subject TEXT NOT NULL,

    visit_day DATE NOT NULL,
    created_at DATETIME NOT NULL,
    ip_address VARCHAR(50) DEFAULT NULL,

    -- ایندکس‌ها برای سرعت بیشتر
    INDEX idx_visit_day (visit_day),
    INDEX idx_national_day (national_id, visit_day)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
