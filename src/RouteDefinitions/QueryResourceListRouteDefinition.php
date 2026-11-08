<?php
namespace Apie\RestApi\RouteDefinitions;

use Apie\Common\ActionDefinitions\ActionDefinitionInterface;
use Apie\Common\ActionDefinitions\GetResourceListActionDefinition;
use Apie\Core\Enums\RequestMethod;
use Apie\RestApi\Controllers\QueryResourceListController;

/**
 * Route definition for querying a list of resources with a request body.
 */
class QueryResourceListRouteDefinition extends GetResourceListRouteDefinition
{
    public static function createFrom(ActionDefinitionInterface $actionDefinition): ?AbstractRestApiRouteDefinition
    {
        if ($actionDefinition instanceof GetResourceListActionDefinition) {
            return new self($actionDefinition->getResourceName(), $actionDefinition->getBoundedContextId());
        }

        return null;
    }

    public function getOperationId(): string
    {
        return 'query-all-' . $this->class->getShortName();
    }

    public function getMethod(): RequestMethod
    {
        return RequestMethod::QUERY;
    }

    public function getController(): string
    {
        return QueryResourceListController::class;
    }
}
