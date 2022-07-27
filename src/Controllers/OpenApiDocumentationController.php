<?php
namespace Apie\RestApi\Controllers;

use Apie\Core\BoundedContext\BoundedContextHashmap;
use Apie\RestApi\OpenApi\OpenApiGenerator;
use cebe\openapi\Writer;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class OpenApiDocumentationController
{
    public function __construct(
        private BoundedContextHashmap $boundedContextHashmap,
        private OpenApiGenerator $openApiGenerator
    ) {
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $boundedContextId = $request->getAttribute('boundedContextId');
        $yaml = $request->getAttribute('yaml');
        $boundedContext = $this->boundedContextHashmap[$boundedContextId];
        $openapi = $this->openApiGenerator->create($boundedContext);
        $psr17Factory = new Psr17Factory();
        $responseBody = $psr17Factory->createStream(
            $yaml ? Writer::writeToYaml($openapi) : Writer::writeToJson($openapi)
        );
        return $psr17Factory->createResponse(200)
            ->withBody($responseBody)
            ->withHeader('Content-Type', 'application/openapi+yaml');
    }
}
