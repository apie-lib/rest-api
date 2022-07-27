<?php
namespace Apie\RestApi\Exceptions;

use Apie\Core\Exceptions\ApieException;

class InvalidContentTypeException extends ApieException
{
    public function __construct(string $contentType)
    {
        parent::__construct(sprintf('Invalid content-type header: "%s"', $contentType));
    }
}
