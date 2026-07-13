<?php

declare(strict_types=1);

namespace Hug\EuLabel\Tests\Unit\Service;

use Hug\EuLabel\Service\GaranData;
use Hug\EuLabel\Service\GaranSummaryPdfGenerator;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class GaranSummaryPdfGeneratorTest extends TestCase
{
    public function testGeneratesPdfForItems(): void
    {
        $loader = new FilesystemLoader();
        $loader->addPath(\dirname(__DIR__, 3) . '/src/Resources/views', 'HugEuLabel');

        $generator = new GaranSummaryPdfGenerator(new Environment($loader));

        $pdf = $generator->generate([
            [
                'label' => 'Massivzaun Giardino',
                'productNumber' => 'b12717',
                'data' => new GaranData(5, 'https://example.com/garantie', 'Holzwerk GmbH'),
            ],
        ]);

        self::assertNotNull($pdf);
        self::assertStringStartsWith('%PDF', $pdf);
    }

    public function testBrokenTemplateReturnsNullAndLogs(): void
    {
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        $generator = new GaranSummaryPdfGenerator(new Environment(new FilesystemLoader()), $logger);

        self::assertNull($generator->generate([]));
    }
}
