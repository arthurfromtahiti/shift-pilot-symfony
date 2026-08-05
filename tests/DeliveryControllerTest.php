<?php

namespace App\Tests;

use App\Controller\DeliveryController;
use PHPUnit\Framework\TestCase;

final class DeliveryControllerTest extends TestCase
{
    public function testListReturnsAllDeliveries(): void
    {
        $controller = new DeliveryController();
        $response = $controller->list();
        $data = json_decode($response->getContent(), true);
        $this->assertCount(3, $data);
    }

    public function testPendingExcludesDelivered(): void
    {
        $controller = new DeliveryController();
        $response = $controller->pending();
        $data = json_decode($response->getContent(), true);
        $this->assertCount(2, $data);
        foreach ($data as $delivery) {
            $this->assertNotSame('livre', $delivery['status']);
        }
    }

    public function testPendingCountReturnsNonDeliveredCount(): void
    {
        $controller = new DeliveryController();
        $response = $controller->pendingCount();
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('count', $data);
        $this->assertSame(2, $data['count']);
    }
}
