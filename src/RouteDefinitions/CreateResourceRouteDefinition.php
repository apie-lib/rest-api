<?php
namespace Apie\RestApi\RouteDefinitions;

use Apie\Common\Actions\CreateObjectAction;
use Apie\Core\BoundedContext\BoundedContextId;
use Apie\Core\Entities\EntityInterface;
use Apie\Core\Enums\RequestMethod;
use Apie\Core\ValueObjects\UrlRouteDefinition;
use ReflectionClass;

/**
 * Route definition for creating an entity.
 */
class CreateResourceRouteDefinition extends AbstractRestApiRouteDefinition
{
    /**
     * @param ReflectionClass<EntityInterface> $className
     */
    public function __construct(ReflectionClass $className, BoundedContextId $boundedContextId)
    {
        parent::__construct($className, $boundedContextId);
    }

    public function getMethod(): RequestMethod
    {
        return RequestMethod::POST;
    }

    public function getOperationId(): string
    {
        return 'post-' . $this->class->getShortName();
    }

    public function getUrl(): UrlRouteDefinition
    {
        return new UrlRouteDefinition($this->class->getShortName());
    }

    public function getAction(): string
    {
        return CreateObjectAction::class;
    }
}
