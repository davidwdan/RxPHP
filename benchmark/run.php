<?php

if (file_exists($file = __DIR__.'/../vendor/autoload.php')) {
    $autoload = require_once $file;
    $autoload->addPsr4('Vendor\\Rx\\Operator\\', __DIR__ . '/custom-operator');
} else {
    throw new RuntimeException('Install dependencies to run benchmark suite.');
}

use Rx\Observable;
use Rx\Observer\CallbackObserver;
use React\EventLoop\LoopInterface;

// Check whether XDebug is enabled
if (in_array('Xdebug', get_loaded_extensions(true))) {
    printf("Please, disable Xdebug extension before running RxPHP benchmarks.\n");
    exit(1);
}

define('MIN_TOTAL_DURATION', 5);
$start = microtime(true);

if ($_SERVER['argc'] === 1) {
    $files = glob(__DIR__ . '/**/*.php');
} else {
    $files = [];
    foreach (array_slice($_SERVER['argv'], 1) as $fileOrDir) {
        if (is_dir($fileOrDir)) {
            $files = array_merge($files, glob($fileOrDir . '/*.php'));
        } else {
            // Force absolute path
            $files[] = $fileOrDir[0] === DIRECTORY_SEPARATOR ? $fileOrDir : $_SERVER['PWD'] . DIRECTORY_SEPARATOR . $fileOrDir;
        }
    }
}


Observable::just($files)
    ->doOnNext(function(array $files) {
        printf("Benchmarking %d file/s (min %ds each)\n", count($files), MIN_TOTAL_DURATION);
        printf("script_name - total_runs (single_run_mean ±standard_deviation) - mem_start [mem_100_iter] mem_end\n");
        printf("==============================================================\n");
    })
    ->concatMap(function($files) { // Flatten the array
        return Observable::fromArray($files);
    })
    ->doOnNext(function($file) {
        printf('%s', pathinfo($file, PATHINFO_FILENAME));
    })
    ->map(function($file) { // Run benchmark
        $durations = [];
        /** @var Observable $observable */
        $observable = null;
        /** @var LoopInterface $loop */
        $loop = null;
        /** @var callable(): Observable $sourceFactory */
        $sourceFactory = null;

        ob_start();

        $testDef = @include $file;

        if (is_array($testDef)) {
            $sourceFactory = $testDef[0];
            $loop = $testDef[1];
        } elseif (is_callable($testDef)) {
            $sourceFactory = $testDef;
        } else {
            throw new Exception("File \"$file\" doesn't contain a valid benchmark");
        }

        $memoryUsage = [memory_get_usage()];

        $benchmarkLoop = function(Observable $observable) use (&$durations, &$memoryUsage) {
            $dummyObserver = new Rx\Observer\CallbackObserver(
                function ($value) { },
                function ($error) { },
                function () use (&$start, &$durations) {
                    $durations[] = (microtime(true) - $start) * 1000;
                }
            );

            $start = microtime(true);
            $observable->subscribe($dummyObserver);

            if (count($durations) === 100) {
                $memoryUsage[] = memory_get_usage();
            }
        };

        $stopStartTime = microtime(true) + MIN_TOTAL_DURATION;

        if ($loop) {
            $reschedule = function() use (&$reschedule, $benchmarkLoop, $sourceFactory, $loop, $stopStartTime) {
                $loop->futureTick(function () use (&$reschedule, $benchmarkLoop, $stopStartTime, $sourceFactory) {
                    $benchmarkLoop($sourceFactory());
                    if ($stopStartTime > microtime(true)) {
                        $reschedule();
                    }
                });
            };

            $reschedule();
            $loop->run();
        } else {
            while ($stopStartTime > microtime(true)) {
                $benchmarkLoop($sourceFactory());
            }
        }

        $memoryUsage[] = memory_get_usage();

        ob_end_clean();

        return [
            'file' => $file,
            'durations' => $durations,
            'memory_usage' => $memoryUsage,
        ];
    })
    ->doOnNext(function(array $result) { // Print the number of successful runs
        printf(' - %d', count($result['durations']));
    })
    ->map(function(array $result) { // Calculate the standard deviation
        $count = count($result['durations']);
        $mean = array_sum($result['durations']) / $count;

        $variance = array_sum(array_map(function($duration) use ($mean) {
            return pow($mean - $duration, 2);
        }, $result['durations']));

        return [
            'file' => $result['file'],
            'memory_usage' => $result['memory_usage'],
            'mean' => $mean,
            'standard_deviation' => pow($variance / $count, 0.5),
        ];
    })
    ->subscribe(new CallbackObserver(
        function(array $result) {
            printf(" (%.2fms ±%.2fms) - ", $result['mean'], $result['standard_deviation']);
            foreach ($result['memory_usage'] as $memory) {
                printf("%.2fMB ", $memory / pow(10, 6));
            }
            printf("\n");
        },
        function(\Exception $error) {
            printf("\nError: %s\n", $error->getMessage());
        },
        function() use ($start) {
            printf("============================================================\n");
            printf("total duration: %.2fs\n", microtime(true) - $start);
        }
    ));