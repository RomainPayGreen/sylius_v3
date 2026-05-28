# Sylius PayGreen Payum Plugin

Fresh Sylius payment plugin skeleton for PayGreen hosted payment pages.

The integration uses `paygreen/paygreen-php` as the only communication layer with PayGreen. The plugin builds SDK models, calls SDK client methods, and maps SDK response data back into Payum/Sylius payment details.

## Targets

- Sylius `^1.14`, prepared for `^2.0`
- Symfony `^6.4`, prepared for `^7.0`
- PHP `^8.2`, prepared for PHP `8.3+`

## Includes

- Sylius plugin bundle skeleton
- Payum gateway factory named `paygreen`
- Sylius payment method configuration form
- Payum capture, notify, and status actions
- Sylius order/payment to PayGreen SDK `PaymentOrder` conversion
- Redirect to PayGreen `hosted_payment_url`
- Return controller skeleton
- Webhook controller skeleton
- PayGreen status mapping
- Basic PHPUnit tests

## Configuration

Register the bundle in the host application:

```php
// config/bundles.php
return [
    PayGreen\SyliusPayumPlugin\PayGreenSyliusPayumPlugin::class => ['all' => true],
];
```

Import routes:

```yaml
# config/routes/paygreen.yaml
paygreen_sylius_payum_plugin:
    resource: '@PayGreenSyliusPayumPlugin/Resources/config/routes.yaml'
```

Install an HTTPlug-compatible HTTP client for the PayGreen SDK, for example:

```bash
composer require php-http/curl-client
```

Then add a Sylius payment method using the `PayGreen` gateway and configure:

- Shop ID
- Public key
- Secret key
- Environment

## Meal vouchers

To flag food products as eligible for meal vouchers, make your Sylius product variant entity implement the plugin interface and use the provided trait:

```php
use PayGreen\SyliusPayumPlugin\Entity\MealVoucherAwareInterface;
use PayGreen\SyliusPayumPlugin\Entity\MealVoucherAwareTrait;
use Sylius\Component\Core\Model\ProductVariant as BaseProductVariant;

class ProductVariant extends BaseProductVariant implements MealVoucherAwareInterface
{
    use MealVoucherAwareTrait;
}
```

Then generate and run the Doctrine migration for the `meal_voucher_compatible` boolean column.

When at least one order item uses an eligible variant, the plugin sends PayGreen V3 `eligible_amounts` for the meal voucher platforms supported by the SDK (`swile`, `restoflash`, `conecs`). API calls still go only through `paygreen/paygreen-php`.
