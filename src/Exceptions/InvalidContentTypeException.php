<?php
namespace Apie\RestApi\Exceptions;

use Apie\Core\Exceptions\ApieException;
use Apie\Core\Exceptions\HttpStatusCodeException;

class InvalidContentTypeException extends ApieException implements HttpStatusCodeException
{
    public function __construct(string $contentType)
    {
        parent::__construct(sprintf('Invalid content-type header: "%s"', $contentType));
    }

    public function getStatusCode(): int
    {
        return 415;
    }
}
