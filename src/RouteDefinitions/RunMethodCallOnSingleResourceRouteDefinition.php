<?php
namespace Apie\RestApi\RouteDefinitions;

use Apie\Common\Actions\RunItemMethodAction;
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
use ReflectionMethod;

/**
 * Route definition for running method on single resource
 */
class RunMethodCallOnSingleResourceRouteDefinition implements RestApiRouteDefinition
{
    /**
     * @param ReflectionClass<EntityInterface> $className
     */
    public function __construct(private ReflectionClass $className, private ReflectionMethod $method, private BoundedContextId $boundedContextId)
    {
    }

    public function getInputType(): ReflectionMethod
    {
        return $this->method;
    }

    /**
     * @return ReflectionClass<EntityInterface>|ReflectionMethod
     */
    public function getOutputType(): ReflectionMethod|ReflectionClass
    {
        if (RunItemMethodAction::shouldReturnResource($this->method)) {
            return $this->className;
        }
        return $this->method;
    }

    public function getPossibleActionResponseStatuses(): ActionResponseStatusList
    {
        $list = [ActionResponseStatus::SUCCESS];
        if (!empty($this->method->getParameters())) {
            $list[] = ActionResponseStatus::CLIENT_ERROR;
        }
        if (!$this->method->isStatic()) {
            $list[] = ActionResponseStatus::NOT_FOUND;
        }
        return new ActionResponseStatusList($list);
    }

    public function getDescription(): string
    {
        $name = RunItemMethodAction::getDisplayNameForMethod($this->method);
        if (str_starts_with($this->method->name, 'add')) {
            return 'Adds ' . $name . ' to ' . $this->className->getShortName();
        }
        if (str_starts_with($this->method->name, 'remove')) {
            return 'Removes ' . $name . ' from ' . $this->className->getShortName();
        }
        return 'Runs method ' . $name . ' on a ' . $this->className->getShortName() . ' with a specific id';
    }

    public function getOperationId(): string
    {
        return 'get-single-' . $this->className->getShortName() . '-run-' . $this->method->name;
    }
    
    public function getTags(): StringList
    {
        $className = $this->className->getShortName();
        $declared = $this->method->getDeclaringClass()->getShortName();
        if ($className !== $declared) {
            return new StringList([$className, $declared, 'action']);
        }
        return new StringList([$className, 'action']);
    }

    public function getMethod(): RequestMethod
    {
        if ($this->method->getNumberOfParameters() === 0) {
            return RequestMethod::GET;
        }
        if (str_starts_with($this->method->name, 'remove')) {
            return RequestMethod::DELETE;
        }
        return RequestMethod::POST;
    }

    public function getUrl(): UrlRouteDefinition
    {
        $url = $this->className->getShortName();
        $url .= ($this->method->isStatic()) ? '/' : ('/{' . ContextConstants::RESOURCE_ID . '}/');
        $url .= RunItemMethodAction::getDisplayNameForMethod($this->method);
        return new UrlRouteDefinition($url);
    }

    public function getController(): string
    {
        return RestApiController::class;
    }

    public function getAction(): string
    {
        return RunItemMethodAction::class;
    }

    public function getRouteAttributes(): array
    {
        return
        [
            RestApiRouteDefinition::OPENAPI_GET => true,
            ContextConstants::RESOURCE_NAME => $this->className->name,
            ContextConstants::METHOD_CLASS => $this->method->getDeclaringClass()->name,
            ContextConstants::METHOD_NAME => $this->method->name,
            ContextConstants::BOUNDED_CONTEXT_ID => $this->boundedContextId->toNative(),
            ContextConstants::OPERATION_ID => $this->getOperationId(),
            ContextConstants::APIE_ACTION => $this->getAction(),
        ];
    }
}
