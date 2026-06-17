<?php

namespace App\Services\Exact;

use Exception;
use Picqer\Financials\Exact\ApiException;
use Throwable;

class ExactApiException extends Exception
{
    public function __construct(
        string $message,
        public readonly ?int $statusCode = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function fromPicqer(ApiException $exception): self
    {
        return new self(
            $exception->getMessage(),
            $exception->getCode() !== 0 ? $exception->getCode() : null,
            $exception,
        );
    }
}
