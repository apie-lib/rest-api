<?php
namespace Apie\RestApi\RouteDefinitions;

use Apie\Common\ActionDefinitionProvider;
use Apie\Common\Interfaces\RouteDefinitionProviderInterface;
use Apie\Common\RouteDefinitions\ActionHashmap;
use Apie\Core\BoundedContext\BoundedContext;
use Apie\Core\Context\ApieContext;
use Psr\Log\LoggerInterface;

final class RestApiRouteDefinitionProvider implements RouteDefinitionProviderInterface
{
    private const CLASSES = [
        CreateResourceRouteDefinition::class,
        RemoveSingleResourceRouteDefinition::class,
        GetResourceListRouteDefinition::class,
        GetSingleResourceRouteDefinition::class,
        PatchSingleResourceRouteDefinition::class,
        RunGlobalMethodRouteDefinition::class,
        RunMethodCallOnSingleResourceRouteDefinition::class,
    ];

    public function __construct(
        private ActionDefinitionProvider $actionDefinitionProvider,
        private LoggerInterface $logger,
    ) {
    }

    public function getActionsForBoundedContext(BoundedContext $boundedContext, ApieContext $apieContext): ActionHashmap
    {
        $routes = [];
        $definition = new OpenApiDocumentationRouteDefinition(true, $boundedContext->getId());
        $routes[$definition->getOperationId()] = $definition;
        $definition = new OpenApiDocumentationRouteDefinition(false, $boundedContext->getId());
        $routes[$definition->getOperationId()] = $definition;
        $definition = new SwaggerUIRouteDefinition($boundedContext->getId());
        $routes[$definition->getOperationId()] = $definition;
        $definition = new SwaggerUIRedirectRouteDefinition($boundedContext->getId());
        $routes[$definition->getOperationId()] = $definition;

        foreach ($this->actionDefinitionProvider->provideActionDefinitions($boundedContext, $apieContext) as $actionDefinition) {
            $found = false;
            foreach (self::CLASSES as $routeDefinitionClass) {
                $routeDefinition = $routeDefinitionClass::createFrom($actionDefinition);
                if ($routeDefinition) {
                    $routes[$routeDefinition->getOperationId()] = $routeDefinition;
                    $found = true;
                }
            }
            if (!$found) {
                $this->logger->debug('No route definition created for ' . get_debug_type($actionDefinition));
            }
        }
        return new ActionHashmap($routes);
    }
}
