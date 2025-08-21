<?php

namespace App\Service\Geocoding;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class GeoGouvGeocoderService
{
    private const BASE_URL = 'https://data.geopf.fr/geocodage/search';

    public function __construct(private readonly HttpClientInterface $http)
    {
    }
    public function     geocodeCity(string $cityName, ?string $postcode = null): ?array
    {
        // On cible l’index "poi" (lieux/unité admin) pour trouver les communes.
        // On limite à 1 résultat, et on peut aider la recherche avec le code postal si fourni.
        $params = [
            'q' => trim($postcode ? sprintf('%s %s', $cityName, $postcode) : $cityName),
            'index' => 'poi',
            'limit' => 1,
        ];

        $response = $this->http->request('GET', self::BASE_URL, ['query' => $params]);

        if (200 !== $response->getStatusCode()) {
            return null;
        }

        $data = $response->toArray(false);

        if (!isset($data['features'][0])) {
            return null;
        }

        $first = $data['features'][0];
        $coords = $first['geometry']['coordinates'] ?? null; // [lon, lat]
        $props = $first['properties'] ?? [];

        if (!$coords || !is_array($coords) || count($coords) < 2) {
            return null;
        }

        // L’API répond en [lon, lat] -> on convertit proprement.
        return [
            'lat' => (float)$coords[1],
            'lon' => (float)$coords[0],
            'label' => $props['label'] ?? ($props['name'] ?? $cityName),
            'city' => $props['city'] ?? null,
            'citycode' => $props['citycode'] ?? null,
            'score' => isset($props['score']) ? (float)$props['score'] : null,
        ];
    }
}
