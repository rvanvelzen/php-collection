<?php
namespace RVV\Collection\Tests;

use PHPUnit\Framework\TestCase;
use RVV\Collection\Hashable;
use RVV\Collection\Map;

class MapTest extends TestCase
{
    public function test_it_accepts_keys_of_almost_all_types()
    {
        $map = new Map();
        $map->set(1, 1);
        $map->set(1.5, 2);
        $map->set('string', 3);
        $map->set([1, 2, 3], 4);
        $map->set(new \stdClass(), 5);

        $this->assertCount(5, $map);
    }

    public function test_it_accepts_resource_as_key()
    {
        $map = new Map();

        $resource = fopen('php://temp', 'w');
        $map->set($resource, 1);

        $this->assertCount(1, $map);
        $this->assertSame(1, $map->get($resource));
    }

    public function test_it_accepts_arrays_with_recursive_references()
    {
        $array = [1, 2];
        $array[] = [&$array];

        $map = new Map();
        $map->set($array, 1);

        $this->assertSame(1, $map->get($array));
    }

    public function test_it_does_not_crash_on_getting_by_arrays_with_recursive_references_that_do_not_exists()
    {
        $array1 = [null, 1, 2];
        $array1[0] = [&$array1];

        $array2 = [null, '1', '2'];
        $array2[0] = [&$array2];

        $map = new Map();
        $map->set($array1, 1);

        $this->assertSame(1, $map->get($array1));
        $this->assertSame(null, $map->get($array2));
    }

    public function test_it_handles_colliding_keys()
    {
        $map = new Map();
        $map->set(1, 1);
        $map->set(1.0, 1.0);
        $map->set('1', '1');

        $this->assertSame(1, $map->get(1));
        $this->assertSame(1.0, $map->get(1.0));
        $this->assertSame('1', $map->get('1'));
    }

    public function test_it_retains_insertion_order()
    {
        $map = new Map();
        $map->set(1, 1);
        $map->set(2, 2);
        $map->set(3, 3);
        $map->delete(2);
        $map->set('1', '1');
        $map->set(2, 4);

        $this->assertSame([1, 3, '1', 2], \iterator_to_array($map->keys()));
    }

    public function test_it_overwrites_the_value_of_an_existing_key()
    {
        $map = new Map();
        $map->set(1, 1);
        $map->set(2, 2);
        $map->set(1, 3);

        $this->assertEquals(3, $map->get(1));
        $this->assertEquals(2, $map->get(2));

        $this->assertSame([1, 2], \iterator_to_array($map->keys()));
    }

    public function test_it_packs_the_structure_if_it_shrinks()
    {
        $map = new Map();
        for ($ii = 0; $ii < 100; ++$ii) {
            $map->set($ii, true);
        }

        // delete 1 element more than half, to force pack
        for ($ii = 0; $ii < 51; ++$ii) {
            $map->delete($ii);
        }

        $entries = new \ReflectionProperty($map, 'entries');
        $entries->setAccessible(true);
        $this->assertLessThan(100, \count($entries->getValue($map)));
    }

    public function test_it_compares_Hashable_objects_using_their_hash_and_equals_methods()
    {
        $object1 = new BadHashable(1);
        $object2 = new BadHashable(1);
        $object3 = new BadHashable(2);

        $map = new Map();
        $map->set($object1, $object1);
        $map->set($object2, $object2);
        $map->set($object3, $object3);

        $this->assertCount(2, $map);
        $this->assertSame($object2, $map->get($object1));
        $this->assertSame($object2, $map->get($object2));
        $this->assertSame($object3, $map->get($object3));
    }

    public function test_it_compares_Hashable_objects_contained_in_an_array_using_their_hash_and_equals_methods()
    {
        $object1 = new BadHashable(1);
        $object2 = new BadHashable(1);
        $object3 = new BadHashable(2);

        $array1 = [$object1];
        $array2 = [$object2];
        $array3 = [$object3];

        $map = new Map();
        $map->set($array1, $object1);
        $map->set($array2, $object2);
        $map->set($array3, $object3);

        $this->assertCount(2, $map);
        $this->assertSame($object2, $map->get($array1));
        $this->assertSame($object2, $map->get($array2));
        $this->assertSame($object3, $map->get($array3));
    }
}

class BadHashable implements Hashable
{
    /** @var int */
    private $value;

    public function __construct(int $value)
    {
        $this->value = $value;
    }

    /**
     * @return mixed
     */
    public function hash()
    {
        return $this->value;
    }

    /**
     * @param mixed $other
     * @return bool
     */
    public function equals($other): bool
    {
        return $other instanceof BadHashable && $other->value === $this->value;
    }
}
