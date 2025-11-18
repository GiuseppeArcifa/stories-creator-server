<?php

declare(strict_types=1);

$env = [
    'host' => getenv('DB_HOST') ?: '127.0.0.1',
    'port' => getenv('DB_PORT') ?: 3306,
    'name' => getenv('DB_NAME') ?: 'stories_creator',
    'user' => getenv('DB_USER') ?: 'root',
    'password' => getenv('DB_PASSWORD') ?: '',
    'charset' => getenv('DB_CHARSET') ?: 'utf8mb4',
];

return [
    'database' => $env,
    'ai_text_generation' => [
        'url' => getenv('AI_TEXT_GENERATION_URL') ?: '',
        'api_key' => getenv('AI_TEXT_GENERATION_API_KEY') ?: '',
    ],
    'ai_audio_generation' => [
        'url' => getenv('AI_AUDIO_GENERATION_URL') ?: '',
        'api_key' => getenv('AI_AUDIO_GENERATION_API_KEY') ?: '',
    ],
];

