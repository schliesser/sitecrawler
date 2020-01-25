<?php

namespace Schliesser\Sitecrawler\Command;

use TYPO3\CMS\Core\Core\Environment;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Error\Exception;

class CrawlSitemapCommand extends Command
{
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
        $fetchDirectory = Environment::getVarPath() . '/sitecrawler';
        // Remove temp directory
        exec('rm -rf ' . $fetchDirectory);
        // Create temp directory
        exec('mkdir ' . $fetchDirectory);

        $output->writeln('Sitemap url:' . $input->getArgument('url'));

        $fileString = $this->fetchUrl($input->getArgument('url'));
        $urls = $this->getUrlListFromFileString($fileString);
        if (isset($urls['error'])) {
            $output->writeln($urls['error']->getCode() . ': ' . $urls['error']->getMessage());
            return;
        }

        //check if sitemap has sub sitemaps
        foreach ($urls as $url) {
            // todo: create callback function
        }

//        print_r($urls);
        /**
         * curl "https://www.doeser-gruppe.de/?sitemap=pages&type=1533906435&cHash=200804aefda721ee8f2b0302caffd3fa"
         * | grep -e loc
         * | sed 's|<loc>\(.*\)<\/loc>$|\1|g'
         * | xargs -I {} curl -s -o /dev/null -w "%{http_code} %{url_effective}\n" -H "x-pjax: true" {}
         */

        // Crawl sitemap website
        // exec('curl -q -r ' . $input->getArgument('sitemapUrl') . ' -P ' . $fetchDirectory);
    }

    protected function fetchUrl(string $url)
    {
        return file_get_contents($url);
    }

    protected function getUrlListFromFileString($fileString): array
    {
        $urls = [];
        try {
            $xml = simplexml_load_string($fileString);
        } catch (Exception $e) {
            return ['error' => $e];
        }
        $sitemap = json_decode(json_encode($xml), TRUE) ?: [];
        if (isset($sitemap['sitemap'])) {
            foreach ($sitemap['sitemap'] as $url) {
                $urls[] = (string)$url['loc'];
            }
        }
        return $urls;
    }
}
