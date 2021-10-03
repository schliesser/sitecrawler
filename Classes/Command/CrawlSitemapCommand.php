<?php

namespace Schliesser\Sitecrawler\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class CrawlSitemapCommand extends Command
{
    /**
     * @var array
     */
    protected $urls = [];

    /**
     * @var int
     */
    protected $sitemapCount = 0;

    /**
     * @var array
     */
    protected $errors = [];

    /**
     * Empty array triggers deprecation error in TYPO3 9.5...
     *
     * @var array|null Default: null
     */
    protected $requestHeaders;

    /**
     * Configure the command by defining the name, options and arguments
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Fetches a website (including all sub pages), so the TYPO3 cache gets filled.')
            ->addArgument(
                'url',
                InputArgument::REQUIRED,
                'The sitemap url.'
            )
            ->addArgument(
                'headers',
                InputArgument::OPTIONAL,
                'Request header arguments in json format. Example: \'{"X-Pjax": true, "Cache-Control": "no-cache"}\''
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $url = (string)$input->getArgument('url');
        $output->writeln('Sitemap url: ' . $url, OutputInterface::VERBOSITY_VERBOSE);

        // Validate input url
        if (!GeneralUtility::isValidUrl($url)) {
            $output->writeln('Error: Invalid url');

            return 1;
        }

        // Set headers from argument
        if ($input->getArgument('headers')) {
            $this->requestHeaders = json_decode($input->getArgument('headers'), true);
            $output->writeln('Headers: ' . var_export($this->requestHeaders, true), OutputInterface::VERBOSITY_DEBUG);
        }

        $output->writeln('Gathering urls for crawling ...');
        // Fetch urls
        $this->processUrl($url);
        if ($this->errors) {
            $this->printErrors($output);

            return 2;
        }

        // Return on empty urls
        if (!$this->urls) {
            $output->writeln('No urls found');

            return 3;
        }

        // Display url and sitemap count
        $output->writeln('Found ' . count($this->urls) . ' url(s) in ' . $this->sitemapCount . ' sitemap(s)');

        // Show urls in debug mode
        $output->writeln('Urls: ' . var_export($this->urls, true), OutputInterface::VERBOSITY_DEBUG);

        // Process url list
        $this->processUrlList($output);

        // Print errors or success
        if ($this->errors) {
            $output->writeln(' Finished with some errors!');
            $this->printErrors($output);

            return 4;
        }

        $output->writeln(' Completed successfully!');

        return 0;
    }

    /**
     * Print errors and reset error storage array
     */
    protected function printErrors(OutputInterface $output): void
    {
        // print errors
        foreach ($this->errors as $error) {
            $output->writeln($error['error'] . ': ' . $error['message']);
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
            try {
                $result = GeneralUtility::getUrl($url);
                if (!$result) {
                    $this->errors[] = ['error' => 1633234397666, 'message' => 'Unable to fetch url: "' . $url . '"'];
                }
            } catch (\Exception $e) {
                $this->errors[] = ['error' => $e->getCode(), 'message' => $e->getMessage()];
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
        if ($urlData['path'] === '/robots.txt') {
            $robotsUrl = true;
        } elseif ($urlData['path'] === '/' || !$urlData['path']) {
            // No path / empty path: use robots.txt file
            // robots.txt needs to be on root always
            $url = $urlData['scheme'] . '://' . $urlData['host'] . ($urlData['port'] ? ':' . $urlData['port'] : '') . '/robots.txt';
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

        if (isset($arr['sitemap']) && is_array($arr['sitemap']) && !empty($arr['sitemap'])) {
            // Check for single entry
            if (isset($arr['sitemap']['loc'])) {
                $this->addSitemap((string)$arr['sitemap']['loc']);
            } else {
                // Handle multiple entries
                foreach ($arr['sitemap'] as $sitemap) {
                    $this->addSitemap((string)$sitemap['loc']);
                }
            }
        } elseif (isset($arr['url']) && is_array($arr['url']) && !empty($arr['url'])) {
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

    protected function readRobotsTxt(string $robotsTxtUrl): array
    {
        // Fetch sitemap urls form robots.txt
        $content = GeneralUtility::getUrl($robotsTxtUrl);
        if (!$content) {
            $this->errors[] = ['error' => 1633234519166, 'message' => 'Unable to fetch robots.txt'];

            return [];
        }
        $sitemaps = [];
        preg_match_all('/^Sitemap: (.*)/m', $content, $matches, PREG_PATTERN_ORDER);
        foreach ($matches[1] as $url) {
            $sitemaps[] = trim($url);
        }

        return $sitemaps;
    }

    protected function getArrayFromUrl(string $url): array
    {
        try {
            $data = GeneralUtility::getUrl($url);
            if ($data === false) {
                $this->errors[] = ['error' => 1633234217716, 'message' => 'Unable to load xml from url: "' . $url . '"'];

                return [];
            }
            $xml = simplexml_load_string($data);
        } catch (\Exception $e) {
            $this->errors[] = ['error' => $e->getCode(), 'message' => $e->getMessage()];

            return [];
        }
        // Convert SimpleXML Objects to associative array
        return json_decode(json_encode($xml), true) ?: [];
    }

    /**
     * Validate url and parse sitemap content
     */
    protected function addSitemap(string $url): void
    {
        if (GeneralUtility::isValidUrl($url)) {
            ++$this->sitemapCount;
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
}
