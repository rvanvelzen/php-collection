<?php
namespace RVV\Collection;

/**
 * @internal
 */
final class MapEntry
{
    /** @var string|int */
    public $hash;
    /** @var mixed */
    public $key;
    /** @var mixed */
    public $value;

    /**
     * @param int|string $hash
     * @param mixed $key
     * @param mixed $value
     */
    public function __construct($hash, $key, $value)
    {
        $this->hash = $hash;
        $this->key = $key;
        $this->value = $value;
    }
}
