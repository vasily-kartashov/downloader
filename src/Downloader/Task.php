<?php

namespace Downloader;

use InvalidArgumentException;

class Task
{
    private $batchSize;
    private $maxRetries;
    private $cacheKeyPrefix;
    private $timeToLive;
    private $items;
    private $validators;
    private $throttle;

    private function __construct()
    {
    }

    public function batchSize(): int
    {
        return $this->batchSize;
    }

    public function maxRetries(): int
    {
        return $this->maxRetries;
    }

    public function cache(): bool
    {
        return $this->cacheKeyPrefix != null;
    }

    public function cacheKeyPrefix(): string
    {
        return $this->cacheKeyPrefix;
    }

    public function timeToLive(): int
    {
        return $this->timeToLive;
    }

    public function items()
    {
        return $this->items;
    }

    public function throttle(): int
    {
        return $this->throttle;
    }

    public function validators()
    {
        return $this->validators;
    }

    public static function builder(): TaskBuilder
    {
        $task = new Task();
        $constructor = function (int $batchSize, int $maxRetries, $cacheKeyPrefix, $timeToLive, array $items, array $validators, int $throttle) use ($task) {
            $task->batchSize = $batchSize;
            $task->maxRetries = $maxRetries;
            $task->cacheKeyPrefix = $cacheKeyPrefix;
            $task->timeToLive = $timeToLive;
            $task->items = $items;
            $task->validators = $validators;
            $task->throttle = $throttle;
            return $task;
        };
        return new class($constructor) implements TaskBuilder
        {
            private $constructor;
            private $cacheKeyPrefix;
            private $timeToLive;
            private $batchSize = 1;
            private $maxRetries = 1;
            private $items = [];
            private $validators = [];
            private $throttle = 0;

            public function __construct(callable $constructor)
            {
                $this->constructor = $constructor;
            }

            public function cache(string $cacheKeyPrefix, int $timeToLive): TaskBuilder
            {
                $this->cacheKeyPrefix = $cacheKeyPrefix;
                $this->timeToLive = $timeToLive;
                return $this;
            }

            public function build(): Task
            {
                return ($this->constructor)(
                    $this->batchSize,
                    $this->maxRetries,
                    $this->cacheKeyPrefix,
                    $this->timeToLive,
                    $this->items,
                    $this->validators,
                    $this->throttle
                );
            }

            public function batch(int $size): TaskBuilder
            {
                if ($size < 1) {
                    throw new InvalidArgumentException('Invalid batch size: ' . $size);
                }
                $this->batchSize = $size;
                return $this;
            }

            public function retry(int $maxRetries): TaskBuilder
            {
                if ($maxRetries < 1) {
                    throw new InvalidArgumentException('Invalid max retries number: ' . $maxRetries);
                }
                $this->maxRetries = $maxRetries;
                return $this;
            }

            public function add($id, string $url): TaskBuilder
            {
                if (!is_scalar($id)) {
                    throw new InvalidArgumentException('Download item ID must be atomic');
                }
                $this->items[$id] = $url;
                return $this;
            }

            public function validate(callable $validator): TaskBuilder
            {
                $this->validators[] = $validator;
                return $this;
            }

            public function throttle(int $pause): TaskBuilder
            {
                if ($pause < 0) {
                    throw new InvalidArgumentException('Invalid throttle pause');
                }
                $this->throttle = $pause;
                return $this;
            }
        };
    }
}
