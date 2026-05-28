<?php

declare(strict_types=1);

namespace PayGreen\SyliusPayumPlugin\Tests\Payum\Factory;

use PayGreen\SyliusPayumPlugin\Payum\Factory\PayGreenGatewayFactory;
use Payum\Core\Bridge\Spl\ArrayObject;
use PHPUnit\Framework\TestCase;

final class PayGreenGatewayFactoryTest extends TestCase
{
    public function testItDefinesThePayGreenFactoryMetadataAndOptions(): void
    {
        $factory = new ExposedPayGreenGatewayFactory();
        $config = $factory->exposeConfig();

        self::assertSame(PayGreenGatewayFactory::FACTORY_NAME, $config['payum.factory_name']);
        self::assertSame('PayGreen', $config['payum.factory_title']);
        self::assertSame(['shop_id', 'public_key', 'secret_key'], $config['payum.required_options']);
        self::assertSame('PRODUCTION', $config['payum.default_options']['environment_mode']);
        self::assertSame('', $config['payum.default_options']['public_key']);
    }
}

final class ExposedPayGreenGatewayFactory extends PayGreenGatewayFactory
{
    public function exposeConfig(): ArrayObject
    {
        $config = new ArrayObject();
        $this->populateConfig($config);

        return $config;
    }
}
