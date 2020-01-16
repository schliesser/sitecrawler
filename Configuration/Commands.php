<?php

return [
    'sitecrawler:crawl' => [
        'class' => \Schliesser\Sitecrawler\Command\CrawlSitemapCommand::class,
        'schedulable' => true,
    ],
];
