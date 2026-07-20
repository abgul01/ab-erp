<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Business-rule exception rendered as the standard error envelope.
 * The machine-readable code lives in the public $bizCode property — we do NOT
 * override Throwable::getCode() because it is final on the base Exception.
 */
class BizException extends RuntimeException
{
    public function __construct(
        public string $bizCode,
        string $message,
        public int $status = 422,
    ) {
        parent::__construct($message);
    }

    public static function make(string $code, string $message, int $status = 422): self
    {
        return new self($code, $message, $status);
    }
}
