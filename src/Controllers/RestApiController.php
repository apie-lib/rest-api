<?php
namespace Apie\RestApi\Controllers;

use Apie\Core\BoundedContext\BoundedContext;
use Apie\Core\BoundedContext\BoundedContextHashmap;
use Apie\Core\ContextBuilders\ContextBuilderFactory;
use Apie\Core\Exceptions\InvalidTypeException;
use Apie\RestApi\ActionProvider;
use Apie\RestApi\Actions\CreateObjectAction;
use Apie\RestApi\Exceptions\InvalidContentTypeException;
use Apie\RestApi\Interfaces\RestApiRouteDefinition;
use Apie\RestApi\RouteDefinitions\RestApiRouteDefinitionProvider;
use Apie\Serializer\DecoderHashmap;
use Apie\Serializer\EncoderHashmap;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class RestApiController
{
    public function __construct(
        private ContextBuilderFactory $contextBuilderFactory,
        private BoundedContextHashmap $boundedContextHashmap,
        private ActionProvider $actionProvider,
        private EncoderHashmap $encoderHashmap,
        private DecoderHashmap $decoderHashmap
    ) {
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $boundedContextId = $request->getAttribute('boundedContextId');
        $resourceName = $request->getAttribute('resourceName');
        $boundedContext = $this->boundedContextHashmap[$boundedContextId];
        $contentTypes = $request->getHeader('Content-Type');
        if (count($contentTypes) !== 1) {
            throw new InvalidContentTypeException($request->getHeaderLine('Content-Type'));
        }
        $contentType = reset($contentTypes);
        if (!isset($this->decoderHashmap[$contentType])) {
            throw new InvalidContentTypeException($contentType);
        }
        $decoder = $this->decoderHashmap[$contentType];
        $rawContents = $request->getMethod() === 'GET' ? [] : $decoder->decode((string) $request->getBody());
        if (!is_array($rawContents)) {
            throw new InvalidTypeException($rawContents, 'array');
        }

        $context = $this->contextBuilderFactory->createFromRequest(
            $request,
            [
                RestApiRouteDefinition::CONTENT_TYPE => $contentType,
                RestApiRouteDefinition::RAW_CONTENTS => $rawContents,
                RestApiRouteDefinition::RESOURCE_NAME => $resourceName,
                BoundedContext::class => $boundedContext,
                ...$request->getAttributes(),
            ]
        )->registerInstance($request);
        $action = $this->actionProvider->getAction($boundedContextId, $request->getAttribute('operationId'), $context);
        $data = ($action)($context, $rawContents);
        
        $contentType = $this->encoderHashmap->getAcceptedContentTypeForRequest($request);
        $encoder = $this->encoderHashmap[$contentType];
        
        $psr17Factory = new Psr17Factory();
        $responseBody = $psr17Factory->createStream($encoder->encode($data));
        return $psr17Factory->createResponse(201)
            ->withBody($responseBody)
            ->withHeader('Content-Type', $contentType);
    }
}
