<?php
namespace Apie\RestApi\OpenApi;

use Apie\Common\ContextBuilders\Exceptions\WrongTokenException;
use Apie\Common\Enums\UrlPrefix;
use Apie\Common\Interfaces\RestApiRouteDefinition;
use Apie\Common\Interfaces\RouteDefinitionProviderInterface;
use Apie\Core\Actions\ActionResponseStatus;
use Apie\Core\Attributes\AllowMultipart;
use Apie\Core\BoundedContext\BoundedContext;
use Apie\Core\BoundedContext\BoundedContextId;
use Apie\Core\ContextBuilders\ContextBuilderFactory;
use Apie\Core\Dto\ListOf;
use Apie\Core\Enums\RequestMethod;
use Apie\Core\Utils\ConverterUtils;
use Apie\Core\ValueObjects\NonEmptyString;
use Apie\RestApi\Events\OpenApiOperationAddedEvent;
use Apie\SchemaGenerator\Builders\ComponentsBuilder;
use Apie\SchemaGenerator\ComponentsBuilderFactory;
use Apie\Serializer\Exceptions\NotAcceptedException;
use Apie\Serializer\Exceptions\ValidationException;
use Apie\Serializer\Serializer;
use Apie\TypeConverter\ReflectionTypeFactory;
use cebe\openapi\Reader;
use cebe\openapi\ReferenceContext;
use cebe\openapi\spec\MediaType;
use cebe\openapi\spec\OpenApi;
use cebe\openapi\spec\Operation;
use cebe\openapi\spec\Parameter;
use cebe\openapi\spec\PathItem;
use cebe\openapi\spec\Paths;
use cebe\openapi\spec\Reference;
use cebe\openapi\spec\RequestBody;
use cebe\openapi\spec\Response;
use cebe\openapi\spec\Schema;
use cebe\openapi\spec\Server;
use Psr\EventDispatcher\EventDispatcherInterface;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionType;
use Throwable;

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
        private EventDispatcherInterface $dispatcher,
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
        $componentsBuilder = $this->componentsFactory->createComponentsBuilder($spec->components);
        $context = $this->contextBuilder->createGeneralContext(
            [
                OpenApiGenerator::class => $this,
                Serializer::class => $this->serializer,
                BoundedContextId::class => $boundedContext->getId(),
                BoundedContext::class => $boundedContext,
            ]
        );
        foreach ($this->routeDefinitionProvider->getActionsForBoundedContext($boundedContext, $context) as $routeDefinition) {
            if ($routeDefinition instanceof RestApiRouteDefinition) {
                if (!in_array(UrlPrefix::API, $routeDefinition->getUrlPrefixes()->toArray())) {
                    continue;
                }
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

    private function createSchemaForInput(ComponentsBuilder $componentsBuilder, RestApiRouteDefinition $routeDefinition, bool $forUpload = false): Schema|Reference
    {
        $input = $routeDefinition->getInputType();
        
        $result = $this->doSchemaForInput($input, $componentsBuilder, $routeDefinition->getMethod());
        if ($forUpload && $routeDefinition->getMethod() !== RequestMethod::GET) {
            $uploads = [];
            $visited = [];
            $state = [];
            $this->findUploads($result, $componentsBuilder, $state, $uploads, $visited);
            $required = ['form'];
            foreach ($uploads as $uploadName => $upload) {
                if (!$upload->nullable) {
                    $required[] = $uploadName;
                }
            }
            return new Schema([
                'type' => 'object',
                'properties' => [
                    'form' => $result,
                    '_csrf' => new Schema(['type' => 'string']),
                    // TODO _internal
                    ...$uploads
                ],
                'required' => $required,
            ]);
        }
        return $result;
    }

    /**
     * @param array<int, string> $state
     * @param array <int|string, mixed> $uploads
     * @param array <string, true> $visited
     */
    private function findUploads(
        Schema|Reference $schema,
        ComponentsBuilder $componentsBuilder,
        array $state,
        array& $uploads,
        array& $visited
    ): void {
        if ($schema instanceof Reference) {
            if (isset($visited[$schema->getReference()])) {
                return;
            }
            $visited[$schema->getReference()] = true;
            $schema = $componentsBuilder->getSchemaForReference($schema);
        }
        if ($schema->__isset('x-upload')) {
            $uploads[implode('.', $state)] = new Schema([
                'type' => 'string',
                'format' => 'binary',
                'nullable' => $schema->nullable,
            ]);
        }
        foreach ($schema->properties ?? [] as $propertyName => $propertySchema) {
            $this->findUploads(
                $propertySchema,
                $componentsBuilder,
                [...$state, $propertyName],
                $uploads,
                $visited
            );
        }
    }

    /**
     * @param ReflectionClass<object>|ReflectionMethod|ReflectionType $input
     */
    private function doSchemaForInput(ReflectionClass|ReflectionMethod|ReflectionType $input, ComponentsBuilder $componentsBuilder, RequestMethod $method = RequestMethod::GET): Schema|Reference
    {
        if ($input instanceof ReflectionClass) {
            if ($method === RequestMethod::PATCH) {
                return $componentsBuilder->addModificationSchemaFor($input->name);
            }
            return $componentsBuilder->addCreationSchemaFor($input->name);
        }
        if ($input instanceof ReflectionMethod) {
            $info = $componentsBuilder->getSchemaForMethod($input);
            return new Schema(
                [
                    'type' => 'object',
                    'properties' => $info->schemas,
                ] + ($info->required ? ['required' => $info->required] : [])
            );
        }
        return $componentsBuilder->getSchemaForType($input, nullable: $input->allowsNull());
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
        return $componentsBuilder->getSchemaForType($output, false, true, $output ? $output->allowsNull() : true);
    }

    private function createSchemaForOutput(ComponentsBuilder $componentsBuilder, RestApiRouteDefinition $routeDefinition): Schema|Reference
    {
        $input = $routeDefinition->getOutputType();
        if ($input instanceof ListOf) {
            return new Schema([
                'type' => 'object',
                'required' => [
                    'filteredCount',
                    'totalCount',
                    'first',
                    'last',
                    'list',
                ],
                'properties' => [
                    'totalCount' => ['type' => 'integer', 'minimum' => 0],
                    'filteredCount' => ['type' => 'integer', 'minimum' => 0],
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

    private function createSchemaForParameter(
        ComponentsBuilder $componentsBuilder,
        RestApiRouteDefinition $routeDefinition,
        string $placeholderName
    ): Schema|Reference {
        $input = $routeDefinition->getInputType();
        $found = false;
        if ($input instanceof ReflectionMethod) {
            foreach ($input->getParameters() as $parameter) {
                if ($parameter->name === $placeholderName) {
                    $found = true;
                    $input = $parameter->getType() ?? ReflectionTypeFactory::createReflectionType('string');
                    break;
                }
            }
        }
        if ($input instanceof ReflectionClass) {
            $methodNames = [
                ['get' . ucfirst($placeholderName), 'hasMethod', 'getMethod', 'getReturnType'],
                ['has' . ucfirst($placeholderName), 'hasMethod', 'getMethod', 'getReturnType'],
                ['is' . ucfirst($placeholderName), 'hasMethod', 'getMethod', 'getReturnType'],
                [$placeholderName, 'hasProperty', 'getProperty', 'getType'],
            ];

            foreach ($methodNames as $optionToCheck) {
                list($propertyName, $has, $get, $type) = $optionToCheck;
                if ($input->$has($propertyName)) {
                    $input = $input->$get($propertyName)->$type();
                    $found = true;
                    break;
                }
            }
        }
        if (!$found) {
            $input = ReflectionTypeFactory::createReflectionType(NonEmptyString::class);
        }
        return $this->doSchemaForInput($input, $componentsBuilder);
    }

    private function generateParameter(
        ComponentsBuilder $componentsBuilder,
        RestApiRouteDefinition $routeDefinition,
        string $placeholderName
    ): Parameter {
        return new Parameter([
            'in' => 'path',
            'name' => $placeholderName,
            'required' => true,
            'description' => $placeholderName . ' of instance of ' . $this->getDisplayValue($routeDefinition->getInputType(), $placeholderName),
            'schema' => $this->createSchemaForParameter($componentsBuilder, $routeDefinition, $placeholderName),
        ]);
    }

    /**
     * @param ReflectionClass<object>|ReflectionMethod|ReflectionType $type
     */
    private function getDisplayValue(ReflectionClass|ReflectionMethod|ReflectionType $type, string $placeholderName): string
    {
        if ($type instanceof ReflectionNamedType) {
            $name = $type->getName();
            if (class_exists($name)) {
                return (new ReflectionClass($name))->getShortName();
            }
            return $name;
        }
        if ($type instanceof ReflectionType) {
            return (string) $type;
        }
        if ($type instanceof ReflectionClass) {
            return $type->getShortName();
        }
        if ($placeholderName === 'id') {
            return $type->getDeclaringClass()->getShortName();
        }
        return $type->name;
    }

    private function supportsMultipart(RestApiRouteDefinition $routeDefinition): bool
    {
        $input = ConverterUtils::toReflectionClass($routeDefinition->getInputType());
        if ($input === null) {
            return false;
        }
        if (!in_array($routeDefinition->getMethod(), [RequestMethod::POST, RequestMethod::PUT, RequestMethod::PATCH])) {
            return false;
        }
        return !empty($input->getAttributes(AllowMultipart::class));
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
        $parameters = [];
        $parameters[] = new Parameter([
            'name' => 'fields',
            'in' => 'query',
            'explode' => false,
            'schema' => new Schema([
                'type' => 'array',
                'items' => new Schema([
                    'type' => 'string',
                ])
            ])
        ]);
        $parameters[] = new Parameter([
            'name' => 'relations',
            'in' => 'query',
            'explode' => false,
            'schema' => new Schema([
                'type' => 'array',
                'items' => new Schema([
                    'type' => 'string',
                ])
            ])
        ]);
        $placeholders = $routeDefinition->getUrl()->getPlaceholders();

        foreach ($placeholders as $placeholderName) {
            $parameters[] = $this->generateParameter($componentsBuilder, $routeDefinition, $placeholderName);
        }
        $operation->parameters = $parameters;

        if ($method !== RequestMethod::GET && $method !== RequestMethod::DELETE) {
            $content = [
                'application/json' => new MediaType(['schema' => $inputSchema]),
            ];
            if ($this->supportsMultipart($routeDefinition)) {
                $uploadSchema = $componentsBuilder->runInContentType(
                    'multipart/form-data',
                    function () use ($componentsBuilder, $routeDefinition) {
                        return $this->createSchemaForInput($componentsBuilder, $routeDefinition, true);
                    }
                );
                $content['multipart/form-data'] = new MediaType([
                    'schema' => $uploadSchema
                ]);
                $parameters = $operation->parameters;
                $parameters[] = new Parameter([
                    'name' => 'x-no-crsf',
                    'in' => 'header',
                    'description' => 'Disable csrf',
                    'schema' => [
                        'type' => 'string',
                        'enum' => [1]
                    ],
                ]);
                $operation->parameters = $parameters;
            }
            $operation->requestBody = new RequestBody([
                'content' => $content
            ]);
        }
        $responses = [
        ];
        foreach ($routeDefinition->getPossibleActionResponseStatuses() as $responseStatus) {
            switch ($responseStatus) {
                case ActionResponseStatus::CREATED:
                    $responses[201] = new Response([
                        'description' => 'Resource was created',
                        'content' => [
                            'application/json' => new MediaType(['schema' => $outputSchema])
                        ]
                    ]);
                    break;
                case ActionResponseStatus::SUCCESS:
                    $responses[200] = new Response([
                        'description' => 'OK',
                        'content' => [
                            'application/json' => new MediaType(['schema' => $outputSchema])
                        ]
                    ]);
                    break;
                case ActionResponseStatus::CLIENT_ERROR:
                    foreach ([400, 405, 406] as $statusCode) {
                        $responses[$statusCode] = new Response([
                            'description' => 'Invalid request',
                            'content' => [
                                'application/json' => new MediaType(['schema' => $componentsBuilder->addDisplaySchemaFor(NotAcceptedException::class)]),
                            ]
                        ]);
                    }
                    $responses[422] = new Response([
                        'description' => 'A validation error occurred',
                        'content' => [
                            'application/json' => new MediaType(['schema' => $componentsBuilder->addDisplaySchemaFor(ValidationException::class)]),
                        ]
                    ]);
                    break;
                case ActionResponseStatus::AUTHORIZATION_ERROR:
                    foreach ([401 => 'Requires authorization', 403 => 'Access denied'] as $statusCode => $description) {
                        $responses[$statusCode] = new Response([
                            'description' => $description,
                            'content' => [
                                'application/json' => new MediaType(['schema' => $componentsBuilder->addDisplaySchemaFor(WrongTokenException::class)]),
                            ]
                        ]);
                    }
                    break;
                case ActionResponseStatus::DELETED:
                    $responses[204] = new Response(['description' => 'Resource was deleted']);
                    break;
                case ActionResponseStatus::NOT_FOUND:
                    $responses[404] = new Response([
                        'description' => 'Resource not found',
                        'content' => [
                            'application/json' => new MediaType(['schema' => $componentsBuilder->addDisplaySchemaFor(Throwable::class)]),
                        ]
                    ]);
                    break;
                case ActionResponseStatus::PERISTENCE_ERROR:
                    $responses[409] = new Response([
                        'description' => 'Resource not found',
                        'content' => [
                            'application/json' => new MediaType(['schema' => $componentsBuilder->addDisplaySchemaFor(Throwable::class)]),
                        ]
                    ]);
                    break;
                default:
                    $responses[500] = new Response([
                        'description' => 'Unknown error occurred',
                        'content' => [
                            'application/json' => new MediaType(['schema' => $componentsBuilder->addDisplaySchemaFor(Throwable::class)]),
                        ]
                    ]);
            }
        }
        $operation->responses = $responses;
        $prop = strtolower($method->value);
        $pathItem->{$prop} = $operation;
        $this->dispatcher->dispatch(
            new OpenApiOperationAddedEvent(
                $componentsBuilder,
                $operation,
                $routeDefinition
            )
        );
    }
}
