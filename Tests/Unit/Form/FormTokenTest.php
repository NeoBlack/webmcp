<?php

declare(strict_types=1);

/*
 * This file is part of the package neoblack/webmcp.
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Neoblack\Webmcp\Tests\Unit\Form;

use Neoblack\Webmcp\Form\FormToken;
use TYPO3\CMS\Core\Crypto\HashService;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class FormTokenTest extends UnitTestCase
{
    private FormToken $token;

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] = str_repeat('a', 96);
        $this->token = new FormToken(new HashService());
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['TYPO3_CONF_VARS']);
        parent::tearDown();
    }

    public function testSignedTokenVerifiesBackToIdentifier(): void
    {
        $signed = $this->token->sign('1:/form/contact.form.yaml');

        self::assertSame('1:/form/contact.form.yaml', $this->token->verify($signed));
    }

    public function testRejectsTamperedToken(): void
    {
        $signed = $this->token->sign('1:/form/contact.form.yaml');

        self::assertNull($this->token->verify($signed . 'x'));
        self::assertNull($this->token->verify('2:/form/evil.form.yaml' . substr($signed, -40)));
    }

    public function testRejectsEmptyToken(): void
    {
        self::assertNull($this->token->verify(''));
    }
}
