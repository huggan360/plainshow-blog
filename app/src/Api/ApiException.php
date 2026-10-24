<?php

namespace Blog\Api;

/** Thrown by handlers to answer with an error status and message. */
class ApiException extends \RuntimeException
{
    public function __construct(string $message, public readonly int $status = 400)
    {
        parent::__construct($message);
    }
}
