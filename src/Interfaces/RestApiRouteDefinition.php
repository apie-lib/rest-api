<?php
namespace Apie\RestApi\Interfaces;

use Apie\Core\Actions\ActionInterface;
use Apie\Core\Actions\HasRouteDefinition;
use Apie\RestApi\Lists\StringList;

interface RestApiRouteDefinition extends ActionInterface, HasRouteDefinition
{
    public const OPENAPI_POST = "OPENAPI_POST";
    public const OPENAPI_ACTION = "OPENAPI_ACTION";

    public function getResourceName(): string;
    public function getDescription(): string;
    public function getOperationId(): string;
    public function getTags(): StringList;
}
