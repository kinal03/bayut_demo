<?php

namespace Modules\RealEstate\Services;

use OpenSearch\Client;

class PropertySearchService
{
    public function __construct(
        protected Client $client
    ) {}

    public function search(array $filters = [])
    {
        $must = [];
        $filter = [];

        if (!empty($filters['keyword'])) {
            $must[] = [
                'multi_match' => [
                    'query' => $filters['keyword'],
                    'fields' => [
                        'name^5',
                        'description^2',
                        'content',
                    ],
                ],
            ];
        }

        if (!empty($filters['tenant_id'])) {
            $filter[] = [
                'term' => [
                    'tenant_id' => $filters['tenant_id'],
                ],
            ];
        }

        if (!empty($filters['city'])) {
            $filter[] = [
                'term' => [
                    'city.keyword' => $filters['city'],
                ],
            ];
        }

        if (!empty($filters['state'])) {
            $filter[] = [
                'term' => [
                    'state.keyword' => $filters['state'],
                ],
            ];
        }

        if (!empty($filters['country'])) {
            $filter[] = [
                'term' => [
                    'country.keyword' => $filters['country'],
                ],
            ];
        }

        if (!empty($filters['type'])) {
            $filter[] = [
                'term' => [
                    'type' => $filters['type'],
                ],
            ];
        }

        if (!empty($filters['bedrooms'])) {
            $filter[] = [
                'term' => [
                    'total_bedroom' => $filters['bedrooms'],
                ],
            ];
        }

        if (!empty($filters['bathrooms'])) {
            $filter[] = [
                'term' => [
                    'total_bathroom' => $filters['bathrooms'],
                ],
            ];
        }

        if (
            !empty($filters['min_price']) ||
            !empty($filters['max_price'])
        ) {
            $range = [];

            if (!empty($filters['min_price'])) {
                $range['gte'] = $filters['min_price'];
            }

            if (!empty($filters['max_price'])) {
                $range['lte'] = $filters['max_price'];
            }

            $filter[] = [
                'range' => [
                    'price' => $range,
                ],
            ];
        }

        if (
            !empty($filters['min_square']) ||
            !empty($filters['max_square'])
        ) {
            $range = [];

            if (!empty($filters['min_square'])) {
                $range['gte'] = $filters['min_square'];
            }

            if (!empty($filters['max_square'])) {
                $range['lte'] = $filters['max_square'];
            }

            $filter[] = [
                'range' => [
                    'square' => $range,
                ],
            ];
        }

        if (!empty($filters['features'])) {
            $features = $filters['features'];

            // Handle string input: comma-separated or space-separated
            if (is_string($features)) {
                $features = array_filter(array_map('trim', preg_split('/[,\s]+/', $features, -1, PREG_SPLIT_NO_EMPTY)));
            }

            // Ensure it's an array and has values
            if (!is_array($features)) {
                $features = [];
            }

            $features = array_values(array_filter($features));

            if (!empty($features)) {
                $filter[] = [
                    'terms' => [
                        'features' => $features,
                    ],
                ];
            }
        }

        if (
            !empty($filters['lat']) &&
            !empty($filters['lon'])
        ) {
            $filter[] = [
                'geo_distance' => [
                    'distance' => '10km',
                    'location' => [
                        'lat' => (float) $filters['lat'],
                        'lon' => (float) $filters['lon'],
                    ],
                ],
            ];
        }

        $page = max(1, (int) ($filters['page'] ?? 1));
        $size = min(100, max(1, (int) ($filters['size'] ?? 20)));

        $query = [];

        if (empty($must) && empty($filter)) {
            $query = ['match_all' => (object) []];
        } else {
            $query = [
                'bool' => [
                    'must' => $must,
                    'filter' => $filter,
                ],
            ];
        }

        return $this->client->search([
            'index' => config('opensearch.index'),
            'body' => [
                'from' => ($page - 1) * $size,
                'size' => $size,
                'query' => $query,
                'sort' => [
                    [
                        '_score' => [
                            'order' => 'desc',
                        ],
                    ],
                    [
                        'created_at' => [
                            'order' => 'desc',
                        ],
                    ],
                ],
            ],
        ]);
    }
}