<?php
namespace Apie\RestApi\RouteDefinitions;

use Apie\Common\ContextConstants;
use Apie\Common\Interfaces\RouteDefinitionProviderInterface;
use Apie\Common\RouteDefinitions\ActionHashmap;
use Apie\Core\BoundedContext\BoundedContext;
use Apie\Core\Context\ApieContext;
use Apie\Core\Enums\RequestMethod;
use Apie\Core\Metadata\MetadataFactory;

final class RestApiRouteDefinitionProvider implements RouteDefinitionProviderInterface
{
    public function getActionsForBoundedContext(BoundedContext $boundedContext, ApieContext $apieContext): ActionHashmap
    {
        $map = [];
        $definition = new OpenApiDocumentationRouteDefinition(true, $boundedContext->getId());
        $map[$definition->getOperationId()] = $definition;
        $definition = new OpenApiDocumentationRouteDefinition(false, $boundedContext->getId());
        $map[$definition->getOperationId()] = $definition;
        $definition = new SwaggerUIRouteDefinition($boundedContext->getId());
        $map[$definition->getOperationId()] = $definition;

        $postContext = $apieContext->withContext(RequestMethod::class, RequestMethod::POST)
            ->withContext(ContextConstants::CREATE_OBJECT, true)
            ->registerInstance($boundedContext);
        foreach ($boundedContext->resources->filterOnApieContext($postContext, false) as $resource) {
            $definition = new CreateResourceRouteDefinition($resource, $boundedContext->getId());
            $map[$definition->getOperationId()] = $definition;
        }

        $getSingleContext = $apieContext->withContext(RequestMethod::class, RequestMethod::GET)
            ->withContext(ContextConstants::GET_OBJECT, true)
            ->registerInstance($boundedContext);
        foreach ($boundedContext->resources->filterOnApieContext($getSingleContext, false) as $resource) {
            $definition = new GetSingleResourceRouteDefinition($resource, $boundedContext->getId());
            $map[$definition->getOperationId()] = $definition;
        }

        $patchSingleContext = $apieContext->withContext(RequestMethod::class, RequestMethod::PATCH)
            ->withContext(ContextConstants::EDIT_OBJECT, true)
            ->registerInstance($boundedContext);
        foreach ($boundedContext->resources->filterOnApieContext($patchSingleContext, false) as $resource) {
            $metadata = MetadataFactory::getModificationMetadata($resource, $patchSingleContext);
            if ($metadata->getHashmap()->count()) {
                $definition = new PatchSingleResourceRouteDefinition($resource, $boundedContext->getId());
                $map[$definition->getOperationId()] = $definition;
            }
        }

        $getAllContext = $apieContext->withContext(RequestMethod::class, RequestMethod::GET)
            ->withContext(ContextConstants::GET_ALL_OBJECTS, true)
            ->registerInstance($boundedContext);
        foreach ($boundedContext->resources->filterOnApieContext($getAllContext, false) as $resource) {
            $definition = new GetResourceListRouteDefinition($resource, $boundedContext->getId());
            $map[$definition->getOperationId()] = $definition;
        }

        $globalActionContext = $apieContext->withContext(ContextConstants::GLOBAL_METHOD, true);
        foreach ($boundedContext->actions->filterOnApieContext($globalActionContext, false) as $action) {
            $definition = new RunGlobalMethodRouteDefinition($action, $boundedContext->getId());
            $map[$definition->getOperationId()] = $definition;
        }

        $resourceActionContext = $apieContext->withContext(ContextConstants::RESOURCE_METHOD, true);
        foreach ($boundedContext->resources->filterOnApieContext($resourceActionContext) as $resource) {
            foreach ($resourceActionContext->getApplicableMethods($resource, false) as $method) {
                $definition = new RunMethodCallOnSingleResourceRouteDefinition(
                    $resource,
                    $method,
                    $boundedContext->getId()
                );
                $map[$definition->getOperationId()] = $definition;
            }
        }
        return new ActionHashmap($map);
    }
}
