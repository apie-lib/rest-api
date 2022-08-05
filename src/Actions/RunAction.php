<?php
namespace Apie\RestApi\Actions;

use Apie\Core\Actions\ActionInterface;
use Apie\Core\Context\ApieContext;
use Apie\Serializer\Serializer;
use ReflectionMethod;

class RunAction implements ActionInterface
{
    public function __construct(private Serializer $serializer)
    {
    }

    /**
     * @param array<string|int, mixed> $rawContents
     */
    public function __invoke(ApieContext $context, array $rawContents): mixed
    {
        $method = new ReflectionMethod($context->getContext('class'), $context->getContext('methodName'));
        $object = $method->isStatic() ? null : $context->getContext($context->getContext('class'));
        $returnValue = $this->serializer->denormalizeOnMethodCall($rawContents, $object, $method, $context);
        return $this->serializer->normalize($returnValue, $context);
    }
}
