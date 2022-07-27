<?php
namespace Apie\RestApi\Lists;

use Apie\Core\Lists\ItemList;

class StringList extends ItemList
{
    protected bool $mutable = false;

    public function offsetGet(mixed $offset): string
    {
        return parent::offsetGet($offset);
    }
}
