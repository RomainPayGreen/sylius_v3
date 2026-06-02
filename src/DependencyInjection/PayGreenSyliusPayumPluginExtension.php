<?php

declare(strict_types=1);

namespace PayGreen\SyliusPayumPlugin\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class PayGreenSyliusPayumPluginExtension extends Extension implements PrependExtensionInterface
{
    public function prepend(ContainerBuilder $container): void
    {
        if ($container->hasExtension('monolog')) {
            $container->prependExtensionConfig('monolog', [
                'channels' => ['paygreen'],
            ]);
        }

        if (!$container->hasExtension('sylius_twig_hooks')) {
            return;
        }

        $container->prependExtensionConfig('sylius_twig_hooks', [
            'hooks' => [
                'gateway_configuration' => [
                    'use_payum' => [
                        'template' => '@PayGreenSyliusPayumPlugin/admin/payment_method/form/sections/gateway_configuration/use_payum.html.twig',
                        'priority' => 0,
                    ],
                ],
                'gateway_configuration.paygreen' => [
                    'config' => [
                        'template' => '@PayGreenSyliusPayumPlugin/admin/payment_method/form/sections/gateway_configuration/config.html.twig',
                        'priority' => 0,
                    ],
                ],
                'sylius_admin.payment_method.create.content.form.sections.gateway_configuration' => [
                    'use_payum' => [
                        'template' => '@PayGreenSyliusPayumPlugin/admin/payment_method/form/sections/gateway_configuration/use_payum.html.twig',
                        'priority' => 0,
                    ],
                ],
                'sylius_admin.payment_method.create.content.form.sections.gateway_configuration.paygreen' => [
                    'config' => [
                        'template' => '@PayGreenSyliusPayumPlugin/admin/payment_method/form/sections/gateway_configuration/config.html.twig',
                        'priority' => 0,
                    ],
                ],
                'sylius_admin.payment_method.update.content.form.sections.gateway_configuration' => [
                    'use_payum' => [
                        'template' => '@PayGreenSyliusPayumPlugin/admin/payment_method/form/sections/gateway_configuration/use_payum.html.twig',
                        'priority' => 0,
                    ],
                ],
                'sylius_admin.payment_method.update.content.form.sections.gateway_configuration.paygreen' => [
                    'config' => [
                        'template' => '@PayGreenSyliusPayumPlugin/admin/payment_method/form/sections/gateway_configuration/config.html.twig',
                        'priority' => 0,
                    ],
                ],
            ],
        ]);
    }

    /**
     * @param array<array-key, mixed> $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');
    }
}
