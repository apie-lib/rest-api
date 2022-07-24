<?php
namespace Apie\RestApi\Lists;

use Apie\Core\Lists\ItemList;
use cebe\openapi\spec\Tag;

class StringList extends ItemList
{
    protected bool $mutable = false;

    public function offsetGet(mixed $offset): string
    {
        return parent::offsetGet($offset);
    }

    public function toOpenApiTags(): array
    {
        return array_map(
            function (string $tag) {
                return new Tag(['name' => $tag]);
            },
            $this->internal
        );
    }
}