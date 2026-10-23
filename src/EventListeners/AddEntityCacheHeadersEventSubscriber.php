<?php
namespace Apie\RestApi\EventListeners;

use Apie\Common\Actions\RemoveObjectAction;
use Apie\Common\Events\ApieResourceCreated;
use Apie\Common\Events\ApieResourceMethodCalled;
use Apie\Common\Events\ApieResourceModified;
use Apie\Common\Events\ApieResourceRead;
use Apie\Common\Events\ApieResponseCreated;
use Apie\Core\BoundedContext\BoundedContextId;
use Apie\Core\ContextConstants;
use Apie\Core\Datalayers\ApieDatalayer;
use Apie\Core\Datalayers\DataLayerMetadata;
use Apie\Core\Entities\EntityInterface;
use Apie\Core\IdentifierUtils;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AddEntityCacheHeadersEventSubscriber implements EventSubscriberInterface
{
    private ?EntityInterface $resource = null;

    public static function getSubscribedEvents(): array
    {
        return [
            ApieResourceCreated::class => 'onApieResourceEvent',
            ApieResourceRead::class => 'onApieResourceEvent',
            ApieResourceModified::class => 'onApieResourceEvent',
            ApieResourceMethodCalled::class => 'onApieResourceEvent',
            ApieResponseCreated::class      => 'onApieResponseCreated',
        ];
    }

    public function onApieResourceEvent(
        ApieResourceCreated|ApieResourceRead|ApieResourceModified|ApieResourceMethodCalled $event
    ): void {
        if (!$event->context->getContext(ContextConstants::REST_API, false)) {
            return;
        }
        $this->resource = $event->resource;
    }

    public function onApieResponseCreated(ApieResponseCreated $event): void
    {
        $context = $event->context;
        $action = $context->getContext(ContextConstants::APIE_ACTION, false);
        $dataLayer = $context->getContext(ApieDatalayer::class, false);
        if (
            $action === RemoveObjectAction::class
            || !$dataLayer instanceof ApieDatalayer
            || !$context->getContext(ContextConstants::REST_API, false)
            || $event->response->getStatusCode() >= 300
            || $event->response->getStatusCode() === 204
        ) {
            $this->resource = null;
            return;
        }
        if ($event->response->hasHeader('ETag')) {
            $event->response = $event->response->withHeader('Cache-Control', 'no-cache');
            $this->resource = null;
            return;
        }

        $resource = $context->getContext(ContextConstants::RESOURCE, false);
        if ($resource instanceof EntityInterface) {
            $identifier = $resource->getId();
        } elseif ($this->resource) {
            $identifier = $this->resource->getId();
        } else {
            $resourceId = $context->getContext(ContextConstants::RESOURCE_ID, false);
            if (!is_string($resourceId)) {
                $this->resource = null;
                return;
            }
            $identifier = IdentifierUtils::idStringToIdentifier($resourceId, $context, false);
            if ($identifier === null) {
                $this->resource = null;
                return;
            }
        }
        $this->resource = null;

        $boundedContextId = new BoundedContextId(
            $context->getContext(ContextConstants::BOUNDED_CONTEXT_ID, false) ?? 'default'
        );
        $metadata = $context->getContext(DataLayerMetadata::class, false);
        if (!$metadata instanceof DataLayerMetadata) {
            $metadata = $dataLayer->getMetadata($identifier, $boundedContextId);
        }
        $event->response = $event->response
            ->withHeader('ETag', '"' . $metadata->etag . '"')
            ->withHeader('Cache-Control', 'no-cache');
    }
}
