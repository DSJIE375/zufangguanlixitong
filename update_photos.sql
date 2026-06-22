-- 添加房间照片表
CREATE TABLE IF NOT EXISTS room_photos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    room_id INT NOT NULL,
    photo_path VARCHAR(255) NOT NULL,
    photo_type VARCHAR(50) NOT NULL,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
);
