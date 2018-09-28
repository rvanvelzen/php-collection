<?php
namespace RVV\Collection;

interface Hashable
{
    /**
     * @return mixed
     */
    public function hash();

    /**
     * @param mixed $obj
     * @return bool
     */
    public function equals($obj): bool;
}
