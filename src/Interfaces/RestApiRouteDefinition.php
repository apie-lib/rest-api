<?php
namespace Apie\RestApi\Interfaces;

use Apie\Core\Actions\HasRouteDefinition;
use Apie\Core\ContextBuilders\ContextBuilderInterface;
use Apie\RestApi\Lists\StringList;

interface RestApiRouteDefinition extends HasRouteDefinition
{
    public const CONTENT_TYPE = 'CONTENT_TYPE';
    public const OPENAPI_POST = 'OPENAPI_POST';
    public const OPENAPI_ACTION = 'OPENAPI_ACTION';
    public const RESOURCE_NAME = 'RESOURCE_NAME';
    public const RAW_CONTENTS = ContextBuilderInterface::RAW_CONTENTS;

    public function getDescription(): string;
    public function getTags(): StringList;
}
