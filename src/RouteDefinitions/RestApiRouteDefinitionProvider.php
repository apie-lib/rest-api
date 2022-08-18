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
        $map = [];
        $definition = new OpenApiDocumentationRouteDefinition(true, $boundedContext->getId());
        $map[$definition->getOperationId()] = $definition;
        $definition = new OpenApiDocumentationRouteDefinition(false, $boundedContext->getId());
        $map[$definition->getOperationId()] = $definition;
        $definition = new SwaggerUIRouteDefinition($boundedContext->getId());
        $map[$definition->getOperationId()] = $definition;

        $postContext = $apieContext->withContext(RequestMethod::class, RequestMethod::POST)
            ->withContext(RestApiRouteDefinition::OPENAPI_POST, true)
            ->registerInstance($boundedContext);
        foreach ($boundedContext->resources->filterOnApieContext($postContext) as $resource) {
            $definition = new CreateResourceRouteDefinition($resource, $boundedContext->getId());
            $map[$definition->getOperationId()] = $definition;
        }

        $getSingleContext = $apieContext->withContext(RequestMethod::class, RequestMethod::GET)
            ->withContext(RestApiRouteDefinition::OPENAPI_GET, true)
            ->registerInstance($boundedContext);
        foreach ($boundedContext->resources->filterOnApieContext($getSingleContext) as $resource) {
            $definition = new GetSingleResourceRouteDefinition($resource, $boundedContext->getId());
            $map[$definition->getOperationId()] = $definition;
        }

        $getAllContext = $apieContext->withContext(RequestMethod::class, RequestMethod::GET)
            ->withContext(RestApiRouteDefinition::OPENAPI_ALL, true)
            ->registerInstance($boundedContext);
        foreach ($boundedContext->resources->filterOnApieContext($getAllContext) as $resource) {
            $definition = new GetResourceListRouteDefinition($resource, $boundedContext->getId());
            $map[$definition->getOperationId()] = $definition;
        }

        $actionContext = $apieContext->withContext(RestApiRouteDefinition::OPENAPI_ACTION, true);
        foreach ($boundedContext->actions->filterOnApieContext($actionContext) as $action) {
            $definition = new RunGlobalMethodRouteDefinition($action, $boundedContext->getId());
            $map[$definition->getOperationId()] = $definition;
        }
        foreach ($boundedContext->resources->filterOnApieContext($actionContext) as $resource) {
            foreach ($actionContext->getApplicableMethods($resource) as $method) {
                $definition = new RunMethodCallOnSingleResourceRouteDefinition(
                    $resource,
                    $method,
                    $boundedContext->getId()
                );
                $map[$definition->getOperationId()] = $definition;
            }
        }
        return new ActionHashmap($map);
    }
}
