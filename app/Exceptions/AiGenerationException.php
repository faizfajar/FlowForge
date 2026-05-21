<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class AiGenerationException extends RuntimeException
{
    public function __construct(string $message, private readonly array $details = [])
    {
        parent::__construct($message);
    }

    public function details(): array
    {
        return $this->details;
    }
}
