<?php

namespace Downloader;

interface TaskBuilder
{
    /**
     * @param string $cacheKeyPrefix
     * @param int $timeToLive
     * @return TaskBuilder
     */
    public function cache(string $cacheKeyPrefix, int $timeToLive): TaskBuilder;

    /**
     * @param int $size
     * @return TaskBuilder
     */
    public function batch(int $size): TaskBuilder;

    /**
     * @param int $maxRetries
     * @return TaskBuilder
     */
    public function retry(int $maxRetries): TaskBuilder;

    /**
     * @param int|string $id
     * @param string $url
     * @return TaskBuilder
     */
    public function add($id, string $url): TaskBuilder;

    /**
     * @param callable(string,array-key,string):bool $validator
     * @return TaskBuilder
     */
    public function validate(callable $validator): TaskBuilder;

    /**
     * @param int $pause
     * @return TaskBuilder
     */
    public function throttle(int $pause): TaskBuilder;

    /**
     * @param array<int,scalar> $options
     * @return TaskBuilder
     */
    public function options(array $options): TaskBuilder;

    /**
     * @return Task
     */
    public function build(): Task;
}
