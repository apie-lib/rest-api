<?php
namespace Apie\RestApi\Interfaces;

use Apie\Core\Actions\ActionInterface;
use Apie\Core\Actions\HasRouteDefinition;
use Apie\Core\ContextBuilders\ContextBuilderInterface;
use Apie\RestApi\Controllers\RestApiController;
use Apie\RestApi\Lists\StringList;
use ReflectionClass;
use ReflectionMethod;
use ReflectionType;

interface RestApiRouteDefinition extends HasRouteDefinition
{
    public const CONTENT_TYPE = 'CONTENT_TYPE';
    public const OPENAPI_POST = 'OPENAPI_POST';
    public const OPENAPI_ACTION = 'OPENAPI_ACTION';
    public const RESOURCE_NAME = 'RESOURCE_NAME';
    public const RAW_CONTENTS = ContextBuilderInterface::RAW_CONTENTS;

    /**
     * @return ReflectionClass<object>|ReflectionMethod|ReflectionType
     */
    public function getInputType(): ReflectionClass|ReflectionMethod|ReflectionType;

    /**
     * @return ReflectionClass<object>|ReflectionMethod|ReflectionType
     */
    public function getOutputType(): ReflectionClass|ReflectionMethod|ReflectionType;

    /**
     * @return class-string<RestApiController>
     */
    public function getController(): string;

    /**
     * @return class-string<ActionInterface>
     */
    public function getAction(): string;

    public function getDescription(): string;
    public function getTags(): StringList;
}
