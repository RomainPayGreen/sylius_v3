<?php

declare(strict_types=1);

namespace PayGreen\SyliusPayumPlugin\Tests\Controller;

use Doctrine\ORM\EntityManagerInterface;
use PayGreen\SyliusPayumPlugin\Controller\WebhookController;
use PayGreen\SyliusPayumPlugin\Status\PayGreenStatusMapper;
use Payum\Core\GatewayInterface;
use Payum\Core\Payum;
use Payum\Core\Request\Notify;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Repository\PaymentRepositoryInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\Component\Payment\Model\PaymentMethodInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use RuntimeException;

final class WebhookControllerTest extends TestCase
{
    public function testItRejectsWebhookWhenSignatureIsInvalid(): void
    {
        $content = json_encode([
            'id' => 'po_123',
            'reference' => 'ORDER-123-payment-7-retry-2',
            'status' => 'captured',
        ], JSON_THROW_ON_ERROR);

        $payment = $this->createPaymentWithWebhookSecret('webhook-secret');
        $payment->expects(self::never())->method('setDetails');

        $paymentRepository = $this->createMock(PaymentRepositoryInterface::class);
        $paymentRepository->method('find')->with('7')->willReturn($payment);

        $payum = $this->createMock(Payum::class);
        $payum->expects(self::never())->method('getGateway');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');

        $response = (new WebhookController($payum, $paymentRepository, $entityManager, new PayGreenStatusMapper()))(
            new Request([], [], [], [], [], [], $content),
        );

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testItProcessesWebhookWhenSignatureIsValid(): void
    {
        $content = json_encode([
            'id' => 'po_123',
            'reference' => 'ORDER-123-payment-7-retry-2',
            'status' => 'captured',
        ], JSON_THROW_ON_ERROR);
        $signature = base64_encode(hash_hmac('sha256', $content, 'webhook-secret', true));

        $payment = $this->createPaymentWithWebhookSecret('webhook-secret');
        $payment->method('getId')->willReturn(7);
        $payment->method('getState')->willReturn(PaymentInterface::STATE_PROCESSING);
        $payment->method('getDetails')->willReturn(['existing' => 'detail']);
        $payment->expects(self::once())->method('setDetails')->with(self::callback(
            static fn (array $details): bool => 'po_123' === $details['paygreen_payment_order_id']
                && 'captured' === $details['paygreen_status']
                && $details['paygreen_response']['reference'] === 'ORDER-123-payment-7-retry-2'
                && !array_key_exists('webhook_secret', $details),
        ));

        $paymentRepository = $this->createMock(PaymentRepositoryInterface::class);
        $paymentRepository->method('find')->with('7')->willReturn($payment);

        $gateway = $this->createMock(GatewayInterface::class);
        $gateway->expects(self::once())->method('execute')->with(self::isInstanceOf(Notify::class));

        $payum = $this->createMock(Payum::class);
        $payum->expects(self::once())->method('getGateway')->with('paygreen')->willReturn($gateway);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $request = new Request([], [], [], [], [], [], $content);
        $request->headers->set('signature', $signature);

        $response = (new WebhookController($payum, $paymentRepository, $entityManager, new PayGreenStatusMapper()))($request);

        self::assertSame(Response::HTTP_ACCEPTED, $response->getStatusCode());
    }

    public function testItReturnsServerErrorWhenWebhookProcessingCannotBePersisted(): void
    {
        $content = json_encode([
            'id' => 'po_123',
            'reference' => 'ORDER-123-payment-7',
            'status' => 'captured',
        ], JSON_THROW_ON_ERROR);
        $signature = base64_encode(hash_hmac('sha256', $content, 'webhook-secret', true));

        $payment = $this->createPaymentWithWebhookSecret('webhook-secret');
        $payment->method('getDetails')->willReturn([]);
        $payment->expects(self::once())->method('setDetails');

        $paymentRepository = $this->createMock(PaymentRepositoryInterface::class);
        $paymentRepository->method('find')->with('7')->willReturn($payment);

        $gateway = $this->createMock(GatewayInterface::class);
        $gateway->expects(self::once())->method('execute')->with(self::isInstanceOf(Notify::class));

        $payum = $this->createMock(Payum::class);
        $payum->expects(self::once())->method('getGateway')->with('paygreen')->willReturn($gateway);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush')->willThrowException(new RuntimeException('Database is unavailable.'));

        $request = new Request([], [], [], [], [], [], $content);
        $request->headers->set('signature', $signature);

        $response = (new WebhookController($payum, $paymentRepository, $entityManager, new PayGreenStatusMapper()))($request);

        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
    }

    private function createPaymentWithWebhookSecret(string $webhookSecret): PaymentInterface
    {
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $gatewayConfig->method('getGatewayName')->willReturn('paygreen');
        $gatewayConfig->method('getConfig')->willReturn(['webhook_secret' => $webhookSecret]);

        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getMethod')->willReturn($paymentMethod);

        return $payment;
    }
}
