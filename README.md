# Collection

Some simple somewhat-efficient collection implementations for PHP. 

## Usage

```php
<?php
use RVV\Collection;

$map = new Map();
$map->set('some key', 'an amazing value!');
$map->set(1, 'a key bound to 1');

var_dump($map->get('some key'));
```
