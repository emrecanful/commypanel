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

    /*
    |--------------------------------------------------------------------------
    | Agent Socket
    |--------------------------------------------------------------------------
    |
    | Unix socket of commypanel-agent, used by the "agent" host driver. The
    | agent only runs on Linux; `agent_allow_non_linux` exists solely so a
    | dry-run agent can be used during development on other systems.
    |
    */

    'agent_socket' => env('AGENT_SOCKET', '/run/commypanel/agent.sock'),

    'agent_allow_non_linux' => (bool) env('AGENT_ALLOW_NON_LINUX', false),

    /*
    |--------------------------------------------------------------------------
    | Deployments
    |--------------------------------------------------------------------------
    */

    'deploy' => [
        // Host ports (bound on 127.0.0.1) handed to app containers.
        'port_range_start' => (int) env('DEPLOY_PORT_START', 20000),
        'port_range_end' => (int) env('DEPLOY_PORT_END', 29999),

        // Seconds to wait for a new container to answer before failing the deploy.
        'health_timeout' => (int) env('DEPLOY_HEALTH_TIMEOUT', 60),

        // Seconds the old container keeps running after traffic has moved.
        'drain_seconds' => (int) env('DEPLOY_DRAIN_SECONDS', 10),

        // Release images kept per app so rollbacks stay possible.
        'keep_images' => (int) env('DEPLOY_KEEP_IMAGES', 3),

        'git_timeout' => (int) env('DEPLOY_GIT_TIMEOUT', 300),

        // Tests only: allow cloning from local paths.
        'git_allow_local' => (bool) env('DEPLOY_GIT_ALLOW_LOCAL', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTPS (Let's Encrypt)
    |--------------------------------------------------------------------------
    |
    | Certificates are issued by commypanel-agent using the HTTP-01 challenge.
    | Outside production the staging CA is the default: its certificates are
    | not trusted by browsers but it has generous rate limits.
    |
    */

    'https' => [
        'staging' => (bool) env('ACME_STAGING', env('APP_ENV') !== 'production'),

        // Renew when a certificate has fewer than this many days left.
        'renew_before_days' => (int) env('ACME_RENEW_BEFORE_DAYS', 30),

        // Wait this long before retrying a failed issuance.
        'retry_after_hours' => (int) env('ACME_RETRY_AFTER_HOURS', 6),
    ],

];
