<?php

namespace App\Services;

class Lang
{
    /** Resolve a supported language code, defaulting to 'de'. */
    public static function resolve(array $settings): string
    {
        $lang = (string) ($settings['language'] ?? 'de');
        $config = config('languages');
        return isset($config[$lang]) ? $lang : 'de';
    }

    /** Get the language config block for the given settings. */
    public static function config(array $settings): array
    {
        $config = config('languages');
        return $config[self::resolve($settings)] ?? $config['de'];
    }

    /** Get config block by explicit language code. */
    public static function for(string $lang): array
    {
        $config = config('languages');
        return $config[$lang] ?? $config['de'];
    }
}
