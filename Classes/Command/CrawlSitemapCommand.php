<?php

namespace Schliesser\Sitecrawler\Command;

use InvalidArgumentException;
use JsonException;
use Schliesser\Sitecrawler\Exception\InvalidFormatException;
use Schliesser\Sitecrawler\Exception\InvalidHeadersException;
use Schliesser\Sitecrawler\Exception\InvalidUrlException;
use Schliesser\Sitecrawler\Helper\Error;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class CrawlSitemapCommand extends Command
{
    /** @var string[] */
    public array $sitemaps = [];
    /** @var string[] */
    protected array $urls = [];
    protected int $sitemapCount = 0;
    /** @var array<Error> */
    protected array $errors = [];
    /** @var array<string, string> */
    protected array $requestHeaders = [
        'User-Agent' => 'TYPO3 sitecrawler',
        'Accept-Encoding' => 'gzip',
    ];

    /**
     * Configure the command by defining the name, options and arguments
     */
    protected function configure(): void
    {
        $this
            ->setHelp('Crawl any sitemap including all sub sitemaps. It gathers all available urls and then calls each url or writes a list to StdOut. Any standard sitemap can be crawled: TYPO3, Shopware, etc. You can use this e.g. to warm up the TYPO3 page cache.')
            ->addArgument(
                'url',
                InputArgument::REQUIRED,
                'The sitemap url.'
            )
            ->addArgument(
                'headers',
                InputArgument::OPTIONAL,
                "Request header arguments in json format. For basic auth you need to base64 encode user:password in the header.\n Example: '{\"Authorization\": \"Basic dXNlcjpwYXNzd29yZA==\", \"Cache-Control\": \"no-cache\"}'"
            )
            ->addOption(
                'list',
                'l',
                InputOption::VALUE_OPTIONAL,
                'Output list of gathered urls instead of crawling them. Accepts \'txt\' or \'json\' as value. (Not usable in the scheduler!)'
            );
    }

    /**
     * @throws InvalidFormatException
     * @throws InvalidHeadersException
     * @throws InvalidUrlException
     * @throws JsonException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $url = $input->getArgument('url');
        if (!is_string($url)) {
            throw new InvalidArgumentException('Argument "url" must be a string!', 1715513484);
        }
        $io->writeln('Sitemap url: ' . $url, OutputInterface::VERBOSITY_VERBOSE);

        // Validate input url
        if (!GeneralUtility::isValidUrl($url)) {
            throw new InvalidUrlException('Invalid url given as argument!', 1657265973215);
        }

        // Set headers from argument
        if ($headers = $input->getArgument('headers')) {
            if (!is_string($headers)) {
                throw new InvalidArgumentException('Argument "headers" must be a json string!', 1715513588);
            }
            $headerArray = json_decode($headers, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($headerArray)) {
                throw new InvalidHeadersException('Invalid header json given!', 1715514805);
            }
            $this->requestHeaders = array_merge($this->requestHeaders, $headerArray);
            $io->writeln('Headers: ' . var_export($this->requestHeaders, true), OutputInterface::VERBOSITY_DEBUG);
        }

        $io->writeln('Gathering urls for crawling ...', OutputInterface::VERBOSITY_VERBOSE);
        // Fetch urls
        $this->processUrl($url);
        if ($this->errors) {
            $this->printErrors($output);

            return 2;
        }

        // Return on empty urls
        if (!$this->urls) {
            $io->warning('No urls found');

            return 3;
        }

        // Display url and sitemap count
        $sitemapCount = count($this->sitemaps);
        $io->writeln('Found ' . count($this->urls) . ' url(s)' . ($sitemapCount ? ' in ' . $sitemapCount . ' sitemap(s)' : ''),
            OutputInterface::VERBOSITY_VERBOSE);

        // Return url list as txt/json when format option is set
        if ($format = $input->getOption('list')) {
            if (!is_string($format)) {
                throw new InvalidArgumentException('Argument "list" must be a string!', 1715514158);
            }
            switch (strtolower($format)) {
                case 'json':
                    $io->write(json_encode(['urls' => $this->urls, 'sitemaps' => $this->sitemaps], JSON_THROW_ON_ERROR));
                    break;
                case 'txt':
                    $io->listing($this->urls);
                    break;
                default:
                    throw new InvalidFormatException('Invalid format for list "' . htmlspecialchars($format) . '"!', 1657265268452);
            }

            return 0;
        }

        // Process url list
        $this->processUrlList($output);

        // Print errors or success
        if ($this->errors) {
            $io->warning('Finished with some errors!');
            $this->printErrors($output);

            return 4;
        }

        $io->success('Completed successfully!');

        return 0;
    }

    /**
     * Print errors and reset error storage array
     */
    protected function printErrors(OutputInterface $output): void
    {
        // print errors
        foreach ($this->errors as $error) {
            $output->writeln($error->getCode() . ': ' . $error->getMessage());
        }

        // reset errors
        $this->errors = [];
    }

    protected function processUrlList(OutputInterface $output): void
    {
        // Init progress bar
        $progressBar = new ProgressBar($output);

        // Process url list
        foreach ($progressBar->iterate($this->urls) as $url) {
            $result = $this->testUrl($url);
            if (!$result) {
                $this->errors[] = new Error(1633234397666, 'Unable to fetch url: "' . $url . '"');
            }
        }

        // Stop progress bar
        // @extensionScannerIgnoreLine
        $progressBar->finish();
    }

    protected function processUrl(string $url): void
    {
        $urlData = parse_url($url);
        $robotsUrl = false;

        // Read robots.txt file if the urls path is /robots.txt
        if (isset($urlData['path']) && $urlData['path'] === '/robots.txt') {
            $robotsUrl = true;
        } elseif ((empty($urlData['path']) || $urlData['path'] === '/') && empty($urlData['query'])) {
            // No path / empty path: use robots.txt file
            // robots.txt needs to be on root always
            if (empty($urlData['scheme']) || empty($urlData['host'])) {
                throw new InvalidUrlException('Missing Scheme and Host in url: "' . $url . '"', 1715515452);
            }
            $url = $urlData['scheme'] . '://' . $urlData['host'] . (isset($urlData['port']) ? ':' . $urlData['port'] : '') . '/robots.txt';
            $robotsUrl = true;
        }
        if ($robotsUrl) {
            if (!empty($sitemaps = $this->readRobotsTxt($url))) {
                foreach ($sitemaps as $sitemap) {
                    $this->getUrlListFromSitemap($sitemap);
                }
            }
        } else {
            $this->getUrlListFromSitemap($url);
        }
    }

    /**
     * Fetch sitemap from url, parse xml and create list with urls
     */
    protected function getUrlListFromSitemap(string $url): void
    {
        $arr = $this->getArrayFromUrl($url);

        if (!empty($arr['sitemap']) && is_array($arr['sitemap'])) {
            // Check for single entry
            if (isset($arr['sitemap']['loc'])) {
                $this->addSitemap((string)$arr['sitemap']['loc']);
            } else {
                // Handle multiple entries
                foreach ($arr['sitemap'] as $sitemap) {
                    $this->addSitemap((string)$sitemap['loc']);
                }
            }
        } elseif (!empty($arr['url']) && is_array($arr['url'])) {
            // Check for single entry
            if (isset($arr['url']['loc'])) {
                $this->addUrl((string)$arr['url']['loc']);
            } else {
                // Handle multiple entries
                foreach ($arr['url'] as $site) {
                    $this->addUrl((string)$site['loc']);
                }
            }
        }
    }

    /**
     * @return string[]
     */
    protected function readRobotsTxt(string $robotsTxtUrl): array
    {
        // Fetch sitemap urls form robots.txt
        $content = $this->getUrl($robotsTxtUrl);
        if (!$content) {
            $this->errors[] = new Error(1633234519166, 'Unable to fetch robots.txt');

            return [];
        }
        $sitemaps = [];
        preg_match_all('/^Sitemap: (.*)/m', $content, $matches, PREG_PATTERN_ORDER);
        foreach ($matches[1] as $url) {
            $sitemaps[] = trim($url);
        }

        return $sitemaps;
    }

    /**
     * @return mixed[]
     */
    protected function getArrayFromUrl(string $url): array
    {
        try {
            $data = $this->getUrl($url);
            if (!$data) {
                $this->errors[] = new Error(1633234217716, 'Unable to load xml from url: "' . $url . '"');

                return [];
            }

            // Decode gzip compressed sitemaps
            $isGzip = 0 === mb_strpos($data, "\x1f" . "\x8b" . "\x08", 0, 'US-ASCII');
            if ($isGzip) {
                $data = gzdecode($data);
                if (!is_string($data)) {
                    $this->errors[] = new Error(1715517082, 'Failed extract gzip compressed sitemap from url: "' . $url . '"');

                    return [];
                }
            }

            // Load xml data
            $xml = simplexml_load_string($data);
        } catch (\Exception $e) {
            $this->errors[] = new Error($e->getCode(), $e->getMessage());

            return [];
        }

        // Convert SimpleXML Objects to associative array
        try {
            $array = json_decode(json_encode($xml, JSON_THROW_ON_ERROR) ?: '', true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $this->errors[] = new Error(1715517272, 'Failed to transform xml data for url: "' . $url . '"');

            return [];
        }
        if (!is_array($array)) {
            $this->errors[] = new Error(1715515053, 'Failed to transform xml data for url: "' . $url . '"');

            return [];
        }

        return $array;
    }

    /**
     * Validate url and parse sitemap content
     */
    protected function addSitemap(string $url): void
    {
        if (GeneralUtility::isValidUrl($url)) {
            ++$this->sitemapCount;
            $this->sitemaps[] = $url;
            $this->getUrlListFromSitemap($url);
        }
    }

    /**
     * Validate url and add it to the urls array which is parsed later on
     */
    protected function addUrl(string $url): void
    {
        if (GeneralUtility::isValidUrl($url)) {
            $this->urls[] = $url;
        }
    }

    /**
     * Wrapper for GeneralUtility::getUrl() with catcher for all exceptions
     */
    protected function getUrl(string $url): ?string
    {
        try {
            $requestFactory = GeneralUtility::makeInstance(RequestFactory::class);
            $response = $requestFactory->request($url, 'GET', ['headers' => $this->requestHeaders ?? []]);

            return $response->getBody()->getContents();
        } catch (\Exception $e) {
            $this->errors[] = new Error($e->getCode(), $e->getMessage());

            return null;
        }
    }

    /**
     * @return mixed[]|null
     */
    protected function testUrl(string $url): ?array
    {
        try {
            $requestFactory = GeneralUtility::makeInstance(RequestFactory::class);
            $response = $requestFactory->request($url, 'HEAD', ['headers' => $this->requestHeaders ?? []]);

            return $response->getHeaders();
        } catch (\Exception $e) {
            $this->errors[] = new Error($e->getCode(), $e->getMessage());

            return null;
        }
    }
}
