-- Tabella stories (aggiornata)
CREATE TABLE IF NOT EXISTS `stories` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(255) NOT NULL,
    `type` VARCHAR(50) NOT NULL,
    `plot` TEXT NOT NULL,
    `teachings` TEXT NOT NULL,
    `final_text_generation_id` INT UNSIGNED NULL,
    `final_audio_generation_id` INT UNSIGNED NULL,
    `duration_minutes` INT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `stories_type_index` (`type`),
    INDEX `stories_final_text_gen_index` (`final_text_generation_id`),
    INDEX `stories_final_audio_gen_index` (`final_audio_generation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabella text_generations
CREATE TABLE IF NOT EXISTS `text_generations` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `story_id` INT UNSIGNED NOT NULL,
    `full_text` LONGTEXT NOT NULL,
    `plot` TEXT NULL,
    `teachings` TEXT NULL,
    `duration_minutes` INT NULL,
    `provider` VARCHAR(50) NULL,
    `model` VARCHAR(100) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `text_generations_story_id_index` (`story_id`),
    CONSTRAINT `text_generations_story_fk` FOREIGN KEY (`story_id`) REFERENCES `stories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabella audio_generations
CREATE TABLE IF NOT EXISTS `audio_generations` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `story_id` INT UNSIGNED NOT NULL,
    `text_generation_id` INT UNSIGNED NOT NULL,
    `audio_file_id` VARCHAR(255) NOT NULL,
    `duration_seconds` INT NULL,
    `voice_name` VARCHAR(100) NULL,
    `provider` VARCHAR(50) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `audio_generations_story_id_index` (`story_id`),
    INDEX `audio_generations_text_gen_id_index` (`text_generation_id`),
    CONSTRAINT `audio_generations_story_fk` FOREIGN KEY (`story_id`) REFERENCES `stories` (`id`) ON DELETE CASCADE,
    CONSTRAINT `audio_generations_text_gen_fk` FOREIGN KEY (`text_generation_id`) REFERENCES `text_generations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Foreign keys per final_text_generation_id e final_audio_generation_id
-- Nota: queste vengono aggiunte dopo la creazione delle tabelle text_generations e audio_generations
-- Lo SchemaManager gestisce la creazione incrementale e l'aggiunta delle foreign key se necessario
