<?php

return [
    'secret' => env('JWT_SECRET', env('APP_KEY')),
    'ttl' => env('JWT_TTL', 60),
    'refresh_ttl' => env('JWT_REFRESH_TTL', 10080),
];
