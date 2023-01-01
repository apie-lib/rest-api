<?php
namespace Apie\RestApi\RouteDefinitions;

use Apie\Common\Enums\UrlPrefix;
use Apie\Common\Lists\UrlPrefixList;
use Apie\Common\RouteDefinitions\AbstractRestApiRouteDefinition as CommonRestApiRouteDefinition;
use Apie\RestApi\Controllers\RestApiController;

abstract class AbstractRestApiRouteDefinition extends CommonRestApiRouteDefinition
{
    /**
     * @return class-string<RestApiController>
     */
    final public function getController(): string
    {
        return RestApiController::class;
    }

    final public function getUrlPrefixes(): UrlPrefixList
    {
        return new UrlPrefixList([UrlPrefix::API]);
    }
}
