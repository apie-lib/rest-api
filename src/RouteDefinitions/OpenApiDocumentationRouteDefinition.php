<?php
namespace Apie\RestApi\RouteDefinitions;

use Apie\Common\Interfaces\HasRouteDefinition;
use Apie\Core\BoundedContext\BoundedContextId;
use Apie\Core\Enums\RequestMethod;
use Apie\Core\ValueObjects\UrlRouteDefinition;
use Apie\RestApi\Controllers\OpenApiDocumentationController;

class OpenApiDocumentationRouteDefinition implements HasRouteDefinition
{
    public function __construct(private readonly bool $yamlFormat, private BoundedContextId $boundedContextId)
    {
    }

    public function getOperationId(): string
    {
        return 'openapi_spec.' . ($this->yamlFormat ? 'yaml' : 'json');
    }

    public function getMethod(): RequestMethod
    {
        return RequestMethod::GET;
    }

    public function getUrl(): UrlRouteDefinition
    {
        return new UrlRouteDefinition('/openapi.' . ($this->yamlFormat ? 'yaml' : 'json'));
    }

    public function getController(): string
    {
        return OpenApiDocumentationController::class;
    }
    
    public function getRouteAttributes(): array
    {
        return [
            'boundedContextId' => $this->boundedContextId->toNative(),
            'yaml' => $this->yamlFormat,
        ];
    }
}
