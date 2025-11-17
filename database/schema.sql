CREATE TABLE IF NOT EXISTS `stories` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(255) NOT NULL,
    `type` VARCHAR(50) NOT NULL,
    `plot` TEXT NOT NULL,
    `teachings` TEXT NOT NULL,
    `generation` TEXT NULL,
    `audio_file_id` VARCHAR(255) NOT NULL,
    `duration_minutes` INT NULL,
    `full_text` LONGTEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `stories_type_index` (`type`),
    INDEX `stories_audio_file_index` (`audio_file_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

