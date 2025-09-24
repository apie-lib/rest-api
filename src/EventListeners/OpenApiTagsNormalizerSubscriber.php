<?php
namespace Apie\RestApi\EventListeners;

use Apie\Core\BoundedContext\BoundedContextHashmap;
use Apie\RestApi\Events\OpenApiSchemaGeneratedEvent;
use cebe\openapi\spec\OpenApi;
use cebe\openapi\spec\Operation;
use cebe\openapi\spec\PathItem;
use cebe\openapi\spec\Tag;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OpenApiTagsNormalizerSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly BoundedContextHashmap $boundedContextHashmap
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            OpenApiSchemaGeneratedEvent::class => 'onOpenApiSchemaGenerated',
        ];
    }

    public function onOpenApiSchemaGenerated(OpenApiSchemaGeneratedEvent $event): void
    {
        $openApi = $event->openApi ?? null;
        if (!$openApi instanceof OpenApi) {
            return;
        }
        $allTags = [];
        foreach ($event->boundedContext->resources as $resource) {
            $allTags[] = $resource->getShortName();
        }
        $addPaths = [];
        // Replace 'all' tag with all available tags
        foreach ($openApi->paths as $path => $pathItem) {
            foreach ($pathItem->getOperations() as $operation) {
                if (in_array('all', $operation->tags ?? [], true)) {
                    foreach ($this->boundedContextHashmap->getTupleIterator() as $tuple) {
                        if ($tuple->boundedContext->getId()->toNative() !== $event->boundedContext->getId()->toNative()) {
                            continue;
                        }
                        if (!in_array($tuple->resourceClass->getShortName(), $allTags, true)) {
                            continue;
                        }
                        $newPath = str_replace('{resourceName}', $tuple->resourceClass->getShortName(), $path);
                        $addPaths[$newPath] = $openApi->paths[$newPath] ?? $addPaths[$newPath] ?? unserialize(serialize($pathItem));
                        assert($addPaths[$newPath] instanceof PathItem);
                        $newOperation = unserialize(serialize($operation));
                        $this->patchOperation($pathItem, $operation, $addPaths[$newPath], $newOperation, $tuple->resourceClass->getShortName());
                    }
                    $newTags = [
                        ...$allTags,
                        ...array_filter(
                            $operation->tags ?? [],
                            fn ($tag) => $tag !== 'all'
                        )
                    ];
                    sort($newTags);
                    $operation->tags = array_values(array_unique($newTags));
                }
            }
        }
        $paths = $openApi->paths;
        foreach ($addPaths as $newPath => $pathItem) {
            $paths[$newPath] = $pathItem;
        }
        sort($allTags);
        $tags = array_map(
            fn ($tag) => ['name' => $tag, 'description' => 'All operations for ' . $tag],
            array_unique($allTags)
        );
        $actionTags = [];
        foreach ($event->boundedContext->actions as $action) {
            $actionTags[] = $action->getDeclaringClass()->getShortName();
        }
        sort($actionTags);
        $tags = array_merge(
            $tags,
            array_map(
                fn ($tag) => new Tag(['name' => $tag, 'description' => 'All operations for ' . $tag]),
                array_unique($actionTags)
            )
        );
        $openApi->tags = $tags;
        $openApi->paths = $paths;
    }

    private function patchOperation(
        PathItem $oldPathItem,
        Operation $oldOperation,
        PathItem $pathItem,
        Operation $operation,
        string $suffix
    ): void {
        $httpMethods = ['get', 'put', 'post', 'delete', 'options', 'head', 'patch', 'trace'];
        foreach ($httpMethods as $httpMethod) {
            if ($oldPathItem->{$httpMethod} === $oldOperation) {
                $pathItem->{$httpMethod} = $operation;
                break;
            }
        }
        $operation->operationId = $operation->operationId . '_' . $suffix;
        if ($operation->parameters) {
            $parameters = array_filter($operation->parameters, function ($parameter) {
                return $parameter->name !== 'resourceName';
            });
            $operation->parameters = array_values($parameters);
        }

        $newTags = [
            $suffix,
            ...array_filter(
                $operation->tags ?? [],
                fn ($tag) => $tag !== 'all'
            )
        ];
        sort($newTags);
        $operation->tags = array_values(array_unique($newTags));
    }
}
