<?php

declare(strict_types=1);

namespace PayGreen\SyliusPayumPlugin\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Paygreen\Sdk\Payment\V3\Environment;
use PayGreen\SyliusPayumPlugin\Bridge\PayGreen\ClientFactory;
use PayGreen\SyliusPayumPlugin\Payum\Factory\PayGreenGatewayFactory;
use PayGreen\SyliusPayumPlugin\Webhook\ListenerRegistrar;
use Psr\Log\LoggerInterface;
use Sylius\Component\Payment\Model\PaymentMethodInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class GatewayConfigSaveListener
{
    public function __construct(
        private readonly ClientFactory $clientFactory,
        private readonly ListenerRegistrar $listenerRegistrar,
        private readonly UrlGeneratorInterface $router,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function onGatewayConfigSave(object $event): void
    {
        $paymentMethod = $this->getSubject($event);
        if (!$paymentMethod instanceof PaymentMethodInterface) {
            return;
        }

        $gatewayConfig = $paymentMethod->getGatewayConfig();
        if (null === $gatewayConfig || !$this->isPayGreenGateway($gatewayConfig)) {
            return;
        }

        $config = $gatewayConfig->getConfig();
        $shopId = $config['shop_id'] ?? null;
        if (!is_string($shopId) || '' === $shopId) {
            $this->logger->warning('PayGreen listener registration skipped because shop_id is missing.');

            return;
        }

        try {
            $webhookUrl = $this->router->generate(
                'paygreen_payment_webhook',
                [],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );
            $client = $this->clientFactory->create($this->normalizeClientConfig($config));
            $hmacKey = $this->listenerRegistrar->register($client, $shopId, $webhookUrl);

            $gatewayConfig->setConfig(array_merge($config, ['webhook_secret' => $hmacKey]));
            $this->entityManager->flush();
        } catch (\Throwable $exception) {
            $this->logger->warning('PayGreen listener registration failed.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function getSubject(object $event): mixed
    {
        if (method_exists($event, 'getSubject')) {
            return $event->getSubject();
        }

        if (method_exists($event, 'getResource')) {
            return $event->getResource();
        }

        return null;
    }

    private function isPayGreenGateway(object $gatewayConfig): bool
    {
        return method_exists($gatewayConfig, 'getFactoryName')
            && PayGreenGatewayFactory::FACTORY_NAME === $gatewayConfig->getFactoryName();
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function normalizeClientConfig(array $config): array
    {
        if (!isset($config['environment']) && isset($config['environment_mode'])) {
            $config['environment'] = $config['environment_mode'];
        }

        $config['environment'] ??= Environment::ENVIRONMENT_PRODUCTION;

        return $config;
    }
}
