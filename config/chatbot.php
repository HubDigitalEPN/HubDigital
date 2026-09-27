<?php

return [
    'enabled' => env('CHAT_ENABLED', true),
    'specimen_search' => env('CHAT_SPECIMEN_SEARCH', true),
    'statistical_ranking' => env('CHAT_STATISTICAL_RANKING', true),
    'semantic_enabled' => false, // Reservado: no se carga ningún modelo en la VM de 1 GB.
    'debug' => env('CHAT_DEBUG', false),
    'high_confidence_enabled' => false, // TEST_FINAL no confirmó precisión suficiente para usar HIGH.
    // El nombre del modelo es fijo para impedir usar accidentalmente uno mayor.
    'use_local_model' => env('CHATBOT_USE_LOCAL_MODEL', false),
    // Las respuestas habituales se resuelven con reglas y datos locales.
    'use_external_biology' => env('CHATBOT_USE_EXTERNAL_BIOLOGY', false),
    'ollama_url' => env('CHATBOT_OLLAMA_URL', 'http://127.0.0.1:11434'),
    'model' => 'qwen3:0.6b',
    'max_model_bytes' => 1_000_000_000,
    'ranking' => [
        // Valores iniciales sujetos a calibración con el corpus del módulo.
        'weights' => [
            'exact' => 0.20, 'alias' => 0.18, 'keyword' => 0.22,
            'fts' => 0.12, 'trigram' => 0.10, 'context' => 0.07,
            'transition' => 0.03, 'historical' => 0.03, 'quality' => 0.05,
        ],
        'thresholds' => ['high' => 0.70, 'medium' => 0.43, 'unknown' => 0.20, 'ambiguous_min' => 0.35, 'min_margin' => 0.08, 'contextual_min_margin' => 0.02],
        'confidence_formula' => ['score' => 0.60, 'margin' => 0.25, 'evidence' => 0.15, 'margin_scale' => 0.30],
    ],
];
