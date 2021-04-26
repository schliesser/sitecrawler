# TYPO3 Sitemap crawler

This extension provides a Symfony command to crawl the sitemap including all sub sitemaps. It gathers all available urls and then calls each url to warm up the TYPO3 page cache.

You can provide custom request headers in json format.

## Examples

Composer based:
```bash
vendor/bin/typo3cms sitecrawler:crawl 'https://www.example.com/sitemap.xml'

# with custom request headers
vendor/bin/typo3cms sitecrawler:crawl 'https://www.example.com/sitemap.xml' '{"X-Pjax": true}'
```
Non composer:
```bash
typo3/sysext/core/bin/typo3 sitecrawler:crawl 'https://www.example.com/sitemap.xml'
```

## Development

- Clone project and `cd` into the extension folder
- Install dependencies for tests: `composer install`
- Run tests with: `composer run test`
