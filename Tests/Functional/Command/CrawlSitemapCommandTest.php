<?php

declare(strict_types=1);

namespace Schliesser\Sitecrawler\Tests\Functional\Command;

use donatj\MockWebServer\MockWebServer;
use donatj\MockWebServer\Response;
use PHPUnit\Framework\Attributes\Test;
use Schliesser\Sitecrawler\Command\CrawlSitemapCommand;
use Schliesser\Sitecrawler\Exception\InvalidFormatException;
use Schliesser\Sitecrawler\Exception\InvalidUrlException;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

class CrawlSitemapCommandTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['typo3conf/ext/sitecrawler'];

    protected CommandTester $commandTester;

    protected static MockWebServer $server;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup mock webserver
        self::$server = new MockWebServer(1337);
        self::$server->start();

        $command = new CrawlSitemapCommand();
        $this->commandTester = new CommandTester($command);
    }

    protected function tearDown(): void
    {
        self::$server->stop();
    }

    #[Test]
    public function noParamsThrowsRuntimeException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->commandTester->execute([]);
    }

    #[Test]
    public function invalidUrlThrowsInvalidUrlException(): void
    {
        $this->expectException(InvalidUrlException::class);
        $this->commandTester->execute(['url' => 'foo-bar']);
    }

    #[Test]
    public function sitemapWithSingleUrlGist(): void
    {
        $this->commandTester->execute(
            ['url' => 'https://gist.githubusercontent.com/schliesser/042fe0d0780bde3f8223a74f25fbb3f1/raw/sitemap-1.xml']
        );
        self::assertStringContainsString('Completed successfully!', $this->commandTester->getDisplay());
    }

    #[Test]
    public function sitemapWithSingleUrl(): void
    {
        $url = self::$server->setResponseOfPath(
            '/sitemap.xml',
            new Response(file_get_contents(__DIR__ . '/../Fixtures/sitemap-1.xml') ?: '')
        );
        $this->commandTester->execute(['url' => $url]);
        self::assertStringContainsString('[OK] Completed successfully!', $this->commandTester->getDisplay());
    }

    #[Test]
    public function invalidListFormat(): void
    {
        $this->expectException(InvalidFormatException::class);
        $url = self::$server->setResponseOfPath(
            '/sitemap.xml',
            new Response(file_get_contents(__DIR__ . '/../Fixtures/sitemap-1.xml') ?: '')
        );
        $this->commandTester->execute([
            'url' => $url,
            '--list' => 'foo',
        ]);
    }

    #[Test]
    public function sitemapWithMultipleUrls(): void
    {
        $url = self::$server->setResponseOfPath(
            '/sitemap.xml',
            new Response(file_get_contents(__DIR__ . '/../Fixtures/sitemap-2.xml') ?: '')
        );
        $this->commandTester->execute([
            'url' => $url,
        ]);
        self::assertStringContainsString('[OK] Completed successfully!', $this->commandTester->getDisplay());
    }

    #[Test]
    public function sitemapWithMultipleUrlsAsJson(): void
    {
        $url = self::$server->setResponseOfPath(
            '/sitemap.xml',
            new Response(file_get_contents(__DIR__ . '/../Fixtures/sitemap-2.xml') ?: '')
        );
        $this->commandTester->execute([
            'url' => $url,
            '--list' => 'json',
        ]);
        self::assertSame(
            '{"urls":["http:\/\/127.0.0.1:1337\/","http:\/\/127.0.0.1:1337\/page"],"sitemaps":[]}',
            $this->commandTester->getDisplay()
        );
    }

    #[Test]
    public function sitemapWithMultipleUrlsAsTxt(): void
    {
        $url = self::$server->setResponseOfPath(
            '/sitemap.xml',
            new Response(file_get_contents(__DIR__ . '/../Fixtures/sitemap-2.xml') ?: '')
        );
        $this->commandTester->execute([
            'url' => $url,
            '--list' => 'txt',
        ]);
        self::assertSame(
            " * http://127.0.0.1:1337/\n * http://127.0.0.1:1337/page\n\n",
            $this->commandTester->getDisplay()
        );
    }

    #[Test]
    public function sitemapIndexWithSingleUrl(): void
    {
        $url = self::$server->setResponseOfPath(
            '/sitemap.xml',
            new Response(file_get_contents(__DIR__ . '/../Fixtures/sitemap-index-1.xml') ?: '')
        );
        self::$server->setResponseOfPath(
            '/sitemap-1.xml',
            new Response(file_get_contents(__DIR__ . '/../Fixtures/sitemap-1.xml') ?: '')
        );
        $this->commandTester->execute(['url' => $url]);
        self::assertStringContainsString('[OK] Completed successfully!', $this->commandTester->getDisplay());
    }

    #[Test]
    public function sitemapIndexWithMultipleSitemapsAsJson(): void
    {
        $url = self::$server->setResponseOfPath(
            '/sitemap.xml',
            new Response(file_get_contents(__DIR__ . '/../Fixtures/sitemap-index-2.xml') ?: '')
        );
        self::$server->setResponseOfPath(
            '/sitemap-1.xml',
            new Response(file_get_contents(__DIR__ . '/../Fixtures/sitemap-1.xml') ?: '')
        );
        self::$server->setResponseOfPath(
            '/sitemap-2.xml',
            new Response(file_get_contents(__DIR__ . '/../Fixtures/sitemap-2.xml') ?: '')
        );
        $this->commandTester->execute([
            'url' => $url,
            '--list' => 'json',
        ]);
        self::assertSame(
            '{"urls":["http:\/\/127.0.0.1:1337\/","http:\/\/127.0.0.1:1337\/","http:\/\/127.0.0.1:1337\/page"],"sitemaps":["http:\/\/127.0.0.1:1337\/sitemap-1.xml","http:\/\/127.0.0.1:1337\/sitemap-2.xml"]}',
            $this->commandTester->getDisplay()
        );
    }

    #[Test]
    public function unableToLoadXmlFromSitemap(): void
    {
        $url = self::$server->setResponseOfPath(
            '/sitemap.xml',
            new Response('404 Not found!', [], 404)
        );
        $this->commandTester->execute(['url' => $url]);
        self::assertStringContainsString('1633234217716:', $this->commandTester->getDisplay());
    }

    #[Test]
    public function unableToFetchRobotsTxt(): void
    {
        $url = self::$server->setResponseOfPath(
            '/robots.txt',
            new Response('404 Not found!', [], 404)
        );
        $this->commandTester->execute(['url' => $url]);
        self::assertStringContainsString('1633234519166:', $this->commandTester->getDisplay());
    }

    #[Test]
    public function unableToFetchRobotsTxtForDomain(): void
    {
        $url = self::$server->setResponseOfPath(
            '/',
            new Response('')
        );
        self::$server->setResponseOfPath(
            '/robots.txt',
            new Response('404 Not found!', [], 404)
        );
        $this->commandTester->execute(['url' => $url]);
        self::assertStringContainsString('1633234519166:', $this->commandTester->getDisplay());
    }

    #[Test]
    public function unableToFetchUrl(): void
    {
        $url = self::$server->setResponseOfPath(
            '/sitemap.xml',
            new Response(file_get_contents(__DIR__ . '/../Fixtures/sitemap-2.xml') ?: '')
        );
        self::$server->setResponseOfPath(
            '/page',
            new Response('', [], 404)
        );
        $this->commandTester->execute(['url' => $url]);
        self::assertStringContainsString('1633234397666', $this->commandTester->getDisplay());
    }

    #[Test]
    public function readSitemapsFromRobotsTxtAsJson(): void
    {
        $url = self::$server->setResponseOfPath(
            '/robots.txt',
            new Response(file_get_contents(__DIR__ . '/../Fixtures/robots.txt') ?: '')
        );
        self::$server->setResponseOfPath(
            '/sitemap-index-1.xml',
            new Response(file_get_contents(__DIR__ . '/../Fixtures/sitemap-index-1.xml') ?: '')
        );
        self::$server->setResponseOfPath(
            '/sitemap-index-2.xml',
            new Response(file_get_contents(__DIR__ . '/../Fixtures/sitemap-index-2.xml') ?: '')
        );
        self::$server->setResponseOfPath(
            '/sitemap-1.xml',
            new Response(file_get_contents(__DIR__ . '/../Fixtures/sitemap-1.xml') ?: '')
        );
        self::$server->setResponseOfPath(
            '/sitemap-2.xml',
            new Response(file_get_contents(__DIR__ . '/../Fixtures/sitemap-2.xml') ?: '')
        );
        $this->commandTester->execute([
            'url' => $url,
            '--list' => 'json',
        ]);
        self::assertSame(
            '{"urls":["http:\/\/127.0.0.1:1337\/","http:\/\/127.0.0.1:1337\/","http:\/\/127.0.0.1:1337\/page","http:\/\/127.0.0.1:1337\/","http:\/\/127.0.0.1:1337\/","http:\/\/127.0.0.1:1337\/","http:\/\/127.0.0.1:1337\/page"],"sitemaps":["http:\/\/127.0.0.1:1337\/sitemap-1.xml","http:\/\/127.0.0.1:1337\/sitemap-1.xml","http:\/\/127.0.0.1:1337\/sitemap-2.xml"]}',
            $this->commandTester->getDisplay()
        );
    }

    #[Test]
    public function sitemapIndexWithGzippedSitemap(): void
    {
        $url = self::$server->setResponseOfPath(
            '/sitemap.xml',
            new Response(file_get_contents(__DIR__ . '/../Fixtures/sitemap-index-gz.xml') ?: '')
        );
        self::$server->setResponseOfPath(
            '/sitemap-2.xml.gz',
            new Response(file_get_contents(__DIR__ . '/../Fixtures/sitemap-2.xml.gz') ?: '')
        );
        $this->commandTester->execute([
            'url' => $url,
            '--list' => 'json',
        ]);
        self::assertSame(
            '{"urls":["http:\/\/127.0.0.1:1337\/","http:\/\/127.0.0.1:1337\/page"],"sitemaps":["http:\/\/127.0.0.1:1337\/sitemap-2.xml.gz"]}',
            $this->commandTester->getDisplay()
        );
    }
}
