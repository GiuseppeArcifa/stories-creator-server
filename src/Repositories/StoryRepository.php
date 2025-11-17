<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Story;
use PDO;
use RuntimeException;

class StoryRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @return array<int, Story>
     */
    public function all(int $limit = 50, int $offset = 0): array
    {
        $sql = 'SELECT * FROM stories ORDER BY created_at DESC LIMIT :limit OFFSET :offset';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(static fn (array $row) => new Story($row), $rows);
    }

    public function find(int $id): ?Story
    {
        $stmt = $this->pdo->prepare('SELECT * FROM stories WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? new Story($row) : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): Story
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $sql = 'INSERT INTO stories (title, type, plot, teachings, duration_minutes, created_at, updated_at)
                VALUES (:title, :type, :plot, :teachings, :duration_minutes, :created_at, :updated_at)';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':title' => $data['title'],
            ':type' => $data['type'],
            ':plot' => $data['plot'],
            ':teachings' => $data['teachings'],
            ':duration_minutes' => $data['duration_minutes'] ?? null,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);

        $id = (int) $this->pdo->lastInsertId();

        $story = $this->find($id);

        if (!$story) {
            throw new RuntimeException('Unable to fetch story after insert.');
        }

        return $story;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): ?Story
    {
        $fields = [];
        $params = [':id' => $id];

        $updatable = [
            'title',
            'type',
            'plot',
            'teachings',
            'final_text_generation_id',
            'final_audio_generation_id',
            'duration_minutes',
        ];

        foreach ($updatable as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = sprintf('%s = :%s', $field, $field);
                $params[":{$field}"] = $data[$field];
            }
        }

        if (empty($fields)) {
            return $this->find($id);
        }

        $fields[] = 'updated_at = :updated_at';
        $params[':updated_at'] = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $sql = 'UPDATE stories SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $this->find($id);
    }

    public function updateFinalGenerations(int $id, ?int $finalTextGenerationId, ?int $finalAudioGenerationId): ?Story
    {
        $fields = [];
        $params = [':id' => $id];

        if ($finalTextGenerationId !== null) {
            $fields[] = 'final_text_generation_id = :final_text_generation_id';
            $params[':final_text_generation_id'] = $finalTextGenerationId;
        }

        if ($finalAudioGenerationId !== null) {
            $fields[] = 'final_audio_generation_id = :final_audio_generation_id';
            $params[':final_audio_generation_id'] = $finalAudioGenerationId;
        }

        if (empty($fields)) {
            return $this->find($id);
        }

        $fields[] = 'updated_at = :updated_at';
        $params[':updated_at'] = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $sql = 'UPDATE stories SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $this->find($id);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM stories WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }
}

