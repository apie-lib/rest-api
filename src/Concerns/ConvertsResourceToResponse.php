<?php
namespace Apie\RestApi\Concerns;

use Apie\Core\Context\ApieContext;
use Apie\Core\ContextBuilders\ContextBuilderInterface;
use Apie\Serializer\Serializer;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;

trait ConvertsResourceToResponse
{
    private Serializer $serializer;

    public function toResponse(ApieContext $context): ResponseInterface
    {
        $resource = $context->getContext(ContextBuilderInterface::RESOURCE);
        // TODO: read accept header for $context
        $data = $this->serializer->normalize($resource, $context);
        $psr17Factory = new Psr17Factory();
        $responseBody = $psr17Factory->createStream(json_encode($data, JSON_UNESCAPED_SLASHES));
        return $psr17Factory->createResponse(201)
            ->withBody($responseBody)
            ->withHeader('Content-Type', 'application/json');
    }
}
