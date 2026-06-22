-- 历史租户记录表
CREATE TABLE IF NOT EXISTS tenant_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    room_id INT NOT NULL,
    room_number VARCHAR(10) NOT NULL,
    tenant_name VARCHAR(50) NOT NULL,
    tenant_phone VARCHAR(20) NOT NULL,
    tenant_idcard VARCHAR(18),
    tenant_gender VARCHAR(10),
    tenant_company VARCHAR(100),
    monthly_rent DECIMAL(10,2) NOT NULL,
    deposit DECIMAL(10,2) DEFAULT 0,
    start_date DATE NOT NULL,
    end_date DATE,
    checkout_reason VARCHAR(50),
    total_paid DECIMAL(10,2) DEFAULT 0,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
