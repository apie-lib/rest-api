<?php
namespace Apie\RestApi\Actions;

use Apie\Core\Context\ApieContext;
use Apie\Core\ContextBuilders\ContextBuilderInterface;
use Apie\Core\Enums\RequestMethod;
use Apie\Core\ValueObjects\UrlRouteDefinition;
use Apie\RestApi\Concerns\ConvertsResourceToResponse;
use Apie\RestApi\Interfaces\RestApiRouteDefinition;
use Apie\RestApi\Lists\StringList;
use Apie\Serializer\Serializer;
use ReflectionClass;

/**
 * Action to create a new object.
 */
class CreateObjectAction implements RestApiRouteDefinition
{
    use ConvertsResourceToResponse;
    
    public function __construct(private ReflectionClass $class, private Serializer $serializer)
    {
    }

    public function getInputType(): ReflectionClass
    {
        return $this->class;
    }

    public function getOutputType(): ReflectionClass
    {
        return $this->class;
    }

    public function getResourceName(): string
    {
        return $this->class->getShortName();
    }

    public function getDescription(): string
    {
        return 'Creates an instance of ' . $this->class->getShortName();
    }
    public function getOperationId(): string
    {
        return 'post-' . $this->class->getShortName();
    }
    public function getTags(): StringList
    {
        return new StringList([$this->class->getShortName(), 'create']);
    }

    public function process(ApieContext $context): ApieContext
    {
        $rawContent = $context->getContext(ContextBuilderInterface::RAW_CONTENTS);
        $resource = $this->serializer->denormalizeNewObject($rawContent, $this->class->name, $context);
        return $context->withContext(ContextBuilderInterface::RESOURCE, $resource);
    }

    public function getValue(ApieContext $context): mixed
    {
        return $context->getContext(ContextBuilderInterface::RESOURCE);
    }

    public function getMethod(): RequestMethod
    {
        return RequestMethod::POST;
    }

    public function getUrl(): UrlRouteDefinition
    {
        return new UrlRouteDefinition($this->class->getShortName());
    }
}
