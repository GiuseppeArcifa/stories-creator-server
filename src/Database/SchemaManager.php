<?php

declare(strict_types=1);

namespace App\Database;

use PDO;
use RuntimeException;

class SchemaManager
{
    private PDO $pdo;

    private string $schemaPath;

    public function __construct(PDO $pdo, string $schemaPath)
    {
        $this->pdo = $pdo;
        $this->schemaPath = $schemaPath;
    }

    public function ensureAllTables(): void
    {
        // Se le tabelle non esistono, esegui lo schema completo
        if (!$this->tableExists('stories')) {
            $this->runSchemaFile();

            return;
        }

        // Aggiorna la tabella stories se necessario
        $this->ensureStoriesTable();

        // Crea le nuove tabelle se non esistono
        if (!$this->tableExists('text_generations')) {
            $this->createTextGenerationsTable();
        } else {
            $this->ensureTextGenerationsTable();
        }

        if (!$this->tableExists('audio_generations')) {
            $this->createAudioGenerationsTable();
        } else {
            $this->ensureAudioGenerationsTable();
        }

        // Aggiungi foreign key per final_text_generation_id e final_audio_generation_id se necessario
        $this->ensureFinalGenerationForeignKeys();
    }

    private function ensureStoriesTable(): void
    {
        $expected = [
            'title' => 'ADD COLUMN `title` VARCHAR(255) NOT NULL AFTER `id`',
            'type' => 'ADD COLUMN `type` VARCHAR(50) NOT NULL AFTER `title`',
            'plot' => 'ADD COLUMN `plot` TEXT NOT NULL AFTER `type`',
            'teachings' => 'ADD COLUMN `teachings` TEXT NOT NULL AFTER `plot`',
            'final_text_generation_id' => 'ADD COLUMN `final_text_generation_id` INT UNSIGNED NULL AFTER `teachings`',
            'final_audio_generation_id' => 'ADD COLUMN `final_audio_generation_id` INT UNSIGNED NULL AFTER `final_text_generation_id`',
            'duration_minutes' => 'ADD COLUMN `duration_minutes` INT NULL AFTER `final_audio_generation_id`',
            'created_at' => 'ADD COLUMN `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `duration_minutes`',
            'updated_at' => 'ADD COLUMN `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`',
        ];

        $existingColumns = $this->getColumns('stories');

        foreach ($expected as $column => $alterStatement) {
            if (!in_array($column, $existingColumns, true)) {
                $this->pdo->exec(sprintf('ALTER TABLE `stories` %s', $alterStatement));
            }
        }

        // Rimuovi colonne obsolete se esistono
        $obsoleteColumns = ['generation', 'audio_file_id', 'full_text'];
        foreach ($obsoleteColumns as $column) {
            if (in_array($column, $existingColumns, true)) {
                // Non rimuoviamo automaticamente per sicurezza, ma potremmo farlo in futuro
                // $this->pdo->exec(sprintf('ALTER TABLE `stories` DROP COLUMN `%s`', $column));
            }
        }
    }

    private function createTextGenerationsTable(): void
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `text_generations` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        $this->pdo->exec($sql);
    }

    private function ensureTextGenerationsTable(): void
    {
        $expected = [
            'id' => 'ADD COLUMN `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST',
            'story_id' => 'ADD COLUMN `story_id` INT UNSIGNED NOT NULL AFTER `id`',
            'full_text' => 'ADD COLUMN `full_text` LONGTEXT NOT NULL AFTER `story_id`',
            'plot' => 'ADD COLUMN `plot` TEXT NULL AFTER `full_text`',
            'teachings' => 'ADD COLUMN `teachings` TEXT NULL AFTER `plot`',
            'duration_minutes' => 'ADD COLUMN `duration_minutes` INT NULL AFTER `teachings`',
            'provider' => 'ADD COLUMN `provider` VARCHAR(50) NULL AFTER `duration_minutes`',
            'model' => 'ADD COLUMN `model` VARCHAR(100) NULL AFTER `provider`',
            'created_at' => 'ADD COLUMN `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `model`',
        ];

        $existingColumns = $this->getColumns('text_generations');

        foreach ($expected as $column => $alterStatement) {
            if (!in_array($column, $existingColumns, true)) {
                $this->pdo->exec(sprintf('ALTER TABLE `text_generations` %s', $alterStatement));
            }
        }
    }

    private function createAudioGenerationsTable(): void
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `audio_generations` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        $this->pdo->exec($sql);
    }

    private function ensureAudioGenerationsTable(): void
    {
        $expected = [
            'id' => 'ADD COLUMN `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST',
            'story_id' => 'ADD COLUMN `story_id` INT UNSIGNED NOT NULL AFTER `id`',
            'text_generation_id' => 'ADD COLUMN `text_generation_id` INT UNSIGNED NOT NULL AFTER `story_id`',
            'audio_file_id' => 'ADD COLUMN `audio_file_id` VARCHAR(255) NOT NULL AFTER `text_generation_id`',
            'duration_seconds' => 'ADD COLUMN `duration_seconds` INT NULL AFTER `audio_file_id`',
            'voice_name' => 'ADD COLUMN `voice_name` VARCHAR(100) NULL AFTER `duration_seconds`',
            'provider' => 'ADD COLUMN `provider` VARCHAR(50) NULL AFTER `voice_name`',
            'created_at' => 'ADD COLUMN `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `provider`',
        ];

        $existingColumns = $this->getColumns('audio_generations');

        foreach ($expected as $column => $alterStatement) {
            if (!in_array($column, $existingColumns, true)) {
                $this->pdo->exec(sprintf('ALTER TABLE `audio_generations` %s', $alterStatement));
            }
        }
    }

    private function ensureFinalGenerationForeignKeys(): void
    {
        // Verifica se le foreign key esistono già
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
            WHERE CONSTRAINT_SCHEMA = DATABASE() 
            AND TABLE_NAME = :table 
            AND CONSTRAINT_NAME = :constraint');
        
        // Foreign key per final_text_generation_id
        $stmt->execute([':table' => 'stories', ':constraint' => 'stories_final_text_gen_fk']);
        if (!$stmt->fetchColumn()) {
            try {
                $this->pdo->exec('ALTER TABLE `stories` 
                    ADD CONSTRAINT `stories_final_text_gen_fk` 
                    FOREIGN KEY (`final_text_generation_id`) 
                    REFERENCES `text_generations` (`id`) 
                    ON DELETE SET NULL');
            } catch (\PDOException $e) {
                // Ignora se la foreign key non può essere aggiunta (es. tabelle non ancora create)
            }
        }

        // Foreign key per final_audio_generation_id
        $stmt->execute([':table' => 'stories', ':constraint' => 'stories_final_audio_gen_fk']);
        if (!$stmt->fetchColumn()) {
            try {
                $this->pdo->exec('ALTER TABLE `stories` 
                    ADD CONSTRAINT `stories_final_audio_gen_fk` 
                    FOREIGN KEY (`final_audio_generation_id`) 
                    REFERENCES `audio_generations` (`id`) 
                    ON DELETE SET NULL');
            } catch (\PDOException $e) {
                // Ignora se la foreign key non può essere aggiunta (es. tabelle non ancora create)
            }
        }
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table LIMIT 1');
        $stmt->execute([':table' => $table]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * @return array<int, string>
     */
    private function getColumns(string $table): array
    {
        $stmt = $this->pdo->query(sprintf('SHOW COLUMNS FROM `%s`', $table));
        $columns = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $column) {
            $columns[] = $column['Field'];
        }

        return $columns;
    }

    private function runSchemaFile(): void
    {
        if (!file_exists($this->schemaPath)) {
            throw new RuntimeException(sprintf('Schema file not found at %s', $this->schemaPath));
        }

        $sql = file_get_contents($this->schemaPath);

        if ($sql === false) {
            throw new RuntimeException('Unable to read schema file.');
        }

        $this->pdo->exec($sql);
    }
}

