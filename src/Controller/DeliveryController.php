<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Suivi des livraisons inter-îles.
 */
class DeliveryController
{
    private const DELIVERIES = [
        ['id' => 1, 'island' => 'Bora Bora', 'status' => 'en_transit', 'etaDays' => 3],
        ['id' => 2, 'island' => 'Moorea', 'status' => 'livre', 'etaDays' => 0],
        ['id' => 3, 'island' => 'Huahine', 'status' => 'en_transit', 'etaDays' => 5],
    ];

    #[Route('/deliveries', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $island = $request->query->get('island');

        if ($island === null) {
            return new JsonResponse(self::DELIVERIES);
        }

        $filtered = array_values(array_filter(
            self::DELIVERIES,
            fn(array $d) => strcasecmp($d['island'], $island) === 0
        ));

        return new JsonResponse($filtered);
    }

    #[Route('/deliveries/pending', methods: ['GET'])]
    public function pending(): JsonResponse
    {
        $pending = array_values(array_filter(
            self::DELIVERIES,
            fn(array $d) => $d['status'] !== 'livre'
        ));
        return new JsonResponse($pending);
    }

    #[Route('/deliveries/pending/count', methods: ['GET'])]
    public function pendingCount(): JsonResponse
    {
        $count = count(array_filter(
            self::DELIVERIES,
            fn(array $d) => $d['status'] !== 'livre'
        ));
        return new JsonResponse(['count' => $count]);
    }

    #[Route('/deliveries/{id}', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): JsonResponse
    {
        foreach (self::DELIVERIES as $delivery) {
            if ($delivery['id'] === $id) {
                return new JsonResponse($delivery);
            }
        }
        return new JsonResponse(['error' => 'Livraison non trouvée'], 404);
    }
}
