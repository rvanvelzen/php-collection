<?php
namespace RVV\Collection;

class Map implements \Countable, \IteratorAggregate
{
    /** @var int */
    private $size = 0;
    /** @var int */
    private $nextIndex = 0;
    /** @var int[][] */
    private $buckets = [];
    /** @var MapEntry[] */
    private $entries = [];

    public function count(): int
    {
        return $this->size;
    }

    public function delete($key): bool
    {
        $hash = $this->hashKey($key);
        if (!isset($this->buckets[$hash])) {
            return false;
        }

        $bucket = $this->buckets[$hash];
        foreach ($bucket as $ii => $index) {
            if (!isset($this->entries[$index])) {
                continue;
            }

            if (!self::keysAreEqual($this->entries[$index]->key, $key)) {
                continue;
            }

            $this->entries[$index] = null;

            --$this->size;

            if ($this->size > 8 && ($this->size < ($this->nextIndex / 2))) {
                $this->pack();
            }

            return true;
        }

        return false;
    }

    public function getIterator(): iterable
    {
        foreach ($this->entries as $entry) {
            if ($entry) {
                yield $entry->key => $entry->value;
            }
        }
    }

    public function forEach(callable $fn): void
    {
        foreach ($this->entries as $entry) {
            if ($entry) {
                $fn($entry->value, $entry->key);
            }
        }
    }

    /**
     * @param mixed $key
     * @return mixed
     */
    public function get($key)
    {
        $entry = $this->getEntry($key);
        return $entry ? $entry->value : null;
    }

    /**
     * @param mixed $key
     * @return bool
     */
    public function has($key): bool
    {
        return $this->getEntry($key) !== null;
    }

    public function keys(): iterable
    {
        foreach ($this->entries as $entry) {
            if ($entry) {
                yield $entry->key;
            }
        }
    }

    /**
     * @param mixed $key
     * @param mixed $value
     * @return $this
     */
    public function set($key, $value): Map
    {
        $hash = $this->hashKey($key);
        if ($entry = $this->getEntry($key, $hash)) {
            $entry->value = $value;
            return $this;
        }

        $entry = new MapEntry();
        $entry->hash = $hash;
        $entry->key = $key;
        $entry->value = $value;

        $index = $this->nextIndex++;
        $this->entries[$index] = $entry;
        $this->buckets[$hash][] = $index;
        ++$this->size;

        return $this;
    }

    public function values(): iterable
    {
        foreach ($this->entries as $entry) {
            if ($entry) {
                yield $entry->value;
            }
        }
    }

    /**
     * @param mixed $key
     * @param string|null $hash
     * @return MapEntry|null
     */
    private function getEntry($key, $hash = null)
    {
        if ($hash === null) {
            $hash = $this->hashKey($key);
        }

        if (isset($this->buckets[$hash])) {
            foreach ($this->buckets[$hash] as $index) {
                if (isset($this->entries[$index])) {
                    $entry = $this->entries[$index];
                    if (self::keysAreEqual($entry->key, $key)) {
                        return $entry;
                    }
                }
            }
        }

        return null;
    }

    private function pack()
    {
        $entries = $this->entries;

        $this->nextIndex = 0;
        $this->buckets = [];
        $this->entries = [];

        foreach ($entries as $entry) {
            if ($entry) {
                $index = $this->nextIndex++;
                $this->entries[$index] = $entry;
                $this->buckets[$entry->hash][] = $index;
            }
        }
    }

    /**
     * @param mixed $key1
     * @param mixed $key2
     * @return bool
     */
    private static function keysAreEqual($key1, $key2): bool
    {
        if ($key1 instanceof Hashable) {
            return $key1->equals($key2);
        }

        if (\is_array($key1) && \is_array($key2)) {
            return self::arrayKeysAreEqual($key1, $key2);
        }

        if ($key1 === $key2) {
            return true;
        }

        return false;
    }

    /**
     * @param array $key1
     * @param array $key2
     * @param array $seen
     * @return bool
     */
    private static function arrayKeysAreEqual($key1, $key2, array $seen = []): bool
    {
        if (\count($key1) !== \count($key2) || \array_keys($key1) !== \array_keys($key2)) {
            return false;
        }

        if (\in_array([$key1, $key2], $seen, true) || \in_array([$key2, $key1], $seen, true)) {
            return true;
        }

        $arrays = [];

        foreach ($key1 as $index => $value1) {
            $value2 = $key2[$index];
            if (\is_array($value1) && \is_array($value2)) {
                $arrays[] = [$value1, $value2];
            } elseif (!self::keysAreEqual($value1, $value2)) {
                return false;
            }
        }

        if ($arrays) {
            $seen[] = [$key1, $key2];

            foreach ($arrays as list($value1, $value2)) {
                if (!self::arrayKeysAreEqual($value1, $value2, $seen)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param mixed $key
     * @return string
     */
    private static function hashKey($key)
    {
        if (\is_object($key)) {
            if ($key instanceof Hashable) {
                return $key->hash();
            }

            static $useId = null;
            if ($useId === null) {
                // prefer spl_object_id only if it's the built-in version - polyfills use spl_object_hash with some
                // dark magic making it much slower
                $useId = \function_exists('spl_object_id') && (new \ReflectionFunction('spl_object_id'))->isInternal();
            }

            return $useId ? \spl_object_id($key) : \spl_object_hash($key);
        }

        if (\is_array($key)) {
            return self::hashArrayKey($key);
        }

        if (\is_resource($key)) {
            return \get_resource_type($key) . $key;
        }

        return $key;
    }

    /**
     * @param array $array
     * @return string
     */
    private static function hashArrayKey($array)
    {
        // Use an algorithm inspired by Symfony's VarCloner to detect recursion
        $result = '';
        $queue = [$array];
        $offset = 0;
        $length = 1;

        static $cookie;
        if ($cookie === null) {
            $cookie = new \stdClass();
        }

        while ($offset < $length) {
            $result .= $offset;
            $values = $refs = $queue[$offset];
            foreach ($values as $key => $value) {
                if (\is_array($value)) {
                    try {
                        $refs[$key] = $cookie;
                        if ($values[$key] === $cookie) {
                            $refs[$key] = $value;
                            $result .= "{$key}\0";
                        } else {
                            $queue[] = $value;
                            ++$length;
                        }
                    } catch (\TypeError $ex) {
                        $result .= "{$key}\0";
                    }
                } else {
                    $value = self::hashKey($value);
                    $result .= "{$key}{$value}";
                }
            }

            ++$offset;
        }

        return $result;
    }
}

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
}
