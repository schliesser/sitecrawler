# TYPO3 Sitemap crawler

This extension provides a Symfony command to crawl any sitemap including all sub sitemaps. It gathers all available urls and then calls each url. This way you can warm up the TYPO3 page cache. Any standard sitemap can be crawled: TYPO3, Shopware, ...

You can provide custom request headers in json format. For basic auth you need to base64 encode user:password in the header.

Since version 1.1.0 the sitecrawler can read `robots.txt` files to fetch all defined sitemaps from it.

## Examples

Composer based:
```bash
vendor/bin/typo3 sitecrawler:crawl 'https://www.example.com/sitemap.xml'

# with custom request headers
vendor/bin/typo3 sitecrawler:crawl 'https://www.example.com/sitemap.xml' '{"Authorization": "Basic dXNlcjpwYXNzd29yZA==", "Cache-Control": "no-cache"}'
```
Non composer:
```bash
typo3/sysext/core/bin/typo3 sitecrawler:crawl 'https://www.example.com/sitemap.xml'
```

## Development

- Clone project and `cd` into the extension folder
- Install dependencies for tests: `composer install`
- Run tests with: `composer run test`
