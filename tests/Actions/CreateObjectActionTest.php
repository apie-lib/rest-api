<?php
namespace Apie\Tests\RestApi\Actions;

use Apie\Core\BoundedContext\BoundedContext;
use Apie\Core\ContextBuilders\ContextBuilderFactory;
use Apie\Core\Controllers\ApieController;
use Apie\Core\Lists\ReflectionClassList;
use Apie\Core\Lists\ReflectionMethodList;
use Apie\Fixtures\Entities\UserWithAddress;
use Apie\Fixtures\Identifiers\UserWithAddressIdentifier;
use Apie\RestApi\Actions\CreateObjectAction;
use Apie\Serializer\Serializer;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use ReflectionClass;

class CreateObjectActionTest extends TestCase
{
    protected function givenAControllerToCreateAnObject(ReflectionClass $class): ApieController
    {
        return new ApieController(
            new CreateObjectAction($class, Serializer::create()),
            ContextBuilderFactory::create(),
            new BoundedContext(new ReflectionClassList(), new ReflectionMethodList())
        );
    }

    protected function givenAPostRequestWithBody(string $uri, mixed $body): RequestInterface
    {
        $factory = new Psr17Factory();
        $stream = $factory->createStream(json_encode($body));
        return $factory->createRequest('POST', $uri)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($stream)
            ->withHeader('Accept', 'application/json');
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
