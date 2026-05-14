-- Создание таблицы для лайков и дислайков
CREATE TABLE IF NOT EXISTS paste_likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    paste_id INT NOT NULL,
    user_id INT NOT NULL,
    type ENUM('like', 'dislike') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (paste_id) REFERENCES pastes(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    -- Один пользователь может поставить только один лайк/дислайк на пасту
    UNIQUE KEY unique_paste_user (paste_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
