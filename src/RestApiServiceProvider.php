<?php
namespace Apie\RestApi;

use Apie\ServiceProviderGenerator\UseGeneratedMethods;
use Illuminate\Support\ServiceProvider;

/**
 * This file is generated with apie/service-provider-generator from file: rest_api.yaml
 * @codecoverageIgnore
 */
class RestApiServiceProvider extends ServiceProvider
{
    use UseGeneratedMethods;

    public function register()
    {
        $this->app->singleton(
            \Apie\RestApi\RouteDefinitions\RestApiRouteDefinitionProvider::class,
            function ($app) {
                return new \Apie\RestApi\RouteDefinitions\RestApiRouteDefinitionProvider(
                
                );
            }
        );
        \Apie\ServiceProviderGenerator\TagMap::register(
            $this->app,
            \Apie\RestApi\RouteDefinitions\RestApiRouteDefinitionProvider::class,
            array(
              0 =>
              array(
                'name' => 'apie.common.route_definition',
              ),
            )
        );
        $this->app->tag([\Apie\RestApi\RouteDefinitions\RestApiRouteDefinitionProvider::class], 'apie.common.route_definition');
        $this->app->singleton(
            \Apie\RestApi\OpenApi\OpenApiGenerator::class,
            function ($app) {
                return new \Apie\RestApi\OpenApi\OpenApiGenerator(
                    $app->make(\Apie\Core\ContextBuilders\ContextBuilderFactory::class),
                    $app->make(\Apie\SchemaGenerator\ComponentsBuilderFactory::class),
                    $app->make('apie.route_definitions.provider'),
                    $app->make(\Apie\Serializer\Serializer::class),
                    $this->parseArgument('%apie.rest_api.base_url%')
                );
            }
        );
        $this->app->singleton(
            \Apie\RestApi\Controllers\OpenApiDocumentationController::class,
            function ($app) {
                return new \Apie\RestApi\Controllers\OpenApiDocumentationController(
                    $app->make(\Apie\Core\BoundedContext\BoundedContextHashmap::class),
                    $app->make(\Apie\RestApi\OpenApi\OpenApiGenerator::class)
                );
            }
        );
        \Apie\ServiceProviderGenerator\TagMap::register(
            $this->app,
            \Apie\RestApi\Controllers\OpenApiDocumentationController::class,
            array(
              0 => 'controller.service_arguments',
            )
        );
        $this->app->tag([\Apie\RestApi\Controllers\OpenApiDocumentationController::class], 'controller.service_arguments');
        $this->app->singleton(
            \Apie\RestApi\Controllers\RestApiController::class,
            function ($app) {
                return new \Apie\RestApi\Controllers\RestApiController(
                    $app->make(\Apie\Core\ContextBuilders\ContextBuilderFactory::class),
                    $app->make(\Apie\Common\ApieFacade::class),
                    $app->make(\Apie\Serializer\EncoderHashmap::class)
                );
            }
        );
        \Apie\ServiceProviderGenerator\TagMap::register(
            $this->app,
            \Apie\RestApi\Controllers\RestApiController::class,
            array(
              0 => 'controller.service_arguments',
            )
        );
        $this->app->tag([\Apie\RestApi\Controllers\RestApiController::class], 'controller.service_arguments');
        $this->app->singleton(
            \Apie\RestApi\Controllers\SwaggerUIController::class,
            function ($app) {
                return new \Apie\RestApi\Controllers\SwaggerUIController(
                    $this->parseArgument('%apie.rest_api.base_url%')
                );
            }
        );
        \Apie\ServiceProviderGenerator\TagMap::register(
            $this->app,
            \Apie\RestApi\Controllers\SwaggerUIController::class,
            array(
              0 => 'controller.service_arguments',
            )
        );
        $this->app->tag([\Apie\RestApi\Controllers\SwaggerUIController::class], 'controller.service_arguments');
        
    }
}
