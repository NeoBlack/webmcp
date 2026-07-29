<?php

declare(strict_types=1);

/*
 * This file is part of the package neoblack/webmcp.
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Neoblack\Webmcp\Tests\Unit\Form;

use Neoblack\Webmcp\Form\FieldSupport;
use PHPUnit\Framework\Attributes\DataProvider;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class FieldSupportTest extends UnitTestCase
{
    /**
     * @return iterable<string, array{string, FieldSupport}>
     */
    public static function typeProvider(): iterable
    {
        yield 'Text is fillable' => ['Text', FieldSupport::Fillable];
        yield 'Email is fillable' => ['Email', FieldSupport::Fillable];
        yield 'SingleSelect is fillable' => ['SingleSelect', FieldSupport::Fillable];
        yield 'CountrySelect is fillable' => ['CountrySelect', FieldSupport::Fillable];
        yield 'StaticText is display-only' => ['StaticText', FieldSupport::DisplayOnly];
        yield 'ContentElement is display-only' => ['ContentElement', FieldSupport::DisplayOnly];
        yield 'Page is structural' => ['Page', FieldSupport::Structural];
        yield 'GridRow is structural' => ['GridRow', FieldSupport::Structural];
        yield 'Hidden is hidden' => ['Hidden', FieldSupport::Hidden];
        yield 'Honeypot is ignored' => ['Honeypot', FieldSupport::Ignored];
        yield 'FileUpload is unsupported' => ['FileUpload', FieldSupport::Unsupported];
        yield 'ImageUpload is unsupported' => ['ImageUpload', FieldSupport::Unsupported];
        yield 'SummaryPage is unsupported' => ['SummaryPage', FieldSupport::Unsupported];
        yield 'unknown custom type is unsupported' => ['MyCustomElement', FieldSupport::Unsupported];
    }

    #[DataProvider('typeProvider')]
    public function testForTypeClassifiesElements(string $type, FieldSupport $expected): void
    {
        self::assertSame($expected, FieldSupport::forType($type));
    }
}
