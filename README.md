# PHP Downloader Library

![CI Status](https://github.com/vasily-kartashov/downloader/CI/badge.svg?branch=master&event=push)
[![Packagist](https://img.shields.io/packagist/v/vasily-kartashov/downloader.svg)](https://packagist.org/packages/hamlet-framework/json-mapper)
[![Packagist](https://img.shields.io/packagist/dt/vasily-kartashov/downloader.svg)](https://packagist.org/packages/hamlet-framework/json-mapper)
[![Coverage Status](https://coveralls.io/repos/github/vasily-kartashov/downloader/badge.svg?branch=master)](https://coveralls.io/github/hamlet-framework/json-mapper?branch=master)
![Psalm coverage](https://shepherd.dev/github/vasily-kartashov/downloader/coverage.svg?)

## Example

```php
<?php

$redisClient = new Redis();
$redisClient->connect('localhost', 6379);
$redisCachePool = new RedisCachePool($redisClient);

$downloader = Downloader($redisCachePool);

$task = Task::builder()
    ->batch(12)
    ->retry(3)
    ->validate(function ($response) {
        return strlen($response) > 1024;
    })
    ->cache('pages.', 12 * 3600)
    ->options([
        CURLOPT_SSL_VERIFYHOST => false
    ])
    ->throttle(120)
    ->add(1, 'http://example/page/1')
    ->add(2, 'http://example/page/2')
    ...
    ->add(9, 'http://example/page/9')
    ->build();
```

This will 
- send multi curl requests to the specified URLs, 12 in each batch.
- The successful responses will be kept in cache for 12 hours.
- The downloader will try to download each page 3 times before moving to the next batch.
- If last failure was less than 2 minutes, a new download will not be attempted.
- Only responses longer than 1024 are treated as successful

```php
$results = $downloader->execute($task);
foreach ($results as $result) {
    if ($result->successful()) {
        echo $result->content();
    } elseif ($result->failed()) {
        echo 'Failed to fetch';
    } elseif ($result->skipped()) {
        echo 'Skipping result, to avoid too many retries';
    }
}
```

## ToDo

- Embed Guzzle and use standards, keep this as a lean interface only
- Add more tests
