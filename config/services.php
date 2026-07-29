<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'line' => [
        'channel_access_token' => env('LINE_CHANNEL_ACCESS_TOKEN'),

        // メッセージ送信の経路
        //   line    : LINE Messaging API へ直接プッシュ（従来どおり）
        //   harness : LINE Harness (OSS) の API 経由で送信
        'driver' => env('LINE_DRIVER', 'line'),

        // リッチメニュー切り替えの担当
        //   app     : 本アプリが切り替える（従来どおり）
        //   harness : LINE Harness 側に委譲し、本アプリからは操作しない
        'richmenu_driver' => env('LINE_RICHMENU_DRIVER', 'app'),

        'harness' => [
            'base_url' => env('LINE_HARNESS_BASE_URL'),
            'api_key' => env('LINE_HARNESS_API_KEY'),
        ],
    ],

];
