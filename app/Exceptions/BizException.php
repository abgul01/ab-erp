<?php

namespace App\Exceptions;

use App\Support\ErrorCodes;
use RuntimeException;

/**
 * Business-rule exception rendered as the standard error envelope.
 *
 * The machine-readable code lives in the public $bizCode property — we do NOT
 * override Throwable::getCode() because it is final on the base Exception.
 *
 * The HTTP status follows from the code unless one is passed: `*_LOCKED` is
 * 423, `*_DUP` and `*_EXISTS` are 409, `*_SCOPE` and `*_FORBIDDEN` are 403.
 * See App\Support\ErrorCodes.
 */
class BizException extends RuntimeException
{
    public int $status;

    public function __construct(
        public string $bizCode,
        string $message,
        ?int $status = null,
    ) {
        parent::__construct($message);
        $this->status = $status ?? ErrorCodes::statusFor($bizCode);
    }

    public static function make(string $code, string $message, ?int $status = null): self
    {
        return new self($code, $message, $status);
    }

    /** Which part of the system refused, for logs and support triage. */
    public function module(): string
    {
        return ErrorCodes::moduleOf($this->bizCode);
    }
}
