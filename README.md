# Laravel ZeptoMail

A [Zoho ZeptoMail](https://www.zoho.com/zeptomail/) mail transport for Laravel.

Based on the original ZeptoMail Laravel driver by Zoho Mail.

## Requirements

- PHP 8.0 or later
- Laravel 9, 10, 11, 12 or 13
- A ZeptoMail account with a verified sending domain

## Installation

```bash
composer require vikramsra/laravel-zeptomail
```

The service provider is registered automatically through package discovery, and it registers the `zeptomail` transport for you.

## Configuration

Add a mailer to the `mailers` array in **config/mail.php**:

```php
'zeptomail' => [
    'transport' => 'zeptomail',
],
```

Then set these values in your **.env** file:

```dotenv
MAIL_MAILER=zeptomail

ZEPTOMAIL_TOKEN="your-send-mail-token"
ZEPTOMAIL_HOST=api.zeptomail.com

MAIL_FROM_ADDRESS=invoice@zylker.com
MAIL_FROM_NAME="App Name"
```

| Variable | Description |
| --- | --- |
| `ZEPTOMAIL_TOKEN` | The **Send Mail token** from your ZeptoMail Mail Agent (**Mail Agents → SMTP/API → API**). You can paste it with or without the `Zoho-enczapikey ` prefix. |
| `ZEPTOMAIL_HOST` | The ZeptoMail API hostname for your account, e.g. `api.zeptomail.com` or `api.zeptomail.eu` (shown on your Mail Agent's setup page). For backwards compatibility you can also give a Zoho data centre, `zoho.com` (default), `zoho.eu`, `zoho.in`, `zoho.com.cn`, `zoho.com.au`, `zoho.jp`, `zohocloud.ca` or `zoho.sa`, which sends to `zeptomail.<data centre>`. |
| `ZEPTOMAIL_TIMEOUT` | How many seconds to wait for the ZeptoMail API before giving up. Default: `30`. |

You can also set `api_key`, `host` and `timeout` on the mailer entry in **config/mail.php**. Those values override the package config. This is useful if you send from more than one Mail Agent.

To publish the config file:

```bash
php artisan vendor:publish --tag=zeptomail-config
```

## Features

- To, CC, BCC and Reply-To recipients
- HTML and plain-text bodies
- Attachments, plus inline images added with `$message->embed()`
- Custom headers, sent as ZeptoMail `mime_headers`
- ZeptoMail's `request_id` is used as the sent message's ID
- Errors throw Symfony's `TransportException`, so the transport works with Laravel's `failover` and `roundrobin` mailers

## Sending a test email

```bash
php artisan tinker
```

```php
Mail::raw('This is a test email', fn ($message) => $message->to('test@example.com')->subject('Testing Laravel'));
```

## Testing

```bash
composer install
composer test
```

## License

MIT
