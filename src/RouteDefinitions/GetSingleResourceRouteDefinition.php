<?php
namespace Apie\RestApi\RouteDefinitions;

use Apie\Common\Actions\GetItemAction;
use Apie\Common\ContextConstants;
use Apie\Core\BoundedContext\BoundedContextId;
use Apie\Core\Entities\EntityInterface;
use Apie\Core\Enums\RequestMethod;
use Apie\Core\ValueObjects\UrlRouteDefinition;
use Apie\RestApi\Controllers\RestApiController;
use Apie\RestApi\Interfaces\RestApiRouteDefinition;
use Apie\RestApi\Lists\StringList;
use ReflectionClass;

/**
 * Route definition for getting a single route.
 */
class GetSingleResourceRouteDefinition implements RestApiRouteDefinition
{
    /**
     * @param ReflectionClass<EntityInterface> $className
     */
    public function __construct(private ReflectionClass $className, private BoundedContextId $boundedContextId)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        return [
            'className' => $this->className->name,
            'boundedContextId' => $this->boundedContextId->toNative(),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function __unserialize(array $data): void
    {
        $this->className = new ReflectionClass($data['className']);
        $this->boundedContextId = new BoundedContextId($data['boundedContextId']);
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

    public function getDescription(): string
    {
        return 'Gets a resource of ' . $this->className->getShortName() . ' with a specific id';
    }

    public function getOperationId(): string
    {
        return 'get-single-' . $this->className->getShortName();
    }
    
    public function getTags(): StringList
    {
        return new StringList([$this->className->getShortName(), 'resource']);
    }

    public function getMethod(): RequestMethod
    {
        return RequestMethod::GET;
    }

    public function getUrl(): UrlRouteDefinition
    {
        return new UrlRouteDefinition($this->className->getShortName() . '/{' . ContextConstants::RESOURCE_ID . '}');
    }

    public function getController(): string
    {
        return RestApiController::class;
    }

    public function getAction(): string
    {
        return GetItemAction::class;
    }

    public function getRouteAttributes(): array
    {
        return
        [
            RestApiRouteDefinition::OPENAPI_GET => true,
            ContextConstants::RESOURCE_NAME => $this->className->name,
            ContextConstants::BOUNDED_CONTEXT_ID => $this->boundedContextId->toNative(),
            ContextConstants::OPERATION_ID => $this->getOperationId(),
        ];
    }
}
