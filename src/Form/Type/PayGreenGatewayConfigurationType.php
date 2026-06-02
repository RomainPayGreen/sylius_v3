<?php

declare(strict_types=1);

namespace PayGreen\SyliusPayumPlugin\Form\Type;

use Paygreen\Sdk\Payment\V3\Environment;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

final class PayGreenGatewayConfigurationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('shop_id', TextType::class, [
                'label' => 'paygreen.gateway_configuration.shop_id',
                'constraints' => [new NotBlank(['groups' => ['sylius']])],
            ])
            ->add('public_key', TextType::class, [
                'label' => 'paygreen.gateway_configuration.public_key',
            ])
            ->add('secret_key', PasswordType::class, [
                'label' => 'paygreen.gateway_configuration.secret_key',
                'always_empty' => false,
            ])
            ->add('webhook_secret', PasswordType::class, [
                'label' => 'paygreen.gateway_configuration.webhook_secret',
                'always_empty' => false,
            ])
            ->add('environment_mode', ChoiceType::class, [
                'label' => 'paygreen.gateway_configuration.environment',
                'choices' => [
                    'paygreen.gateway_configuration.environment_production' => Environment::ENVIRONMENT_PRODUCTION,
                    'paygreen.gateway_configuration.environment_sandbox' => Environment::ENVIRONMENT_SANDBOX,
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
