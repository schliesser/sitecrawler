<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'TYPO3 sitemap crawler',
    'description' => 'This extension provides a Symfony command to crawl any sitemap including all sub sitemaps. It gathers all available urls and then calls each url. This way you can warm up the TYPO3 page cache. Any standard sitemap can be crawled: TYPO3, Shopware, ...',
    'category' => 'be',
    'author' => 'André Buchmann',
    'author_email' => 'andy.schliesser@gmail.com',
    'state' => 'stable',
    'clearCacheOnLoad' => false,
    'version' => '3.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '12.4.0-13.4.99',
        ],
    ],
];
