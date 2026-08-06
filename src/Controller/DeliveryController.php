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
    private const DEFAULT_DELIVERIES = [
        ['id' => 1, 'island' => 'Bora Bora', 'status' => 'en_transit', 'etaDays' => 3],
        ['id' => 2, 'island' => 'Moorea', 'status' => 'livre', 'etaDays' => 0],
        ['id' => 3, 'island' => 'Huahine', 'status' => 'en_transit', 'etaDays' => 5],
    ];

    private const VALID_STATUSES = ['en_transit', 'livre'];

    private array $deliveries;

    public function __construct()
    {
        $this->deliveries = self::DEFAULT_DELIVERIES;
    }

    #[Route('/deliveries', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $island = $request->query->get('island');
        $maxEtaDaysRaw = $request->query->get('maxEtaDays');
        $maxEtaDays = is_numeric($maxEtaDaysRaw) ? (int) $maxEtaDaysRaw : null;

        $filtered = array_values(array_filter(
            $this->deliveries,
            fn(array $d) =>
                ($island === null || strcasecmp($d['island'], $island) === 0) &&
                ($maxEtaDays === null || $d['etaDays'] <= $maxEtaDays)
        ));

        return new JsonResponse($filtered);
    }

    #[Route('/deliveries/pending', methods: ['GET'])]
    public function pending(): JsonResponse
    {
        $pending = array_values(array_filter(
            $this->deliveries,
            fn(array $d) => $d['status'] !== 'livre'
        ));
        return new JsonResponse($pending);
    }

    #[Route('/deliveries/pending/count', methods: ['GET'])]
    public function pendingCount(): JsonResponse
    {
        $count = count(array_filter(
            $this->deliveries,
            fn(array $d) => $d['status'] !== 'livre'
        ));
        return new JsonResponse(['count' => $count]);
    }

    #[Route('/deliveries/{id}', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): JsonResponse
    {
        foreach ($this->deliveries as $delivery) {
            if ($delivery['id'] === $id) {
                return new JsonResponse($delivery);
            }
        }
        return new JsonResponse(['error' => 'Livraison non trouvée'], 404);
    }

    #[Route('/deliveries/{id}', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $body = json_decode($request->getContent(), true);
        if (!is_array($body)) {
            return new JsonResponse(['error' => 'Corps de requête invalide'], 400);
        }

        $hasStatus = array_key_exists('status', $body);
        $hasEtaDays = array_key_exists('etaDays', $body);

        if (!$hasStatus && !$hasEtaDays) {
            return new JsonResponse(['error' => 'Aucun champ à mettre à jour'], 400);
        }

        if ($hasStatus && !in_array($body['status'], self::VALID_STATUSES, true)) {
            return new JsonResponse(['error' => 'Statut invalide'], 400);
        }

        if ($hasEtaDays && (!is_int($body['etaDays']) || $body['etaDays'] < 0)) {
            return new JsonResponse(['error' => 'etaDays invalide'], 400);
        }

        foreach ($this->deliveries as &$delivery) {
            if ($delivery['id'] === $id) {
                if ($hasStatus) {
                    $delivery['status'] = $body['status'];
                }
                if ($hasEtaDays) {
                    $delivery['etaDays'] = $body['etaDays'];
                }
                return new JsonResponse($delivery);
            }
        }

        return new JsonResponse(['error' => 'Livraison non trouvée'], 404);
    }
}
