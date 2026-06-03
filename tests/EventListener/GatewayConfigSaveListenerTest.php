<?php

declare(strict_types=1);

namespace PayGreen\SyliusPayumPlugin\Tests\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Psr7\Response;
use Http\Client\HttpClient;
use Paygreen\Sdk\Payment\V3\Environment;
use PayGreen\SyliusPayumPlugin\Bridge\PayGreen\ClientFactory;
use PayGreen\SyliusPayumPlugin\Bridge\PayGreen\ResponseExtractor;
use PayGreen\SyliusPayumPlugin\EventListener\GatewayConfigSaveListener;
use PayGreen\SyliusPayumPlugin\Payum\Factory\PayGreenGatewayFactory;
use PayGreen\SyliusPayumPlugin\Webhook\ListenerRegistrar;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\NullLogger;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\Component\Payment\Model\PaymentMethodInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class GatewayConfigSaveListenerTest extends TestCase
{
    public function testItDoesNothingForNonPayGreenGateway(): void
    {
        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->expects(self::never())->method('generate');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');

        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $gatewayConfig->method('getGatewayName')->willReturn('offline');
        $gatewayConfig->method('getFactoryName')->willReturn('offline');

        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        $this->listener($router, $entityManager)->onGatewayConfigSave(new GatewayConfigEvent($paymentMethod));
    }

    public function testItStoresWebhookSecretAfterPayGreenGatewaySave(): void
    {
        $httpClient = new GatewayConfigSaveHttpClient([
            new Response(200, [], $this->json(['data' => ['token' => 'jwt_123']])),
            new Response(200, [], $this->json(['data' => []])),
            new Response(200, [], $this->json(['data' => [
                'id' => 'listener_created',
                'hmac_key' => 'hmac_created',
            ]])),
        ]);

        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->method('generate')->with(
            'paygreen_payment_webhook',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL,
        )->willReturn('https://example.test/payment/webhook/paygreen');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $gatewayConfig->method('getGatewayName')->willReturn('paygreen2');
        $gatewayConfig->method('getFactoryName')->willReturn(PayGreenGatewayFactory::FACTORY_NAME);
        $gatewayConfig->method('getConfig')->willReturn([
            'shop_id' => 'sh_123',
            'secret_key' => 'sk_123',
            'environment_mode' => Environment::ENVIRONMENT_SANDBOX,
        ]);
        $gatewayConfig->expects(self::once())->method('setConfig')->with(self::callback(
            static fn (array $config): bool => ($config['webhook_secret'] ?? null) === 'hmac_created',
        ));

        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        $this->listener($router, $entityManager, $httpClient)->onGatewayConfigSave(new GatewayConfigEvent($paymentMethod));

        self::assertSame('/auth/authentication/sh_123/secret-key', $httpClient->requests[0]->getUri()->getPath());
        self::assertSame('/notifications/listeners', $httpClient->requests[1]->getUri()->getPath());
        self::assertSame('/notifications/listeners', $httpClient->requests[2]->getUri()->getPath());
        self::assertSame('POST', $httpClient->requests[2]->getMethod());
    }

    public function testItUsesConfiguredWebhookUrlWhenProvided(): void
    {
        $httpClient = new GatewayConfigSaveHttpClient([
            new Response(200, [], $this->json(['data' => ['token' => 'jwt_123']])),
            new Response(200, [], $this->json(['data' => []])),
            new Response(200, [], $this->json(['data' => [
                'id' => 'listener_created',
                'hmac_key' => 'hmac_created',
            ]])),
        ]);

        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->expects(self::never())->method('generate');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $gatewayConfig->method('getGatewayName')->willReturn('paygreen2');
        $gatewayConfig->method('getFactoryName')->willReturn(PayGreenGatewayFactory::FACTORY_NAME);
        $gatewayConfig->method('getConfig')->willReturn([
            'shop_id' => 'sh_123',
            'secret_key' => 'sk_123',
            'webhook_url' => 'https://public.example.test/payment/paygreen/webhook',
            'environment_mode' => Environment::ENVIRONMENT_SANDBOX,
        ]);
        $gatewayConfig->expects(self::once())->method('setConfig')->with(self::callback(
            static fn (array $config): bool => ($config['webhook_secret'] ?? null) === 'hmac_created',
        ));

        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        $this->listener($router, $entityManager, $httpClient)->onGatewayConfigSave(new GatewayConfigEvent($paymentMethod));

        $requestBody = json_decode((string) $httpClient->requests[2]->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('https://public.example.test/payment/paygreen/webhook', $requestBody['url'] ?? null);
    }

    public function testItSkipsListenerRegistrationWhenGeneratedWebhookUrlIsLocal(): void
    {
        $httpClient = new GatewayConfigSaveHttpClient([]);

        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('http://localhost/payment/paygreen/webhook');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');

        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $gatewayConfig->method('getGatewayName')->willReturn('paygreen2');
        $gatewayConfig->method('getFactoryName')->willReturn(PayGreenGatewayFactory::FACTORY_NAME);
        $gatewayConfig->method('getConfig')->willReturn([
            'shop_id' => 'sh_123',
            'secret_key' => 'sk_123',
            'environment_mode' => Environment::ENVIRONMENT_SANDBOX,
        ]);
        $gatewayConfig->expects(self::never())->method('setConfig');

        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        $this->listener($router, $entityManager, $httpClient)->onGatewayConfigSave(new GatewayConfigEvent($paymentMethod));

        self::assertSame([], $httpClient->requests);
    }

    private function listener(
        UrlGeneratorInterface $router,
        EntityManagerInterface $entityManager,
        ?GatewayConfigSaveHttpClient $httpClient = null,
    ): GatewayConfigSaveListener {
        return new GatewayConfigSaveListener(
            new ClientFactory($httpClient ?? new GatewayConfigSaveHttpClient([])),
            new ListenerRegistrar(new ResponseExtractor()),
            $router,
            $entityManager,
            new NullLogger(),
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function json(array $payload): string
    {
        return json_encode($payload, JSON_THROW_ON_ERROR);
    }
}

final class GatewayConfigEvent
{
    public function __construct(private readonly mixed $subject)
    {
    }

    public function getSubject(): mixed
    {
        return $this->subject;
    }
}

final class GatewayConfigSaveHttpClient implements HttpClient
{
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
}
