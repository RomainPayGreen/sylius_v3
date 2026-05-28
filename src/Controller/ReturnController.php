<?php

declare(strict_types=1);

namespace PayGreen\SyliusPayumPlugin\Controller;

use Payum\Core\Payum;
use Payum\Core\Request\Notify;
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
        $gateway->execute(new Notify($token));

        $httpRequestVerifier->invalidate($token);

        return new RedirectResponse($token->getAfterUrl() ?: '/');
    }
}
