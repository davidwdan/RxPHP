<?php

use Rx\Observable;
use Rx\Scheduler\ImmediateScheduler;

$scheduler = new ImmediateScheduler();

$source = Observable::range(0, 500, $scheduler)
    ->takeLast(50);

return function() use ($source) {
    return $source;
};