<img src="https://raw.githubusercontent.com/apie-lib/apie-lib-monorepo/main/docs/apie-logo.svg" width="100px" align="left" />
<h1>rest-api</h1>






 [![Latest Stable Version](https://poser.pugx.org/apie/rest-api/v)](https://packagist.org/packages/apie/rest-api) [![Total Downloads](https://poser.pugx.org/apie/rest-api/downloads)](https://packagist.org/packages/apie/rest-api) [![Latest Unstable Version](https://poser.pugx.org/apie/rest-api/v/unstable)](https://packagist.org/packages/apie/rest-api) [![License](https://poser.pugx.org/apie/rest-api/license)](https://packagist.org/packages/apie/rest-api) [![PHP Composer](https://apie-lib.github.io/projectCoverage/coverage-rest-api.svg)](https://apie-lib.github.io/projectCoverage/rest-api/index.html)  

[![PHP Composer](https://github.com/apie-lib/rest-api/actions/workflows/php.yml/badge.svg?event=push)](https://github.com/apie-lib/rest-api/actions/workflows/php.yml)

This package is part of the [Apie](https://github.com/apie-lib) library.
The code is maintained in a monorepo, so PR's need to be sent to the [monorepo](https://github.com/apie-lib/apie-lib-monorepo/pulls)

## Documentation
Exposes Apie resources as a REST API: it turns actions from `apie/common` into HTTP routes,
generates an OpenAPI document with `apie/schema-generator`, and serves a Swagger UI. It builds
on `apie/serializer` for (de)normalization and works with any PSR-7/PSR-15 application.

### Standalone usage
Install it with:
```bash
composer require apie/rest-api
```

Construct `Apie\RestApi\RouteDefinitions\RestApiRouteDefinitionProvider` (which turns Apie
actions into routes handled by `Apie\RestApi\Controllers\RestApiController`) and
`Apie\RestApi\OpenApi\OpenApiGenerator` to build the OpenAPI document served by
`Apie\RestApi\Controllers\OpenApiDocumentationController` and
`Apie\RestApi\Controllers\SwaggerUIController`. These pieces can be wired manually into any
PSR-15 application.

### Symfony integration
Via `apie/apie-bundle`, `rest_api.yaml` registers the route definition provider, the OpenAPI
generator (using the `apie.rest_api.base_url` parameter) and the controllers as Symfony
controller services, plus event subscribers that add the `Accept-Language` header, normalize
OpenAPI tags and prune unused schema components.

### Laravel integration
Via `apie/laravel-apie`, the generated `Apie\RestApi\RestApiServiceProvider` registers the same
route definition provider, controllers and OpenAPI generator so the REST API and Swagger UI
routes are available as Artisan/Laravel routes.
