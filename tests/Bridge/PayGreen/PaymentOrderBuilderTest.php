<?php

declare(strict_types=1);

namespace PayGreen\SyliusPayumPlugin\Tests\Bridge\PayGreen;

use PayGreen\SyliusPayumPlugin\Bridge\PayGreen\PaymentOrderBuilder;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;

final class PaymentOrderBuilderTest extends TestCase
{
    public function testItBuildsAPayGreenPaymentOrderFromSyliusPayment(): void
    {
        $billingAddress = $this->createAddress('Romain', 'Da Costa');
        $shippingAddress = $this->createAddress('Romain', 'Da Costa');

        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getId')->willReturn(42);
        $customer->method('getEmail')->willReturn('romain@example.com');

        $order = $this->createMock(OrderInterface::class);
        $order->method('getBillingAddress')->willReturn($billingAddress);
        $order->method('getShippingAddress')->willReturn($shippingAddress);
        $order->method('getCurrencyCode')->willReturn('USD');
        $order->method('getNumber')->willReturn('ORDER-123');
        $order->method('getCustomer')->willReturn($customer);

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getId')->willReturn(7);
        $payment->method('getAmount')->willReturn(4500);
        $payment->method('getCurrencyCode')->willReturn('USD');
        $payment->method('getOrder')->willReturn($order);
        $payment->method('getDetails')->willReturn([]);

        $paymentOrder = (new PaymentOrderBuilder())->build(
            $payment,
            ['shop_id' => 'sh_123'],
            'https://example.com/return',
            'https://example.com/cancel',
        );

        self::assertSame('ORDER-123-payment-7', $paymentOrder->getReference());
        self::assertSame(4500, $paymentOrder->getAmount());
        self::assertSame('usd', $paymentOrder->getCurrency());
        self::assertTrue($paymentOrder->isAutoCapture());
        self::assertSame('sh_123', $paymentOrder->getShopId());
        self::assertSame('https://example.com/return', $paymentOrder->getReturnUrl());
        self::assertSame('https://example.com/cancel', $paymentOrder->getCancelUrl());
        self::assertSame('romain@example.com', $paymentOrder->getBuyer()->getEmail());
        self::assertSame('FR', $paymentOrder->getShippingAddress()->getCountryCode());
    }

    public function testItMakesRetryReferencesUniqueAfterCancelledHostedPageReturn(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getNumber')->willReturn('ORDER-123');

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getId')->willReturn(7);
        $payment->method('getAmount')->willReturn(4500);
        $payment->method('getCurrencyCode')->willReturn('EUR');
        $payment->method('getOrder')->willReturn($order);
        $payment->method('getDetails')->willReturn(['paygreen_retry_count' => 2]);

        $paymentOrder = (new PaymentOrderBuilder())->build($payment, ['shop_id' => 'sh_123'], null, null);

        self::assertSame('ORDER-123-payment-7-retry-2', $paymentOrder->getReference());
    }

    private function createAddress(string $firstName, string $lastName): AddressInterface
    {
        $address = $this->createMock(AddressInterface::class);
        $address->method('getFirstName')->willReturn($firstName);
        $address->method('getLastName')->willReturn($lastName);
        $address->method('getStreet')->willReturn('3 route de bosville');
        $address->method('getCity')->willReturn('Cany-Barville');
        $address->method('getCountryCode')->willReturn('FR');
        $address->method('getPostcode')->willReturn('76450');
        $address->method('getProvinceName')->willReturn(null);

        return $address;
    }
}
