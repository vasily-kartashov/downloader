<?php

namespace Downloader;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;

class TaskTest extends TestCase
{
    public function testParameterCapture()
    {
        $validator = function () {};

        $task = Task::builder()
            ->cache('prefix.', 12)
            ->validate($validator)
            ->add(1, 'a')
            ->add(2, 'b')
            ->add(2, 'c')
            ->batch(6)
            ->retry(4)
            ->throttle(100)
            ->build();

        Assert::assertInstanceOf(Task::class, $task);
        Assert::assertEquals('prefix.', $task->cacheKeyPrefix());
        Assert::assertEquals(12, $task->timeToLive());
        Assert::assertEquals([$validator], $task->validators());
        Assert::assertEquals([1 => 'a', 2 => 'c'], iterator_to_array($task->items()));
        Assert::assertEquals(6, $task->batchSize());
        Assert::assertEquals(4, $task->maxRetries());
        Assert::assertEquals(100, $task->throttle());
    }
}
