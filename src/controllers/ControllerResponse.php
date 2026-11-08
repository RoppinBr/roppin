<?php
class ControllerResponse
{
    private array $body;
    private int $statusCode;

    public function __construct(array $body, int $statusCode = 200)
    {
        $this->body = $body;
        $this->statusCode = $statusCode;
    }

    public function getBody(): array
    {
        return $this->body;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}