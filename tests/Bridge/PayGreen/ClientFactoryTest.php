<?php

declare(strict_types=1);

namespace PayGreen\SyliusPayumPlugin\Tests\Bridge\PayGreen;

use GuzzleHttp\Psr7\Response;
use Http\Client\HttpClient;
use PayGreen\SyliusPayumPlugin\Bridge\PayGreen\ClientFactory;
use Paygreen\Sdk\Payment\V3\Environment;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class ClientFactoryTest extends TestCase
{
    public function testItAuthenticatesTheSdkClientBeforeUsingIt(): void
    {
        $httpClient = new class([
            new Response(200, [], json_encode(['data' => ['token' => 'jwt_123']], JSON_THROW_ON_ERROR)),
            new Response(200, [], '{}'),
        ]) implements HttpClient {
            /**
             * @var list<RequestInterface>
             */
            public array $requests = [];

            /**
             * @param list<ResponseInterface> $responses
             */
            public function __construct(private array $responses)
            {
            }

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->requests[] = $request;

                return array_shift($this->responses) ?? new Response(200, [], '{}');
            }
        };

        $client = (new ClientFactory($httpClient))->create([
            'shop_id' => 'sh_123',
            'secret_key' => 'sk_123',
            'environment' => Environment::ENVIRONMENT_SANDBOX,
        ]);

        $client->getPaymentOrder('po_123');

        self::assertSame('/auth/authentication/sh_123/secret-key', $httpClient->requests[0]->getUri()->getPath());
        self::assertSame('sk_123', $httpClient->requests[0]->getHeaderLine('Authorization'));
        self::assertSame('/payment/payment-orders/po_123', $httpClient->requests[1]->getUri()->getPath());
        self::assertSame('Bearer jwt_123', $httpClient->requests[1]->getHeaderLine('Authorization'));
    }
}
