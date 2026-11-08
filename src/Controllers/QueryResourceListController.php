<?php
namespace Apie\RestApi\Controllers;

use Apie\Common\RequestBodyDecoder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class QueryResourceListController
{
    public function __construct(
        private readonly RestApiController $restApiController,
        private readonly RequestBodyDecoder $requestBodyDecoder
    ) {
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $queryParams = array_merge(
            $request->getQueryParams(),
            $this->requestBodyDecoder->decodeBody($request)
        );

        return ($this->restApiController)(
            $request->withMethod('GET')->withQueryParams($queryParams)
        );
    }
}
