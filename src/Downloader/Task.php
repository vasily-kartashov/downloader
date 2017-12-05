<?php

namespace Downloader;

use ArrayIterator;
use Exception;
use InvalidArgumentException;
use Iterator;

final class Task
{
    /** @var int */
    private $batchSize;

    /** @var int */
    private $maxRetries;

    /** @var string|null */
    private $cacheKeyPrefix;

    /** @var int|null */
    private $timeToLive;

    /** @var string[] */
    private $items;

    /** @var callable[] */
    private $validators;

    /** @var int */
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

    /**
     * @return string
     * @throws Exception
     */
    public function cacheKeyPrefix(): string
    {
        if ($this->cacheKeyPrefix === null) {
            throw new Exception('Cache parameters not set');
        }
        return $this->cacheKeyPrefix;
    }

    /**
     * @return int
     * @throws Exception
     */
    public function timeToLive(): int
    {
        if ($this->timeToLive === null) {
            throw new Exception('Cache parameters not set');
        }
        return $this->timeToLive;
    }

    public function items(): Iterator
    {
        return new ArrayIterator($this->items);
    }

    public function itemCount(): int
    {
        return count($this->items);
    }

    public function throttle(): int
    {
        return $this->throttle;
    }

    /**
     * @return callable[]
     */
    public function validators(): array
    {
        return $this->validators;
    }

    public static function builder(): TaskBuilder
    {
        $task = new Task();

        $constructor =
        /**
         * @param int $batchSize
         * @param int $maxRetries
         * @param string|null $cacheKeyPrefix
         * @param int|null $timeToLive
         * @param string[] $items
         * @param callable[] $validators
         * @param int $throttle
         * @return Task
         */
        function (int $batchSize, int $maxRetries, $cacheKeyPrefix, $timeToLive, array $items, array $validators, int $throttle) use ($task): Task {
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
            /** @var callable */
            private $constructor;

            /** @var string|null */
            private $cacheKeyPrefix;

            /** @var int|null */
            private $timeToLive;

            /** @var int */
            private $batchSize = 1;

            /** @var int */
            private $maxRetries = 1;

            /** @var string[] */
            private $items = [];

            /** @var callable[] */
            private $validators = [];

            /** @var int */
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

            /**
             * @param int|string $id
             * @param string $url
             * @return TaskBuilder
             */
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
