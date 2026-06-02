<?php

declare(strict_types=1);

namespace PayGreen\SyliusPayumPlugin\Tests\Form\Type;

use PayGreen\SyliusPayumPlugin\Form\Type\PayGreenGatewayConfigurationType;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Form\Test\Traits\ValidatorExtensionTrait;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\Constraints\NotBlank;

final class PayGreenGatewayConfigurationTypeTest extends TypeTestCase
{
    use ValidatorExtensionTrait;

    /**
     * @return iterable<string, array{string}>
     */
    public static function secretFieldProvider(): iterable
    {
        yield 'secret key' => ['secret_key'];
    }

    #[DataProvider('secretFieldProvider')]
    public function testSecretFieldsPreserveStoredValueOnEdit(string $fieldName): void
    {
        $form = $this->factory->create(PayGreenGatewayConfigurationType::class, [
            'shop_id' => 'sh_123',
            'public_key' => 'pk_123',
            'secret_key' => 'sk_123',
            'environment_mode' => 'SANDBOX',
        ]);

        $field = $form->get($fieldName);

        self::assertInstanceOf(PasswordType::class, $field->getConfig()->getType()->getInnerType());
        self::assertFalse($field->getConfig()->getOption('always_empty'));
        self::assertSame($form->getData()[$fieldName], $field->getViewData());
    }

    #[DataProvider('secretFieldProvider')]
    public function testSecretFieldsAreNotRequiredOnEveryEdit(string $fieldName): void
    {
        $form = $this->factory->create(PayGreenGatewayConfigurationType::class);
        $constraints = $form->get($fieldName)->getConfig()->getOption('constraints');

        self::assertIsArray($constraints);
        foreach ($constraints as $constraint) {
            self::assertNotInstanceOf(NotBlank::class, $constraint);
        }
    }
}
