<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Supported locales
    |--------------------------------------------------------------------------
    |
    | Each entry maps a locale code to its display metadata. The `code` is used
    | for `App::setLocale()` and as the lang/<code> directory name. The
    | `native` label is shown in the locale switcher UI.
    |
    | To add a new language: create lang/<code>/* files, add a matching JSON
    | file at resources/js/i18n/locales/<code>.json, and append an entry here.
    |
    */

    'default' => 'uz',

    'supported' => [
        'uz' => [
            'code' => 'uz',
            'native' => "O'zbekcha",
            'english' => 'Uzbek (Latin)',
            'flag' => '🇺🇿',
        ],
        'uz-Cyrl' => [
            'code' => 'uz-Cyrl',
            'native' => 'Ўзбекча',
            'english' => 'Uzbek (Cyrillic)',
            'flag' => '🇺🇿',
        ],
        'ru' => [
            'code' => 'ru',
            'native' => 'Русский',
            'english' => 'Russian',
            'flag' => '🇷🇺',
        ],
        'en' => [
            'code' => 'en',
            'native' => 'English',
            'english' => 'English',
            'flag' => '🇬🇧',
        ],
    ],

];
