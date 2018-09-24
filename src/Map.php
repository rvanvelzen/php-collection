<?php
namespace RVV\Collection;

/**
 * @internal
 */
class MapEntry
{
    /** @var mixed */
    public $key;
    /** @var mixed */
    public $value;
    /** @var MapEntry|null */
    public $prev = null;
    /** @var MapEntry|null */
    public $next = null;
}

class Map implements \Countable, \IteratorAggregate
{
    /** @var int */
    private $size = 0;
    /** @var MapEntry[][] */
    private $buckets = [];
    /** @var MapEntry|null */
    private $first = null;
    /** @var MapEntry|null */
    private $last = null;

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

            if ($entry->next) {
                $entry->next->prev = $entry->prev;
            }
            if ($entry->prev) {
                $entry->prev->next = $entry->next;
            }
            if ($entry === $this->first) {
                $this->first = $entry->next;
            }
            if ($entry === $this->last) {
                $this->last = $entry->prev;
            }

            --$this->size;

            return true;
        }

        return false;
    }

    public function getIterator(): iterable
    {
        $entry = $this->first;
        while ($entry) {
            yield $entry->key => $entry->value;
            $entry = $entry->next;
        }
    }

    public function forEach(callable $fn): void
    {
        $entry = $this->first;
        while ($entry) {
            $fn($entry->value, $entry->key);
            $entry = $entry->next;
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
        $entry = $this->first;
        while ($entry) {
            yield $entry->key;
            $entry = $entry->next;
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


        if ($this->last) {
            $this->last->next = $entry;
            $entry->prev = $this->last;
        }
        $this->last = $entry;
        if (!$this->first) {
            $this->first = $entry;
        }

        $this->buckets[$hash][] = $entry;
        ++$this->size;

        return $this;
    }

    public function values(): iterable
    {
        $entry = $this->first;
        while ($entry) {
            yield $entry->value;
            $entry = $entry->next;
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
            return \spl_object_id($key);
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
        $result = [];
        $queue = [$array];
        $offset = 0;
        $length = 1;

        $cookie = new \stdClass();

        while ($offset < $length) {
            $values = $refs = $queue[$offset];
            foreach ($values as $key => $value) {
                if (\is_array($value)) {
                    $refs[$key] = $cookie;
                    if ($values[$key] === $cookie) {
                        $refs[$key] = $value;
                        $result[] = "{$offset}\1{$key}\0ref\0";
                    } else {
                        $queue[] = $value;
                        ++$length;
                    }
                } else {
                    $result[] = "{$offset}\1{$key}\2" . self::hashKey($value);
                }
            }

            ++$offset;
        }

        return implode("\3", $result);
    }
}
