<?php
namespace Apie\RestApi\EventListeners;

use Apie\Common\Actions\CreateObjectAction;
use Apie\Common\Actions\GetItemAction;
use Apie\Common\Actions\GetListAction;
use Apie\Common\Actions\ModifyObjectAction;
use Apie\Core\BoundedContext\BoundedContextId;
use Apie\Core\Context\ApieContext;
use Apie\Core\ContextConstants;
use Apie\Core\Datalayers\ApieDatalayer;
use Apie\Core\Datalayers\ApieDatalayerWithFilters;
use Apie\Core\Enums\RequestMethod;
use Apie\Core\Metadata\MetadataFactory;
use Apie\RestApi\Events\OpenApiOperationAddedEvent;
use Apie\TypeConverter\ReflectionTypeFactory;
use cebe\openapi\spec\Parameter;
use cebe\openapi\spec\Response;
use cebe\openapi\spec\Schema;
use ReflectionClass;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OpenApiOperationAddedEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ApieDatalayer $apieDatalayer
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            OpenApiOperationAddedEvent::class => 'onOperationAdded'
        ];
    }

    public function onOperationAdded(OpenApiOperationAddedEvent $event): void
    {
        $responses = $event->operation->responses ?? [];

        if ($event->routeDefinition->getAction() === GetItemAction::class) {
            $parameters = $event->operation->parameters ?? [];
            $parameters[] = new Parameter([
                'name' => 'If-None-Match',
                'in' => 'header',
                'required' => false,
                'explode' => false,
                'schema' => new Schema([
                    'type' => 'array',
                    'items' => new Schema([
                        'type' => 'string',
                    ]),
                ]),
            ]);
            $event->operation->parameters = $parameters;
            $responses = $event->operation->responses ?? [];
            $responses[304] = new Response([
                'description' => 'Not Modified'
            ]);
            $event->operation->responses = $responses;
            return;
        }

        if ($event->routeDefinition->getAction() === ModifyObjectAction::class) {
            $parameters = $event->operation->parameters ?? [];
            $parameters[] = new Parameter([
                'name' => 'If-Match',
                'in' => 'header',
                'required' => false,
                'explode' => false,
                'schema' => new Schema([
                    'type' => 'array',
                    'items' => new Schema([
                        'type' => 'string',
                    ]),
                ]),
            ]);
            $event->operation->parameters = $parameters;
            return;
        }

        if (
            $event->routeDefinition->getAction() === CreateObjectAction::class
            && $event->method === RequestMethod::PUT
        ) {
            $parameters = $event->operation->parameters ?? [];
            $parameters[] = new Parameter([
                'name' => 'If-Match',
                'in' => 'header',
                'required' => false,
                'explode' => false,
                'schema' => new Schema([
                    'type' => 'array',
                    'items' => new Schema([
                        'type' => 'string',
                    ]),
                ]),
            ]);
            $event->operation->parameters = $parameters;
            return;
        }

        if ($this->apieDatalayer instanceof ApieDatalayerWithFilters) {
            $routeAttributes = $event->routeDefinition->getRouteAttributes();
            $resourceName = $routeAttributes[ContextConstants::RESOURCE_NAME] ?? null;
            $boundedContextId = $routeAttributes[ContextConstants::BOUNDED_CONTEXT_ID] ?? 'default';
            if ($resourceName && $event->routeDefinition->getAction() === GetListAction::class) {
                $refl = new ReflectionClass($resourceName);
                $fieldMetadata = MetadataFactory::getResultMetadata(
                    $refl,
                    new ApieContext()
                )->getHashmap();
                $filterColumns = $this->apieDatalayer->getFilterColumns($refl, new BoundedContextId($boundedContextId));
                // TODO: this assumes that if there are no filters, there is also no global search?
                if (null === $filterColumns) {
                    return;
                }
                $operation = $event->operation;
                $parameters = $operation->parameters ?? [];
                $parameters ??= [];
                $parameters[] = new Parameter([
                    'name' => 'items_per_page',
                    'in' => 'query',
                    'schema' => new Schema([
                        'type' => 'integer',
                        'minimum' => 1
                    ])
                ]);
                $parameters[] = new Parameter([
                    'name' => 'page',
                    'in' => 'query',
                    'schema' => new Schema([
                        'type' => 'integer',
                        'minimum' => 0
                    ])
                ]);
                $parameters[] = new Parameter([
                    'name' => 'search',
                    'in' => 'query',
                    'schema' => new Schema([
                        'type' => 'string',
                        'minLength' => 1,
                    ])
                ]);
                foreach ($filterColumns as $filterColumn) {
                    $schema = new Schema([
                        'type' => 'string',
                        'minLength' => 1,
                    ]);
                    if (isset($fieldMetadata[$filterColumn])) {
                        $typehint = ReflectionTypeFactory::createReflectionType('string');
                        $schema = $event->componentsBuilder->getSchemaForType(
                            $typehint,
                            false,
                            true,
                            $fieldMetadata[$filterColumn]->allowsNull()
                        );
                    }

                    $parameters[] = new Parameter([
                        'name' => 'query[' . $filterColumn . ']',
                        'in' => 'query',
                        'schema' => $schema,
                    ]);
                }
                $orderByColumns = $this->apieDatalayer->getOrderByColumns($refl, new BoundedContextId($boundedContextId));
                if ($orderByColumns?->count()) {
                    $values = [];
                    foreach ($orderByColumns as $orderByColumn) {
                        array_push(
                            $values,
                            $orderByColumn,
                            '+' . $orderByColumn,
                            '-' . $orderByColumn
                        );
                    }

                    $parameters[] = new Parameter([
                        'name' => 'order_by',
                        'in' => 'query',
                        'schema' => new Schema([
                            'type' => 'array',
                            'items' => [
                                'type' => 'string',
                                'enum' => $values,
                            ]
                        ])
                    ]);
                }
                $operation->parameters = $parameters;
            }
        }
    }
}
