<?php
namespace Apie\RestApi\Controllers;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class SwaggerUIController
{
    private readonly string $htmlPath;

    public function __construct(
        private readonly string $baseUrl,
        ?string $htmlPath = null
    ) {
        $this->htmlPath = null === $htmlPath ? __DIR__ . '/../../resources/swagger-ui/index.html' : $htmlPath;
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $boundedContextId = $request->getAttribute('boundedContextId');
        $search = [
            '%%OPENAPI_YAML%%'
        ];
        $replace = [
            '/' . trim($this->baseUrl) . '/' . $boundedContextId . '/openapi.yaml'
        ];

        $responseBody = str_replace($search, $replace, file_get_contents($this->htmlPath));
        $psr17Factory = new Psr17Factory();
        return $psr17Factory->createResponse(200)
            ->withBody($psr17Factory->createStream($responseBody))
            ->withHeader('Content-Type', 'text/html');
    }
}
