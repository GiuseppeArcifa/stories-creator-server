<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\AudioGeneration;
use PDO;
use RuntimeException;

class AudioGenerationRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @return array<int, AudioGeneration>
     */
    public function findByStoryId(int $storyId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM audio_generations WHERE story_id = :story_id ORDER BY created_at DESC');
        $stmt->bindValue(':story_id', $storyId, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(static fn (array $row) => new AudioGeneration($row), $rows);
    }

    /**
     * Recupera tutte le generazioni audio per multiple story_id (query ottimizzata)
     *
     * @param array<int> $storyIds
     *
     * @return array<int, array<int, AudioGeneration>> Array associativo con story_id come chiave
     */
    public function findByStoryIds(array $storyIds): array
    {
        if (empty($storyIds)) {
            return [];
        }

        // Crea placeholders per la query IN
        $placeholders = implode(',', array_fill(0, count($storyIds), '?'));

        $stmt = $this->pdo->prepare(sprintf(
            'SELECT * FROM audio_generations WHERE story_id IN (%s) ORDER BY story_id, created_at DESC',
            $placeholders
        ));

        $stmt->execute($storyIds);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Raggruppa per story_id
        $grouped = [];

        foreach ($rows as $row) {
            $storyId = (int) $row['story_id'];

            if (!isset($grouped[$storyId])) {
                $grouped[$storyId] = [];
            }

            $grouped[$storyId][] = new AudioGeneration($row);
        }

        return $grouped;
    }

    public function find(int $id): ?AudioGeneration
    {
        $stmt = $this->pdo->prepare('SELECT * FROM audio_generations WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? new AudioGeneration($row) : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): AudioGeneration
    {
        $sql = 'INSERT INTO audio_generations (story_id, text_generation_id, audio_file_id, duration_seconds, voice_name, provider, created_at)
                VALUES (:story_id, :text_generation_id, :audio_file_id, :duration_seconds, :voice_name, :provider, :created_at)';

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':story_id' => $data['story_id'],
            ':text_generation_id' => $data['text_generation_id'],
            ':audio_file_id' => $data['audio_file_id'],
            ':duration_seconds' => $data['duration_seconds'] ?? null,
            ':voice_name' => $data['voice_name'] ?? null,
            ':provider' => $data['provider'] ?? null,
            ':created_at' => $now,
        ]);

        $id = (int) $this->pdo->lastInsertId();

        $audioGeneration = $this->find($id);

        if (!$audioGeneration) {
            throw new RuntimeException('Unable to fetch audio generation after insert.');
        }

        return $audioGeneration;
    }

    public function belongsToStory(int $id, int $storyId): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM audio_generations WHERE id = :id AND story_id = :story_id');
        $stmt->execute([
            ':id' => $id,
            ':story_id' => $storyId,
        ]);

        return (bool) $stmt->fetchColumn();
    }
}

