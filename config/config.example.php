<?php

return [
    'database' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'stories_creator',
        'user' => 'root',
        'password' => 'secret',
        'charset' => 'utf8mb4',
    ],
    'ai_text_generation' => [
        'url' => getenv('AI_TEXT_GENERATION_URL') ?: '',
        'api_key' => getenv('AI_TEXT_GENERATION_API_KEY') ?: '',
    ],
];

