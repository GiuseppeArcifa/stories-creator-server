<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\TextGeneration;
use PDO;
use RuntimeException;

class TextGenerationRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @return array<int, TextGeneration>
     */
    public function findByStoryId(int $storyId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM text_generations WHERE story_id = :story_id ORDER BY created_at DESC');
        $stmt->bindValue(':story_id', $storyId, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(static fn (array $row) => new TextGeneration($row), $rows);
    }

    public function find(int $id): ?TextGeneration
    {
        $stmt = $this->pdo->prepare('SELECT * FROM text_generations WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? new TextGeneration($row) : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): TextGeneration
    {
        $sql = 'INSERT INTO text_generations (story_id, full_text, plot, teachings, duration_minutes, provider, model, created_at)
                VALUES (:story_id, :full_text, :plot, :teachings, :duration_minutes, :provider, :model, :created_at)';

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':story_id' => $data['story_id'],
            ':full_text' => $data['full_text'],
            ':plot' => $data['plot'] ?? null,
            ':teachings' => $data['teachings'] ?? null,
            ':duration_minutes' => $data['duration_minutes'] ?? null,
            ':provider' => $data['provider'] ?? null,
            ':model' => $data['model'] ?? null,
            ':created_at' => $now,
        ]);

        $id = (int) $this->pdo->lastInsertId();

        $textGeneration = $this->find($id);

        if (!$textGeneration) {
            throw new RuntimeException('Unable to fetch text generation after insert.');
        }

        return $textGeneration;
    }

    /**
     * Crea multiple generazioni in una transazione
     *
     * @param array<int, array<string, mixed>> $generationsData
     *
     * @return array<int, TextGeneration>
     */
    public function createBatch(array $generationsData): array
    {
        $this->pdo->beginTransaction();

        try {
            $generations = [];

            foreach ($generationsData as $data) {
                $generations[] = $this->create($data);
            }

            $this->pdo->commit();

            return $generations;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function belongsToStory(int $id, int $storyId): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM text_generations WHERE id = :id AND story_id = :story_id');
        $stmt->execute([
            ':id' => $id,
            ':story_id' => $storyId,
        ]);

        return (bool) $stmt->fetchColumn();
    }
}

