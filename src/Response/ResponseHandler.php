<?php

namespace KaspiQrSdk\Response;

use Psr\Http\Message\ResponseInterface;

/**
 * Handles HTTP Response processing and provides utility methods
 * to retrieve status code and response contents.
 */
class ResponseHandler
{
    const HTTP_INTERNAL_SERVER_ERROR = 500;

    /**
     * @var ResponseInterface|null
     */
    private $response;

    public function __construct(?ResponseInterface $response)
    {
        $this->response = $response;
    }

    public function getStatusCode(): int
    {
        if ($this->response !== null) {
            return $this->response->getStatusCode();
        }

        return self::HTTP_INTERNAL_SERVER_ERROR;
    }

    public function getContents(): string
    {
        if ($this->response !== null) {
            $contents = $this->response->getBody()->getContents();
            $this->response->getBody()->rewind();
            return $contents;
        }

        return '';
    }
}