<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class RunCollection extends ResourceCollection
{
    public $collects = WorkflowRunResource::class;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
            'meta' => [
                'next_cursor' => method_exists($this->resource, 'nextCursor') ? $this->resource->nextCursor()?->encode() : null,
                'per_page' => method_exists($this->resource, 'perPage') ? $this->resource->perPage() : 15,
            ],
        ];
    }
}
