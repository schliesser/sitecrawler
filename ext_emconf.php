<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'TYPO3 sitemap crawler',
    'description' => 'Crawl the sitemap (including all sub sitemaps), and call each url to warm up the TYPO3 page cache.',
    'category' => 'be',
    'author' => 'André Buchmann',
    'author_email' => 'andy.schliesser@gmail.com',
    'state' => 'stable',
    'clearCacheOnLoad' => false,
    'version' => '1.0.1',
    'constraints' => [
        'depends' => [
            'typo3' => '9.5.0-10.4.99',
        ]
    ]
];
