<?php
namespace Apie\RestApi\RouteDefinitions;

use Apie\Core\BoundedContext\BoundedContextId;
use Apie\Core\Enums\RequestMethod;
use Apie\Core\ValueObjects\UrlRouteDefinition;
use Apie\RestApi\Controllers\RunGlobalMethodController;
use Apie\RestApi\Interfaces\RestApiRouteDefinition;
use Apie\RestApi\Lists\StringList;
use ReflectionMethod;
use ReflectionType;

class RunGlobalMethodRouteDefinition implements RestApiRouteDefinition
{
    public function __construct(private readonly ReflectionMethod $method, private readonly BoundedContextId $boundedContextId)
    {
    }

    public function getMethod(): RequestMethod
    {
        return empty($this->method->getParameters()) ? RequestMethod::GET : RequestMethod::POST;
    }

    public function getUrl(): UrlRouteDefinition
    {
        return new UrlRouteDefinition($this->method->getName() . '/');
    }

    public function getController(): string
    {
        return RunGlobalMethodController::class;
    }

    public function getRouteAttributes(): array
    {
        return [
            'boundedContextId' => $this->boundedContextId->toNative(),
        ];
    }

    public function getDescription(): string
    {
        return 'Calls method ' . $this->method->getName() . ' and returns return value.';
    }

    public function getOperationId(): string
    {
        return 'call-method-' . $this->method->getDeclaringClass()->getShortName() . '-' . $this->method->getName();
    }

    public function getTags(): StringList
    {
        return new StringList([$this->method->getDeclaringClass()->getShortName(), 'methodCall']);
    }

    public function getInputType(): ReflectionMethod
    {
        return $this->method;
    }

    public function getOutputType(): ReflectionType
    {
        return $this->method->getReturnType();
    }
}
