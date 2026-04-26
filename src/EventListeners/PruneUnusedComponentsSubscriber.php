<?php
namespace Apie\RestApi\EventListeners;

use Apie\RestApi\Events\OpenApiSchemaGeneratedEvent;
use cebe\openapi\spec\OpenApi;
use cebe\openapi\SpecBaseObject;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PruneUnusedComponentsSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            OpenApiSchemaGeneratedEvent::class => 'onOpenApiSchemaGenerated',
        ];
    }

    public function onOpenApiSchemaGenerated(OpenApiSchemaGeneratedEvent $event): void
    {
        $openApi = $event->openApi;

        do {
            $before = count($openApi->components->schemas ?? []);

            $usedSchemas = $this->collectUsedSchemaRefs($openApi);
            $schemas = $openApi->components->schemas ?? [];

            foreach ($schemas as $name => $_schema) {
                if (!isset($usedSchemas[$name])) {
                    unset($schemas[$name]);
                }
            }

            $openApi->components->schemas = $schemas;

            $after = count($openApi->components->schemas ?? []);
        } while ($after !== $before);
    }

    /**
     * @return array<string, bool>
     */
    private function collectUsedSchemaRefs(OpenApi $openApi): array
    {
        $used = [];
        $queue = [];

        $this->collectRefsFromRoot($openApi, $queue);

        while (!empty($queue)) {
            $ref = array_pop($queue);

            if (!str_starts_with($ref, '#/components/schemas/')) {
                continue;
            }

            $name = substr($ref, strlen('#/components/schemas/'));
            $used[$name] = true;
        }

        return $used;
    }

    /**
     * @param array<int, string> $queue
     */
    private function collectRefsFromRoot(OpenApi $openApi, array &$queue): void
    {
        foreach ($openApi->paths ?? [] as $path) {
            foreach ($path->getOperations() as $op) {
                $this->collectRefsFromObject($op, $queue);
            }
        }

        foreach ($openApi->components->parameters ?? [] as $p) {
            $this->collectRefsFromObject($p, $queue);
        }

        foreach ($openApi->components->requestBodies ?? [] as $rb) {
            $this->collectRefsFromObject($rb, $queue);
        }

        foreach ($openApi->components->responses ?? [] as $r) {
            $this->collectRefsFromObject($r, $queue);
        }

        foreach ($openApi->components->schemas ?? [] as $r) {
            $this->collectRefsFromObject($r, $queue);
        }
    }

    /**
     * @param array<int, string> $queue
     */
    private function collectRefsFromObject(mixed $node, array &$queue): void
    {
        if (is_array($node)) {
            foreach ($node as $v) {
                $this->collectRefsFromObject($v, $queue);
            }
            return;
        }
        if ($node instanceof SpecBaseObject) {
            foreach ($node->getSerializableData() as $prop) {
                $this->collectRefsFromObject($prop, $queue);
            }
            return;
        }

        if (is_object($node)) {
            foreach (get_object_vars($node) as $v) {
                $this->collectRefsFromObject($v, $queue);
            }
            return;
        }

        if (is_string($node)) {
            if (str_starts_with($node, '#/components/schemas/')) {
                $queue[] = $node;
            }
        }
    }
}
