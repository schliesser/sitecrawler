<?php

namespace Schliesser\Sitecrawler\Command;

use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Error\Exception;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class CrawlSitemapCommand extends Command
{
    /**
     * @var array
     */
    protected $urls = [];

    /**
     * @var array
     */
    private $errors = [];

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
                'Request header arguments in json format. Example: \'{"X-Pjax": true, "Cache-Control": "no-cache"}\'');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        $requestHeaders = null;
        $url = (string)$input->getArgument('url');
        $output->writeln('Sitemap url: ' . $url, OutputInterface::VERBOSITY_VERBOSE);

        // Validate input url
        if (!GeneralUtility::isValidUrl($url)) {
            $output->writeln('Error: Invalid url');
            return;
        }

        // Fetch urls
        $this->getUrlListFromSiteMap($url);
        if ($this->errors) {
            $this->printErrors($output);
            return;
        }

        // Return on empty urls
        if (!$this->urls) {
            $output->writeln('No urls found');
            return;
        }
        $output->writeln('Found ' . count($this->urls) . ' urls to crawl');
        // Show urls in debug mode
        $output->writeln('Urls: ' . var_export($this->urls, true), OutputInterface::VERBOSITY_DEBUG);

        // Set headers from argument
        if ($input->getArgument('headers')) {
            $requestHeaders = json_decode($input->getArgument('headers'), true);
            $output->writeln('Headers: ' . var_export($requestHeaders, true), OutputInterface::VERBOSITY_DEBUG);
        }

        // Init progress bar
        $progressBar = new ProgressBar($output, count($this->urls));

        //check if sitemap has sub sitemaps
        foreach ($this->urls as $url) {
            GeneralUtility::getUrl($url, 2, $requestHeaders, $error);
            if ($error) {
                $this->errors[] = $error;
            }
            $progressBar->advance();
        }

        // Stop progress bar
        $progressBar->finish();

        // Print errors or success
        if ($this->errors) {
            $output->writeln(' Finished with some errors!');
            $this->printErrors($output);
        } else {
            $output->writeln(' Completed successfully!');
        }
    }

    protected function printErrors(OutputInterface $output)
    {
        // print errors
        foreach ($this->errors as $error) {
            $output->writeln($error['error'] . ': ' . $error['message']);
        }

        // reset errors
        $this->errors = [];
    }

    /**
     * @param string $siteMapUrl
     */
    protected function getUrlListFromSiteMap(string $siteMapUrl): void
    {
        try {
            $xml = simplexml_load_string(GeneralUtility::getUrl($siteMapUrl));
        } catch (Exception $e) {
            $this->errors[] = ['error' => $e->getCode(), 'message' => $e->getMessage()];
            return;
        }
        $siteMap = json_decode(json_encode($xml), true) ?: [];
        if (($sites = $siteMap['sitemap']) || ($sites = $siteMap['url'])) {
            foreach ($sites as $site) {
                if ($url = (string)$site['loc']) {
                    $params = GeneralUtility::explodeUrl2Array(parse_url($url)['query']);
                    if (array_key_exists('sitemap', $params) || (int)$params['type'] === 1533906435) {
                        $this->getUrlListFromSiteMap($url);
                    } else {
                        $this->urls[] = $url;
                    }
                }
            }
        }
    }
}
