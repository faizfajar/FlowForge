<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Workflow;

use App\Exceptions\DagCycleException;
use App\Exceptions\DagValidationException;
use App\Services\Workflow\DagParser;
use PHPUnit\Framework\TestCase;

class DagParserTest extends TestCase
{
    public function test_valid_linear_dag_returns_topological_order(): void
    {
        $parsed = (new DagParser())->parse($this->dag([
            ['id' => 'A', 'dependencies' => []],
            ['id' => 'B', 'dependencies' => ['A']],
            ['id' => 'C', 'dependencies' => ['B']],
        ]));

        $this->assertSame(['A', 'B', 'C'], (new DagParser())->topologicalSort($parsed->adjacencyList));
    }

    public function test_valid_parallel_dag_returns_parallel_groups(): void
    {
        $parsed = (new DagParser())->parse($this->dag([
            ['id' => 'A', 'dependencies' => []],
            ['id' => 'B', 'dependencies' => ['A']],
            ['id' => 'C', 'dependencies' => ['A']],
            ['id' => 'D', 'dependencies' => ['B', 'C']],
        ]));

        $this->assertSame([['A'], ['B', 'C'], ['D']], $parsed->parallelGroups);
    }

    public function test_dag_with_cycle_throws_cycle_exception(): void
    {
        $this->expectException(DagCycleException::class);

        (new DagParser())->parse($this->dag([
            ['id' => 'A', 'dependencies' => ['C']],
            ['id' => 'B', 'dependencies' => ['A']],
            ['id' => 'C', 'dependencies' => ['B']],
        ]));
    }

    public function test_missing_dependency_reference_throws_validation_exception(): void
    {
        $this->expectException(DagValidationException::class);

        (new DagParser())->parse($this->dag([
            ['id' => 'A', 'dependencies' => ['B']],
        ]));
    }

    public function test_duplicate_step_ids_throw_validation_exception(): void
    {
        $this->expectException(DagValidationException::class);

        (new DagParser())->parse($this->dag([
            ['id' => 'A', 'dependencies' => []],
            ['id' => 'A', 'dependencies' => []],
        ]));
    }

    public function test_single_isolated_step(): void
    {
        $parsed = (new DagParser())->parse($this->dag([
            ['id' => 'A', 'dependencies' => []],
        ]));

        $this->assertSame([['A']], $parsed->parallelGroups);
    }

    public function test_complex_dag_has_three_waves(): void
    {
        $parsed = (new DagParser())->parse($this->dag([
            ['id' => 'A', 'dependencies' => []],
            ['id' => 'B', 'dependencies' => []],
            ['id' => 'C', 'dependencies' => ['A']],
            ['id' => 'D', 'dependencies' => ['A']],
            ['id' => 'E', 'dependencies' => ['B']],
            ['id' => 'F', 'dependencies' => ['C', 'D']],
            ['id' => 'G', 'dependencies' => ['E']],
        ]));

        $this->assertSame([['A', 'B'], ['C', 'D', 'E'], ['F', 'G']], $parsed->parallelGroups);
    }

    public function test_empty_steps_array_throws_validation_exception(): void
    {
        $this->expectException(DagValidationException::class);

        (new DagParser())->parse(['steps' => []]);
    }

    public function test_step_depending_on_itself_throws_cycle_exception(): void
    {
        $this->expectException(DagCycleException::class);

        (new DagParser())->parse($this->dag([
            ['id' => 'A', 'dependencies' => ['A']],
        ]));
    }

    public function test_all_steps_in_parallel(): void
    {
        $parsed = (new DagParser())->parse($this->dag([
            ['id' => 'A', 'dependencies' => []],
            ['id' => 'B', 'dependencies' => []],
            ['id' => 'C', 'dependencies' => []],
        ]));

        $this->assertSame([['A', 'B', 'C']], $parsed->parallelGroups);
    }

    /**
     * @param  array<int, array<string, mixed>>  $steps
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function dag(array $steps): array
    {
        return ['steps' => $steps];
    }
}
