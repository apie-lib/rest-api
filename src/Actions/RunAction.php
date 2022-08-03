<?php
namespace Apie\RestApi\Actions;

use Apie\Core\Actions\ActionInterface;
use Apie\Core\Context\ApieContext;
use Apie\Serializer\Serializer;
use ReflectionMethod;

class RunAction implements ActionInterface
{
    public function __construct(private ReflectionMethod $method, private Serializer $serializer)
    {
    }

    /**
     * @param array<string|int, mixed> $rawContents
     */
    public function __invoke(ApieContext $context, array $rawContents): mixed
    {
        $object = $this->method->isStatic() ? null : $context->getContext($this->method->getDeclaringClass()->name);
        return $this->serializer->denormalizeOnMethodCall($rawContents, $object, $this->method, $context);
    }
}
