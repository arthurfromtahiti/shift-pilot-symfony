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

    public function testPendingCountReturnsCount(): void
    {
        $controller = new DeliveryController();
        $response = $controller->pendingCount();
        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('count', $data);
        $this->assertSame(2, $data['count']);
    }

    public function testShowReturnsDeliveryById(): void
    {
        $controller = new DeliveryController();
        $response = $controller->show(1);
        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame(1, $data['id']);
        $this->assertSame('Bora Bora', $data['island']);
    }

    public function testShowReturns404ForUnknownId(): void
    {
        $controller = new DeliveryController();
        $response = $controller->show(999);
        $this->assertSame(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Livraison non trouvée', $data['error']);
    }
}
