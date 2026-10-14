<?php

namespace ZohoMail\LaravelZeptoMail\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Exception\InvalidArgumentException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use ZohoMail\LaravelZeptoMail\Transport\ZeptoMailTransport;

class ZeptoMailTransportTest extends TestCase
{
    private array $history = [];

    private function transport(array $responses, string $host = 'zoho.com', string $token = 'secret'): ZeptoMailTransport
    {
        $this->history = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        return new ZeptoMailTransport($token, $host, new Client(['handler' => $stack]));
    }

    private function okResponse(): Response
    {
        return new Response(201, [], json_encode(['message' => 'OK', 'request_id' => 'req-123']));
    }

    private function sentPayload(): array
    {
        return json_decode((string) $this->history[0]['request']->getBody(), true);
    }

    private function email(): Email
    {
        return (new Email())
            ->from(new Address('sender@example.com', 'Sender'))
            ->to(new Address('to@example.com', 'To Person'))
            ->subject('Hello');
    }

    public function test_it_sends_the_expected_request(): void
    {
        $email = $this->email()
            ->cc('cc@example.com')
            ->bcc('bcc@example.com')
            ->replyTo(new Address('reply@example.com', 'Reply'))
            ->html('<p>Hi</p>')
            ->text('Hi');

        $sent = $this->transport([$this->okResponse()], 'zoho.eu')->send($email);

        /** @var Request $request */
        $request = $this->history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('https://zeptomail.zoho.eu/v1.1/email', (string) $request->getUri());
        $this->assertSame('Zoho-enczapikey secret', $request->getHeaderLine('Authorization'));
        $this->assertSame(30.0, (float) $this->history[0]['options']['timeout']);

        $payload = $this->sentPayload();
        $this->assertSame(['address' => 'sender@example.com', 'name' => 'Sender'], $payload['from']);
        $this->assertSame('Hello', $payload['subject']);
        $this->assertSame([['email_address' => ['address' => 'to@example.com', 'name' => 'To Person']]], $payload['to']);
        $this->assertSame([['email_address' => ['address' => 'cc@example.com']]], $payload['cc']);
        $this->assertSame([['email_address' => ['address' => 'bcc@example.com']]], $payload['bcc']);
        $this->assertSame([['address' => 'reply@example.com', 'name' => 'Reply']], $payload['reply_to']);
        $this->assertSame('<p>Hi</p>', $payload['htmlbody']);
        $this->assertSame('Hi', $payload['textbody']);
        $this->assertArrayNotHasKey('attachments', $payload);
        $this->assertArrayNotHasKey('inline_images', $payload);

        $this->assertSame('req-123', $sent->getMessageId());
    }

    public function test_it_does_not_double_prefix_the_token(): void
    {
        $this->transport([$this->okResponse()], 'zoho.com', 'Zoho-enczapikey secret')->send($this->email()->text('Hi'));

        $this->assertSame('Zoho-enczapikey secret', $this->history[0]['request']->getHeaderLine('Authorization'));
    }

    public function test_text_only_mail_is_sent_as_textbody(): void
    {
        $this->transport([$this->okResponse()])->send($this->email()->text("Line 1\nLine 2"));

        $payload = $this->sentPayload();
        $this->assertSame("Line 1\nLine 2", $payload['textbody']);
        $this->assertArrayNotHasKey('htmlbody', $payload);
    }

    public function test_attachments_and_inline_images_are_separated(): void
    {
        $email = $this->email()
            ->html('<img src="cid:logo">')
            ->attach('file contents', 'report.txt', 'text/plain')
            ->embed('image bytes', 'logo', 'image/png');

        $this->transport([$this->okResponse()])->send($email);

        $payload = $this->sentPayload();
        $this->assertSame([[
            'content' => base64_encode('file contents'),
            'name' => 'report.txt',
            'mime_type' => 'text/plain',
        ]], $payload['attachments']);

        $this->assertCount(1, $payload['inline_images']);
        $this->assertSame(base64_encode('image bytes'), $payload['inline_images'][0]['content']);
        $this->assertSame('image/png', $payload['inline_images'][0]['mime_type']);
        $this->assertNotEmpty($payload['inline_images'][0]['cid']);
    }

    public function test_custom_headers_are_sent_as_mime_headers(): void
    {
        $email = $this->email()->text('Hi');
        $email->getHeaders()->addTextHeader('X-Campaign', 'welcome');

        $this->transport([$this->okResponse()])->send($email);

        $this->assertSame(['X-Campaign' => 'welcome'], $this->sentPayload()['mime_headers']);
    }

    public function test_api_errors_throw_a_transport_exception(): void
    {
        $body = json_encode(['error' => [
            'code' => 'TM_3601',
            'message' => 'Sender address not verified',
            'details' => [['message' => 'Invalid from address', 'target' => 'from']],
        ]]);

        $this->expectException(TransportExceptionInterface::class);
        $this->expectExceptionMessage('HTTP 400): Sender address not verified - Invalid from address (from)');

        $this->transport([new Response(400, [], $body)])->send($this->email()->text('Hi'));
    }

    public function test_connection_errors_throw_a_transport_exception(): void
    {
        $this->expectException(TransportExceptionInterface::class);
        $this->expectExceptionMessage('Could not reach the ZeptoMail API');

        $this->transport([new ConnectException('timed out', new Request('POST', 'https://zeptomail.zoho.com'))])
            ->send($this->email()->text('Hi'));
    }

    public function test_it_rejects_an_unknown_host(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid ZeptoMail host "zoho.xyz"');

        new ZeptoMailTransport('secret', 'zoho.xyz');
    }

    public function test_it_rejects_an_empty_token(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ZeptoMail API token is missing');

        new ZeptoMailTransport('', 'zoho.com');
    }
}
