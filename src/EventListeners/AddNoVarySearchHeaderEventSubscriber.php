<?php
namespace Apie\RestApi\EventListeners;

use Apie\Common\Actions\GetListAction;
use Apie\Common\Events\ApieResponseCreated;
use Apie\Core\BoundedContext\BoundedContextId;
use Apie\Core\ContextConstants;
use Apie\Core\Datalayers\ApieDatalayer;
use Apie\Core\Datalayers\ApieDatalayerWithFilters;
use ReflectionClass;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AddNoVarySearchHeaderEventSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            ApieResponseCreated::class => 'onApieResponseCreated',
        ];
    }

    public function onApieResponseCreated(ApieResponseCreated $event): void
    {
        $context = $event->context;
        if (
            !$context->getContext(ContextConstants::REST_API, false)
            || $context->getContext(ContextConstants::APIE_ACTION, false) !== GetListAction::class
        ) {
            return;
        }

        $dataLayer = $context->getContext(ApieDatalayer::class, false);
        $resourceName = $context->getContext(ContextConstants::RESOURCE_NAME, false);
        if (
            !$dataLayer instanceof ApieDatalayerWithFilters
            || !is_string($resourceName)
            || !class_exists($resourceName)
        ) {
            return;
        }

        $resourceClass = new ReflectionClass($resourceName);
        $boundedContextId = new BoundedContextId(
            $context->getContext(ContextConstants::BOUNDED_CONTEXT_ID, false) ?? 'default'
        );
        $filterColumns = $dataLayer->getFilterColumns($resourceClass, $boundedContextId);
        if ($filterColumns === null) {
            return;
        }

        $parameters = ['page', 'items_per_page', 'search'];
        foreach ($filterColumns as $filterColumn) {
            $parameters[] = 'query[' . $filterColumn . ']';
        }

        if ($dataLayer->getOrderByColumns($resourceClass, $boundedContextId)?->count()) {
            $parameters[] = 'order_by';
        }

        $except = array_map(
            static fn (string $parameter): string => '"' . addcslashes($parameter, '"\\') . '"',
            $parameters
        );
        $event->response = $event->response->withHeader(
            'No-Vary-Search',
            'params, except=(' . implode(' ', $except) . ')'
        );
    }
}
