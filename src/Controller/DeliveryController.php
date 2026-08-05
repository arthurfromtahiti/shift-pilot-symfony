<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
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
    public function list(): JsonResponse
    {
        return new JsonResponse(self::DELIVERIES);
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

    #[Route('/deliveries/pending', methods: ['GET'])]
    public function pending(): JsonResponse
    {
        $pending = array_values(array_filter(
            self::DELIVERIES,
            fn(array $d) => $d['status'] !== 'livre'
        ));
        return new JsonResponse($pending);
    }
}
