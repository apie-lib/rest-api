<?php
namespace Apie\RestApi\Interfaces;

use Apie\Core\Actions\HasActionDefinition;
use Apie\Core\Actions\HasRouteDefinition;
use Apie\RestApi\Controllers\RestApiController;
use Apie\RestApi\Lists\StringList;
use Apie\RestApi\RouteDefinitions\ListOf;
use ReflectionClass;
use ReflectionMethod;
use ReflectionType;

interface RestApiRouteDefinition extends HasRouteDefinition, HasActionDefinition
{
    public const OPENAPI_POST = 'openapi_post';
    public const OPENAPI_ALL = 'openapi_all';
    public const OPENAPI_GET = 'openapi_get';
    public const OPENAPI_ACTION = 'openapi_action';
    /**
     * @return ReflectionClass<object>|ReflectionMethod|ReflectionType|ListOf
     */
    public function getInputType(): ReflectionClass|ReflectionMethod|ReflectionType|ListOf;

    /**
     * @return ReflectionClass<object>|ReflectionMethod|ReflectionType|ListOf
     */
    public function getOutputType(): ReflectionClass|ReflectionMethod|ReflectionType|ListOf;

    /**
     * @return class-string<RestApiController>
     */
    public function getController(): string;

    public function getDescription(): string;
    public function getTags(): StringList;
}
