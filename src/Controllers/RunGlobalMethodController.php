<?php
namespace Apie\RestApi\Controllers;

use Apie\Core\BoundedContext\BoundedContext;
use Apie\Core\BoundedContext\BoundedContextHashmap;
use Apie\Core\ContextBuilders\ContextBuilderFactory;
use Apie\RestApi\Actions\RunAction;
use Apie\RestApi\Exceptions\InvalidContentTypeException;
use Apie\RestApi\Interfaces\RestApiRouteDefinition;
use Apie\Serializer\DecoderHashmap;
use Apie\Serializer\EncoderHashmap;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class RunGlobalMethodController
{
    public function __construct(
        private ContextBuilderFactory $contextBuilderFactory,
        private BoundedContextHashmap $boundedContextHashmap,
        private RunAction $runAction,
        private EncoderHashmap $encoderHashmap,
        private DecoderHashmap $decoderHashmap
    ) {
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $boundedContextId = $request->getAttribute('boundedContextId');
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
        $rawContents = $decoder->decode((string) $request->getBody());
        $context = $this->contextBuilderFactory->createFromRequest(
            $request,
            [
                RestApiRouteDefinition::CONTENT_TYPE => $contentType,
                RestApiRouteDefinition::OPENAPI_POST => true,
                RestApiRouteDefinition::RAW_CONTENTS => $rawContents,
                BoundedContext::class => $boundedContext,
            ]
        )->registerInstance($request);
        $data = ($this->runAction)($context, $rawContents ?? []);
        $contentType = $this->encoderHashmap->getAcceptedContentTypeForRequest($request);
        $encoder = $this->encoderHashmap[$contentType];
        
        $psr17Factory = new Psr17Factory();
        $responseBody = $psr17Factory->createStream($encoder->encode($data));
        return $psr17Factory->createResponse(200)
            ->withBody($responseBody)
            ->withHeader('Content-Type', $contentType);
    }
}
