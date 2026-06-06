<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Application\Marketing\MetaConversionsApi;
use PHPUnit\Framework\TestCase;

final class MetaPurchasePayloadTest extends TestCase
{
    public function testPurchaseEventIdIsDeterministicBySaleId(): void
    {
        $first = MetaConversionsApi::purchaseEventId(42, 'CR-2026-001');
        $second = MetaConversionsApi::purchaseEventId(42, 'CR-2026-001');

        self::assertSame($first, $second);
        self::assertStringStartsWith('purchase-', $first);
    }

    public function testPurchaseCustomDataFollowsMetaExample(): void
    {
        $customData = MetaConversionsApi::buildPurchaseCustomDataFromSale([
            'id_sale' => 7,
            'code_sale' => 'CR-007',
            'total_sale' => 65000,
            'quantity_sale' => 1,
        ]);

        self::assertSame(['currency' => 'COP', 'value' => 65000.0], $customData);
    }

    public function testPurchaseUserDataIncludesFullCustomerAndCheckoutContext(): void
    {
        $customer = MetaConversionsApi::userDataFromCustomer([
            'email_customer' => 'test@example.com',
            'phone_customer' => '3001234567',
            'name_customer' => 'Juan Perez',
            'lastname_customer' => 'Garcia',
            'city_customer' => 'Medellin',
            'department_customer' => 'Antioquia',
            'id_customer_sale' => '55',
        ]);

        self::assertArrayHasKey('em', $customer);
        self::assertArrayHasKey('ph', $customer);
        self::assertArrayHasKey('fn', $customer);
        self::assertArrayHasKey('external_id', $customer);

        $checkout = MetaConversionsApi::userDataFromStoredMeta([
            'fbp' => 'fb.1.123.abc',
            'client_ip_address' => '190.0.0.1',
        ]);

        self::assertSame('190.0.0.1', $checkout['client_ip_address']);
    }

    public function testPurchaseIsServerOnlyNotBrowserTrackEvent(): void
    {
        self::assertFalse(MetaConversionsApi::isAllowedTrackEvent('Purchase'));
        self::assertTrue(MetaConversionsApi::isStandardEvent('Purchase'));
    }
}
