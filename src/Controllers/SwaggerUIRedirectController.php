<?php
namespace Apie\RestApi\Controllers;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class SwaggerUIRedirectController
{
    private readonly string $htmlPath;

    public function __construct(
        ?string $htmlPath = null
    ) {
        $this->htmlPath = null === $htmlPath ? __DIR__ . '/../../resources/swagger-ui/oauth2-redirect.html' : $htmlPath;
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $psr17Factory = new Psr17Factory();
        return $psr17Factory->createResponse(200)
            ->withBody($psr17Factory->createStream(file_get_contents($this->htmlPath)))
            ->withHeader('Content-Type', 'text/html');
    }
}
