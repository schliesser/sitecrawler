<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Sitecrawler',
    'description' => 'Crawl the sitemap (including all sub sitemaps), so the TYPO3 cache gets filled.',
    'category' => 'be',
    'author' => 'André Schließer',
    'author_email' => 'andy.schliesser@gmail.com',
    'state' => 'stable',
    'clearCacheOnLoad' => false,
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '9.5.0-10.4.99',
        ]
    ]
];
