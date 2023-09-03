<?php
namespace Apie\RestApi\EventListeners;

use Apie\Common\Actions\GetListAction;
use Apie\Common\ContextConstants;
use Apie\Core\BoundedContext\BoundedContextId;
use Apie\Core\Context\ApieContext;
use Apie\Core\Datalayers\ApieDatalayer;
use Apie\Core\Datalayers\ApieDatalayerWithFilters;
use Apie\Core\Metadata\MetadataFactory;
use Apie\RestApi\Events\OpenApiOperationAddedEvent;
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
                if (null === $filterColumns) {
                    return;
                }
                $operation = $event->operation;
                $parameters = $operation->parameters ?? [];
                $parameters ??= [];
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
                        $typehint = $fieldMetadata[$filterColumn]->getTypehint();
                        $schema = $event->componentsBuilder->getSchemaForType(
                            $typehint,
                            false,
                            true
                        );
                    }

                    $parameters[] = new Parameter([
                        'name' => 'query[' . $filterColumn . ']',
                        'in' => 'query',
                        'schema' => $schema,
                    ]);
                }
                $operation->parameters = $parameters;
            }
        }
    }
}
