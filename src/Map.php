<?php
namespace RVV\Collection;

class Map implements \Countable, \IteratorAggregate
{
    /** @var int */
    private $size = 0;
    /** @var MapEntry[][] */
    private $buckets = [];

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
        foreach ($bucket as $ii => $entry) {
            if ($entry->key !== $key) {
                continue;
            }

            $last = \array_pop($bucket);
            if ($last !== $entry) {
                $bucket[$ii] = $last;
            } elseif (!$bucket) {
                unset($this->buckets[$hash]);
            } else {
                $this->buckets[$hash] = $bucket;
            }

            --$this->size;

            return true;
        }

        return false;
    }

    public function getIterator(): iterable
    {
        foreach ($this->buckets as $bucket) {
            foreach ($bucket as $entry) {
                yield $entry->key => $entry->value;
            }
        }
    }

    public function forEach(callable $fn): void
    {
        foreach ($this->buckets as $bucket) {
            foreach ($bucket as $entry) {
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
        foreach ($this->buckets as $bucket) {
            foreach ($bucket as $entry) {
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
        $entry->key = $key;
        $entry->value = $value;

        $this->buckets[$hash][] = $entry;
        ++$this->size;

        return $this;
    }

    public function values(): iterable
    {
        foreach ($this->buckets as $bucket) {
            foreach ($bucket as $entry) {
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
            foreach ($this->buckets[$hash] as $entry) {
                if ($entry->key === $key) {
                    return $entry;
                }
            }
        }

        return null;
    }

    /**
     * @param mixed $key
     * @return string
     */
    private static function hashKey($key)
    {
        if (\is_object($key)) {
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
                    $refs[$key] = $cookie;
                    if ($values[$key] === $cookie) {
                        $refs[$key] = $value;
                        $result .= "{$key}\0";
                    } else {
                        $queue[] = $value;
                        ++$length;
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
    /** @var mixed */
    public $key;
    /** @var mixed */
    public $value;
}
