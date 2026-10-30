<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Glue Dashboard
    |--------------------------------------------------------------------------
    |
    | Where the app reports itself after each deploy. Every app reports to the
    | same Dashboard, so this only changes to try a report against another one.
    |
    */

    'url' => env('GLUE_BACKOFFICE_URL', 'https://dashboard.glue.be/'),

    /*
    |--------------------------------------------------------------------------
    | Reporting token
    |--------------------------------------------------------------------------
    |
    | The install's own token, issued on its page on the Dashboard. Set it on
    | the production host only: without it the app reports nothing.
    |
    */

    'token' => env('GLUE_BACKOFFICE_TOKEN'),

];
