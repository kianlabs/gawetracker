<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'glints' => [
        // No-auth GraphQL endpoint powering Glints' own job search. Overridable
        // so tests can point the source at a fake.
        'endpoint' => env('GLINTS_ENDPOINT', 'https://glints.com/api/v2-alc/graphql'),

        // Two-letter country code; "ID" scopes results to Indonesia.
        'country' => env('GLINTS_COUNTRY', 'ID'),
    ],

    'jobstreet' => [
        // SEEK v5 JobSearch API. Jobstreet/SEEK/JobsDB share this platform; the
        // site key selects the market (ID-Main, SG-Main, MY-Main, HK-Main…).
        'endpoint' => env('JOBSTREET_ENDPOINT', 'https://id.jobstreet.com/api/jobsearch/v5/search'),
        'site_key' => env('JOBSTREET_SITE_KEY', 'ID-Main'),
    ],

    'gmail' => [
        // OAuth2 access token with the gmail.readonly scope. Used by
        // `php artisan emails:import --gmail` to pull JobStreet/Glints mail.
        'access_token' => env('GMAIL_ACCESS_TOKEN'),

        // OAuth2 client used by the "Connect Gmail" flow. Create these in the
        // Google Cloud Console and add the redirect URI below to the client.
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect_uri' => env('GOOGLE_REDIRECT_URI'),
    ],

];
