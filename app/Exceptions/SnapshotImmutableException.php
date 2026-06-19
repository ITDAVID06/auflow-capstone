<?php

namespace App\Exceptions;

class SnapshotImmutableException extends \RuntimeException
{
    public function __construct(string $message = 'Snapshot is locked and cannot be modified.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
