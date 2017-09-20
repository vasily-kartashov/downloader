<?php

namespace Downloader;

use Cache\Adapter\Common\CacheItem;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Downloader implements LoggerAwareInterface
{
    private $cache;
    private $logger;

    public function __construct(CacheItemPoolInterface $cache)
    {
        $this->cache = $cache;
        $this->logger = new NullLogger();
    }

    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param Task $task
     * @return Result[]
     */
    public function execute(Task $task): array
    {
        $results = [];
        $multiHandle = curl_multi_init();
        $startTime = microtime(true);

        $batches = [];
        $batch = [];
        foreach ($task->items() as $id => $url) {
            if ($task->cache()) {
                $cacheKey = $task->cacheKeyPrefix() . $id;
                $item = $this->cache->getItem($cacheKey);
                if ($item->isHit()) {
                    $content = $item->get();
                    if ($content !== null) {
                        $results[$id] = $this->result($content, true, false, false);
                    } else {
                        $results[$id] = $this->result(null, false, false, true);
                    }
                    continue;
                }
            }
            $batch[$id] = $url;
            if (count($batch) == $task->batchSize()) {
                $batches[] = $batch;
                $batch = [];
            }
        }
        if (!empty($batch)) {
            $batches[] = $batch;
        }

        foreach ($batches as $batch) {
            $attempt = 0;
            do {
                $handles = [];
                foreach ($batch as $id => $url) {
                    $this->logger->debug('Sending request to {url}', ['url' => $url]);
                    $handle = curl_init();
                    curl_setopt($handle, CURLOPT_URL, $url);
                    curl_setopt($handle, CURLOPT_RETURNTRANSFER, 1);
                    curl_multi_add_handle($multiHandle, $handle);
                    $handles[$id] = $handle;
                }
                $running = null;
                do {
                    curl_multi_exec($multiHandle, $running);
                } while ($running > 0);

                foreach ($handles as $id => $handle) {
                    $content = curl_multi_getcontent($handle);
                    $valid = true;
                    foreach ($task->validators() as $validator) {
                        if (!$validator($content)) {
                            $valid = false;
                            break;
                        }
                    }

                    if ($valid) {
                        if ($task->cache()) {
                            $cacheKey = $task->cacheKeyPrefix() . $id;
                            $item = new CacheItem($cacheKey);
                            $item->set($content);
                            $item->expiresAfter($task->timeToLive());
                            $this->cache->save($item);
                        }
                        $results[$id] = $this->result($content, true, false, false);
                        unset($batch[$id]);
                    }
                    curl_multi_remove_handle($multiHandle, $handle);
                }
            } while ($attempt++ < $task->maxRetries() && !empty($batch));

            foreach ($batch as $id => $_) {
                $cacheKey = $task->cacheKeyPrefix() . $id;
                $item = new CacheItem($cacheKey);
                $item->expiresAfter($task->throttle());
                $this->cache->save($item);
                $results[$id] = $this->result(null, false, true, false);
            }
        }
        curl_multi_close($multiHandle);
        $endTime = microtime(true);
        $this->logger->debug('Fetched data from {count} URL(s) in {duration} sec.', [
            'count' => count($task->items()),
            'duration' => trim(sprintf('%6.3f', $endTime - $startTime))
        ]);
        return $results;
    }

    private function result($content, bool $successful, bool $failed, bool $skipped): Result
    {
        return new class($content, $successful, $failed, $skipped) implements Result
        {
            private $content;
            private $successful;
            private $failed;
            private $skipped;

            public function __construct($content, bool $successful, bool $failed, bool $skipped)
            {
                $this->content = $content;
                $this->successful = $successful;
                $this->failed = $failed;
                $this->skipped = $skipped;
            }

            public function content(): string
            {
                return $this->content;
            }

            public function successful(): bool
            {
                return $this->successful;
            }

            public function failed(): bool
            {
                return $this->failed;
            }

            public function skipped(): bool
            {
                return $this->skipped;
            }
        };
    }
}
