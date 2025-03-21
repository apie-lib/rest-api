<?php
namespace Apie\RestApi\RouteDefinitions;

use Apie\Common\Enums\UrlPrefix;
use Apie\Common\Interfaces\HasRouteDefinition;
use Apie\Common\Lists\UrlPrefixList;
use Apie\Core\BoundedContext\BoundedContextId;
use Apie\Core\ContextConstants;
use Apie\Core\Enums\RequestMethod;
use Apie\Core\ValueObjects\UrlRouteDefinition;
use Apie\RestApi\Controllers\SwaggerUIController;

class SwaggerUIRouteDefinition implements HasRouteDefinition
{
    public function __construct(private readonly BoundedContextId $boundedContextId)
    {
    }

    public function getOperationId(): string
    {
        return 'swagger_ui';
    }

    public function getMethod(): RequestMethod
    {
        return RequestMethod::GET;
    }

    public function getUrl(): UrlRouteDefinition
    {
        return new UrlRouteDefinition('/');
    }

    public function getController(): string
    {
        return SwaggerUIController::class;
    }
    
    public function getRouteAttributes(): array
    {
        return [
            ContextConstants::BOUNDED_CONTEXT_ID => $this->boundedContextId->toNative(),
        ];
    }

    public function getUrlPrefixes(): UrlPrefixList
    {
        return new UrlPrefixList([UrlPrefix::API]);
    }
}
