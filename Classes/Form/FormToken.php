<?php

declare(strict_types=1);

/*
 * This file is part of the package neoblack/webmcp.
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Neoblack\Webmcp\Form;

use TYPO3\CMS\Core\Crypto\HashService;
use TYPO3\CMS\Core\Exception\Crypto\InvalidHashStringException;

/**
 * Signs and verifies the form-submission token that ties a form tool to a single
 * form. The persistence identifier is minted into an HMAC-signed token when the
 * tool is registered and verified again at the submit endpoint, so an agent can
 * neither forge a token nor retarget the endpoint at a different (or non-opted-in)
 * form. The identifier itself is not secret — the HMAC only guarantees integrity.
 */
final class FormToken
{
    private const SECRET = 'neoblack_webmcp/form-submit';

    public function __construct(
        private readonly HashService $hashService,
    ) {
    }

    public function sign(string $persistenceIdentifier): string
    {
        return $this->hashService->appendHmac($persistenceIdentifier, self::SECRET);
    }

    /**
     * The persistence identifier carried by a valid token, or null if the token is
     * missing or tampered with.
     */
    public function verify(string $token): ?string
    {
        if ('' === $token) {
            return null;
        }
        try {
            return $this->hashService->validateAndStripHmac($token, self::SECRET);
        } catch (InvalidHashStringException) {
            return null;
        }
    }
}
