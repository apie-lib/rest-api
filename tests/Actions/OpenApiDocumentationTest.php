<?php
namespace Apie\Tests\RestApi\Actions;

use Apie\Core\BoundedContext\BoundedContext;
use Apie\Core\ContextBuilders\ContextBuilderFactory;
use Apie\Core\Controllers\ApieController;
use Apie\Core\Lists\ReflectionClassList;
use Apie\Core\Lists\ReflectionMethodList;
use Apie\Fixtures\Actions\StaticActionExample;
use Apie\Fixtures\Entities\UserWithAddress;
use Apie\Fixtures\Entities\UserWithAutoincrementKey;
use Apie\RestApi\Actions\OpenApiDocumentation;
use Apie\RestApi\OpenApi\OpenApiGenerator;
use Apie\RestApi\RouteDefinitions\RestApiRouteDefinitionProvider;
use Apie\SchemaGenerator\ComponentsBuilderFactory;
use Apie\Serializer\Serializer;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use ReflectionClass;
use ReflectionMethod;

class OpenApiDocumentationTest extends TestCase
{
    protected function givenAControllerToProvideOpenApiDocumentation(): ApieController
    {
        $contextBuilder = ContextBuilderFactory::create();
        return new ApieController(
            new OpenApiDocumentation(new OpenApiGenerator(
                $contextBuilder,
                ComponentsBuilderFactory::createComponentsBuilderFactory(),
                new RestApiRouteDefinitionProvider(),
                Serializer::create()
            )),
            $contextBuilder,
            $this->givenABoundedContext()
        );
    }

    protected function givenAGetRequest(string $uri): RequestInterface
    {
        $factory = new Psr17Factory();
        return $factory->createRequest('GET', $uri)
            ->withHeader('Accept', 'application/json');
    }

    protected function givenABoundedContext(): BoundedContext
    {
        return new BoundedContext(
            new ReflectionClassList([
                new ReflectionClass(UserWithAddress::class),
                new ReflectionClass(UserWithAutoincrementKey::class)
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
