CREATE TABLE `phlags` (
    `phlag_id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL,
    `description` varchar(1024) DEFAULT NULL,
    `start_datetime` datetime DEFAULT NULL,
    `end_datetime` datetime DEFAULT NULL,
    `type` enum('SWITCH','INTEGER','FLOAT','STRING') DEFAULT NULL,
    `value` varchar(255) DEFAULT NULL,
    `create_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `update_datetime` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`phlag_id`),
    UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `phlag_api_keys` (
    `plag_api_key_id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `description` varchar(255) NOT NULL,
    `api_key` varchar(64) NOT NULL,
    `create_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`plag_api_key_id`),
    UNIQUE KEY `description` (`description`),
    UNIQUE KEY `api_key` (`api_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `phlag_users` (
    `phlag_user_id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `username` varchar(255) NOT NULL,
    `full_name` varchar(255) NOT NULL,
    `email` varchar(255) NOT NULL,
    `password` varchar(255) NOT NULL,
    `create_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `update_datetime` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`phlag_user_id`),
    UNIQUE KEY `username` (`username`),
    UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `phlag_environments` (
    `phlag_environment_id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL,
    `create_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `update_datetime` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`phlag_environment_id`),
    UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `phlag_password_reset_tokens` (
    `phlag_password_reset_token_id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `phlag_user_id` bigint unsigned NOT NULL,
    `token` varchar(64) NOT NULL,
    `expires_at` datetime NOT NULL,
    `used` tinyint(1) NOT NULL DEFAULT 0,
    `create_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`phlag_password_reset_token_id`),
    UNIQUE KEY `token` (`token`),
    KEY `phlag_user_id` (`phlag_user_id`),
    KEY `expires_at` (`expires_at`),
    CONSTRAINT `fk_password_reset_user` FOREIGN KEY (`phlag_user_id`) REFERENCES `phlag_users` (`phlag_user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
