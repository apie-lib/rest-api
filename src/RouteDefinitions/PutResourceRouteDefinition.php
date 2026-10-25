<?php
namespace Apie\RestApi\RouteDefinitions;

use Apie\Common\ActionDefinitions\ActionDefinitionInterface;
use Apie\Common\ActionDefinitions\ReplaceResourceActionDefinition;
use Apie\Core\BoundedContext\BoundedContextId;
use Apie\Core\ContextConstants;
use Apie\Core\Entities\EntityInterface;
use Apie\Core\Enums\RequestMethod;
use Apie\Core\ValueObjects\UrlRouteDefinition;
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

    public function getOperationId(): string
    {
        return 'replace-' . $this->class->getShortName();
    }

    public function getUrl(): UrlRouteDefinition
    {
        return new UrlRouteDefinition($this->class->getShortName() . '/{' . ContextConstants::RESOURCE_ID . '}');
    }

    public function getMethod(): RequestMethod
    {
        return RequestMethod::PUT;
    }

    public static function createFrom(ActionDefinitionInterface $actionDefinition): ?AbstractRestApiRouteDefinition
    {
        if ($actionDefinition instanceof ReplaceResourceActionDefinition) {
            return new self($actionDefinition->getResourceName(), $actionDefinition->getBoundedContextId());
        }
        return null;
    }
}
