<?php
namespace Apie\RestApi\Controllers;

use Apie\Common\ApieFacade;
use Apie\Common\ContextConstants;
use Apie\Core\BoundedContext\BoundedContext;
use Apie\Core\BoundedContext\BoundedContextHashmap;
use Apie\Core\ContextBuilders\ContextBuilderFactory;
use Apie\Core\Exceptions\InvalidTypeException;
use Apie\RestApi\Exceptions\InvalidContentTypeException;
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
        private ApieFacade $apieFacade,
        private EncoderHashmap $encoderHashmap,
        private DecoderHashmap $decoderHashmap
    ) {
    }

    /**
     * @return array<string|int, mixed>
     */
    private function decodeBody(ServerRequestInterface $request): array
    {
        $contentTypes = $request->getHeader('Content-Type');
        if (count($contentTypes) !== 1) {
            throw new InvalidContentTypeException($request->getHeaderLine('Content-Type'));
        }
        $contentType = reset($contentTypes);
        if (!isset($this->decoderHashmap[$contentType])) {
            throw new InvalidContentTypeException($contentType);
        }
        $decoder = $this->decoderHashmap[$contentType];
        $rawContents = $request->getMethod() === 'GET' ? $request->getQueryParams() : $decoder->decode((string) $request->getBody());
        if (!is_array($rawContents)) {
            throw new InvalidTypeException($rawContents, 'array');
        }
        return $rawContents;
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $boundedContextId = $request->getAttribute('boundedContextId');
        $boundedContext = $this->boundedContextHashmap[$boundedContextId];
        
        $rawContents = $this->decodeBody($request);

        $context = $this->contextBuilderFactory->createFromRequest(
            $request,
            [
                ContextConstants::RAW_CONTENTS => $rawContents,
                BoundedContext::class => $boundedContext,
                ...$request->getAttributes(),
            ]
        );

        $action = $this->apieFacade->getAction($boundedContextId, $request->getAttribute('operationId'), $context);
        $data = ($action)($context, $rawContents);

        return $this->createResponse($request, $data);
    }

    private function createResponse(ServerRequestInterface $request, mixed $output): ResponseInterface
    {
        $contentType = $this->encoderHashmap->getAcceptedContentTypeForRequest($request);
        $encoder = $this->encoderHashmap[$contentType];
        
        $psr17Factory = new Psr17Factory();
        $responseBody = $psr17Factory->createStream($encoder->encode($output));

        return $psr17Factory->createResponse(201)
            ->withBody($responseBody)
            ->withHeader('Content-Type', $contentType);
    }
}
