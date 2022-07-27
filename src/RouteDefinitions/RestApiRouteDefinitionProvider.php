<?php
namespace Apie\RestApi\RouteDefinitions;

use Apie\Core\BoundedContext\BoundedContext;
use Apie\Core\Context\ApieContext;
use Apie\Core\Enums\RequestMethod;
use Apie\Core\RouteDefinitions\ActionHashmap;
use Apie\Core\RouteDefinitions\RouteDefinitionProviderInterface;
use Apie\RestApi\Interfaces\RestApiRouteDefinition;

class RestApiRouteDefinitionProvider implements RouteDefinitionProviderInterface
{
    public function getActionsForBoundedContext(BoundedContext $boundedContext, ApieContext $apieContext): ActionHashmap
    {
        $postContext = $apieContext->withContext(RequestMethod::class, RequestMethod::POST)
            ->withContext(RestApiRouteDefinition::OPENAPI_POST, true)
            ->registerInstance($boundedContext);

        $map = [];
        $definition = new OpenApiDocumentationRouteDefinition(true, $boundedContext->getId());
        $map[$definition->getUrl()->toNative()] = $definition;
        $definition = new OpenApiDocumentationRouteDefinition(false, $boundedContext->getId());
        $map[$definition->getUrl()->toNative()] = $definition;
        foreach ($boundedContext->resources->filterOnApieContext($postContext) as $resource) {
            $definition = new CreateResourceRouteDefinition($resource, $boundedContext->getId());
            $map[$definition->getUrl()->toNative()] = $definition;
        }

        $actionContext = $apieContext->withContext(RestApiRouteDefinition::OPENAPI_ACTION, true);
        foreach ($boundedContext->actions->filterOnApieContext($actionContext) as $action) {
            $definition = new RunGlobalMethodRouteDefinition($action, $boundedContext->getId());
            $map[$definition->getUrl()->toNative()] = $definition;
        }
        return new ActionHashmap($map);
    }
}
