<?php
namespace Apie\RestApi\Controllers;

use Apie\Core\BoundedContext\BoundedContextHashmap;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class SwaggerUIController
{
    private readonly string $htmlPath;

    public function __construct(
        private readonly string $baseUrl,
        private readonly BoundedContextHashmap $boundedContextHashmap,
        ?string $htmlPath = null
    ) {
        $this->htmlPath = null === $htmlPath ? __DIR__ . '/../../resources/swagger-ui/index.html' : $htmlPath;
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $boundedContextId = $request->getAttribute('boundedContextId');
        $search = [
            '%%OPENAPI_YAML%%',
            '%%OPENAPIS_AVAILABLE%%',
        ];
        $boundedContext = $this->boundedContextHashmap[$boundedContextId];
        $urls = [
            [
                'url' => '/' . trim($this->baseUrl, '/') . '/' . $boundedContextId . '/openapi.yaml',
                'name' => $boundedContextId . '(' . $boundedContext->actions->count() . ' actions, ' . $boundedContext->resources->count() . ' resources)',
            ]
        ];
        
        foreach ($this->boundedContextHashmap as $availableBoundedContextId => $boundedContext) {
            if ($boundedContextId !== $availableBoundedContextId) {
                $urls[] = [
                    'url' => '/' . trim($this->baseUrl, '/') . '/' . $availableBoundedContextId . '/openapi.yaml',
                    'name' => $availableBoundedContextId . '(' . $boundedContext->actions->count() . ' actions, ' . $boundedContext->resources->count() . ' resources)',
                ];
            }
        }
        $replace = [
            '/' . trim($this->baseUrl, '/') . '/' . $boundedContextId . '/openapi.yaml',
            json_encode($urls),
        ];

        $responseBody = str_replace($search, $replace, file_get_contents($this->htmlPath));
        $psr17Factory = new Psr17Factory();
        return $psr17Factory->createResponse(200)
            ->withBody($psr17Factory->createStream($responseBody))
            ->withHeader('Content-Type', 'text/html');
    }
}
