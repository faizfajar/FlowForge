<?php

declare(strict_types=1);

namespace App\Services\Workflow;

use App\Exceptions\DagCycleException;
use App\Exceptions\DagValidationException;
use App\Enums\StepType;
use App\Services\Workflow\DTOs\ParsedDag;

class DagParser
{
    /**
     * @param  array<string, mixed>  $dagDefinition
     */
    public function parse(array $dagDefinition): ParsedDag
    {
        $rawSteps = $dagDefinition['steps'] ?? null;

        if (! is_array($rawSteps) || $rawSteps === []) {
            throw new DagValidationException('DAG must contain at least one step.');
        }

        $steps = [];
        $adjacencyList = [];
        $reverseAdjacency = [];

        foreach ($rawSteps as $step) {
            if (! is_array($step) || ! isset($step['id']) || ! is_string($step['id']) || $step['id'] === '') {
                throw new DagValidationException('Every DAG step must have a non-empty string id.');
            }

            if (array_key_exists($step['id'], $steps)) {
                throw new DagValidationException("Duplicate DAG step id [{$step['id']}].");
            }

            if (! isset($step['type']) || ! is_string($step['type']) || StepType::tryFrom($step['type']) === null) {
                throw new DagValidationException("Step [{$step['id']}] has an unsupported type.");
            }

            if (! isset($step['name']) || ! is_string($step['name']) || trim($step['name']) === '') {
                throw new DagValidationException("Step [{$step['id']}] must have a non-empty name.");
            }

            if (! isset($step['config']) || ! is_array($step['config'])) {
                throw new DagValidationException("Step [{$step['id']}] must have a config object.");
            }

            $steps[$step['id']] = $step;
            $adjacencyList[$step['id']] = [];
            $reverseAdjacency[$step['id']] = [];
        }

        foreach ($steps as $stepId => $step) {
            $dependencies = $step['dependencies'] ?? [];

            if (! is_array($dependencies)) {
                throw new DagValidationException("Dependencies for step [{$stepId}] must be an array.");
            }

            foreach ($dependencies as $dependencyId) {
                if (! is_string($dependencyId) || $dependencyId === '') {
                    throw new DagValidationException("Dependency references for step [{$stepId}] must be non-empty strings.");
                }

                if (! array_key_exists($dependencyId, $steps)) {
                    throw new DagValidationException("Step [{$stepId}] depends on missing step [{$dependencyId}].");
                }

                $adjacencyList[$dependencyId][] = $stepId;
                $reverseAdjacency[$stepId][] = $dependencyId;
            }
        }

        if ($this->detectCycle($adjacencyList)) {
            throw new DagCycleException('DAG contains a cycle.');
        }

        $topologicalOrder = $this->topologicalSort($adjacencyList);

        return new ParsedDag(
            steps: $steps,
            adjacencyList: $adjacencyList,
            reverseAdjacency: $reverseAdjacency,
            parallelGroups: $this->getParallelGroups($topologicalOrder, $adjacencyList),
        );
    }

    /**
     * @param  array<string, array<int, string>>  $adjacencyList
     */
    public function detectCycle(array $adjacencyList): bool
    {
        return count($this->sortWithKahn($adjacencyList)) !== count($adjacencyList);
    }

    /**
     * @param  array<string, array<int, string>>  $adjacencyList
     * @return array<int, string>
     */
    public function topologicalSort(array $adjacencyList): array
    {
        return $this->sortWithKahn($adjacencyList);
    }

    /**
     * @param  array<int, string>  $topologicalOrder
     * @param  array<string, array<int, string>>  $adjacencyList
     * @return array<int, array<int, string>>
     */
    public function getParallelGroups(array $topologicalOrder, array $adjacencyList): array
    {
        $inDegree = array_fill_keys(array_keys($adjacencyList), 0);

        foreach ($adjacencyList as $children) {
            foreach ($children as $child) {
                $inDegree[$child]++;
            }
        }

        $remaining = array_fill_keys($topologicalOrder, true);
        $groups = [];

        while ($remaining !== []) {
            $group = [];

            foreach ($topologicalOrder as $stepId) {
                if (($remaining[$stepId] ?? false) && $inDegree[$stepId] === 0) {
                    $group[] = $stepId;
                }
            }

            if ($group === []) {
                break;
            }

            foreach ($group as $stepId) {
                unset($remaining[$stepId]);

                foreach ($adjacencyList[$stepId] as $child) {
                    $inDegree[$child]--;
                }
            }

            $groups[] = $group;
        }

        return $groups;
    }

    /**
     * @param  array<string, array<int, string>>  $adjacencyList
     * @return array<int, string>
     */
    private function sortWithKahn(array $adjacencyList): array
    {
        $inDegree = array_fill_keys(array_keys($adjacencyList), 0);

        foreach ($adjacencyList as $children) {
            foreach ($children as $child) {
                $inDegree[$child]++;
            }
        }

        $queue = [];
        foreach ($inDegree as $stepId => $degree) {
            if ($degree === 0) {
                $queue[] = $stepId;
            }
        }

        $order = [];
        while ($queue !== []) {
            $stepId = array_shift($queue);
            $order[] = $stepId;

            foreach ($adjacencyList[$stepId] as $child) {
                $inDegree[$child]--;

                if ($inDegree[$child] === 0) {
                    $queue[] = $child;
                }
            }
        }

        return $order;
    }
}
