<?php

declare(strict_types=1);

namespace App\Models;

class TextGeneration
{
    public ?int $id;
    public int $story_id;
    public string $full_text;
    public ?string $plot;
    public ?string $teachings;
    public ?int $duration_minutes;
    public ?string $provider;
    public ?string $model;
    public string $created_at;

    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(array $attributes)
    {
        $this->id = isset($attributes['id']) ? (int) $attributes['id'] : null;
        $this->story_id = (int) ($attributes['story_id'] ?? 0);
        $this->full_text = (string) ($attributes['full_text'] ?? '');
        $this->plot = isset($attributes['plot']) ? (string) $attributes['plot'] : null;
        $this->teachings = isset($attributes['teachings']) ? (string) $attributes['teachings'] : null;
        $this->duration_minutes = isset($attributes['duration_minutes']) ? (int) $attributes['duration_minutes'] : null;
        $this->provider = isset($attributes['provider']) ? (string) $attributes['provider'] : null;
        $this->model = isset($attributes['model']) ? (string) $attributes['model'] : null;
        $this->created_at = (string) ($attributes['created_at'] ?? '');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'id' => $this->id,
            'full_text' => $this->full_text,
            'provider' => $this->provider,
            'model' => $this->model,
            'created_at' => $this->created_at,
        ];

        // Includi solo i campi non null per evitare ridondanza
        // NOTA: plot e teachings sono esclusi perché ridondanti rispetto alla storia
        if ($this->duration_minutes !== null) {
            $data['duration_minutes'] = $this->duration_minutes;
        }

        return $data;
    }
}

