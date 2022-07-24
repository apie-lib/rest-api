<?php
namespace Apie\RestApi\Actions;

use Apie\Core\Actions\ActionInterface;
use Apie\Core\Actions\HasRouteDefinition;
use Apie\Core\BoundedContext\BoundedContext;
use Apie\Core\Context\ApieContext;
use Apie\Core\ContextBuilders\ContextBuilderInterface;
use Apie\Core\Enums\RequestMethod;
use Apie\Core\ValueObjects\UrlRouteDefinition;
use Apie\RestApi\OpenApi\OpenApiGenerator;
use cebe\openapi\spec\OpenApi;
use cebe\openapi\Writer;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use ReflectionClass;

/**
 * Add an OpenAPI specification in yaml format.
 */
class OpenApiDocumentation implements ActionInterface, HasRouteDefinition
{
    public function __construct(private OpenApiGenerator $openApiGenerator)
    {
    }

    public function getInputType(): ?ReflectionClass
    {
        return null;
    }

    public function getOutputType(): ReflectionClass
    {
        return new ReflectionClass(OpenApi::class);
    }

    public function process(ApieContext $context): ApieContext
    {
        return $context->withContext(
            ContextBuilderInterface::RESOURCE,
            $this->openApiGenerator->create($context->getContext(BoundedContext::class))
        );
    }

    public function getValue(ApieContext $context): OpenApi
    {
        return $context->getContext(ContextBuilderInterface::RESOURCE);
    }

    public function getMethod(): RequestMethod
    {
        return RequestMethod::GET;
    }
    public function getUrl(): UrlRouteDefinition
    {
        return new UrlRouteDefinition('/openapi.yaml');
    }

    public function toResponse(ApieContext $context): ResponseInterface
    {
        /** @var OpenApi $resource */
        $resource = $context->getContext(ContextBuilderInterface::RESOURCE);
        $psr17Factory = new Psr17Factory();
        $responseBody = $psr17Factory->createStream(Writer::writeToYaml($resource));
        return $psr17Factory->createResponse(201)
            ->withBody($responseBody)
            ->withHeader('Content-Type', 'application/openapi+yaml');
    }
}
