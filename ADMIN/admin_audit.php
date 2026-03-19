<?php
if (!function_exists('rbj_ensure_admin_activity_logs_table')) {
    function rbj_ensure_admin_activity_logs_table(mysqli $conn): void
    {
        static $ensured = false;
        if ($ensured) {
            return;
        }
        $conn->query("
            CREATE TABLE IF NOT EXISTS admin_activity_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                admin_id INT NOT NULL,
                action VARCHAR(120) NOT NULL,
                entity_type VARCHAR(80) NOT NULL,
                entity_id INT DEFAULT NULL,
                details_json TEXT DEFAULT NULL,
                ip_address VARCHAR(45) DEFAULT NULL,
                user_agent VARCHAR(255) DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_admin_activity_admin (admin_id),
                INDEX idx_admin_activity_entity (entity_type, entity_id),
                INDEX idx_admin_activity_created (created_at),
                CONSTRAINT fk_admin_activity_user FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");
        $ensured = true;
    }
}

if (!function_exists('rbj_admin_log')) {
    function rbj_admin_log(mysqli $conn, int $admin_id, string $action, string $entity_type, ?int $entity_id = null, array $details = []): void
    {
        if ($admin_id <= 0 || $action === '' || $entity_type === '') {
            return;
        }

        rbj_ensure_admin_activity_logs_table($conn);
        $json = !empty($details) ? json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
        $ip = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
        $ua = trim((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
        if ($ip === '') {
            $ip = null;
        }
        if ($ua === '') {
            $ua = null;
        }
        $stmt = $conn->prepare("
            INSERT INTO admin_activity_logs (admin_id, action, entity_type, entity_id, details_json, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        if (!$stmt) {
            return;
        }
        $stmt->bind_param('ississs', $admin_id, $action, $entity_type, $entity_id, $json, $ip, $ua);
        $stmt->execute();
        $stmt->close();
    }
}

