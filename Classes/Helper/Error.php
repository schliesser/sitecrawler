<?php

declare(strict_types=1);

namespace Schliesser\Sitecrawler\Helper;

class Error
{
    public function __construct(protected readonly int $code, protected readonly string $message)
    {
    }

    public function getCode(): int
    {
        return $this->code;
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}
