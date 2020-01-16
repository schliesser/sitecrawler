<?php

namespace Schliesser\Sitecrawler\Command;

use TYPO3\CMS\Core\Core\Environment;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

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
                'sitemapUrl',
                InputArgument::REQUIRED,
                'The sitemap url.'
            )
            ->addArgument(
                'hasSubSitemap',
                InputArgument::OPTIONAL,
                'The sitemap contains sub sitemaps',
                1
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

        echo $input->getArgument('sitemapUrl');

//        $xml = file_get_contents($input->getArgument('sitemapUrl'));
//        $sitemap = new SimpleXmlElement($xml);
//        foreach($sitemap->url as $url) {
//            $urls[] = (string)$url->loc;
//        }
//
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
}
