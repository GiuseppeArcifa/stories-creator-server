<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\StoryRepository;

class StoryController
{
    public function __construct(private readonly StoryRepository $repository)
    {
    }

    /**
     * @param array<string, string> $params
     */
    public function index(array $params = []): void
    {
        $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 50;
        $offset = isset($_GET['offset']) ? (int) $_GET['offset'] : 0;

        $limit = max(1, min($limit, 100));
        $offset = max(0, $offset);

        $stories = $this->repository->all($limit, $offset);
        $payload = array_map(static fn ($story) => $story->toArray(), $stories);

        jsonResponse($payload);
    }

    /**
     * @param array<string, string> $params
     */
    public function show(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);

        $story = $this->repository->find($id);

        if (!$story) {
            jsonError('Story not found', 404);
            return;
        }

        jsonResponse($story->toArray());
    }

    /**
     * @param array<string, string> $params
     */
    public function store(array $params = []): void
    {
        $data = getJsonInput();
        $missing = $this->validateRequired($data, ['title', 'type', 'plot', 'teachings', 'audio_file_id']);

        if (!empty($missing)) {
            jsonResponse(['error' => 'Missing required fields', 'fields' => $missing], 400);
            return;
        }

        try {
            $story = $this->repository->create($data);
            jsonResponse($story->toArray(), 201);
        } catch (\Throwable $e) {
            jsonError('Unable to create story: ' . $e->getMessage(), 500);
        }
    }

    /**
     * @param array<string, string> $params
     */
    public function update(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);

        if ($id <= 0) {
            jsonError('Invalid story id', 400);
            return;
        }

        $payload = getJsonInput();

        if (empty($payload)) {
            jsonError('No data provided for update', 400);
            return;
        }

        $story = $this->repository->update($id, $payload);

        if (!$story) {
            jsonError('Story not found', 404);
            return;
        }

        jsonResponse($story->toArray());
    }

    /**
     * @param array<string, string> $params
     */
    public function destroy(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);

        if ($id <= 0) {
            jsonError('Invalid story id', 400);
            return;
        }

        $story = $this->repository->find($id);

        if (!$story) {
            jsonError('Story not found', 404);
            return;
        }

        $deleted = $this->repository->delete($id);

        if (!$deleted) {
            jsonError('Unable to delete story', 500);
            return;
        }

        jsonResponse(['message' => 'Story deleted']);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, string> $fields
     *
     * @return array<int, string>
     */
    private function validateRequired(array $data, array $fields): array
    {
        $missing = [];

        foreach ($fields as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                $missing[] = $field;
            }
        }

        return $missing;
    }
}

