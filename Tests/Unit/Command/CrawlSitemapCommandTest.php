<?php

declare(strict_types=1);

namespace Schliesser\Sitecrawler\Test\Unit\Command;

/*
 * This file is part of the sitecrawler extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

use Prophecy\Prophecy\ObjectProphecy;
use Schliesser\Sitecrawler\Command\CrawlSitemapCommand;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\TestingFramework\Core\AccessibleObjectInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Class CrawlSitemapCommandTest
 */
class CrawlSitemapCommandTest extends UnitTestCase
{
    /**
     * @var CrawlSitemapCommand|AccessibleObjectInterface $mockedCrawlSitemapCommand
     */
    protected $mockedCommand;

    /**
     * @var InputInterface|ObjectProphecy $input
     */
    protected $input;

    /**
     * @var OutputInterface|ObjectProphecy $input
     */
    protected $output;

    /**
     * setUp function
     */
    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['TYPO3_CONF_VARS']['HTTP']['verify'] = 1;

        $this->mockedCommand = $this->getAccessibleMock(CrawlSitemapCommand::class, ['dummy']);
        $this->input = $this->prophesize(InputInterface::class);
        $this->output = $this->prophesize(OutputInterface::class);
    }

    /**
     * @test
     */
    public function executeWillStopBeforeHeaderArgumentOnEmptyUrlArgument(): void
    {
        $this->input->getArgument('url')->shouldBeCalled();
        $this->input->getArgument('header')->shouldNotBeCalled();

        $result = $this->mockedCommand->_call('execute', $this->input->reveal(), $this->output->reveal());
        self::assertEquals(1, $result);
    }

    /**
     * @test
     */
    public function executeWillExitOnErrorsWith2(): void
    {
        $url = 'https://localhost/foo/bar';
        // Cannot validate url if index is missing
        unset($GLOBALS['TYPO3_CONF_VARS']['HTTP']);

        $this->input->getArgument('url')->willReturn($url);
        $this->input->getArgument('headers')->willReturn('');

        $result = $this->mockedCommand->_call('execute', $this->input->reveal(), $this->output->reveal());
        self::assertEquals(2, $result);
    }

    /**
     * @test
     */
    public function executeWillExitOnEmptyUrlList(): void
    {
        $url = 'https://localhost/foo/bar';

        $this->input->getArgument('url')->willReturn($url);
        $this->input->getArgument('headers')->willReturn('');

        $result = $this->mockedCommand->_call('execute', $this->input->reveal(), $this->output->reveal());
        self::assertEquals(3, $result);
    }

    public function sitemapSamples(): array
    {
        return [
            [
                // Sitemap with single url
                'https://gist.githubusercontent.com/schliesser/042fe0d0780bde3f8223a74f25fbb3f1/raw/83c57e5eee37baf0c07d6c9b8c9c2cf8da920fd8/sitemap-1.xml',
                0, // Sitemaps
                1, // Urls
                0, // Command exit value
            ],
            [
                // Sitemap with multiple urls
                'https://gist.githubusercontent.com/schliesser/042fe0d0780bde3f8223a74f25fbb3f1/raw/83c57e5eee37baf0c07d6c9b8c9c2cf8da920fd8/sitemap-2.xml',
                0, // Sitemaps
                2, // Urls
                0, // Command exit value
            ],
            [
                // Sitemap index with single sitemap
                'https://gist.githubusercontent.com/schliesser/042fe0d0780bde3f8223a74f25fbb3f1/raw/7ba391c93119a9dc93a85a3a4b1aabd4dba36de5/sitemap-index-1.xml',
                1, // Sitemaps
                1, // Urls
                0, // Command exit value
            ],
            [
                // Sitemap index with multiple sitemaps
                'https://gist.githubusercontent.com/schliesser/042fe0d0780bde3f8223a74f25fbb3f1/raw/7ba391c93119a9dc93a85a3a4b1aabd4dba36de5/sitemap-index-2.xml',
                2, // Sitemaps
                3, // Urls
                0, // Command exit value
            ],
        ];
    }

    /**
     * @dataProvider sitemapSamples
     * @test
     * @param string $url
     * @param int $sitemapCount
     * @param int $urlCount
     * @param int $exitCode
     */
    public function executeWillExitAfterUrlProcessingWithoutErrors(string $url, int $sitemapCount, int $urlCount, int $exitCode): void
    {
        $this->input->getArgument('url')->willReturn($url);
        $this->input->getArgument('headers')->willReturn('');

        // Mock Output for progress bar
        $output = $this->createMock(OutputInterface::class);
        $outputFormatterInterface = $this->createMock(OutputFormatterInterface::class);
        $outputFormatterInterface->method('isDecorated')->willReturn(false);
        $output->method('getFormatter')->willReturn($outputFormatterInterface);

        $result = $this->mockedCommand->_call('execute', $this->input->reveal(), $output);
        self::assertEquals($sitemapCount,$this->mockedCommand->_get('sitemapCount'));
        self::assertCount($urlCount, $this->mockedCommand->_get('urls'));
        self::assertEquals($exitCode, $result); // TYPO3 10 throws an "E_USER_DEPRECATION" error
    }
}
