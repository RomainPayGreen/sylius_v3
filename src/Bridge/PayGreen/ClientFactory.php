<?php

declare(strict_types=1);

namespace PayGreen\SyliusPayumPlugin\Bridge\PayGreen;

use Http\Client\HttpClient;
use Http\Discovery\HttpClientDiscovery;
use Paygreen\Sdk\Payment\V3\Client;
use Paygreen\Sdk\Payment\V3\Environment;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

final class ClientFactory
{
    public function __construct(private readonly ?HttpClient $httpClient = null)
    {
    }

    /**
     * @param array{shop_id?: string, public_key?: string, secret_key?: string, environment?: string} $config
     */
    public function create(array $config): Client
    {
        $client = new Client(
            $this->httpClient ?? HttpClientDiscovery::find(),
            new Environment(
                (string) ($config['shop_id'] ?? ''),
                (string) ($config['secret_key'] ?? ''),
                (string) ($config['environment'] ?? Environment::ENVIRONMENT_PRODUCTION),
            ),
        );

        $this->authenticate($client);

        return $client;
    }

    private function authenticate(Client $client): void
    {
        $payload = $this->normalizeResponse($client->authenticate());
        $token = $payload['data']['token'] ?? null;

        if (!is_string($token) || '' === $token) {
            $message = $payload['message'] ?? null;

            throw new RuntimeException(sprintf(
                'PayGreen authentication failed%s.',
                is_string($message) && '' !== $message ? sprintf(': %s', $message) : ''
            ));
        }

        $client->setBearer($token);
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeResponse(ResponseInterface $response): array
    {
        $body = $response->getBody();
        if ($body->isSeekable()) {
            $body->rewind();
        }

        $decoded = json_decode($body->getContents(), true);

        return is_array($decoded) ? $decoded : [];
    }
}
