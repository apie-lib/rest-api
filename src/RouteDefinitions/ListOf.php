<?php
namespace Apie\RestApi\RouteDefinitions;

use Apie\RestApi\Interfaces\RestApiRouteDefinition;
use ReflectionClass;
use ReflectionMethod;
use ReflectionType;

/**
 * Used internally to indicate a paginated result of some object.
 *
 * @see RestApiRouteDefinition::getInputType()
 * @see RestApiRouteDefinition::getOutputType()
 */
final class ListOf
{
    /**
     * @param ReflectionClass<object>|ReflectionMethod|ReflectionType $type
     */
    public function __construct(
        public readonly ReflectionClass|ReflectionMethod|ReflectionType $type
    ) {
    }
}
