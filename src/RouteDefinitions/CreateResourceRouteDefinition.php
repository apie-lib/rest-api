<?php
namespace Apie\RestApi\RouteDefinitions;

use Apie\Common\ActionDefinitions\ActionDefinitionInterface;
use Apie\Common\ActionDefinitions\CreateResourceActionDefinition;
use Apie\Common\ActionDefinitions\ReplaceResourceActionDefinition;
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
    public static function createFrom(ActionDefinitionInterface $actionDefinition): ?AbstractRestApiRouteDefinition
    {
        if ($actionDefinition instanceof CreateResourceActionDefinition) {
            return new self($actionDefinition->getResourceName(), $actionDefinition->getBoundedContextId());
        }
        // TODO: should become PUT
        if ($actionDefinition instanceof ReplaceResourceActionDefinition) {
            return new self($actionDefinition->getResourceName(), $actionDefinition->getBoundedContextId(), true);
        }
        return null;
    }

    /**
     * @param ReflectionClass<EntityInterface> $className
     */
    public function __construct(ReflectionClass $className, BoundedContextId $boundedContextId, private bool $put = false)
    {
        parent::__construct($className, $boundedContextId);
    }

    public function getMethod(): RequestMethod
    {
        return RequestMethod::POST;
    }

    public function getOperationId(): string
    {
        return ($this->put ? 'put-' : 'post-') . $this->class->getShortName();
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
