<?php
namespace Apie\Tests\RestApi\Controllers;

use Apie\Core\BoundedContext\BoundedContext;
use Apie\Core\BoundedContext\BoundedContextHashmap;
use Apie\Core\BoundedContext\BoundedContextId;
use Apie\Core\ContextBuilders\ContextBuilderFactory;
use Apie\Core\Lists\ReflectionClassList;
use Apie\Core\Lists\ReflectionMethodList;
use Apie\Fixtures\Entities\UserWithAddress;
use Apie\Fixtures\Identifiers\UserWithAddressIdentifier;
use Apie\RestApi\Actions\CreateObjectAction;
use Apie\RestApi\Controllers\CreateResourceController;
use Apie\Serializer\DecoderHashmap;
use Apie\Serializer\EncoderHashmap;
use Apie\Serializer\Serializer;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionClass;

class CreateResourceControllerTest extends TestCase
{
    protected function givenAControllerToCreateAnObject(ReflectionClass $class): CreateResourceController
    {
        return new CreateResourceController(
            ContextBuilderFactory::create(),
            new BoundedContextHashmap(['test' => $this->givenABoundedContext()]),
            new CreateObjectAction(Serializer::create()),
            EncoderHashmap::create(),
            DecoderHashmap::create()
        );
    }

    protected function givenABoundedContext(): BoundedContext
    {
        return new BoundedContext(
            new BoundedContextId('test'),
            new ReflectionClassList([
                new ReflectionClass(UserWithAddress::class)
            ]),
            new ReflectionMethodList([
            ])
        );
    }

    protected function givenAPostRequestWithBody(string $uri, mixed $body): ServerRequestInterface
    {
        $factory = new Psr17Factory();
        $stream = $factory->createStream(json_encode($body));
        return $factory->createServerRequest('POST', $uri)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($stream)
            ->withHeader('Accept', 'application/json')
            ->withAttribute('boundedContextId', 'test')
            ->withAttribute('resourceName', UserWithAddress::class);
    }

    /**
     * @test
     */
    public function it_can_create_an_object()
    {
        $testItem = $this->givenAControllerToCreateAnObject(new ReflectionClass(UserWithAddress::class));
        $id = UserWithAddressIdentifier::createRandom()->toNative();
        $data = [
            'address' => [
                'street' => 'Evergreen Terrace',
                'streetNumber' => 742,
                'zipcode' => 14141,
                'city' => 'Evergreen Terrace',
            ],
            'id' => $id,
        ];
        $request = $this->givenAPostRequestWithBody('/UserWithAddress', $data);
        $actual = $testItem($request);
        $this->assertStringContainsStringIgnoringCase('application/json', $actual->getHeader('Content-Type')[0] ?? '(null)');
        $body = json_decode((string) $actual->getBody(), true);
        $expectedData = [
            ...$data,
            'password' => null,
        ];
        $this->assertEquals(
            $expectedData,
            $body
        );
    }
}
