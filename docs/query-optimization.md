# Query Optimization Notes

## Optimized Query

The dashboard and monitoring views frequently need active workflow runs for a single tenant. The hot-path query is:

```sql
SELECT started_at
FROM workflow_runs
WHERE tenant_id = :tenant_id
  AND status = 'running'
ORDER BY started_at DESC NULLS LAST
LIMIT 20;
```

## Supporting Index

`database/migrations/2024_01_01_000005_create_workflow_runs_table.php` creates this partial index:

```sql
CREATE INDEX workflow_runs_running_partial_index
ON workflow_runs (tenant_id, started_at)
WHERE status = 'running';
```

This keeps the index small and focused on the runtime status used most often by the dashboard and queue monitoring surfaces.

## EXPLAIN Evidence

Captured from the local PostgreSQL container:

```sql
EXPLAIN
SELECT started_at
FROM workflow_runs
WHERE tenant_id = (SELECT id FROM tenants LIMIT 1)
  AND status = 'running'
ORDER BY started_at DESC NULLS LAST
LIMIT 20;
```

Planner output:

```text
Limit  (cost=8.32..8.33 rows=1 width=8)
  InitPlan 1 (returns $0)
    ->  Limit  (cost=0.00..0.15 rows=1 width=16)
          ->  Seq Scan on tenants  (cost=0.00..10.70 rows=70 width=16)
  ->  Sort  (cost=8.17..8.17 rows=1 width=8)
        Sort Key: workflow_runs.started_at DESC NULLS LAST
        ->  Index Only Scan using workflow_runs_running_partial_index on workflow_runs  (cost=0.14..8.16 rows=1 width=8)
              Index Cond: (tenant_id = $0)
```

The important part is the `Index Only Scan using workflow_runs_running_partial_index`, which confirms PostgreSQL is using the partial index for the active-run lookup.
