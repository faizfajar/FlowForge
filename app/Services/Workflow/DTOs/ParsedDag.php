<?php

declare(strict_types=1);

namespace App\Services\Workflow\DTOs;

readonly class ParsedDag
{
    /**
     * @param  array<string, array<string, mixed>>  $steps
     * @param  array<string, array<int, string>>  $adjacencyList
     * @param  array<string, array<int, string>>  $reverseAdjacency
     * @param  array<int, array<int, string>>  $parallelGroups
     */
    public function __construct(
        public array $steps,
        public array $adjacencyList,
        public array $reverseAdjacency,
        public array $parallelGroups,
    ) {
    }
}
