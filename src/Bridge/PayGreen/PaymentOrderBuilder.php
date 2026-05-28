<?php

declare(strict_types=1);

namespace PayGreen\SyliusPayumPlugin\Bridge\PayGreen;

use Paygreen\Sdk\Payment\V3\Model\Address;
use Paygreen\Sdk\Payment\V3\Model\Buyer;
use Paygreen\Sdk\Payment\V3\Model\PaymentOrder;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;

final class PaymentOrderBuilder
{
    /**
     * @param array<string, mixed> $config
     */
    public function build(PaymentInterface $payment, array $config, ?string $returnUrl, ?string $cancelUrl): PaymentOrder
    {
        $order = $payment->getOrder();
        $billingAddress = $order instanceof OrderInterface ? $order->getBillingAddress() : null;
        $shippingAddress = $order instanceof OrderInterface ? $order->getShippingAddress() : null;

        $paymentOrder = new PaymentOrder();
        $paymentOrder->setReference($this->resolveReference($payment, $order));
        $paymentOrder->setAmount((int) $payment->getAmount());
        $paymentOrder->setCurrency(strtolower((string) ($payment->getCurrencyCode() ?: $order?->getCurrencyCode())));
        $paymentOrder->setAutoCapture(true);
        $paymentOrder->setDescription($this->resolveDescription($order));
        $paymentOrder->setShopId((string) ($config['shop_id'] ?? ''));

        if (null !== $billingAddress) {
            $paymentOrder->setBuyer($this->buildBuyer($order, $billingAddress));
        }

        if (null !== $shippingAddress) {
            $paymentOrder->setShippingAddress($this->buildAddress($shippingAddress));
        }

        $this->callOptionalSetter($paymentOrder, 'setReturnUrl', $returnUrl);
        $this->callOptionalSetter($paymentOrder, 'setCancelUrl', $cancelUrl);

        return $paymentOrder;
    }

    private function buildBuyer(?OrderInterface $order, AddressInterface $billingAddress): Buyer
    {
        $customer = $order?->getCustomer();

        $buyer = new Buyer();
        $buyer->setReference((string) ($customer?->getId() ?? $order?->getNumber() ?? $order?->getId() ?? 'guest'));
        $buyer->setEmail((string) ($customer?->getEmail() ?? $this->callOptionalGetter($order, 'getCustomerEmail') ?? ''));
        $buyer->setFirstName((string) $billingAddress->getFirstName());
        $buyer->setLastName((string) $billingAddress->getLastName());
        $buyer->setBillingAddress($this->buildAddress($billingAddress));

        return $buyer;
    }

    private function buildAddress(AddressInterface $address): Address
    {
        $payGreenAddress = new Address();
        $payGreenAddress->setStreetLineOne(trim((string) $address->getStreet()));
        $payGreenAddress->setCity((string) $address->getCity());
        $payGreenAddress->setCountryCode(strtoupper((string) $address->getCountryCode()));
        $payGreenAddress->setPostalCode(substr((string) $address->getPostcode(), 0, 10));

        $this->callOptionalSetter($payGreenAddress, 'setStreetLineTwo', $address->getProvinceName());

        return $payGreenAddress;
    }

    private function resolveReference(PaymentInterface $payment, ?OrderInterface $order): string
    {
        $orderReference = (string) ($order?->getNumber() ?? $order?->getId() ?? 'order');
        $details = $payment->getDetails() ?? [];
        $retryCount = (int) ($details['paygreen_retry_count'] ?? 0);
        $retrySuffix = $retryCount > 0 ? sprintf('-retry-%d', $retryCount) : '';

        return sprintf('%s-payment-%s%s', $orderReference, (string) ($payment->getId() ?? uniqid('', true)), $retrySuffix);
    }

    private function resolveDescription(?OrderInterface $order): string
    {
        if (null === $order) {
            return 'Sylius order payment';
        }

        return sprintf('Sylius order %s', (string) ($order->getNumber() ?? $order->getId()));
    }

    private function callOptionalSetter(object $object, string $method, mixed $value): void
    {
        if (null === $value || '' === $value || !method_exists($object, $method)) {
            return;
        }

        $object->{$method}($value);
    }

    private function callOptionalGetter(?object $object, string $method): mixed
    {
        if (null === $object || !method_exists($object, $method)) {
            return null;
        }

        return $object->{$method}();
    }
}
