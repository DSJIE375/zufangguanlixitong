-- 租房管理系统数据库初始化
-- 创建数据库
CREATE DATABASE IF NOT EXISTS rental_system DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE rental_system;

-- 管理员表
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    realname VARCHAR(50) NOT NULL,
    role VARCHAR(20) DEFAULT 'admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 房间类型表
CREATE TABLE IF NOT EXISTS room_types (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    area DECIMAL(5,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 房间表
CREATE TABLE IF NOT EXISTS rooms (
    id INT PRIMARY KEY AUTO_INCREMENT,
    floor INT NOT NULL,
    room_number VARCHAR(10) NOT NULL,
    room_type_id INT,
    status VARCHAR(20) DEFAULT 'available',
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_type_id) REFERENCES room_types(id)
);

-- 租客表
CREATE TABLE IF NOT EXISTS tenants (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    id_card VARCHAR(18),
    gender VARCHAR(10),
    company VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 租赁合同表
CREATE TABLE IF NOT EXISTS contracts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    room_id INT NOT NULL,
    tenant_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE,
    monthly_rent DECIMAL(10,2) NOT NULL,
    deposit DECIMAL(10,2) DEFAULT 0,
    status VARCHAR(20) DEFAULT 'active',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES rooms(id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id)
);

-- 水电费账单表
CREATE TABLE IF NOT EXISTS bills (
    id INT PRIMARY KEY AUTO_INCREMENT,
    contract_id INT NOT NULL,
    bill_month VARCHAR(7) NOT NULL,
    water_start DECIMAL(10,2) DEFAULT 0,
    water_end DECIMAL(10,2) DEFAULT 0,
    water_usage DECIMAL(10,2) DEFAULT 0,
    water_price DECIMAL(10,4) DEFAULT 0,
    water_amount DECIMAL(10,2) DEFAULT 0,
    elec_start DECIMAL(10,2) DEFAULT 0,
    elec_end DECIMAL(10,2) DEFAULT 0,
    elec_usage DECIMAL(10,2) DEFAULT 0,
    elec_price DECIMAL(10,4) DEFAULT 0,
    elec_amount DECIMAL(10,2) DEFAULT 0,
    rent_amount DECIMAL(10,2) DEFAULT 0,
    total_amount DECIMAL(10,2) DEFAULT 0,
    status VARCHAR(20) DEFAULT 'unpaid',
    paid_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (contract_id) REFERENCES contracts(id)
);

-- 房间照片表
CREATE TABLE IF NOT EXISTS room_photos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    room_id INT NOT NULL,
    photo_path VARCHAR(255) NOT NULL,
    photo_type VARCHAR(50) NOT NULL,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
);

-- 留言表
CREATE TABLE IF NOT EXISTS messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    content TEXT NOT NULL,
    is_read TINYINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 系统设置表
CREATE TABLE IF NOT EXISTS settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(50) NOT NULL UNIQUE,
    setting_value TEXT,
    description VARCHAR(100)
);

-- 插入默认数据
INSERT INTO users (username, password, realname, role) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '管理员', 'admin');

INSERT INTO room_types (name, description, price, area) VALUES
('单人间', '适合单人居住，配备基本家具', 500.00, 15.00),
('双人间', '适合两人居住，配备双人床和衣柜', 800.00, 25.00),
('大床房', '配备1.8米大床，适合情侣', 900.00, 30.00);

INSERT INTO settings (setting_key, setting_value, description) VALUES
('water_price', '3.50', '水费单价（元/吨）'),
('electricity_price', '0.60', '电费单价（元/度）'),
('site_name', '我的出租房', '网站名称'),
('site_phone', '13800138000', '联系电话（多个用逗号分隔）'),
('site_address', 'XX市XX区XX路XX号', '公寓地址');

-- 生成房间数据（2-6楼，每层5个房间，跳过含4的房号）
-- 2楼: 201, 202, 203, 205, 206
INSERT INTO rooms (floor, room_number, room_type_id, status) VALUES
(2, '201', 1, 'available'),
(2, '202', 1, 'available'),
(2, '203', 2, 'available'),
(2, '205', 2, 'available'),
(2, '206', 3, 'available'),
(3, '301', 1, 'available'),
(3, '302', 1, 'available'),
(3, '303', 2, 'available'),
(3, '305', 2, 'available'),
(3, '306', 3, 'available'),
(4, '401', 1, 'available'),
(4, '402', 1, 'available'),
(4, '403', 2, 'available'),
(4, '405', 2, 'available'),
(4, '406', 3, 'available'),
(5, '501', 1, 'available'),
(5, '502', 1, 'available'),
(5, '503', 2, 'available'),
(5, '505', 2, 'available'),
(5, '506', 3, 'available'),
(6, '601', 1, 'available'),
(6, '602', 1, 'available'),
(6, '603', 2, 'available'),
(6, '605', 2, 'available'),
(6, '606', 3, 'available');
