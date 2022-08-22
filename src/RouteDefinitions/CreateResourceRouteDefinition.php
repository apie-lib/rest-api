<?php
namespace Apie\RestApi\RouteDefinitions;

use Apie\Common\Actions\CreateObjectAction;
use Apie\Common\ContextConstants;
use Apie\Core\Actions\ActionResponseStatus;
use Apie\Core\Actions\ActionResponseStatusList;
use Apie\Core\BoundedContext\BoundedContextId;
use Apie\Core\Entities\EntityInterface;
use Apie\Core\Enums\RequestMethod;
use Apie\Core\ValueObjects\UrlRouteDefinition;
use Apie\RestApi\Controllers\RestApiController;
use Apie\RestApi\Interfaces\RestApiRouteDefinition;
use Apie\RestApi\Lists\StringList;
use ReflectionClass;

/**
 * Route definition for creating an entity.
 */
class CreateResourceRouteDefinition implements RestApiRouteDefinition
{
    /**
     * @param ReflectionClass<EntityInterface> $className
     */
    public function __construct(private ReflectionClass $className, private BoundedContextId $boundedContextId)
    {
    }

    /**
     * @return ReflectionClass<EntityInterface>
     */
    public function getInputType(): ReflectionClass
    {
        return $this->className;
    }

    /**
     * @return ReflectionClass<EntityInterface>
     */
    public function getOutputType(): ReflectionClass
    {
        return $this->className;
    }

    public function getPossibleActionResponseStatuses(): ActionResponseStatusList
    {
        return new ActionResponseStatusList([
            ActionResponseStatus::CREATED,
            ActionResponseStatus::CLIENT_ERROR,
            ActionResponseStatus::PERISTENCE_ERROR
        ]);
    }

    public function getDescription(): string
    {
        return 'Creates an instance of ' . $this->className->getShortName();
    }

    public function getOperationId(): string
    {
        return 'post-' . $this->className->getShortName();
    }
    
    public function getTags(): StringList
    {
        return new StringList([$this->className->getShortName(), 'resource']);
    }

    public function getMethod(): RequestMethod
    {
        return RequestMethod::POST;
    }
    public function getUrl(): UrlRouteDefinition
    {
        return new UrlRouteDefinition($this->className->getShortName());
    }

    public function getController(): string
    {
        return RestApiController::class;
    }

    public function getAction(): string
    {
        return CreateObjectAction::class;
    }

    public function getRouteAttributes(): array
    {
        return
        [
            RestApiRouteDefinition::OPENAPI_POST => true,
            ContextConstants::RESOURCE_NAME => $this->className->name,
            ContextConstants::BOUNDED_CONTEXT_ID => $this->boundedContextId->toNative(),
            ContextConstants::OPERATION_ID => $this->getOperationId(),
            ContextConstants::APIE_ACTION => $this->getAction(),
        ];
    }
}
