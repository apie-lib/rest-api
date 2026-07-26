<?php
namespace Apie\RestApi\EventListeners;

use Apie\Core\Attributes\ExampleValue;
use Apie\Core\Identifiers\SnakeCaseSlug;
use Apie\Core\Utils\ConverterUtils;
use Apie\RestApi\Events\OpenApiOperationAddedEvent;
use Apie\RestApi\Events\OpenApiSchemaGeneratedEvent;
use Apie\SchemaGenerator\SchemaGenerator;
use Apie\TypeConverter\ReflectionTypeFactory;
use cebe\openapi\spec\Example;
use cebe\openapi\spec\Reference;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AddAcceptLanguageEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ?string $languageTypehint,
        private readonly SchemaGenerator $schemaFactory,
    ) {
    }
    public static function getSubscribedEvents(): array
    {
        return [
            OpenApiOperationAddedEvent::class => 'onOpenApiOperationAdded',
            OpenApiSchemaGeneratedEvent::class => 'onOpenApiSchemaGenerated',
        ];
    }

    public function onOpenApiOperationAdded(OpenApiOperationAddedEvent $event): void
    {
        if ($this->languageTypehint === null) {
            return;
        }
        $parameters = $event->operation->parameters ?? [];
        $ref = new Reference([
            '$ref' => '#/components/parameters/AcceptLanguage',
        ]);
        $parameters[] = $ref;
        if (!$event->method->hasOptionalOrNoContentBody()) {
            $ref = new Reference([
                '$ref' => '#/components/parameters/ContentLanguage',
            ]);
            $parameters[] = $ref;
        }
        $event->operation->parameters = $parameters;
    }

    public function onOpenApiSchemaGenerated(OpenApiSchemaGeneratedEvent $event): void
    {
        if ($this->languageTypehint === null) {
            return;
        }
        $openApi = $event->openApi;

        $parameters = $openApi->components->parameters ?? [];
        $examples = $this->getExamples(ReflectionTypeFactory::createReflectionType($this->languageTypehint));
        $parameter = new \cebe\openapi\spec\Parameter([
            'name' => 'Accept-Language',
            'in' => 'header',
            'required' => false,
            'description' => 'Preferred language for the response.',
            'schema' => $this->schemaFactory->createSchema(
                $this->languageTypehint,
                display: false
            ),
        ]);
        if ($examples) {
            $parameter->examples = $examples;
        }
        $parameters['AcceptLanguage'] = $parameter;
        $parameter = new \cebe\openapi\spec\Parameter([
            'name' => 'Content-Language',
            'in' => 'header',
            'required' => false,
            'description' => 'Language of content body.',
            'schema' => $this->schemaFactory->createSchema(
                $this->languageTypehint,
                display: false
            ),
        ]);
        if ($examples) {
            $parameter->examples = $examples;
        }
        $parameters['ContentLanguage'] = $parameter;
        $openApi->components->parameters = $parameters;
    }

    /**
     * @return array<string, Example>
     */
    private function getExamples(\ReflectionType $input): array
    {
        $examples = [];
        if ($input instanceof \ReflectionNamedType) {
            $class = ConverterUtils::toReflectionClass($input);
            if ($class === null) {
                return [];
            }
            foreach ($class->getAttributes(ExampleValue::class) as $attribute) {
                $exampleValue = $attribute->newInstance();
                $id = SnakeCaseSlug::fromText($exampleValue->name)->toNative();
                $examples[$id] = new Example([
                    'summary' => $exampleValue->name,
                    'value' => $exampleValue->toExample(),
                ]);
            }
            return $examples;
        }
        assert($input instanceof \ReflectionIntersectionType || $input instanceof \ReflectionUnionType);
        foreach ($input->getTypes() as $type) {
            // only allow interfaces or union types. Merge examples of intersections make no sense otherwise.
            if ($input instanceof \ReflectionUnionType || ConverterUtils::toReflectionClass($type)?->isInterface()) {
                $examples = array_merge($examples, $this->getExamples($type));
            }
        }
        return $examples;
    }
}
