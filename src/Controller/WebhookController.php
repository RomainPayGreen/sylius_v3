<?php

declare(strict_types=1);

namespace PayGreen\SyliusPayumPlugin\Controller;

use Doctrine\ORM\EntityManagerInterface;
use PayGreen\SyliusPayumPlugin\Status\PayGreenStatusMapper;
use Payum\Core\Payum;
use Payum\Core\Request\Notify;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Repository\PaymentRepositoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class WebhookController
{
    public function __construct(
        private readonly Payum $payum,
        private readonly PaymentRepositoryInterface $paymentRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly PayGreenStatusMapper $statusMapper,
        private readonly string $webhookSecretConfigKey = 'webhook_secret',
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $content = $request->getContent();
        $payload = json_decode($content, true);

        if (!is_array($payload)) {
            return new JsonResponse(['error' => 'Invalid JSON payload.'], Response::HTTP_BAD_REQUEST);
        }

        $status = $this->findByKey($payload, 'status');
        $payment = $this->findPayment($payload);

        if (!$payment instanceof PaymentInterface) {
            return new JsonResponse([
                'accepted' => false,
                'error' => 'Payment could not be resolved from the PayGreen webhook payload.',
                'paygreen_status' => $status,
                'mapped_status' => $this->statusMapper->map(is_string($status) ? $status : null),
            ], Response::HTTP_ACCEPTED);
        }

        if (!$this->isSignatureValid($request, $content, $payment)) {
            return new JsonResponse(['error' => 'Invalid PayGreen webhook signature.'], Response::HTTP_UNAUTHORIZED);
        }

        $paymentOrderId = $this->findByKey($payload, 'id');
        $gatewayName = $payment->getMethod()?->getGatewayConfig()?->getGatewayName();
        try {
            $details = $payment->getDetails() ?? [];
            $payment->setDetails(array_filter(array_replace($details, [
                'paygreen_payment_order_id' => is_string($paymentOrderId) ? $paymentOrderId : null,
                'paygreen_status' => is_string($status) ? $status : null,
                'paygreen_response' => $payload,
            ]), static fn (mixed $value): bool => null !== $value));

            if (is_string($gatewayName) && '' !== $gatewayName) {
                $this->payum->getGateway($gatewayName)->execute(new Notify($payment));
            }

            $this->entityManager->flush();
        } catch (Throwable) {
            return new JsonResponse(['error' => 'PayGreen webhook processing failed.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse([
            'accepted' => true,
            'payment_id' => $payment->getId(),
            'paygreen_status' => $status,
            'mapped_status' => $this->statusMapper->map(is_string($status) ? $status : null),
            'payment_state' => $payment->getState(),
        ], Response::HTTP_ACCEPTED);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function findPayment(array $payload): ?PaymentInterface
    {
        $reference = $this->findByKey($payload, 'reference');
        if (!is_string($reference) || 1 !== preg_match('/-payment-(\d+)(?:-retry-\d+)?$/', $reference, $matches)) {
            return null;
        }

        $payment = $this->paymentRepository->find($matches[1]);

        return $payment instanceof PaymentInterface ? $payment : null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function findByKey(array $payload, string $key): mixed
    {
        if (array_key_exists($key, $payload)) {
            return $payload[$key];
        }

        foreach ($payload as $value) {
            if (is_array($value)) {
                $nested = $this->findByKey($value, $key);

                if (null !== $nested) {
                    return $nested;
                }
            }
        }

        return null;
    }

    private function isSignatureValid(Request $request, string $content, PaymentInterface $payment): bool
    {
        $signature = (string) $request->headers->get('signature', '');
        if ('' === $signature) {
            return false;
        }

        $webhookSecret = $this->resolveWebhookSecret($payment);
        if (null === $webhookSecret || '' === $webhookSecret) {
            return false;
        }

        $expectedSignature = base64_encode(hash_hmac('sha256', $content, $webhookSecret, true));

        return hash_equals($expectedSignature, $signature);
    }

    private function resolveWebhookSecret(PaymentInterface $payment): ?string
    {
        $config = $payment->getMethod()?->getGatewayConfig()?->getConfig();
        if (!is_array($config)) {
            return null;
        }

        $webhookSecret = $config[$this->webhookSecretConfigKey] ?? null;

        return is_string($webhookSecret) ? $webhookSecret : null;
    }
}
