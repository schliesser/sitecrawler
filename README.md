# sitecrawler
##**Sitemap crawler** for TYPO3

This extension provides a Symfony command to crawl the sitemap including all sub sitemaps. It gathers all available urls and than calls each url to warm up the TYPO3 page cache.

You can provide custom request headers in json format.

## Examples

Simple sitemap
```bash
vendor/bin/typo3cms sitecrawler:crawl https://www.example.com/sitemap.xml
```

Custom request headers:
```bash
vendor/bin/typo3cms sitecrawler:crawl https://www.example.com/sitemap.xml '{"X-Pjax": true}'
```
