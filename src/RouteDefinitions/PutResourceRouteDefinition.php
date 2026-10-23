<?php
namespace Apie\RestApi\RouteDefinitions;

use Apie\Common\ActionDefinitions\ActionDefinitionInterface;
use Apie\Common\ActionDefinitions\ReplaceResourceActionDefinition;
use Apie\Core\BoundedContext\BoundedContextId;
use Apie\Core\Entities\EntityInterface;
use ReflectionClass;

/**
 * PUT route for creating or replacing a resource with a client-provided identifier.
 */
final class PutResourceRouteDefinition extends CreateResourceRouteDefinition
{
    /**
     * @param ReflectionClass<covariant EntityInterface> $className
     */
    public function __construct(ReflectionClass $className, BoundedContextId $boundedContextId)
    {
        parent::__construct($className, $boundedContextId, true);
    }

    public static function createFrom(ActionDefinitionInterface $actionDefinition): ?AbstractRestApiRouteDefinition
    {
        if ($actionDefinition instanceof ReplaceResourceActionDefinition) {
            return new self($actionDefinition->getResourceName(), $actionDefinition->getBoundedContextId());
        }
        return null;
    }
}
