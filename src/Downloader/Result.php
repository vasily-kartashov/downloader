<?php

namespace Downloader;

interface Result
{
    public function content(): string;

    public function successful(): bool;

    public function failed(): bool;

    public function skipped(): bool;
}
