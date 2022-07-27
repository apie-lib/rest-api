<?php
namespace Apie\RestApi\Actions;

use Apie\Core\Actions\ActionInterface;
use Apie\Core\Context\ApieContext;
use Apie\RestApi\Interfaces\RestApiRouteDefinition;
use Apie\Serializer\Serializer;

/**
 * Action to create a new object.
 */
class CreateObjectAction implements ActionInterface
{
    public function __construct(private Serializer $serializer)
    {
    }
    
    public function __invoke(ApieContext $context, array $rawContents): mixed
    {
        $resource = $this->serializer->denormalizeNewObject(
            $rawContents,
            $context->getContext(RestApiRouteDefinition::RESOURCE_NAME),
            $context
        );
        // TODO: persistence layer
        return $this->serializer->normalize($resource, $context);
    }
}
