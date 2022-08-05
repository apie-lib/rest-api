<?php
namespace Apie\RestApi;

use Apie\Core\Actions\ActionInterface;
use Apie\Core\BoundedContext\BoundedContextHashmap;
use Apie\Core\Context\ApieContext;
use Apie\Core\RouteDefinitions\RouteDefinitionProviderInterface;
use Apie\RestApi\Interfaces\RestApiRouteDefinition;
use Apie\Serializer\Serializer;
use LogicException;

final class ActionProvider
{
    public function __construct(
        private RouteDefinitionProviderInterface $routeDefinitionProvider,
        private BoundedContextHashmap $boundedContextHashmap,
        private Serializer $serializer
    ) {
    }

    public function getAction(string $boundedContextId, string $operationId, ApieContext $apieContext): ActionInterface
    {
        $boundedContext = $this->boundedContextHashmap[$boundedContextId];
        $actions = $this->routeDefinitionProvider->getActionsForBoundedContext($boundedContext, $apieContext);
        foreach ($actions as $action) {
            if ($action instanceof RestApiRouteDefinition && $action->getOperationId() === $operationId) {
                return $this->createAction($action->getAction());
            }
        }
        throw new LogicException(sprintf('"%s" action id not found!', $operationId));
    }

    /**
     * @param class-string<ActionInterface> $classAction
     */
    private function createAction(string $classAction): ActionInterface
    {
        return new $classAction($this->serializer);
    }
}
