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

    public function testListFiltersByIslandCaseInsensitiveLowercase(): void
    {
        $controller = new DeliveryController();
        $response = $controller->list(new Request(['island' => 'moorea']));
        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data);
        $this->assertSame('Moorea', $data[0]['island']);
    }

    public function testListFiltersByIslandCaseInsensitiveUppercase(): void
    {
        $controller = new DeliveryController();
        $response = $controller->list(new Request(['island' => 'BORA BORA']));
        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data);
        $this->assertSame('Bora Bora', $data[0]['island']);
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

    public function testListFiltersByMaxEtaDays(): void
    {
        $controller = new DeliveryController();
        $response = $controller->list(new Request(['maxEtaDays' => '3']));
        $data = json_decode($response->getContent(), true);
        $this->assertCount(2, $data);
        foreach ($data as $delivery) {
            $this->assertLessThanOrEqual(3, $delivery['etaDays']);
        }
    }

    public function testListFiltersByMaxEtaDaysZero(): void
    {
        $controller = new DeliveryController();
        $response = $controller->list(new Request(['maxEtaDays' => '0']));
        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data);
        $this->assertSame('Moorea', $data[0]['island']);
    }

    public function testListFiltersByMaxEtaDaysAll(): void
    {
        $controller = new DeliveryController();
        $response = $controller->list(new Request(['maxEtaDays' => '5']));
        $data = json_decode($response->getContent(), true);
        $this->assertCount(3, $data);
    }

    public function testListFiltersByIslandAndMaxEtaDays(): void
    {
        $controller = new DeliveryController();
        $response = $controller->list(new Request(['island' => 'Bora Bora', 'maxEtaDays' => '3']));
        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data);
        $this->assertSame('Bora Bora', $data[0]['island']);
    }

    public function testListFiltersByIslandAndMaxEtaDaysNoResult(): void
    {
        $controller = new DeliveryController();
        $response = $controller->list(new Request(['island' => 'Huahine', 'maxEtaDays' => '3']));
        $data = json_decode($response->getContent(), true);
        $this->assertSame([], $data);
    }

    public function testUpdateStatusToLivre(): void
    {
        $controller = new DeliveryController();
        $request = new Request([], [], [], [], [], [], json_encode(['status' => 'livre']));
        $response = $controller->update(1, $request);
        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame(1, $data['id']);
        $this->assertSame('livre', $data['status']);
        $this->assertSame(0, $data['etaDays']);
    }

    public function testUpdateStatusToLivreResetEtaDaysToZero(): void
    {
        $controller = new DeliveryController();
        $request = new Request([], [], [], [], [], [], json_encode(['status' => 'livre']));
        $response = $controller->update(1, $request);
        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame(0, $data['etaDays']);
    }

    public function testUpdateStatusToLivreWithPositiveEtaDaysReturns400(): void
    {
        $controller = new DeliveryController();
        $request = new Request([], [], [], [], [], [], json_encode(['status' => 'livre', 'etaDays' => 5]));
        $response = $controller->update(1, $request);
        $this->assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Combinaison incohérente : une livraison livrée ne peut avoir un délai d\'arrivée positif', $data['error']);
    }

    public function testUpdateEtaDaysToPositiveOnLivreDeliveryReturns400(): void
    {
        $controller = new DeliveryController();
        $request = new Request([], [], [], [], [], [], json_encode(['etaDays' => 2]));
        $response = $controller->update(2, $request);
        $this->assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Combinaison incohérente : une livraison livrée ne peut avoir un délai d\'arrivée positif', $data['error']);
    }

    public function testUpdateStatusToEnTransit(): void
    {
        $controller = new DeliveryController();
        $request = new Request([], [], [], [], [], [], json_encode(['status' => 'en_transit']));
        $response = $controller->update(2, $request);
        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('en_transit', $data['status']);
    }

    public function testUpdateEtaDays(): void
    {
        $controller = new DeliveryController();
        $request = new Request([], [], [], [], [], [], json_encode(['etaDays' => 1]));
        $response = $controller->update(3, $request);
        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame(1, $data['etaDays']);
    }

    public function testUpdateStatusAndEtaDays(): void
    {
        $controller = new DeliveryController();
        $request = new Request([], [], [], [], [], [], json_encode(['status' => 'livre', 'etaDays' => 0]));
        $response = $controller->update(3, $request);
        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('livre', $data['status']);
        $this->assertSame(0, $data['etaDays']);
    }

    public function testUpdateReturns404ForUnknownId(): void
    {
        $controller = new DeliveryController();
        $request = new Request([], [], [], [], [], [], json_encode(['status' => 'livre']));
        $response = $controller->update(999, $request);
        $this->assertSame(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Livraison non trouvée', $data['error']);
    }

    public function testUpdateReturns400ForInvalidStatus(): void
    {
        $controller = new DeliveryController();
        $request = new Request([], [], [], [], [], [], json_encode(['status' => 'perdu']));
        $response = $controller->update(1, $request);
        $this->assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Statut invalide', $data['error']);
    }

    public function testUpdateReturns400ForNegativeEtaDays(): void
    {
        $controller = new DeliveryController();
        $request = new Request([], [], [], [], [], [], json_encode(['etaDays' => -1]));
        $response = $controller->update(1, $request);
        $this->assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('etaDays invalide', $data['error']);
    }

    public function testUpdateReturns400WhenNoFields(): void
    {
        $controller = new DeliveryController();
        $request = new Request([], [], [], [], [], [], json_encode([]));
        $response = $controller->update(1, $request);
        $this->assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Aucun champ à mettre à jour', $data['error']);
    }

    public function testUpdateReturns400ForInvalidJson(): void
    {
        $controller = new DeliveryController();
        $request = new Request([], [], [], [], [], [], 'not-json');
        $response = $controller->update(1, $request);
        $this->assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Corps de requête invalide', $data['error']);
    }

    public function testUpdateMutatesStateWithinSameInstance(): void
    {
        $controller = new DeliveryController();
        $request = new Request([], [], [], [], [], [], json_encode(['status' => 'livre']));
        $controller->update(1, $request);

        $response = $controller->pendingCount();
        $data = json_decode($response->getContent(), true);
        $this->assertSame(1, $data['count']);
    }

    public function testListReturns400ForNonNumericMaxEtaDays(): void
    {
        $controller = new DeliveryController();
        $response = $controller->list(new Request(['maxEtaDays' => 'abc']));
        $this->assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('maxEtaDays invalide', $data['error']);
    }

    public function testListReturns400ForNegativeMaxEtaDays(): void
    {
        $controller = new DeliveryController();
        $response = $controller->list(new Request(['maxEtaDays' => '-1']));
        $this->assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('maxEtaDays invalide', $data['error']);
    }

    public function testListReturns400WhenIslandIsArray(): void
    {
        $controller = new DeliveryController();
        $response = $controller->list(new Request(['island' => ['Moorea', 'Bora Bora']]));
        $this->assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
    }

    public function testListReturns400WhenMaxEtaDaysIsArray(): void
    {
        $controller = new DeliveryController();
        $response = $controller->list(new Request(['maxEtaDays' => ['3']]));
        $this->assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
    }

    public function testListEmptyIslandIgnoresFilter(): void
    {
        $controller = new DeliveryController();
        $response = $controller->list(new Request(['island' => '']));
        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(3, $data);
    }
}
