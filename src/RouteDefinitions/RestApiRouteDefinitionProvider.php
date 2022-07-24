<?php
namespace Apie\RestApi\RouteDefinitions;

use Apie\Core\BoundedContext\BoundedContext;
use Apie\Core\Context\ApieContext;
use Apie\Core\Enums\RequestMethod;
use Apie\Core\RouteDefinitions\ActionHashmap;
use Apie\Core\RouteDefinitions\RouteDefinitionProviderInterface;
use Apie\RestApi\Actions\CreateObjectAction;
use Apie\RestApi\Actions\OpenApiDocumentation;
use Apie\RestApi\Actions\RunAction;
use Apie\RestApi\Interfaces\RestApiRouteDefinition;
use Apie\RestApi\OpenApi\OpenApiGenerator;
use Apie\Serializer\Serializer;

class RestApiRouteDefinitionProvider implements RouteDefinitionProviderInterface
{
    public function getActionsForBoundedContext(BoundedContext $boundedContext, ApieContext $apieContext): ActionHashmap
    {
        $postContext = $apieContext->withContext(RequestMethod::class, RequestMethod::POST)
            ->withContext(RestApiRouteDefinition::OPENAPI_POST, true);

        $map = [];
        $definition = new OpenApiDocumentation(
            $apieContext->getContext(OpenApiGenerator::class)
        );
        $map[$definition->getUrl()->toNative()] = $definition;
        foreach ($boundedContext->resources->filterOnApieContext($postContext) as $resource) {
            $definition = new CreateObjectAction($resource, $postContext->getContext(Serializer::class));
            $map[$definition->getUrl()->toNative()] = $definition;
        }

        $actionContext = $apieContext->withContext(RestApiRouteDefinition::OPENAPI_ACTION, true);
        foreach ($boundedContext->actions->filterOnApieContext($actionContext) as $action) {
            $definition = new RunAction($action, $postContext->getContext(Serializer::class));
            $map[$definition->getUrl()->toNative()] = $definition;
        }
        return new ActionHashmap($map);
    }
}
