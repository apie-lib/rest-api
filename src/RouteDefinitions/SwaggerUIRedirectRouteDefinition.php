<?php
namespace Apie\RestApi\RouteDefinitions;

use Apie\Common\ContextConstants;
use Apie\Core\Actions\HasRouteDefinition;
use Apie\Core\BoundedContext\BoundedContextId;
use Apie\Core\Enums\RequestMethod;
use Apie\Core\ValueObjects\UrlRouteDefinition;
use Apie\RestApi\Controllers\SwaggerUIRedirectController;

class SwaggerUIRedirectRouteDefinition implements HasRouteDefinition
{
    public function __construct(private readonly BoundedContextId $boundedContextId)
    {
    }

    public function getOperationId(): string
    {
        return 'swagger_ui_redirect';
    }

    /**
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        return [
            'boundedContextId' => $this->boundedContextId->toNative(),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function __unserialize(array $data): void
    {
        $this->boundedContextId = new BoundedContextId($data['boundedContextId']);
    }

    public function getMethod(): RequestMethod
    {
        return RequestMethod::GET;
    }

    public function getUrl(): UrlRouteDefinition
    {
        return new UrlRouteDefinition('/oauth2-redirect.html');
    }

    public function getController(): string
    {
        return SwaggerUIRedirectController::class;
    }
    
    public function getRouteAttributes(): array
    {
        return [
            ContextConstants::BOUNDED_CONTEXT_ID => $this->boundedContextId->toNative(),
        ];
    }
}
