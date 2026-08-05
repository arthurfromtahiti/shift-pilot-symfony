<?php

namespace App\Tests;

use App\Controller\DeliveryController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class DeliveryControllerTest extends TestCase
{
    public function testListReturnsAllDeliveries(): void
    {
        $controller = new DeliveryController();
        $response = $controller->list(new Request());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(3, $data);
    }

    public function testListFiltersByIsland(): void
    {
        $controller = new DeliveryController();
        $response = $controller->list(new Request(['island' => 'Moorea']));
        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data);
        $this->assertSame('Moorea', $data[0]['island']);
    }

    public function testListReturnsEmptyArrayForUnknownIsland(): void
    {
        $controller = new DeliveryController();
        $response = $controller->list(new Request(['island' => 'Tahiti']));
        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame([], $data);
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
