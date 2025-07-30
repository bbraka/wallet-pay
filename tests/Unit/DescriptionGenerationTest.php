<?php

namespace Tests\Unit;

use App\Enums\OrderType;
use App\Models\User;
use App\Services\Merchant\OrdersService;
use Tests\TestCase;

class DescriptionGenerationTest extends TestCase
{
    public function test_generates_descriptive_order_descriptions(): void
    {
        $service = new OrdersService();
        $targetUser = new User(['email' => 'user@test.com']);
        $receiverUser = new User(['email' => 'receiver@test.com']);

        // Test admin top-up description
        $adminDescription = $service->generateOrderDescription(
            OrderType::ADMIN_TOP_UP,
            $targetUser,
            null,
            123
        );
        $this->assertEquals('Admin top-up for user@test.com - Order #123', $adminDescription);

        // Test user top-up description
        $userTopUpDescription = $service->generateOrderDescription(
            OrderType::USER_TOP_UP,
            $targetUser,
            null,
            456
        );
        $this->assertEquals('Order purchased funds #456 - User top-up by user@test.com', $userTopUpDescription);

        // Test internal transfer description
        $transferDescription = $service->generateOrderDescription(
            OrderType::INTERNAL_TRANSFER,
            $targetUser,
            $receiverUser,
            789
        );
        $this->assertEquals('Received funds from user@test.com to receiver@test.com', $transferDescription);

        // Test user withdrawal description
        $withdrawalDescription = $service->generateOrderDescription(
            OrderType::USER_WITHDRAWAL,
            $targetUser,
            null,
            101
        );
        $this->assertEquals('User withdrawal request by user@test.com - Order #101', $withdrawalDescription);

        // Test custom description takes precedence
        $customDescription = $service->generateOrderDescription(
            OrderType::ADMIN_TOP_UP,
            $targetUser,
            null,
            123,
            'Custom description provided'
        );
        $this->assertEquals('Custom description provided', $customDescription);
    }
}