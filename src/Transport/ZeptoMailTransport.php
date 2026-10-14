<?php

namespace ZohoMail\LaravelZeptoMail\Transport;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\InvalidArgumentException;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MessageConverter;
use Symfony\Component\Mime\Part\DataPart;

class ZeptoMailTransport extends AbstractTransport
{
    /**
     * Supported Zoho data centres, mapped to the ZeptoMail API domain.
     */
    public const HOSTS = [
        'zoho.com',
        'zoho.eu',
        'zoho.in',
        'zoho.com.cn',
        'zoho.com.au',
        'zoho.jp',
        'zohocloud.ca',
        'zoho.sa',
    ];

    /**
     * Headers that are sent as dedicated payload fields and must not be duplicated in mime_headers.
     */
    private const RESERVED_HEADERS = [
        'from', 'to', 'cc', 'bcc', 'reply-to', 'sender', 'subject', 'date', 'message-id',
        'return-path', 'content-type', 'content-transfer-encoding', 'mime-version',
    ];

    private const AUTH_PREFIX = 'Zoho-enczapikey ';

    protected string $apiKey;
    protected string $host;
    protected float $timeout;
    protected ClientInterface $client;

    public function __construct(string $apiKey, string $host = 'zoho.com', ?ClientInterface $client = null, float $timeout = 30)
    {
        parent::__construct();

        $apiKey = trim($apiKey);

        if ($apiKey === '') {
            throw new InvalidArgumentException('ZeptoMail API token is missing. Set ZEPTOMAIL_TOKEN in your .env file.');
        }

        if (! in_array($host, self::HOSTS, true)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid ZeptoMail host "%s". Set ZEPTOMAIL_HOST to one of: %s.',
                $host,
                implode(', ', self::HOSTS)
            ));
        }

        $this->apiKey = stripos($apiKey, trim(self::AUTH_PREFIX)) === 0 ? $apiKey : self::AUTH_PREFIX.$apiKey;
        $this->host = $host;
        $this->timeout = $timeout;
        $this->client = $client ?? new HttpClient();
    }

    public function __toString(): string
    {
        return 'zeptomail';
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());
        $payload = $this->getPayload($email, $message->getEnvelope());

        try {
            $response = $this->client->request('POST', $this->getEndpoint(), [
                'headers' => [
                    'Authorization' => $this->apiKey,
                    'Accept' => 'application/json',
                    'User-Agent' => 'Laravel',
                ],
                'json' => $payload,
                'timeout' => $this->timeout,
                'connect_timeout' => min(10, $this->timeout),
                'http_errors' => false,
            ]);
        } catch (GuzzleException $e) {
            throw new TransportException('Could not reach the ZeptoMail API: '.$e->getMessage(), 0, $e);
        }

        $body = (string) $response->getBody();
        $statusCode = $response->getStatusCode();
        $result = json_decode($body, true);

        if ($statusCode >= 400) {
            $exception = new TransportException(sprintf(
                'Unable to send an email via ZeptoMail (HTTP %d): %s',
                $statusCode,
                $this->getErrorMessage($result, $body)
            ), $statusCode);
            $exception->appendDebug($body);

            throw $exception;
        }

        if (is_array($result) && isset($result['request_id'])) {
            $message->setMessageId($result['request_id']);
        }
    }

    protected function getEndpoint(): string
    {
        return 'https://zeptomail.'.$this->host.'/v1.1/email';
    }

    protected function getPayload(Email $email, Envelope $envelope): array
    {
        $payload = [
            'from' => $this->formatAddress($this->getFrom($email, $envelope)),
            'subject' => (string) $email->getSubject(),
        ];

        $recipients = $this->getRecipients($email, $envelope);

        foreach (['to', 'cc', 'bcc'] as $type) {
            if (! empty($recipients[$type])) {
                $payload[$type] = array_map(
                    fn (Address $address) => ['email_address' => $this->formatAddress($address)],
                    $recipients[$type]
                );
            }
        }

        if ($replyTo = $email->getReplyTo()) {
            $payload['reply_to'] = array_map(fn (Address $address) => $this->formatAddress($address), $replyTo);
        }

        if (null !== $html = $this->bodyToString($email->getHtmlBody())) {
            $payload['htmlbody'] = $html;
        }

        if (null !== $text = $this->bodyToString($email->getTextBody())) {
            $payload['textbody'] = $text;
        }

        foreach ($email->getAttachments() as $attachment) {
            $this->addAttachment($payload, $attachment);
        }

        if ($headers = $this->getCustomHeaders($email)) {
            $payload['mime_headers'] = $headers;
        }

        return $payload;
    }

    protected function getFrom(Email $email, Envelope $envelope): Address
    {
        return $email->getFrom()[0] ?? $envelope->getSender();
    }

    /**
     * Group envelope recipients into to, cc and bcc based on the message headers.
     *
     * @return array<string, Address[]>
     */
    protected function getRecipients(Email $email, Envelope $envelope): array
    {
        $recipients = ['to' => [], 'cc' => [], 'bcc' => []];

        $bcc = array_map(fn (Address $address) => strtolower($address->getAddress()), $email->getBcc());
        $cc = array_map(fn (Address $address) => strtolower($address->getAddress()), $email->getCc());

        foreach ($envelope->getRecipients() as $recipient) {
            $address = strtolower($recipient->getAddress());

            if (in_array($address, $bcc, true)) {
                $recipients['bcc'][] = $recipient;
            } elseif (in_array($address, $cc, true)) {
                $recipients['cc'][] = $recipient;
            } else {
                $recipients['to'][] = $recipient;
            }
        }

        return $recipients;
    }

    protected function addAttachment(array &$payload, DataPart $attachment): void
    {
        $headers = $attachment->getPreparedHeaders();
        $mimeType = $attachment->getMediaType().'/'.$attachment->getMediaSubtype();
        $filename = $headers->getHeaderParameter('Content-Disposition', 'filename');

        if ($headers->getHeaderBody('Content-Disposition') === 'inline') {
            $payload['inline_images'][] = [
                'cid' => $attachment->getContentId(),
                'content' => base64_encode($attachment->getBody()),
                'mime_type' => $mimeType,
            ];

            return;
        }

        $payload['attachments'][] = [
            'content' => base64_encode($attachment->getBody()),
            'name' => $filename ?? 'attachment',
            'mime_type' => $mimeType,
        ];
    }

    protected function getCustomHeaders(Email $email): array
    {
        $headers = [];

        foreach ($email->getHeaders()->all() as $name => $header) {
            if (in_array(strtolower($name), self::RESERVED_HEADERS, true)) {
                continue;
            }

            $headers[$header->getName()] = $header->getBodyAsString();
        }

        return $headers;
    }

    protected function formatAddress(Address $address): array
    {
        $formatted = ['address' => $address->getAddress()];

        if ($address->getName() !== '') {
            $formatted['name'] = $address->getName();
        }

        return $formatted;
    }

    /**
     * @param resource|string|null $body
     */
    private function bodyToString($body): ?string
    {
        if (is_resource($body)) {
            rewind($body);
            $body = stream_get_contents($body);
        }

        return $body === null || $body === false ? null : (string) $body;
    }

    private function getErrorMessage($result, string $body): string
    {
        if (is_array($result) && isset($result['error'])) {
            $error = $result['error'];
            $message = $error['message'] ?? 'Unknown error';

            foreach ($error['details'] ?? [] as $detail) {
                if (isset($detail['message'])) {
                    $message .= ' - '.$detail['message'].(isset($detail['target']) ? ' ('.$detail['target'].')' : '');
                }
            }

            return $message;
        }

        return $body !== '' ? $body : 'Empty response';
    }
}
