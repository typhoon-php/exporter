<?php

declare(strict_types=1);

$object = new stdClass();
$object->a = 1;
$object->self = $object;

return $object;
