<?php

declare(strict_types=1);

return [

    /*
    | The MNB exchange rate web service. It answers on plain HTTP only.
    */
    'endpoint' => env('MNB_ENDPOINT', 'http://www.mnb.hu/arfolyamok.asmx'),

    /*
    | Request timeout in seconds.
    */
    'timeout' => (float) env('MNB_TIMEOUT', 10),

    /*
    | How many days on() looks back for the last published table when the requested day has
    | none (weekends, public holidays, the Christmas break).
    */
    'lookback_days' => (int) env('MNB_LOOKBACK_DAYS', 14),

    /*
    | Responses are cached by request. Published history does not change, so it is kept long;
    | anything that can still change today is kept briefly. Set 'enabled' to false to always
    | call the service.
    */
    'cache' => [
        'enabled' => (bool) env('MNB_CACHE', true),
        'store' => env('MNB_CACHE_STORE'),
        'prefix' => 'mnb-exchange-rates',
        'ttl' => [
            'current' => 600,
            'currencies' => 86400,
            'history' => 2592000,
        ],
    ],

];
