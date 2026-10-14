<?php

return [
    // Send Mail token from your ZeptoMail Mail Agent (with or without the "Zoho-enczapikey" prefix).
    'api_key' => env('ZEPTOMAIL_TOKEN'),

    // Zoho data centre of your account: zoho.com, zoho.eu, zoho.in, zoho.com.cn, zoho.com.au, zoho.jp, zohocloud.ca or zoho.sa.
    'host' => env('ZEPTOMAIL_HOST', 'zoho.com'),

    // Maximum number of seconds to wait for the ZeptoMail API.
    'timeout' => env('ZEPTOMAIL_TIMEOUT', 30),
];
