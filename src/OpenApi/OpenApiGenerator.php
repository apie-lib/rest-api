<?php
namespace Apie\RestApi\OpenApi;

use Apie\Core\BoundedContext\BoundedContext;
use Apie\Core\ContextBuilders\ContextBuilderFactory;
use Apie\Core\Enums\RequestMethod;
use Apie\Core\RouteDefinitions\RouteDefinitionProviderInterface;
use Apie\RestApi\Interfaces\RestApiRouteDefinition;
use Apie\SchemaGenerator\Builders\ComponentsBuilder;
use Apie\SchemaGenerator\ComponentsBuilderFactory;
use Apie\Serializer\Serializer;
use cebe\openapi\Reader;
use cebe\openapi\ReferenceContext;
use cebe\openapi\spec\MediaType;
use cebe\openapi\spec\OpenApi;
use cebe\openapi\spec\Operation;
use cebe\openapi\spec\PathItem;
use cebe\openapi\spec\Paths;
use cebe\openapi\spec\Reference;
use cebe\openapi\spec\RequestBody;
use cebe\openapi\spec\Response;
use cebe\openapi\spec\Schema;
use cebe\openapi\spec\Tag;
use ReflectionClass;

class OpenApiGenerator
{
    /**
     * Serialized string of OpenAPI so we always get a deep clone.
     */
    private string $baseSpec;
    public function __construct(
        private ContextBuilderFactory $contextBuilder,
        private ComponentsBuilderFactory $componentsFactory,
        private RouteDefinitionProviderInterface $routeDefinitionProvider,
        private Serializer $serializer,
        ?OpenApi $baseSpec = null
    ) {
        $baseSpec ??= $this->createDefaultSpec();
        if (!$baseSpec->paths) {
            $baseSpec->paths = new Paths([]);
        }
        $this->baseSpec = serialize($baseSpec);
    }

    private function createDefaultSpec(): OpenApi
    {
        return Reader::readFromYamlFile(
            __DIR__ . '/../../resources/openapi.yaml',
            OpenApi::class,
            ReferenceContext::RESOLVE_MODE_INLINE
        );
    }

    public function create(BoundedContext $boundedContext): OpenApi
    {
        $spec = unserialize($this->baseSpec);
        $componentsBuilder = $this->componentsFactory->createComponentsBuilder();
        $context = $this->contextBuilder->createGeneralContext([OpenApiGenerator::class => $this, Serializer::class => $this->serializer]);
        foreach ($this->routeDefinitionProvider->getActionsForBoundedContext($boundedContext, $context) as $routeDefinition) {
            if ($routeDefinition instanceof RestApiRouteDefinition) {
                $path = $routeDefinition->getUrl()->toNative();
                if ($spec->paths->hasPath($path)) {
                    $pathItem = $spec->paths->getPath($path);
                } else {
                    $pathItem = new PathItem([]);
                    $spec->paths->addPath($path, $pathItem);
                }
                $this->addAction($pathItem, $componentsBuilder, $routeDefinition);
            }
        }

        $spec->components = $componentsBuilder->getComponents();

        return $spec;
    }

    private function createSchemaForInput(ComponentsBuilder $componentsBuilder, RestApiRouteDefinition $routeDefinition): Schema|Reference|null
    {
        $input = $routeDefinition->getInputType();
        if ($input instanceof ReflectionClass) {
            return $componentsBuilder->addCreationSchemaFor($input->name);
        }
        return null;
    }

    private function createSchemaForOutput(ComponentsBuilder $componentsBuilder, RestApiRouteDefinition $routeDefinition): Schema|Reference|null
    {
        $input = $routeDefinition->getOutputType();
        if ($input instanceof ReflectionClass) {
            return $componentsBuilder->addDisplaySchemaFor($input->name);
        }
        return null;
    }

    private function addAction(PathItem $pathItem, ComponentsBuilder $componentsBuilder, RestApiRouteDefinition $routeDefinition)
    {
        $method = $routeDefinition->getMethod();
        $inputSchema = $this->createSchemaForInput($componentsBuilder, $routeDefinition);
        $outputSchema = $this->createSchemaForOutput($componentsBuilder, $routeDefinition);
        $operation = new Operation([
            'tags' => $routeDefinition->getTags()->toArray(),
            'description' => $routeDefinition->getDescription(),
            'operationId' => $routeDefinition->getOperationId(),
        ]);
        if ($inputSchema && $method !== RequestMethod::GET) {
            $operation->requestBody = new RequestBody([
                'content' => [
                    'application/json' => new MediaType(['schema' => $inputSchema])
                ]
            ]);
        }
        if ($outputSchema) {
            $operation->responses = [
                201 => new Response([
                    'description' => 'OK',
                    'content' => [
                        'application/json' => new MediaType(['schema' => $outputSchema])
                    ]
                ]),
            ];
        }
        $prop = strtolower($method->value);
        $pathItem->{$prop} = $operation;
    }
}
