<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Output canvas (Reels / Shorts, 9:16 vertical)
    |--------------------------------------------------------------------------
    */
    'canvas' => [
        'width' => 1080,
        'height' => 1920,
    ],

    /*
    |--------------------------------------------------------------------------
    | Background image upload rules
    |--------------------------------------------------------------------------
    */
    'backgrounds' => [
        'disk' => 'public',
        'directory' => 'backgrounds',
        'max_upload_kb' => 10240, // 10 MB
        'max_width' => 1080,      // resized down to this width, aspect preserved
        'accepted' => ['jpg', 'jpeg', 'png'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Scene defaults
    |--------------------------------------------------------------------------
    */
    'scene' => [
        'default_duration_seconds' => 5,
        'min_duration_seconds' => 1,
        'max_duration_seconds' => 15,
    ],

    /*
    |--------------------------------------------------------------------------
    | Render archiving
    |--------------------------------------------------------------------------
    | Shotstack CDN URLs are temporary (stage renders expire in ~24h). When
    | enabled, a finished render is copied to a durable disk and its public
    | URL replaces the Shotstack one.
    */
    'archive' => [
        'enabled' => env('RENDER_ARCHIVE_ENABLED', false),
        'disk' => env('RENDER_ARCHIVE_DISK', 's3'),
        'directory' => 'renders',
    ],

    // Email the project owner when a render finishes or fails.
    'notify_by_email' => env('RENDER_NOTIFICATIONS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Shotstack render settings
    |--------------------------------------------------------------------------
    */
    'shotstack' => [
        'fps' => 30,
        'format' => 'mp4',
        'timeline_background' => '#000000',
        'caption' => [
            'font_family' => 'Montserrat ExtraBold',
            'font_size' => 42,
            'font_color' => '#ffffff',
            'background_color' => '#000000',
            'background_opacity' => 0.5,
            'side_margin' => 120, // px trimmed from canvas width for the text box
            'bottom_offset' => 0.08, // Shotstack offset units (0..1 of viewport)
        ],
    ],
];
