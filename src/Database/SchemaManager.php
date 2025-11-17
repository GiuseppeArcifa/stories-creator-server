<?php

declare(strict_types=1);

namespace App\Database;

use PDO;
use RuntimeException;

class SchemaManager
{
    public function __construct(
        private PDO $pdo,
        private string $schemaPath
    ) {
    }

    public function ensureStoriesTable(): void
    {
        if (!$this->tableExists('stories')) {
            $this->runSchemaFile();

            return;
        }

        $expected = [
            'title' => 'ADD COLUMN `title` VARCHAR(255) NOT NULL AFTER `id`',
            'type' => 'ADD COLUMN `type` VARCHAR(50) NOT NULL AFTER `title`',
            'plot' => 'ADD COLUMN `plot` TEXT NOT NULL AFTER `type`',
            'teachings' => 'ADD COLUMN `teachings` TEXT NOT NULL AFTER `plot`',
            'generation' => 'ADD COLUMN `generation` TEXT NULL AFTER `teachings`',
            'audio_file_id' => 'ADD COLUMN `audio_file_id` VARCHAR(255) NOT NULL AFTER `generation`',
            'duration_minutes' => 'ADD COLUMN `duration_minutes` INT NULL AFTER `audio_file_id`',
            'full_text' => 'ADD COLUMN `full_text` LONGTEXT NULL AFTER `duration_minutes`',
            'created_at' => 'ADD COLUMN `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `full_text`',
            'updated_at' => 'ADD COLUMN `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`',
        ];

        $existingColumns = $this->getColumns('stories');

        foreach ($expected as $column => $alterStatement) {
            if (!in_array($column, $existingColumns, true)) {
                $this->pdo->exec(sprintf('ALTER TABLE `stories` %s', $alterStatement));
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

