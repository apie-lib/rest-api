<?php
namespace Apie\Tests\RestApi\Controllers;

use Apie\Common\RequestBodyDecoder;
use Apie\RestApi\Controllers\QueryResourceListController;
use Apie\RestApi\Controllers\RestApiController;
use Apie\Serializer\DecoderHashmap;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

class QueryResourceListControllerTest extends TestCase
{
    #[Test]
    public function it_forwards_a_query_request_as_get_with_body_parameters(): void
    {
        $response = new Response();
        $restApiController = $this->createMock(RestApiController::class);
        $restApiController
            ->expects(self::once())
            ->method('__invoke')
            ->with(self::callback(function (ServerRequestInterface $request): bool {
                self::assertSame('GET', $request->getMethod());
                self::assertSame(
                    ['fields' => 'name', 'filter' => ['status' => 'active']],
                    $request->getQueryParams()
                );
                return true;
            }))
            ->willReturn($response);

        $factory = new Psr17Factory();
        $body = $factory->createStream();
        $body->write('{"filter":{"status":"active"}}');
        $request = $factory->createServerRequest('QUERY', '/items')
            ->withHeader('Content-Type', 'application/json')
            ->withQueryParams(['fields' => 'name'])
            ->withBody($body);
        $controller = new QueryResourceListController(
            $restApiController,
            new RequestBodyDecoder(DecoderHashmap::create())
        );

        self::assertSame($response, $controller($request));
    }
}
