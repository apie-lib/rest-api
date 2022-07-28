<?php
namespace Apie\Tests\RestApi\Controllers;

use Apie\Core\BoundedContext\BoundedContext;
use Apie\Core\BoundedContext\BoundedContextHashmap;
use Apie\Core\BoundedContext\BoundedContextId;
use Apie\Core\ContextBuilders\ContextBuilderFactory;
use Apie\Core\Lists\ReflectionClassList;
use Apie\Core\Lists\ReflectionMethodList;
use Apie\Fixtures\Actions\StaticActionExample;
use Apie\Fixtures\Entities\UserWithAddress;
use Apie\Fixtures\Identifiers\UserWithAddressIdentifier;
use Apie\RestApi\Actions\CreateObjectAction;
use Apie\RestApi\Actions\RunAction;
use Apie\RestApi\Controllers\CreateResourceController;
use Apie\RestApi\Controllers\RunGlobalMethodController;
use Apie\Serializer\DecoderHashmap;
use Apie\Serializer\EncoderHashmap;
use Apie\Serializer\Serializer;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionClass;
use ReflectionMethod;

class RunGlobalMethodControllerTest extends TestCase
{
    protected function givenAControllerToRunArbitraryMethod(): RunGlobalMethodController
    {
        return new RunGlobalMethodController(
            ContextBuilderFactory::create(),
            new BoundedContextHashmap(['test' => $this->givenABoundedContext()]),
            new RunAction(new ReflectionMethod(StaticActionExample::class, 'secretCode'), Serializer::create()),
            EncoderHashmap::create(),
            DecoderHashmap::create()
        );
    }

    protected function givenABoundedContext(): BoundedContext
    {
        return new BoundedContext(
            new BoundedContextId('test'),
            new ReflectionClassList([
            ]),
            new ReflectionMethodList([
                new ReflectionMethod(StaticActionExample::class, 'secretCode')
            ])
        );
    }

    protected function givenAGetRequest(string $uri): ServerRequestInterface
    {
        $factory = new Psr17Factory();
        return $factory->createServerRequest('GET', $uri)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'application/json')
            ->withAttribute('boundedContextId', 'test');
    }

    /**
     * @test
     */
    public function it_can_run_a_method()
    {
        $testItem = $this->givenAControllerToRunArbitraryMethod();
        $request = $this->givenAGetRequest('/SecretCode');
        $actual = $testItem($request);
        $this->assertStringContainsStringIgnoringCase('application/json', $actual->getHeader('Content-Type')[0] ?? '(null)');
        $body = json_decode((string) $actual->getBody(), true);
        $expectedData = StaticActionExample::secretCode()->toArray();
        $this->assertEquals(
            $expectedData,
            $body
        );
    }
}
