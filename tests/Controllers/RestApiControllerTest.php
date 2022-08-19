<?php
namespace Apie\Tests\RestApi\Controllers;

use Apie\Common\Actions\RunAction;
use Apie\Common\ContextConstants;
use Apie\Common\Tests\Concerns\ProvidesApieFacade;
use Apie\Core\ContextBuilders\ContextBuilderFactory;
use Apie\Fixtures\Actions\StaticActionExample;
use Apie\Fixtures\BoundedContextFactory;
use Apie\RestApi\Controllers\RestApiController;
use Apie\Serializer\DecoderHashmap;
use Apie\Serializer\EncoderHashmap;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

class RestApiControllerTest extends TestCase
{
    use ProvidesApieFacade;

    protected function givenAControllerToRunArbitraryMethod(): RestApiController
    {
        $boundedContextHashmap = BoundedContextFactory::createHashmap();
        return new RestApiController(
            ContextBuilderFactory::create(),
            $boundedContextHashmap,
            $this->givenAnApieFacade(RunAction::class, $boundedContextHashmap),
            EncoderHashmap::create(),
            DecoderHashmap::create()
        );
    }

    protected function givenAGetRequest(string $uri): ServerRequestInterface
    {
        $factory = new Psr17Factory();
        return $factory->createServerRequest('GET', $uri)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'application/json')
            ->withAttribute(ContextConstants::BOUNDED_CONTEXT_ID, 'default')
            ->withAttribute(ContextConstants::SERVICE_CLASS, StaticActionExample::class)
            ->withAttribute(ContextConstants::METHOD_NAME, 'secretCode')
            ->withAttribute(ContextConstants::OPERATION_ID, 'test');
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
