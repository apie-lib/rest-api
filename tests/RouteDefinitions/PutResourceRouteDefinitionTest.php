<?php
namespace Apie\Tests\RestApi\RouteDefinitions;

use Apie\Core\BoundedContext\BoundedContextId;
use Apie\Core\Enums\RequestMethod;
use Apie\RestApi\RouteDefinitions\PutResourceRouteDefinition;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use stdClass;

class PutResourceRouteDefinitionTest extends TestCase
{
    #[Test]
    public function it_puts_a_resource_at_its_identifier_url(): void
    {
        $route = new PutResourceRouteDefinition(
            new ReflectionClass(stdClass::class),
            new BoundedContextId('test')
        );

        self::assertSame(RequestMethod::PUT, $route->getMethod());
        self::assertSame('/stdClass/{id}', $route->getUrl()->toNative());
    }
}
