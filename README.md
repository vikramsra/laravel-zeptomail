**Laravel Zoho ZeptoMail readme**

**Pre-requisites**

1.  Zoho ZeptoMail application with verified domain.
    
2.  Laravel v9.0 and above.
    

**Installation and configuration**

You can add ZeptoMail driver to send emails from your Laravel application. As a first step, you should install ZeptoMail.

Navigate to the application's root folder and paste the following code.

```composer require vikramsra/laravel-zeptomail:dev-main```

Next, you should set ZeptoMail as a mail transport. Follow the steps given [in this section](https://laravel.com/docs/10.x/mail#custom-transports) to add and define a custom transport. Next, you can add the mailer definition within your application's **config/mail.php** configuration file

```'zeptomail' => ['transport' => 'zeptomail',],```

Once you configure mail transport, add the following parameters in the **.env file** of your Laravel application.

Add ZeptoMail's API token in the .env file using the following command. Copy the Send Mail token from the desired Mail Agent and paste it in this column.

```
ZEPTOMAIL_HOST=zoho.com
ZEPTOMAIL_TOKEN="SEND_MAIL_TOKEN"
```

Set the **MAIL\_MAILER** variable to ZeptoMail.

```MAIL_MAILER=zeptomail```

Add the FROM address available in the .env file

```
MAIL_FROM_ADDRESS=invoice@zylker.com 
MAIL_FROM_NAME="App Name"
```
**Sending test email**

You can check the configuration by sending a test email. You can use the Tinker configuration to do so.

Open your command prompt / terminal and enter the following commands.

Launch the tinker environment.

```php artisan tinker```

Next, execute the following email-sending command

```Mail::raw('This is a test email', function ($message) { $message->to('test@email.com') ->subject('Testing Laravel'); });```

Enter the relevant email address in the **to** field.

Once you execute the command, go to the to address to check your configuration.
