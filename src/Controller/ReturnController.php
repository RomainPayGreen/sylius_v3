<?php

declare(strict_types=1);

namespace PayGreen\SyliusPayumPlugin\Controller;

use Payum\Core\Payum;
use Payum\Core\Request\Capture;
use Payum\Core\Security\TokenInterface;
use Payum\Core\Storage\IdentityInterface;
use RuntimeException;
use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

final class ReturnController
{
    public function __construct(private readonly Payum $payum)
    {
    }

    public function __invoke(Request $request): RedirectResponse
    {
        $httpRequestVerifier = $this->payum->getHttpRequestVerifier();
        $token = $httpRequestVerifier->verify($request);

        $gateway = $this->payum->getGateway($token->getGatewayName());
        $gateway->execute(new Capture($this->resolvePayment($token)));

        $httpRequestVerifier->invalidate($token);

        return new RedirectResponse($token->getAfterUrl() ?: '/');
    }

    private function resolvePayment(TokenInterface $token): PaymentInterface
    {
        $identity = $token->getDetails();
        if (!$identity instanceof IdentityInterface) {
            throw new RuntimeException('The Payum token does not reference a payment.');
        }

        $payment = $this->payum->getStorage($identity->getClass())->find($identity);
        if (!$payment instanceof PaymentInterface) {
            throw new RuntimeException('The Payum token payment could not be resolved.');
        }

        return $payment;
    }
}
