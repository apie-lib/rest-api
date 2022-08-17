<?php
namespace Apie\RestApi\RouteDefinitions;

use Apie\Common\Actions\RunAction;
use Apie\Common\ContextConstants;
use Apie\Core\BoundedContext\BoundedContextId;
use Apie\Core\Enums\RequestMethod;
use Apie\Core\ValueObjects\UrlRouteDefinition;
use Apie\RestApi\Controllers\RestApiController;
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

    private function getNameToDisplay(): string
    {
        $methodName = $this->method->getName();
        if ($methodName === '__invoke') {
            return $this->method->getDeclaringClass()->getShortName();
        }

        return $methodName;
    }

    public function getUrl(): UrlRouteDefinition
    {
        $methodName = $this->method->getName();
        if ($methodName === '__invoke') {
            return new UrlRouteDefinition($this->method->getDeclaringClass()->getShortName());
        }
        return new UrlRouteDefinition($this->method->getDeclaringClass()->getShortName() . '/' . $methodName);
    }

    public function getController(): string
    {
        return RestApiController::class;
    }

    public function getAction(): string
    {
        return RunAction::class;
    }

    public function getRouteAttributes(): array
    {
        return [
            RestApiRouteDefinition::OPENAPI_ACTION => true,
            ContextConstants::BOUNDED_CONTEXT_ID => $this->boundedContextId->toNative(),
            ContextConstants::SERVICE_CLASS => $this->method->getDeclaringClass()->name,
            ContextConstants::METHOD_NAME => $this->method->getName(),
            ContextConstants::OPERATION_ID => $this->getOperationId(),
        ];
    }

    public function getDescription(): string
    {
        return 'Calls method ' . $this->getNameToDisplay() . ' and returns return value.';
    }

    public function getOperationId(): string
    {
        $methodName = $this->method->getName();
        $suffix = $methodName === '__invoke' ? '' : ('-' . $methodName);
        return 'call-method-' . $this->method->getDeclaringClass()->getShortName() . $suffix;
    }

    public function getTags(): StringList
    {
        return new StringList([$this->method->getDeclaringClass()->getShortName(), 'action']);
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
