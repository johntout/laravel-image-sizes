<?php

return [
    'encode' => 'webp',
    'image_field' => 'image',
    'video_field' => 'video',
    'filesystem_disk' => 'local',
    'sizes' => [
        'originalImage' => [],
        'bigImage' => [
            'size' => [
                'width' => 800,
                'height' => 465,
            ],
        ],
        'thumbnails' => [
            'size' => [
                'width' => 80,
                'height' => 80,
            ],
        ],
    ],
    'video_providers' => [
        'HTML',
        'Twitch',
        'YouTube',
        'Facebook',
        'Vimeo',
        'Dailymotion',
    ],
    'video_providers_urls' => [
        'Twitch' => '{video}&parent='.env('APP_DOMAIN', 'localhost'),
        'YouTube' => 'https://www.youtube.com/embed/{video}',
        'Facebook' => 'https://www.facebook.com/plugins/video.php?href={video}&show_text=0',
        'Vimeo' => 'https://player.vimeo.com/video/{video}',
        'Dailymotion' => 'https://www.dailymotion.com/embed/video/{video}',
    ],
    'preview_image_url' => 'https://via.placeholder.com/560x315.png',
];
