<?php

namespace Downloader;

use Cache\Adapter\Common\CacheItem;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class Downloader implements LoggerAwareInterface
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

        $attempts = [];
        $queue = [];
        $urls = [];
        foreach ($task->items() as $id => $url) {
            $attempts[$id] = 0;
            $queue[] = $id;
            $urls[$id] = $url;
        }

        while (!empty($queue)) {
            $id = array_pop($queue);

            if ($task->cache()) {
                $cacheKey = $task->cacheKeyPrefix() . $id;
                $item = $this->cache->getItem($cacheKey);
                if ($item->isHit()) {
                    $response = $item->get();
                    if ($response !== null) {
                        $results[$id] = $this->result($response, true, false, false);
                    } else {
                        $results[$id] = $this->result(null, false, false, true);
                    }
                    continue;
                }
            }

            $batch[] = $id;
            if (count($batch) == $task->batchSize() || empty($queue)) {
                $handles = [];
                foreach ($batch as $id) {
                    $this->logger->debug('Sending request to {url}', ['url' => $urls[$id]]);
                    $handle = curl_init();
                    curl_setopt($handle, CURLOPT_URL, $urls[$id]);
                    curl_setopt($handle, CURLOPT_RETURNTRANSFER, 1);
                    curl_multi_add_handle($multiHandle, $handle);
                    $handles[$id] = $handle;
                    $attempts[$id]++;
                }
                $batch = [];
                $running = null;
                do {
                    curl_multi_exec($multiHandle, $running);
                } while ($running > 0);

                $responses = [];
                foreach ($handles as $id => $handle) {
                    $response = curl_multi_getcontent($handle);
                    $valid = true;
                    foreach ($task->validators() as $validator) {
                        if (!$validator($response)) {
                            $valid = false;
                            break;
                        }
                    }
                    $responses[$id] = $valid ? $response : null;
                }

                foreach ($responses as $id => $response) {
                    if ($response !== null) {
                        if ($task->cache()) {
                            $cacheKey = $task->cacheKeyPrefix() . $id;
                            $item = new CacheItem($cacheKey);
                            $item->set($response);
                            $item->expiresAfter($task->timeToLive());
                            $this->cache->save($item);
                        }
                        $results[$id] = $this->result($response, true, false, false);
                    } elseif ($attempts == $task->maxRetries()) {
                        if ($task->cache()) {
                            $cacheKey = $task->cacheKeyPrefix() . $id;
                            $item = new CacheItem($cacheKey);
                            $item->expiresAfter($task->throttle());
                            $this->cache->save($item);
                        }
                        $this->result(null, false, true, false);
                    } else {
                        array_push($queue, $id);
                    }
                };
            }
        }
        curl_multi_close($multiHandle);
        $endTime = microtime(true);
        $this->logger->debug('Fetched data from {count} URL(s) in {duration} sec.', [
            'count' => $task->itemCount(),
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
