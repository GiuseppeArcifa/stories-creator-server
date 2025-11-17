<?php

declare(strict_types=1);

namespace App\Models;

class Story
{
    public ?int $id;
    public string $title;
    public string $type;
    public string $plot;
    public string $teachings;
    public string $generation;
    public string $audio_file_id;
    public ?int $duration_minutes;
    public ?string $full_text;
    public string $created_at;
    public string $updated_at;

    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(array $attributes)
    {
        $this->id = isset($attributes['id']) ? (int) $attributes['id'] : null;
        $this->title = (string) ($attributes['title'] ?? '');
        $this->type = (string) ($attributes['type'] ?? '');
        $this->plot = (string) ($attributes['plot'] ?? '');
        $this->teachings = (string) ($attributes['teachings'] ?? '');
        $this->generation = (string) ($attributes['generation'] ?? '');
        $this->audio_file_id = (string) ($attributes['audio_file_id'] ?? '');
        $this->duration_minutes = isset($attributes['duration_minutes']) ? (int) $attributes['duration_minutes'] : null;
        $this->full_text = $attributes['full_text'] ?? null;
        $this->created_at = (string) ($attributes['created_at'] ?? '');
        $this->updated_at = (string) ($attributes['updated_at'] ?? '');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'type' => $this->type,
            'plot' => $this->plot,
            'teachings' => $this->teachings,
            'generation' => $this->generation,
            'audio_file_id' => $this->audio_file_id,
            'duration_minutes' => $this->duration_minutes,
            'full_text' => $this->full_text,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

