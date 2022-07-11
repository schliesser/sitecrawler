<?php

declare(strict_types=1);

namespace Schliesser\Sitecrawler\Tests\Functional\Command;

use Schliesser\Sitecrawler\Exception\InvalidFormatException;
use Schliesser\Sitecrawler\Exception\InvalidUrlException;
use Symfony\Component\Console\Exception\RuntimeException;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Schliesser\Sitecrawler\Command\CrawlSitemapCommand;
use Symfony\Component\Console\Tester\CommandTester;

class CrawlSitemapCommandTest extends FunctionalTestCase
{
    /**
     * @var array
     */
    protected $testExtensionsToLoad = ['typo3conf/ext/sitecrawler'];

    protected CommandTester $commandTester;

    protected function setUp(): void
    {
        parent::setUp();

        $command = new CrawlSitemapCommand();
        $this->commandTester = new CommandTester($command);
    }

    /**
     * @test
     * @dataProvider commandDataProvider
     */
    public function crawlSitemapCommandTest(array $parameters, string $expectedOutput, string $expectedError = ''): void
    {
        $arguments = [];
        if (!empty($parameters)) {
            $arguments = $parameters;
        }

        if (!empty($expectedError)) {
            $this->expectException($expectedError);
        }

        $this->commandTester->execute($arguments);
        $commandOutput = $this->commandTester->getDisplay();

        self::assertStringContainsString($expectedOutput, $commandOutput);
    }

    public function commandDataProvider(): iterable
    {
        yield 'No url param' => [
            'parameters' => [],
            'expectedOutput' => '',
            'expectedError' => RuntimeException::class,
        ];
        yield 'Invalid url' => [
            'parameters' => [
                'url' => 'foo-bar',
            ],
            'expectedOutput' => '',
            'expectedError' => InvalidUrlException::class,
        ];
        yield 'Sitemap with single url' => [
            'parameters' => [
                'url' => 'https://gist.githubusercontent.com/schliesser/042fe0d0780bde3f8223a74f25fbb3f1/raw/sitemap-1.xml',
            ],
            'expectedOutput' => 'Completed successfully!',
            'expectedError' => '',
        ];
        yield 'Invalid list format' => [
            'parameters' => [
                'url' => 'https://gist.githubusercontent.com/schliesser/042fe0d0780bde3f8223a74f25fbb3f1/raw/sitemap-1.xml',
                '--list' => 'foo',
            ],
            'expectedOutput' => '',
            'expectedError' => InvalidFormatException::class,
        ];
        yield 'Sitemap with multiple urls as json' => [
            'parameters' => [
                'url' => 'https://gist.githubusercontent.com/schliesser/042fe0d0780bde3f8223a74f25fbb3f1/raw/sitemap-2.xml',
                '--list' => 'json',
            ],
            'expectedOutput' => '{"urls":["https:\/\/example.com\/","https:\/\/example.com\/page"],"sitemaps":[]}',
            'expectedError' => '',
        ];
        yield 'Sitemap with multiple urls as txt' => [
            'parameters' => [
                'url' => 'https://gist.githubusercontent.com/schliesser/042fe0d0780bde3f8223a74f25fbb3f1/raw/sitemap-2.xml',
                '--list' => 'txt',
            ],
            'expectedOutput' => '* https://example.com/page',
            'expectedError' => '',
        ];
        yield 'Sitemap with multiple urls' => [
            'parameters' => [
                'url' => 'https://gist.githubusercontent.com/schliesser/042fe0d0780bde3f8223a74f25fbb3f1/raw/sitemap-2.xml',
            ],
            'expectedOutput' => '[WARNING] Finished with some errors!',
            'expectedError' => '',
        ];
        yield 'Sitemap index with single sitemap' => [
            'parameters' => [
                'url' => 'https://gist.githubusercontent.com/schliesser/042fe0d0780bde3f8223a74f25fbb3f1/raw/sitemap-index-1.xml',
            ],
            'expectedOutput' => 'Completed successfully!',
            'expectedError' => '',
        ];
        yield 'Sitemap index with multiple sitemaps' => [
            'parameters' => [
                'url' => 'https://gist.githubusercontent.com/schliesser/042fe0d0780bde3f8223a74f25fbb3f1/raw/sitemap-index-2.xml',
                '--list' => 'json',
            ],
            'expectedOutput' => '{"urls":["https:\/\/example.com\/","https:\/\/example.com\/","https:\/\/example.com\/page"],"sitemaps":["https:\/\/gist.githubusercontent.com\/schliesser\/042fe0d0780bde3f8223a74f25fbb3f1\/raw\/sitemap-1.xml","https:\/\/gist.githubusercontent.com\/schliesser\/042fe0d0780bde3f8223a74f25fbb3f1\/raw\/sitemap-2.xml"]}',
            'expectedError' => '',
        ];
        // Check custom error codes
        yield 'Unable to fetch robots.txt' => [
            'parameters' => [
                'url' => 'https://example.com/robots.txt',
            ],
            'expectedOutput' => '1633234519166',
            'expectedError' => '',
        ];
        yield 'Unable to fetch robots.txt for domain' => [
            'parameters' => [
                'url' => 'https://example.com/',
            ],
            'expectedOutput' => '1633234519166',
            'expectedError' => '',
        ];
        yield 'Unable to fetch url' => [
            'parameters' => [
                'url' => 'https://gist.githubusercontent.com/schliesser/042fe0d0780bde3f8223a74f25fbb3f1/raw/sitemap-2.xml',
            ],
            'expectedOutput' => '1633234397666',
            'expectedError' => '',
        ];
        yield 'Unable to load xml from url' => [
            'parameters' => [
                'url' => 'https://example.com/sitemap.xml',
            ],
            'expectedOutput' => '1633234217716',
            'expectedError' => '',
        ];
        // Resolve robots.txt successfully
        //yield 'Read robots.txt' => [
        //    'parameters' => [
        //        // Todo: find a way to simulate the urls locally or place the fake robots.txt on a temp server
        //        'url' => 'https://gist.githubusercontent.com/schliesser/042fe0d0780bde3f8223a74f25fbb3f1/raw/robots.txt',
        //    ],
        //    'expectedOutput' => 'Completed successfully!',
        //    'expectedError' => '',
        //];
    }
}
