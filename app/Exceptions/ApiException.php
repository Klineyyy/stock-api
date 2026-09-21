<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * A failure the API explains to the caller: a readable message plus a stable `code` a client can
 * switch on ("insufficient_stock") without parsing the English.
 */
class ApiException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status, public readonly string $errorCode)
    {
        parent::__construct($message);
    }

    public static function notFound(string $message): self
    {
        return new self($message, 404, 'not_found');
    }

    public static function insufficientStock(string $message): self
    {
        return new self($message, 422, 'insufficient_stock');
    }

    public static function conflict(string $message): self
    {
        return new self($message, 409, 'conflict');
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), 'code' => $this->errorCode], $this->status);
    }
}
