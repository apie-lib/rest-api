<?php
namespace Apie\RestApi\OpenApi;

use Apie\Core\BoundedContext\BoundedContext;
use Apie\Core\ContextBuilders\ContextBuilderFactory;
use Apie\Core\Enums\RequestMethod;
use Apie\Core\RouteDefinitions\RouteDefinitionProviderInterface;
use Apie\RestApi\Interfaces\RestApiRouteDefinition;
use Apie\RestApi\RouteDefinitions\ListOf;
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
use cebe\openapi\spec\Server;
use ReflectionClass;
use ReflectionMethod;
use ReflectionType;

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
        private string $baseUrl = '',
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
        $urlPrefix = $this->baseUrl . '/' . $boundedContext->getId();
        $spec->servers = [new Server(['url' => $urlPrefix]), new Server(['url' => 'http://localhost/' . $urlPrefix])];
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

    private function createSchemaForInput(ComponentsBuilder $componentsBuilder, RestApiRouteDefinition $routeDefinition): Schema|Reference
    {
        $input = $routeDefinition->getInputType();
        if ($input instanceof ListOf) {
            return new Schema([
                'type' => 'array',
                'items' => $this->doSchemaForInput($input->type, $componentsBuilder),
            ]);
        }
        
        return $this->doSchemaForInput($input, $componentsBuilder);
    }

    /**
     * @param ReflectionClass<object>|ReflectionMethod|ReflectionType $input
     */
    private function doSchemaForInput(ReflectionClass|ReflectionMethod|ReflectionType $input, ComponentsBuilder $componentsBuilder): Schema|Reference
    {
        if ($input instanceof ReflectionClass) {
            return $componentsBuilder->addCreationSchemaFor($input->name);
        }
        if ($input instanceof ReflectionMethod) {
            $info = $componentsBuilder->getSchemaForMethod($input);
            return new Schema([
                'type' => 'object',
                'properties' => $info->schemas,
                'required' => $info->required,
            ]);
        }
        return $componentsBuilder->getSchemaForType($input);
    }

    /**
     * @param ReflectionClass<object>|ReflectionMethod|ReflectionType $output
     */
    private function doSchemaForOutput(ReflectionClass|ReflectionMethod|ReflectionType $output, ComponentsBuilder $componentsBuilder): Schema|Reference
    {
        if ($output instanceof ReflectionClass) {
            return $componentsBuilder->addDisplaySchemaFor($output->name);
        }
        if ($output instanceof ReflectionMethod) {
            $output = $output->getReturnType();
        }
        return $componentsBuilder->getSchemaForType($output, false, true);
    }

    private function createSchemaForOutput(ComponentsBuilder $componentsBuilder, RestApiRouteDefinition $routeDefinition): Schema|Reference
    {
        $input = $routeDefinition->getOutputType();
        if ($input instanceof ListOf) {
            return new Schema([
                'type' => 'object',
                'required' => [
                    'totalCount',
                    'first',
                    'last',
                    'list',
                ],
                'properties' => [
                    'totalCount' => ['type' => 'integer'],
                    'first' => ['type' => 'string', 'format' => 'uri'],
                    'last' => ['type' => 'string', 'format' => 'uri'],
                    'prev' => ['type' => 'string', 'format' => 'uri'],
                    'next' => ['type' => 'string', 'format' => 'uri'],
                    'list' => [
                        'type' => 'array',
                        'items' => $this->doSchemaForOutput($input->type, $componentsBuilder),
                    ]
                ]
            ]);
        }
        return $this->doSchemaForOutput($input, $componentsBuilder);
    }

    private function addAction(PathItem $pathItem, ComponentsBuilder $componentsBuilder, RestApiRouteDefinition $routeDefinition): void
    {
        $method = $routeDefinition->getMethod();
        if ($method === RequestMethod::CONNECT) {
            return;
        }
        $inputSchema = $this->createSchemaForInput($componentsBuilder, $routeDefinition);
        $outputSchema = $this->createSchemaForOutput($componentsBuilder, $routeDefinition);
        $operation = new Operation([
            'tags' => $routeDefinition->getTags()->toArray(),
            'description' => $routeDefinition->getDescription(),
            'operationId' => $routeDefinition->getOperationId(),
        ]);
        if ($method !== RequestMethod::GET) {
            $operation->requestBody = new RequestBody([
                'content' => [
                    'application/json' => new MediaType(['schema' => $inputSchema])
                ]
            ]);
        }
        $operation->responses = [
            201 => new Response([
                'description' => 'OK',
                'content' => [
                    'application/json' => new MediaType(['schema' => $outputSchema])
                ]
            ]),
        ];
        $prop = strtolower($method->value);
        $pathItem->{$prop} = $operation;
    }
}
