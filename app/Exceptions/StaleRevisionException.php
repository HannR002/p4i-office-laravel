<?php

namespace App\Exceptions;

use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class StaleRevisionException extends RuntimeException implements HttpExceptionInterface
{
    public function getStatusCode(): int
    {
        return 409;
    }

    public function getHeaders(): array
    {
        return [];
    }
}
