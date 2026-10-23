<?php
namespace Apie\RestApi\Controllers;

use Apie\Common\ApieFacade;
use Apie\Common\Events\ResponseDispatcher;
use Apie\Common\Exceptions\EntityNotModifiedException;
use Apie\Common\StreamFactory\StreamFactoryInterface;
use Apie\Core\Actions\ActionResponse;
use Apie\Core\ContextBuilders\ContextBuilderFactory;
use Apie\Core\ContextConstants;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class RestApiController
{
    public function __construct(
        private readonly ContextBuilderFactory $contextBuilderFactory,
        private readonly ApieFacade $apieFacade,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly ResponseDispatcher $responseDispatcher
    ) {
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $context = $this->contextBuilderFactory->createFromRequest($request, [ContextConstants::REST_API => true]);

        $action = $this->apieFacade->createAction($context);
        try {
            $data = ($action)($context, $context->getContext(ContextConstants::RAW_CONTENTS));
        } catch (EntityNotModifiedException $exception) {
            $response = new Response(304, [
                'ETag' => $exception->etag,
                'Cache-Control' => 'no-cache',
            ]);
            return $this->responseDispatcher->triggerResponseCreated($response, $context);
        }

        return $this->createResponse($request, $data);
    }

    private function createResponse(ServerRequestInterface $request, ActionResponse $output): ResponseInterface
    {
        $response = $this->streamFactory->createStream($request, $output) ?? new Response();
        $response = $this->responseDispatcher->triggerResponseCreated($response, $output->apieContext);

        return $response;
    }
}
