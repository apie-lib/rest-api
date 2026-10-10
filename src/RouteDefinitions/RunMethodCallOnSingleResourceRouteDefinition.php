<?php
namespace Apie\RestApi\RouteDefinitions;

use Apie\Common\ActionDefinitions\ActionDefinitionInterface;
use Apie\Common\ActionDefinitions\RunResourceMethodDefinition;
use Apie\Common\Actions\RunItemMethodAction;
use Apie\Core\Attributes\ActionOptions;
use Apie\Core\BoundedContext\BoundedContextId;
use Apie\Core\ContextConstants;
use Apie\Core\Entities\EntityInterface;
use Apie\Core\Enums\RequestMethod;
use Apie\Core\ValueObjects\UrlRouteDefinition;
use ReflectionClass;
use ReflectionMethod;

/**
 * Route definition for running method on single resource
 */
class RunMethodCallOnSingleResourceRouteDefinition extends AbstractRestApiRouteDefinition
{
    private string $suffix = '';
    /**
     * @param ReflectionClass<covariant EntityInterface> $className
     */
    public function __construct(
        ReflectionClass $className,
        ReflectionMethod $method,
        BoundedContextId $boundedContextId,
        private ?ActionOptions $actionOptions = null
    ) {
        parent::__construct($className, $boundedContextId, $method);
        if ($this->actionOptions) {
            $this->suffix = '-' . md5(json_encode($this->actionOptions));
        }
    }

    public function getActionOptions(): ?ActionOptions
    {
        return $this->actionOptions;
    }

    public function getOperationId(): string
    {
        return 'get-single-' . $this->class->getShortName() . '-run-' . $this->method->name . $this->suffix;
    }
    
    public function getMethod(): RequestMethod
    {
        if ($this->actionOptions?->requestMethod) {
            return $this->actionOptions->requestMethod;
        }
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
        $url = $this->class->getShortName();
        $url .= ($this->method->isStatic()) ? '/' : ('/{' . ContextConstants::RESOURCE_ID . '}/');
        $url .= RunItemMethodAction::getDisplayNameForMethod($this->method);
        if ($this->actionOptions?->pathRemainder) {
            $url .= '/' . $this->actionOptions->pathRemainder;
        }
        return new UrlRouteDefinition($url);
    }

    public function getAction(): string
    {
        return RunItemMethodAction::class;
    }

    public static function createFrom(ActionDefinitionInterface $actionDefinition): ?AbstractRestApiRouteDefinition
    {
        if ($actionDefinition instanceof RunResourceMethodDefinition) {
            return new self(
                $actionDefinition->getResourceName(),
                $actionDefinition->getMethod(),
                $actionDefinition->getBoundedContextId(),
                $actionDefinition->getActionOptions()
            );
        }
        return null;
    }
}
