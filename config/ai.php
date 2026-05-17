<?php

return [
    'providers' => [
        'ollama' => [
            'url' => env('OLLAMA_URL', 'http://localhost:11434'),
            'model' => env('OLLAMA_MODEL', 'qwen2.5:3b'),
        ],
    ],
];
