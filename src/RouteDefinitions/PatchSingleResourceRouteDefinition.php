<?php
namespace Apie\RestApi\RouteDefinitions;

use Apie\Common\Actions\ModifyObjectAction;
use Apie\Common\ContextConstants;
use Apie\Core\BoundedContext\BoundedContextId;
use Apie\Core\Entities\EntityInterface;
use Apie\Core\Enums\RequestMethod;
use Apie\Core\ValueObjects\UrlRouteDefinition;
use ReflectionClass;

/**
 * Route definition for modifying a single resource.
 */
class PatchSingleResourceRouteDefinition extends AbstractRestApiRouteDefinition
{
    /**
     * @param ReflectionClass<EntityInterface> $className
     */
    public function __construct(ReflectionClass $className, BoundedContextId $boundedContextId)
    {
        parent::__construct($className, $boundedContextId);
    }

    public function getOperationId(): string
    {
        return 'patch-single-' . $this->class->getShortName();
    }

    public function getMethod(): RequestMethod
    {
        return RequestMethod::PATCH;
    }

    public function getUrl(): UrlRouteDefinition
    {
        return new UrlRouteDefinition($this->class->getShortName() . '/{' . ContextConstants::RESOURCE_ID . '}');
    }

    public function getAction(): string
    {
        return ModifyObjectAction::class;
    }
}
