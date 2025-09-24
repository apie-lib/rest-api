<?php
namespace Apie\RestApi\Events;

use Apie\Core\BoundedContext\BoundedContext;
use cebe\openapi\spec\OpenApi;

final class OpenApiSchemaGeneratedEvent
{
    public function __construct(
        public readonly OpenApi $openApi,
        public readonly BoundedContext $boundedContext
    ) {
    }
}
