<?php

namespace Downloader;

use Cache\Adapter\Void\VoidCachePool;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;

class DownloaderTest extends TestCase
{
    public function testSimpleDownload()
    {
        $cache = new VoidCachePool();
        $downloader = new Downloader($cache);

        $count = 64;

        $builder = Task::builder()
            ->batch(12)
            ->retry(2)
            ->throttle(30);
        for ($i = 0; $i < $count; $i++) {
            $builder->add($i, 'http://example.com');
        }

        $results = $downloader->execute($builder->build());
        Assert::assertCount($count, $results);
    }
}