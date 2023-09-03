<?php
namespace Apie\RestApi\Events;

use Apie\Common\Interfaces\RestApiRouteDefinition;
use Apie\SchemaGenerator\Builders\ComponentsBuilder;
use cebe\openapi\spec\Operation;

final class OpenApiOperationAddedEvent
{
    public function __construct(
        public readonly ComponentsBuilder $componentsBuilder,
        public readonly Operation $operation,
        public readonly RestApiRouteDefinition $routeDefinition,
    ) {
    }
}
