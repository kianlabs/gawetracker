<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Shared Production Database
    |--------------------------------------------------------------------------
    |
    | The local `.env` and the production container `gawetracker-app` both point
    | at the same MySQL database. That overlap is how a `migrate:fresh` run from
    | the host once wiped production. AppServiceProvider uses this name to
    | refuse destructive migration commands against that database.
    |
    */

    'shared_database' => env('GAWETRACKER_SHARED_DB', 'gawetracker'),

    /*
    |--------------------------------------------------------------------------
    | Destructive Command Escape Hatch
    |--------------------------------------------------------------------------
    |
    | Set GAWETRACKER_ALLOW_DESTRUCTIVE_DB=true to lift the guard, e.g. when
    | intentionally rebuilding an isolated throwaway database that happens to
    | share the name above. Never enable it against production.
    |
    */

    'allow_destructive' => (bool) env('GAWETRACKER_ALLOW_DESTRUCTIVE_DB', false),

];
