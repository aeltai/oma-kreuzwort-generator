<?php

return [
    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY', ''),
        'model'   => env('ANTHROPIC_MODEL', 'claude-opus-4-5'),
        'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
        'version' => '2023-06-01',
    ],

    'browsershot' => [
        // Leave null to let Browsershot auto-detect via node_modules/puppeteer
        'chrome_path' => env('BROWSERSHOT_CHROME_PATH'),
        'node_binary' => env('BROWSERSHOT_NODE_BINARY'),
        'npm_binary'  => env('BROWSERSHOT_NPM_BINARY'),
    ],
];
