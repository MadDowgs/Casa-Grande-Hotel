<?php

namespace App\Exceptions;

use RuntimeException;

/** Business/validation error. On /api routes it is rendered as JSON {"error": "..."} with the HTTP status. */
class BookingException extends RuntimeException
{
    public function __construct(string $message, public int $status = 400, public array $extra = [])
    {
        parent::__construct($message);
    }

    public function report(): void
    {
        // expected errors: no need to write them to the log
    }

    public function render($request)
    {
        return response()->json(array_merge(['error' => $this->getMessage()], $this->extra), $this->status);
    }
}
