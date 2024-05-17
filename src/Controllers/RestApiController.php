<?php
namespace Apie\RestApi\Controllers;

use Apie\Common\ApieFacade;
use Apie\Common\Events\ResponseDispatcher;
use Apie\Core\Actions\ActionResponse;
use Apie\Core\ContextBuilders\ContextBuilderFactory;
use Apie\Core\ContextConstants;
use Apie\Serializer\EncoderHashmap;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class RestApiController
{
    public function __construct(
        private readonly ContextBuilderFactory $contextBuilderFactory,
        private readonly ApieFacade $apieFacade,
        private readonly EncoderHashmap $encoderHashmap,
        private readonly ResponseDispatcher $responseDispatcher
    ) {
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $context = $this->contextBuilderFactory->createFromRequest($request, [ContextConstants::REST_API => true]);

        $action = $this->apieFacade->createAction($context);
        $data = ($action)($context, $context->getContext(ContextConstants::RAW_CONTENTS));

        return $this->createResponse($request, $data);
    }

    private function createResponse(ServerRequestInterface $request, ActionResponse $output): ResponseInterface
    {
        $contentType = $this->encoderHashmap->getAcceptedContentTypeForRequest($request);
        $encoder = $this->encoderHashmap[$contentType];
        
        $psr17Factory = new Psr17Factory();
        $statusCode = $output->getStatusCode();

        $responseBody = $psr17Factory->createStream($statusCode === 204 ? '' : $encoder->encode($output->getResultAsNativeData()));

        $response = $psr17Factory->createResponse($statusCode)
            ->withBody($responseBody)
            ->withHeader('Content-Type', $contentType);
        $response = $this->responseDispatcher->triggerResponseCreated($response, $output->apieContext);

        return $response;
    }
}
