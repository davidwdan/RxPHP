<?php

declare(strict_types = 1);

namespace Rx\Testing;

use Rx\Functional\FunctionalTestCase;
use Rx\Observable;

class RecordedTest extends FunctionalTestCase
{

    /**
     * @test
     */
    public function compare_basic_types()
    {
        $r1 = new Recorded(100, 42);
        $r2 = new Recorded(100, 42);
        $this->assertTrue($r1->equals($r2));

        $r3 = new Recorded(100, 42);
        $r4 = new Recorded(150, 42);
        $this->assertFalse($r3->equals($r4));

        $r5 = new Recorded(100, 42);
        $r6 = new Recorded(100, 24);
        $this->assertFalse($r5->equals($r6));
    }

    /**
     * @test
     */
    public function compare_cold_observables()
    {
        $records1 = onNext(100, $this->createColdObservable([
            onNext(150, 1),
            onNext(200, 2),
            onNext(250, 3),
            onCompleted(300),
        ]));
        $records2 = onNext(100, $this->createColdObservable([
            onNext(150, 1),
            onNext(200, 2),
            onNext(250, 3),
            onCompleted(300),
        ]));

        $this->assertMessages([$records1], [$records2]);
        $this->assertTrue($records1->equals($records2));
    }

    /**
     * @test
     */
    public function compare_cold_observables_not_equal()
    {
        $records1 = onNext(100, $this->createColdObservable([
            onNext(150, 1),
            onNext(200, 42), // this is wrong
            onNext(250, 3),
            onCompleted(300),
        ]));
        $records2 = onNext(100, $this->createColdObservable([
            onNext(150, 1),
            onNext(200, 2),
            onNext(250, 3),
            onCompleted(300),
        ]));

        $this->assertMessagesNotEqual([$records1], [$records2]);
        $this->assertFalse($records1->equals($records2));
    }

    /**
     * @test
     */
    public function compare_with_range_cold_observable()
    {
        $records1 = onNext(100, Observable::range(1, 3, $this->scheduler));
        $records2 = onNext(100, $this->createColdObservable([
            onNext(1, 1),
            onNext(2, 2),
            onNext(3, 3),
            onCompleted(4),
        ]));

        $this->assertMessages([$records1], [$records2]);
    }

    /**
     * @test
     */
    public function compare_with_delayed_range_cold_observable()
    {
        $records1 = onNext(100, $this->createColdObservable([
            onNext(50, 1),
            onNext(100, 2),
            onNext(150, 3),
            onCompleted(200)
        ])->delay(100, $this->scheduler));

        $records2 = onNext(100, $this->createColdObservable([
            onNext(150, 1),
            onNext(200, 2),
            onNext(250, 3),
            onCompleted(300),
        ]));

        $this->assertMessages([$records1], [$records2]);
    }

    /**
     * @test
     */
    public function observables_at_different_time_with_same_records_arent_equal()
    {
        $records1 = onNext(50, $this->createColdObservable([
            onNext(50, 1),
            onNext(100, 2),
        ]));
        $records2 = onNext(100, $this->createColdObservable([
            onNext(50, 1),
            onNext(100, 2),
        ]));

        $this->assertFalse($records1->equals($records2));
        $this->assertEquals('[OnNext(1)@50, OnNext(2)@100]@50', $records1->__toString());
    }

    /**
     * @test
     */
    public function observables_with_inner_records_at_different_time_arent_equal()
    {
        $records1 = onNext(100, $this->createColdObservable([
            onNext(50, 1),
            onNext(150, 2),
        ]));
        $records2 = onNext(100, $this->createColdObservable([
            onNext(50, 1),
            onNext(100, 2),
        ]));

        $this->assertFalse($records1->equals($records2));
        $this->assertEquals('[OnNext(1)@50, OnNext(2)@150]@100', $records1->__toString());
    }

    /**
     * @test
     */
    public function observables_with_more_nested_inner_observables()
    {
        $records1 = onNext(100, $this->createColdObservable([
            onNext(50, 1),
            onNext(100, 2),
            onNext(150, $this->createColdObservable([
                onNext(10, 3),
                onNext(20, 4),
                onNext(30, $this->createColdObservable([
                    onNext(10, 5),
                    onNext(20, 6),
                ])),
            ])->delay(100, $this->scheduler)),
        ]));
        $records2 = onNext(100, $this->createColdObservable([
            onNext(50, 1),
            onNext(100, 2),
            onNext(150, $this->createColdObservable([
                onNext(110, 3),
                onNext(120, 4),
                onNext(130, $this->createColdObservable([
                    onNext(10, 5),
                    onNext(20, 6),
                ])),
            ])),
        ]));

        $this->assertMessages([$records1], [$records2]);
        $this->assertTrue($records1->equals($records2));
    }

    /**
     * @test
     */
    public function observables_with_difference_in_nested_inner_observables()
    {
        $records1 = onNext(100, $this->createColdObservable([
            onNext(50, 1),
            onNext(100, 2),
            onNext(150, $this->createColdObservable([
                onNext(10, 3),
                onNext(20, 4),
                onNext(30, $this->createColdObservable([
                    onNext(10, 5),
                    onNext(20, 6),
                ])),
            ])->delay(100, $this->scheduler)),
        ]));
        $records2 = onNext(100, $this->createColdObservable([
            onNext(50, 1),
            onNext(100, 2),
            onNext(150, $this->createColdObservable([
                onNext(110, 3),
                onNext(120, 4),
                onNext(130, $this->createColdObservable([
                    onNext(10, 5),
                    onNext(20, 42), // this is wrong
                ])),
            ])),
        ]));

        $this->scheduler->start();
        $this->assertFalse($records1->equals($records2));
    }

}
