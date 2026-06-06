<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Application\Webhook\OpenPayWebhookEventTypes;
use PHPUnit\Framework\TestCase;

final class OpenPayWebhookEventTypesTest extends TestCase
{
    public function testApprovedEventsContainChargeSucceeded(): void
    {
        self::assertContains(OpenPayWebhookEventTypes::CHARGE_SUCCEEDED, OpenPayWebhookEventTypes::approvedEvents());
    }

    public function testRejectedEventsContainFailureAndCancellation(): void
    {
        $rejected = OpenPayWebhookEventTypes::rejectedEvents();
        self::assertContains(OpenPayWebhookEventTypes::CHARGE_FAILED, $rejected);
        self::assertContains(OpenPayWebhookEventTypes::CHARGE_CANCELLED, $rejected);
        self::assertContains(OpenPayWebhookEventTypes::CHARGE_REFUNDED, $rejected);
    }

    public function testIgnoredEventsDoNotOverlapWithApproved(): void
    {
        $overlap = array_intersect(
            OpenPayWebhookEventTypes::ignoredEvents(),
            OpenPayWebhookEventTypes::approvedEvents()
        );
        self::assertSame([], array_values($overlap));
    }

    public function testChargeCreatedIsIgnoredNotApproved(): void
    {
        self::assertContains(OpenPayWebhookEventTypes::CHARGE_CREATED, OpenPayWebhookEventTypes::ignoredEvents());
        self::assertNotContains(OpenPayWebhookEventTypes::CHARGE_CREATED, OpenPayWebhookEventTypes::approvedEvents());
    }

    public function testForRegistrationIncludesVerificationAndMainEvents(): void
    {
        $events = OpenPayWebhookEventTypes::forRegistration();
        self::assertContains(OpenPayWebhookEventTypes::VERIFICATION, $events);
        self::assertContains(OpenPayWebhookEventTypes::CHARGE_SUCCEEDED, $events);
        self::assertContains(OpenPayWebhookEventTypes::CHARGE_FAILED, $events);
    }
}
