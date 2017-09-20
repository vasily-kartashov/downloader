<?php

namespace Downloader;

interface TaskBuilder
{
    public function cache(string $cacheKeyPrefix, int $timeToLive): TaskBuilder;

    public function batch(int $size): TaskBuilder;

    public function retry(int $retries): TaskBuilder;

    public function add($id, string $url): TaskBuilder;

    public function validate(callable $validator): TaskBuilder;

    public function throttle(int $pause): TaskBuilder;

    public function build(): Task;
}
