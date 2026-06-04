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

        // Sylius 2.x ships sylius_twig_hooks; Sylius 1.x does not. Use it as the
        // version discriminator: on 2.x the `sylius_ui` extension still exists but
        // no longer accepts the `events` option, so prepending it there breaks the
        // whole app.
        $isSyliusTwoOrHigher = $container->hasExtension('sylius_twig_hooks');

        // Sylius 1.x admin: render the meal voucher checkbox through UI template events.
        if (!$isSyliusTwoOrHigher && $container->hasExtension('sylius_ui')) {
            $container->prependExtensionConfig('sylius_ui', [
                'events' => [
                    'sylius.admin.product_variant.tab_details' => [
                        'blocks' => [
                            'paygreen_meal_voucher_compatible' => [
                                'template' => '@PayGreenSyliusPayumPlugin/admin/product_variant/meal_voucher_compatible.html.twig',
                                'priority' => 10,
                            ],
                        ],
                    ],
                    'sylius.admin.product.tab_details' => [
                        'blocks' => [
                            'paygreen_meal_voucher_compatible' => [
                                'template' => '@PayGreenSyliusPayumPlugin/admin/product/meal_voucher_compatible.html.twig',
                                'priority' => 10,
                            ],
                        ],
                    ],
                ],
            ]);
        }

        if (!$isSyliusTwoOrHigher) {
            return;
        }

        $container->prependExtensionConfig('sylius_twig_hooks', [
            'hooks' => [
                // Sylius 2.x admin: dedicated "PayGreen" tab (side navigation + section)
                // on the product and product variant forms, like other plugins (e.g. Mollie).
                'sylius_admin.product.create.content.form.side_navigation' => [
                    'paygreen' => [
                        'template' => '@PayGreenSyliusPayumPlugin/admin/product/form/side_navigation/paygreen.html.twig',
                        'priority' => -100,
                    ],
                ],
                'sylius_admin.product.create.content.form.sections' => [
                    'paygreen' => [
                        'template' => '@PayGreenSyliusPayumPlugin/admin/product/form/sections/paygreen.html.twig',
                        'priority' => -100,
                    ],
                ],
                'sylius_admin.product.create.content.form.sections.paygreen' => [
                    'meal_voucher' => [
                        'template' => '@PayGreenSyliusPayumPlugin/admin/product/form/sections/paygreen/meal_voucher.html.twig',
                        'priority' => 0,
                    ],
                ],
                'sylius_admin.product.update.content.form.side_navigation' => [
                    'paygreen' => [
                        'template' => '@PayGreenSyliusPayumPlugin/admin/product/form/side_navigation/paygreen.html.twig',
                        'priority' => -100,
                    ],
                ],
                'sylius_admin.product.update.content.form.sections' => [
                    'paygreen' => [
                        'template' => '@PayGreenSyliusPayumPlugin/admin/product/form/sections/paygreen.html.twig',
                        'priority' => -100,
                    ],
                ],
                'sylius_admin.product.update.content.form.sections.paygreen' => [
                    'meal_voucher' => [
                        'template' => '@PayGreenSyliusPayumPlugin/admin/product/form/sections/paygreen/meal_voucher.html.twig',
                        'priority' => 0,
                    ],
                ],
                'sylius_admin.product_variant.create.content.form.side_navigation' => [
                    'paygreen' => [
                        'template' => '@PayGreenSyliusPayumPlugin/admin/product_variant/form/side_navigation/paygreen.html.twig',
                        'priority' => -100,
                    ],
                ],
                'sylius_admin.product_variant.create.content.form.sections' => [
                    'paygreen' => [
                        'template' => '@PayGreenSyliusPayumPlugin/admin/product_variant/form/sections/paygreen.html.twig',
                        'priority' => -100,
                    ],
                ],
                'sylius_admin.product_variant.create.content.form.sections.paygreen' => [
                    'meal_voucher' => [
                        'template' => '@PayGreenSyliusPayumPlugin/admin/product_variant/form/sections/paygreen/meal_voucher.html.twig',
                        'priority' => 0,
                    ],
                ],
                'sylius_admin.product_variant.update.content.form.side_navigation' => [
                    'paygreen' => [
                        'template' => '@PayGreenSyliusPayumPlugin/admin/product_variant/form/side_navigation/paygreen.html.twig',
                        'priority' => -100,
                    ],
                ],
                'sylius_admin.product_variant.update.content.form.sections' => [
                    'paygreen' => [
                        'template' => '@PayGreenSyliusPayumPlugin/admin/product_variant/form/sections/paygreen.html.twig',
                        'priority' => -100,
                    ],
                ],
                'sylius_admin.product_variant.update.content.form.sections.paygreen' => [
                    'meal_voucher' => [
                        'template' => '@PayGreenSyliusPayumPlugin/admin/product_variant/form/sections/paygreen/meal_voucher.html.twig',
                        'priority' => 0,
                    ],
                ],
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
