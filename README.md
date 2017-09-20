# PHP Downloader Library

Try and download data from weather underground, and you'll know what this library is for.

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

- Add tests
- Keep only PSR dependencies
- Expose CURL options
- Push retries into next batch to fully use multi-handle
