<?php
namespace Apie\Tests\RestApi\Controllers;

use Apie\Core\BoundedContext\BoundedContext;
use Apie\Core\BoundedContext\BoundedContextHashmap;
use Apie\Core\BoundedContext\BoundedContextId;
use Apie\Core\ContextBuilders\ContextBuilderFactory;
use Apie\Core\Lists\ReflectionClassList;
use Apie\Core\Lists\ReflectionMethodList;
use Apie\Fixtures\Actions\StaticActionExample;
use Apie\Fixtures\Entities\Order;
use Apie\Fixtures\Entities\UserWithAddress;
use Apie\Fixtures\Entities\UserWithAutoincrementKey;
use Apie\RestApi\Controllers\OpenApiDocumentationController;
use Apie\RestApi\OpenApi\OpenApiGenerator;
use Apie\RestApi\RouteDefinitions\RestApiRouteDefinitionProvider;
use Apie\SchemaGenerator\ComponentsBuilderFactory;
use Apie\Serializer\Serializer;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionClass;
use ReflectionMethod;

class OpenApiDocumentationControllerTest extends TestCase
{
    protected function givenAControllerToProvideOpenApiDocumentation(): OpenApiDocumentationController
    {
        $contextBuilder = ContextBuilderFactory::create();
        return new OpenApiDocumentationController(
            new BoundedContextHashmap(['test' => $this->givenABoundedContext()]),
            new OpenApiGenerator(
                $contextBuilder,
                ComponentsBuilderFactory::createComponentsBuilderFactory(),
                new RestApiRouteDefinitionProvider(),
                Serializer::create()
            )
        );
    }

    protected function givenAGetRequest(string $uri): ServerRequestInterface
    {
        $factory = new Psr17Factory();
        return $factory->createServerRequest('GET', $uri)
            ->withHeader('Accept', 'application/json')
            ->withAttribute('boundedContextId', 'test')
            ->withAttribute('yaml', true);
    }

    protected function givenABoundedContext(): BoundedContext
    {
        return new BoundedContext(
            new BoundedContextId('test'),
            new ReflectionClassList([
                new ReflectionClass(UserWithAddress::class),
                new ReflectionClass(UserWithAutoincrementKey::class),
                new ReflectionClass(Order::class),
            ]),
            new ReflectionMethodList([
                new ReflectionMethod(StaticActionExample::class, 'secretCode')
            ])
        );
    }

    /**
     * @test
     */
    public function it_can_create_an_openapi_schema()
    {
        $testItem = $this->givenAControllerToProvideOpenApiDocumentation();
        $request = $this->givenAGetRequest('/openapi.yaml');
        $actual = $testItem($request);
        $contents = (string) $actual->getBody();
        $file = __DIR__ . '/../../fixtures/expected-spec.yaml';
        file_put_contents($file, $contents);
        $this->assertEquals(file_get_contents($file), $contents);
    }
}
