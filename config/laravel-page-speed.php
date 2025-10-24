<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enable Laravel Page Speed
    |--------------------------------------------------------------------------
    |
    | Set this field to false to disable the laravel page speed service.
    | You would probably replace that in your local configuration to get a readable output.
    |
    */
    'enable' => env('LARAVEL_PAGE_SPEED_ENABLE', true),

    /*
    |--------------------------------------------------------------------------
    | Skip Routes
    |--------------------------------------------------------------------------
    |
    | Skip Routes paths to exclude.
    | You can use * as wildcard.
    |
    | Development/Debug Tools (Issue #164):
    | If you've customized the routes for debug tools in your application
    | (e.g., Horizon at '/admin/horizon' or Telescope at '/debug/telescope'),
    | you MUST update these patterns to match your custom routes.
    |
    | Examples of custom routes:
    |   'admin/horizon/*'      // Horizon::routes(['domain' => null, 'path' => 'admin/horizon'])
    |   'debug/telescope/*'    // Telescope::$path = 'debug/telescope'
    |   'my-path/clockwork/*'  // Custom Clockwork path
    |
    */
    'skip' => [
        // Development/Debug Tools (Issue #164)
        '_debugbar/*',    // Laravel Debugbar (usually fixed path)
        'horizon/*',      // Laravel Horizon (default, change if customized)
        '_ignition/*',    // Laravel Ignition - Error Pages (usually fixed path)
        'clockwork/*',    // Clockwork (default, change if customized)
        'telescope/*',    // Laravel Telescope (default, change if customized)

        // Binary/Document Files
        '*.xml',
        '*.less',
        '*.pdf',
        '*.doc',
        '*.txt',
        '*.ico',
        '*.rss',
        '*.zip',
        '*.mp3',
        '*.rar',
        '*.exe',
        '*.wmv',
        '*.doc',
        '*.avi',
        '*.ppt',
        '*.mpg',
        '*.mpeg',
        '*.tif',
        '*.wav',
        '*.mov',
        '*.psd',
        '*.ai',
        '*.xls',
        '*.mp4',
        '*.m4a',
        '*.swf',
        '*.dat',
        '*.dmg',
        '*.iso',
        '*.flv',
        '*.m4v',
        '*.torrent'
    ],
];
