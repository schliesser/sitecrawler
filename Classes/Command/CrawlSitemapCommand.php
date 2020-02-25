<?php

namespace Schliesser\Sitecrawler\Command;

use TYPO3\CMS\Core\Core\Environment;
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
            ->setDescription('Fetches a website (including all subpages), so the TYPO3 cache gets filled.')
            ->addArgument(
                'url',
                InputArgument::REQUIRED,
                'The sitemap url.'
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        $headers = null;
        $fetchDirectory = Environment::getVarPath() . '/sitecrawler';

        // Flush temp directory
        if (!GeneralUtility::flushDirectory($fetchDirectory)) {
            // Create temp directory
            GeneralUtility::mkdir($fetchDirectory);
        }
        $url = (string)$input->getArgument('url');

        $output->writeln('Sitemap url: ' . $url, OutputInterface::VERBOSITY_VERBOSE);

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

        // Show urls in debug mode
        $output->writeln(var_export($this->urls, true), OutputInterface::VERBOSITY_DEBUG);

        $output->writeln('Found ' . count($this->urls) . ' urls to crawl');

        //check if sitemap has sub sitemaps
        foreach ($this->urls as $url) {
            $output->writeln($url, OutputInterface::VERBOSITY_VERBOSE);
            // todo: call url with or without pjax header
            $output->writeln(GeneralUtility::getUrl($url, 2, $headers));
        }

        if ($this->errors) {
            $this->printErrors($output);
        }

        /**
         * curl "https://www.doeser-gruppe.de/?sitemap=pages&type=1533906435&cHash=200804aefda721ee8f2b0302caffd3fa"
         * | grep -e loc
         * | sed 's|<loc>\(.*\)<\/loc>$|\1|g'
         * | xargs -I {} curl -s -o /dev/null -w "%{http_code} %{url_effective}\n" -H "x-pjax: true" {}
         */

        // Crawl sitemap website
        // exec('curl -q -r ' . $input->getArgument('sitemapUrl') . ' -P ' . $fetchDirectory);
    }

    protected function printErrors(OutputInterface $output)
    {
        // print errors
        foreach ($this->errors as $error) {
            $output->writeln($error->getCode() . ': ' . $error->getMessage());
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
            $this->errors[] = $e;
            return;
        }
        $siteMap = json_decode(json_encode($xml), true) ?: [];
        if (($sites = $siteMap['sitemap']) || ($sites = $siteMap['url'])) {
            foreach ($sites as $site) {
                if ($url = (string)$site['loc']) {
                    $params = GeneralUtility::explodeUrl2Array(parse_url($url)['query']);
                    if (array_key_exists('sitemap', $params) || (int)$params['type'] === 1533906435) {
                        $this->getUrlListFromSiteMap($url);
                        $this->urls[] = $url;
                    } else {
                        $this->urls[] = $url;
                    }
                }
            }
        }
    }
}
