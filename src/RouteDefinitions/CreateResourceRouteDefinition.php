<?php
namespace Apie\RestApi\RouteDefinitions;

use Apie\Core\BoundedContext\BoundedContextId;
use Apie\Core\Entities\EntityInterface;
use Apie\Core\Enums\RequestMethod;
use Apie\Core\ValueObjects\UrlRouteDefinition;
use Apie\RestApi\Actions\CreateObjectAction;
use Apie\RestApi\Controllers\CreateResourceController;
use Apie\RestApi\Controllers\RestApiController;
use Apie\RestApi\Interfaces\RestApiRouteDefinition;
use Apie\RestApi\Lists\StringList;
use ReflectionClass;

class CreateResourceRouteDefinition implements RestApiRouteDefinition
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
        return 'Creates an instance of ' . $this->className->getShortName();
    }

    public function getOperationId(): string
    {
        return 'post-' . $this->className->getShortName();
    }
    
    public function getTags(): StringList
    {
        return new StringList([$this->className->getShortName(), 'create']);
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
        return [
            'boundedContextId' => $this->boundedContextId->toNative(),
            'resourceName' => $this->className->name,
            'operationId' => $this->getOperationId(),
        ];
    }
}
