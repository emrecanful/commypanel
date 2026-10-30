<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Panel Data Directory
    |--------------------------------------------------------------------------
    |
    | Everything the panel owns on disk lives here: the SQLite databases,
    | certificates and app data. Production installs use /var/lib/commypanel.
    |
    */

    'data_path' => env('COMMYPANEL_DATA_PATH', storage_path('app/commypanel')),

    /*
    |--------------------------------------------------------------------------
    | Host Driver
    |--------------------------------------------------------------------------
    |
    | How the panel performs privileged host operations (Docker, nginx, ...).
    | "fake" only records what would happen and is used in tests and on macOS.
    | "agent" talks to commypanel-agent over its Unix socket.
    |
    */

    'host_driver' => env('HOST_DRIVER', 'fake'),

];
