<?php

namespace Downloader;

use Cache\Adapter\Void\VoidCachePool;
use Exception;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;

class DownloaderTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function testSimpleDownload()
    {
        $cache = new VoidCachePool();
        $downloader = new Downloader($cache);

        $count = 64;

        $builder = Task::builder()
            ->batch(12)
            ->retry(2)
            ->options([
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
            ])
            ->throttle(30);
        for ($i = 0; $i < $count; $i++) {
            $builder->add($i, 'https://example.com');
        }

        $results = $downloader->execute($builder->build());
        Assert::assertCount($count, $results);
    }
}
