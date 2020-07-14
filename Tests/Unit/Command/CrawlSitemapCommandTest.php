<?php

declare(strict_types = 1);


namespace Schliesser\Sitecrawler\Tests\Unit\Command;

/*
 * This file is part of the sitecrawler extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

use PHPUnit\Framework\MockObject\MockObject;
use Prophecy\Prophecy\ObjectProphecy;
use Schliesser\Sitecrawler\Command\CrawlSitemapCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\AccessibleObjectInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Class CrawlSitemapCommandTest
 * @package Schliesser\Sitecrawler\Tests\Unit\Command
 */
class CrawlSitemapCommandTest extends UnitTestCase
{
    /**
     * @test
     */
    public function executeWillStopBeforeHeaderArgumentOnEmptyUrlArgument()
    {
        /** @var CrawlSitemapCommand|AccessibleObjectInterface $mockedCrawlSitemapCommand */
        $mockedCrawlSitemapCommand = $this->getAccessibleMock(CrawlSitemapCommand::class, ['dummy'], [], '', false);

        /** @var InputInterface|ObjectProphecy $input */
        $input = $this->prophesize(InputInterface::class);
        $input->getArgument('url')->shouldBeCalled();
        $input->getArgument('header')->shouldNotBeCalled();

        $mockedCrawlSitemapCommand->_call('execute', $input->reveal(), $this->prophesize(OutputInterface::class)->reveal());
    }

    /**
     * @test
     */
    public function executeWillExitOnErrorsWith2()
    {
        $url = 'https://domain.tld/foo/bar';

        /** @var CrawlSitemapCommand|AccessibleObjectInterface $mockedCrawlSitemapCommand */
        $mockedCrawlSitemapCommand = $this->getAccessibleMock(CrawlSitemapCommand::class, ['dummy'], [], '', false);

        /** @var InputInterface|ObjectProphecy $input */
        $input = $this->prophesize(InputInterface::class);
        $input->getArgument('url')->willReturn($url);
        $input->getArgument('headers')->willReturn('');

        $result = $mockedCrawlSitemapCommand->_call('execute', $input->reveal(), $this->prophesize(OutputInterface::class)->reveal());
        $this->assertEquals(2,$result);
    }
}
