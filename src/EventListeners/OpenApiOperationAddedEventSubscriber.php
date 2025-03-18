<?php
namespace Apie\RestApi\EventListeners;

use Apie\Common\Actions\GetListAction;
use Apie\Core\BoundedContext\BoundedContextId;
use Apie\Core\Context\ApieContext;
use Apie\Core\ContextConstants;
use Apie\Core\Datalayers\ApieDatalayer;
use Apie\Core\Datalayers\ApieDatalayerWithFilters;
use Apie\Core\Metadata\MetadataFactory;
use Apie\RestApi\Events\OpenApiOperationAddedEvent;
use Apie\TypeConverter\ReflectionTypeFactory;
use cebe\openapi\spec\Parameter;
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
                        'type' => 'int',
                        'min' => 1
                    ])
                ]);
                $parameters[] = new Parameter([
                    'name' => 'page',
                    'in' => 'query',
                    'schema' => new Schema([
                        'type' => 'int',
                        'min' => 0
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
