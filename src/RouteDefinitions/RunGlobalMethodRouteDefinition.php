<?php
namespace Apie\RestApi\RouteDefinitions;

use Apie\Common\ActionDefinitions\ActionDefinitionInterface;
use Apie\Common\ActionDefinitions\RunGlobalMethodDefinition;
use Apie\Common\Actions\RunAction;
use Apie\Common\Concerns\ReadsRouteAttribute;
use Apie\Core\Attributes\Route;
use Apie\Core\BoundedContext\BoundedContextId;
use Apie\Core\Enums\RequestMethod;
use Apie\Core\ValueObjects\UrlRouteDefinition;
use ReflectionMethod;

class RunGlobalMethodRouteDefinition extends AbstractRestApiRouteDefinition
{
    use ReadsRouteAttribute;

    private const CURRENT_TARGET = Route::API;

    public function __construct(ReflectionMethod $method, BoundedContextId $boundedContextId)
    {
        parent::__construct($method->getDeclaringClass(), $boundedContextId, $method);
    }

    public function getMethod(): RequestMethod
    {
        $route = $this->getRouteAttribute();
        if ($route && $route->requestMethod) {
            return $route->requestMethod;
        }
        return empty($this->method->getParameters()) ? RequestMethod::GET : RequestMethod::POST;
    }

    public function getUrl(): UrlRouteDefinition
    {
        $route = $this->getRouteAttribute();
        if ($route) {
            return new UrlRouteDefinition($route->routeDefinition);
        }
        $methodName = $this->method->getName();
        if ($methodName === '__invoke') {
            return new UrlRouteDefinition($this->method->getDeclaringClass()->getShortName());
        }
        return new UrlRouteDefinition($this->method->getDeclaringClass()->getShortName() . '/' . $methodName);
    }

    public function getAction(): string
    {
        return RunAction::class;
    }

    public function getOperationId(): string
    {
        $methodName = $this->method->getName();
        $suffix = $methodName === '__invoke' ? '' : ('-' . $methodName);
        return 'call-method-' . $this->method->getDeclaringClass()->getShortName() . $suffix;
    }

    public static function createFrom(ActionDefinitionInterface $actionDefinition): ?AbstractRestApiRouteDefinition
    {
        if ($actionDefinition instanceof RunGlobalMethodDefinition) {
            return new self($actionDefinition->getMethod(), $actionDefinition->getBoundedContextId());
        }
        return null;
    }
}
