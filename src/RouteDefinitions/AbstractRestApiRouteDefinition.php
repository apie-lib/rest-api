<?php
namespace Apie\RestApi\RouteDefinitions;

use Apie\Common\ContextConstants;
use Apie\Core\Actions\ActionResponseStatusList;
use Apie\Core\BoundedContext\BoundedContextId;
use Apie\Core\Dto\ListOf;
use Apie\Core\Lists\StringList;
use Apie\RestApi\Controllers\RestApiController;
use Apie\RestApi\Interfaces\RestApiRouteDefinition;
use ReflectionClass;
use ReflectionMethod;
use ReflectionType;

abstract class AbstractRestApiRouteDefinition implements RestApiRouteDefinition
{
    /**
     * @param ReflectionClass<object> $class
     */
    public function __construct(
        protected readonly ReflectionClass $class,
        protected readonly ?BoundedContextId $boundedContextId = null,
        protected readonly ?ReflectionMethod $method = null
    ) {
    }

    /**
     * @return ReflectionClass<object>|ReflectionMethod|ReflectionType
     */
    final public function getInputType(): ReflectionClass|ReflectionMethod|ReflectionType
    {
        $actionClass = $this->getAction();
        return $actionClass::getInputType($this->class, $this->method);
    }

    /**
     * @return ReflectionClass<object>|ReflectionMethod|ReflectionType|ListOf
     */
    final public function getOutputType(): ReflectionClass|ReflectionMethod|ReflectionType|ListOf
    {
        $actionClass = $this->getAction();
        return $actionClass::getOutputType($this->class, $this->method);
    }

    final public function getPossibleActionResponseStatuses(): ActionResponseStatusList
    {
        $actionClass = $this->getAction();
        return $actionClass::getPossibleActionResponseStatuses($this->method);
    }

    /**
     * @return class-string<RestApiController>
     */
    final public function getController(): string
    {
        return RestApiController::class;
    }

    final public function getDescription(): string
    {
        $actionClass = $this->getAction();
        return $actionClass::getDescription($this->class, $this->method);
    }

    final public function getTags(): StringList
    {
        $actionClass = $this->getAction();
        return $actionClass::getTags($this->class, $this->method);
    }

    final public function getRouteAttributes(): array
    {
        $actionClass = $this->getAction();
        $attributes = $actionClass::getRouteAttributes($this->class, $this->method);
        $attributes[ContextConstants::APIE_ACTION] = $this->getAction();
        $attributes[ContextConstants::OPERATION_ID] = $this->getOperationId();
        $attributes[ContextConstants::BOUNDED_CONTEXT_ID] = $this->boundedContextId->toNative();
        return $attributes;
    }
}
