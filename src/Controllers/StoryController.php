<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\AudioGenerationRepository;
use App\Repositories\StoryRepository;
use App\Repositories\TextGenerationRepository;
use App\Services\AudioGenerationService;
use App\Services\TextGenerationService;

class StoryController
{
    private StoryRepository $repository;
    private TextGenerationRepository $textGenerationRepository;
    private AudioGenerationRepository $audioGenerationRepository;
    private TextGenerationService $textGenerationService;
    private AudioGenerationService $audioGenerationService;

    public function __construct(
        StoryRepository $repository,
        TextGenerationRepository $textGenerationRepository,
        AudioGenerationRepository $audioGenerationRepository,
        TextGenerationService $textGenerationService,
        AudioGenerationService $audioGenerationService
    ) {
        $this->repository = $repository;
        $this->textGenerationRepository = $textGenerationRepository;
        $this->audioGenerationRepository = $audioGenerationRepository;
        $this->textGenerationService = $textGenerationService;
        $this->audioGenerationService = $audioGenerationService;
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

        // Recupera le storie
        $stories = $this->repository->all($limit, $offset);

        // Estrai gli ID delle storie
        $storyIds = array_filter(
            array_map(static fn ($story) => $story->id, $stories),
            static fn ($id) => $id !== null
        );

        // Recupera tutte le generazioni in query ottimizzate (una query per tipo)
        $textGenerationsByStory = [];
        $audioGenerationsByStory = [];

        if (!empty($storyIds)) {
            $textGenerationsByStory = $this->textGenerationRepository->findByStoryIds($storyIds);
            $audioGenerationsByStory = $this->audioGenerationRepository->findByStoryIds($storyIds);
        }

        // Costruisci la risposta con le generazioni annidate
        $payload = array_map(function ($story) use ($textGenerationsByStory, $audioGenerationsByStory) {
            $storyData = $story->toArray();
            $storyId = $story->id ?? 0;

            // Aggiungi sempre text_generations (anche se vuoto)
            $textGens = $textGenerationsByStory[$storyId] ?? [];
            $storyData['text_generations'] = array_map(
                static fn ($gen) => $gen->toArray(),
                $textGens
            );

            // Aggiungi sempre audio_generations (anche se vuoto)
            $audioGens = $audioGenerationsByStory[$storyId] ?? [];
            $storyData['audio_generations'] = array_map(
                static fn ($gen) => $gen->toArray(),
                $audioGens
            );

            return $storyData;
        }, $stories);

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

        $storyData = $story->toArray();

        // Include sempre le generazioni nel dettaglio
        $storyData['text_generations'] = array_map(
            fn ($gen) => $gen->toArray(),
            $this->textGenerationRepository->findByStoryId($id)
        );
        $storyData['audio_generations'] = array_map(
            fn ($gen) => $gen->toArray(),
            $this->audioGenerationRepository->findByStoryId($id)
        );

        jsonResponse($storyData);
    }

    /**
     * @param array<string, string> $params
     */
    public function store(array $params = []): void
    {
        $data = getJsonInput();
        $missing = $this->validateRequired($data, ['title', 'type', 'plot', 'teachings']);

        if (!empty($missing)) {
            jsonResponse(['error' => 'Missing required fields', 'fields' => $missing], 400);
            return;
        }

        try {
            // Crea la storia
            $story = $this->repository->create($data);

            // Prepara i dati per la generazione testo (usa duration se presente, altrimenti duration_minutes)
            $duration = $data['duration'] ?? $data['duration_minutes'] ?? null;

            // Prepara il payload per l'API esterna
            $apiPayload = [
                'title' => $data['title'],
                'type' => $data['type'],
                'plot' => $data['plot'],
                'teachings' => $data['teachings'],
            ];

            if ($duration !== null) {
                $apiPayload['duration'] = (int) $duration;
            }

            if (isset($data['otherNotes']) && $data['otherNotes'] !== '') {
                $apiPayload['otherNotes'] = $data['otherNotes'];
            } elseif (isset($data['other_notes']) && $data['other_notes'] !== '') {
                $apiPayload['otherNotes'] = $data['other_notes'];
            }

            // Chiama l'API esterna per generare i testi
            $textGenerations = [];

            try {
                $generations = $this->textGenerationService->generateText($apiPayload);

                // Prepara i dati per il salvataggio nel DB
                $generationsData = [];

                foreach ($generations as $generation) {
                    $generationsData[] = [
                        'story_id' => $story->id ?? 0,
                        'full_text' => $generation['generated_text'],
                        'provider' => 'ai_service',
                        'model' => 'default-model',
                    ];
                }

                // Salva le generazioni in una transazione
                $textGenerations = $this->textGenerationRepository->createBatch($generationsData);
            } catch (\Throwable $e) {
                // Se la generazione fallisce, logga l'errore ma non blocca la creazione della storia
                // Le generazioni rimarranno vuote
                error_log('Text generation failed for story ' . ($story->id ?? 'unknown') . ': ' . $e->getMessage());
            }

            // Prepara la risposta con la storia e le generazioni
            $response = $story->toArray();
            $response['text_generations'] = array_map(static fn ($gen) => $gen->toArray(), $textGenerations);

            jsonResponse($response, 201);
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
     * @param array<string, string> $params
     */
    public function updateFinalGenerations(array $params): void
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

        $data = getJsonInput();
        $finalTextGenerationId = isset($data['final_text_generation_id']) ? (int) $data['final_text_generation_id'] : null;
        $finalAudioGenerationId = isset($data['final_audio_generation_id']) ? (int) $data['final_audio_generation_id'] : null;

        // Valida final_text_generation_id se fornito
        if ($finalTextGenerationId !== null) {
            $textGen = $this->textGenerationRepository->find($finalTextGenerationId);
            if (!$textGen) {
                jsonError('Text generation not found', 404);
                return;
            }
            if (!$this->textGenerationRepository->belongsToStory($finalTextGenerationId, $id)) {
                jsonError('Text generation does not belong to this story', 400);
                return;
            }
        }

        // Valida final_audio_generation_id se fornito
        if ($finalAudioGenerationId !== null) {
            $audioGen = $this->audioGenerationRepository->find($finalAudioGenerationId);
            if (!$audioGen) {
                jsonError('Audio generation not found', 404);
                return;
            }
            if (!$this->audioGenerationRepository->belongsToStory($finalAudioGenerationId, $id)) {
                jsonError('Audio generation does not belong to this story', 400);
                return;
            }
        }

        $updatedStory = $this->repository->updateFinalGenerations($id, $finalTextGenerationId, $finalAudioGenerationId);

        if (!$updatedStory) {
            jsonError('Unable to update story', 500);
            return;
        }

        jsonResponse($updatedStory->toArray());
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

    /**
     * @param array<string, string> $params
     */
    public function finalize(array $params): void
    {
        $storyId = (int) ($params['id'] ?? 0);

        if ($storyId <= 0) {
            jsonError('Invalid story id', 400);
            return;
        }

        // 1) Validazioni iniziali
        $story = $this->repository->find($storyId);

        if (!$story) {
            jsonError('Story not found', 404);
            return;
        }

        $data = getJsonInput();

        if (!isset($data['final_text_generation_id'])) {
            jsonError('Missing required field: final_text_generation_id', 400);
            return;
        }

        $finalTextGenerationId = (int) $data['final_text_generation_id'];

        // Verifica che la text_generation definitiva esista e appartenga alla story
        $finalTextGeneration = $this->textGenerationRepository->find($finalTextGenerationId);

        if (!$finalTextGeneration) {
            jsonError('Final text generation not found', 404);
            return;
        }

        if (!$this->textGenerationRepository->belongsToStory($finalTextGenerationId, $storyId)) {
            jsonError('Final text generation does not belong to this story', 400);
            return;
        }

        // Valida le text_generations passate
        if (!isset($data['text_generations']) || !is_array($data['text_generations'])) {
            jsonError('Missing or invalid text_generations array', 400);
            return;
        }

        foreach ($data['text_generations'] as $textGen) {
            if (!isset($textGen['id']) || !isset($textGen['full_text'])) {
                jsonError('Each text generation must have id and full_text', 400);
                return;
            }

            $textGenId = (int) $textGen['id'];

            if (!$this->textGenerationRepository->belongsToStory($textGenId, $storyId)) {
                jsonError(sprintf('Text generation %d does not belong to this story', $textGenId), 400);
                return;
            }
        }

        // Avvia transazione (usa il PDO del repository)
        $pdo = $this->textGenerationRepository->getPdo();
        $pdo->beginTransaction();

        try {
            // 2) Aggiornamento delle generazioni testuali
            foreach ($data['text_generations'] as $textGen) {
                $updateData = [
                    'full_text' => $textGen['full_text'],
                ];

                if (isset($textGen['plot'])) {
                    $updateData['plot'] = $textGen['plot'];
                }
                if (isset($textGen['teachings'])) {
                    $updateData['teachings'] = $textGen['teachings'];
                }
                if (isset($textGen['duration_minutes'])) {
                    $updateData['duration_minutes'] = (int) $textGen['duration_minutes'];
                }

                $this->textGenerationRepository->update((int) $textGen['id'], $updateData);
            }

            // 3) Impostare la generazione testuale definitiva sulla story
            $storyUpdateData = [
                'final_text_generation_id' => $finalTextGenerationId,
            ];

            // Aggiorna duration_minutes della story con quella della generazione definitiva se presente
            $finalTextGen = $this->textGenerationRepository->find($finalTextGenerationId);
            if ($finalTextGen && $finalTextGen->duration_minutes !== null) {
                $storyUpdateData['duration_minutes'] = $finalTextGen->duration_minutes;
            }

            $this->repository->update($storyId, $storyUpdateData);

            // 4) Chiamata all'API esterna per generare l'audio
            $finalTextGen = $this->textGenerationRepository->find($finalTextGenerationId);

            if (!$finalTextGen) {
                throw new \RuntimeException('Unable to fetch final text generation');
            }

            $audioPayload = [
                'title' => $story->title,
                'type' => $story->type,
                'full_text' => $finalTextGen->full_text,
                'teachings' => $story->teachings,
                'duration' => $finalTextGen->duration_minutes ?? $story->duration_minutes,
            ];

            if ($story->other_notes !== null && $story->other_notes !== '') {
                $audioPayload['otherNotes'] = $story->other_notes;
            }

            if (isset($data['audio_options'])) {
                if (isset($data['audio_options']['voice_name'])) {
                    $audioPayload['voice_name'] = $data['audio_options']['voice_name'];
                }
                if (isset($data['audio_options']['provider'])) {
                    $audioPayload['provider'] = $data['audio_options']['provider'];
                }
            }

            $audioResult = $this->audioGenerationService->generateAudio($audioPayload);

            // 5) Salvataggio della generazione audio nel DB
            $audioGenerationData = [
                'story_id' => $storyId,
                'text_generation_id' => $finalTextGenerationId,
                'audio_file_id' => $audioResult['audio_file_id'],
            ];

            if (isset($audioResult['duration_seconds'])) {
                $audioGenerationData['duration_seconds'] = $audioResult['duration_seconds'];
            }

            if (isset($data['audio_options']['provider'])) {
                $audioGenerationData['provider'] = $data['audio_options']['provider'];
            }

            if (isset($data['audio_options']['voice_name'])) {
                $audioGenerationData['voice_name'] = $data['audio_options']['voice_name'];
            }

            $audioGeneration = $this->audioGenerationRepository->create($audioGenerationData);

            // 6) Impostare l'audio definitivo sulla story
            $this->repository->update($storyId, [
                'final_audio_generation_id' => $audioGeneration->id,
            ]);

            // 7) Commit della transazione
            $pdo->commit();

            // 8) Risposta API
            $updatedStory = $this->repository->find($storyId);

            if (!$updatedStory) {
                throw new \RuntimeException('Unable to fetch updated story');
            }

            $textGenerations = $this->textGenerationRepository->findByStoryId($storyId);
            $audioGenerations = $this->audioGenerationRepository->findByStoryId($storyId);

            $response = $updatedStory->toArray();
            $response['text_generations'] = array_map(static fn ($gen) => $gen->toArray(), $textGenerations);
            $response['audio_generations'] = array_map(static fn ($gen) => $gen->toArray(), $audioGenerations);

            jsonResponse($response, 200);
        } catch (\Throwable $e) {
            $pdo->rollBack();
            jsonError('Audio generation service unavailable or returned invalid data: ' . $e->getMessage(), 502);
        }
    }
}

