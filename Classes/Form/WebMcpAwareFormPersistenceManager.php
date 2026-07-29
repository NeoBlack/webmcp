<?php

declare(strict_types=1);

/*
 * This file is part of the package neoblack/webmcp.
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Neoblack\Webmcp\Form;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Form\Domain\DTO\SearchCriteria;
use TYPO3\CMS\Form\Domain\ValueObject\FormIdentifier;
use TYPO3\CMS\Form\Mvc\Persistence\Exception\PersistenceManagerException;
use TYPO3\CMS\Form\Mvc\Persistence\FormPersistenceManagerInterface;

/**
 * Decorates the form persistence manager to enforce the strict WebMCP support
 * policy at save time. Saving a form that is enabled for WebMCP but contains
 * fields the bridge cannot handle is refused.
 *
 * The refusal is a {@see PersistenceManagerException}, thrown from ``save()`` —
 * which the form editor's save action already catches and turns into a clean
 * error message shown to the editor (``showSaveErrorMessage``). This is the one
 * spot in the whole save flow that surfaces a message, which is why the check
 * lives here rather than in a ``BeforeFormIsSaved`` listener (that runs before the
 * editor's guarded save and could only throw an uncaught, generic error).
 *
 * Every other method delegates verbatim, so the frontend load path is untouched.
 */
final class WebMcpAwareFormPersistenceManager implements FormPersistenceManagerInterface
{
    public function __construct(
        private readonly FormPersistenceManagerInterface $inner,
        private readonly FormSchemaBuilder $schemaBuilder,
    ) {
    }

    /**
     * @param array<string, mixed> $formDefinition
     * @param array<string, mixed> $formSettings
     */
    public function save(string $persistenceIdentifier, array $formDefinition, array $formSettings, ?string $storageLocation = null): FormIdentifier
    {
        $this->assertOfferable($formDefinition);

        return $this->inner->save($persistenceIdentifier, $formDefinition, $formSettings, $storageLocation);
    }

    /**
     * @param array<string, mixed> $formDefinition
     */
    private function assertOfferable(array $formDefinition): void
    {
        $options = $formDefinition['renderingOptions']['webmcp'] ?? null;
        if (!is_array($options) || true !== ($options['enable'] ?? false)) {
            return;
        }

        $result = $this->schemaBuilder->build($formDefinition);
        if ($result->supported) {
            return;
        }

        $fields = implode(', ', array_map(
            static fn (array $field): string => $field['identifier'] . ' (' . $field['type'] . ')',
            $result->unsupported,
        ));

        throw new PersistenceManagerException('This form is enabled for WebMCP but contains field(s) it cannot handle: ' . $fields . '. Remove them or turn off WebMCP for this form before saving.', 1721400001);
    }

    /**
     * @param array<string, mixed>|null $typoScriptSettings
     *
     * @return array<string, mixed>
     */
    public function load(string $persistenceIdentifier, ?array $typoScriptSettings = null, ?ServerRequestInterface $request = null): array
    {
        return $this->inner->load($persistenceIdentifier, $typoScriptSettings, $request);
    }

    /**
     * @param array<string, mixed> $formSettings
     */
    public function delete(string $persistenceIdentifier, array $formSettings): void
    {
        $this->inner->delete($persistenceIdentifier, $formSettings);
    }

    /**
     * @param array<string, mixed> $formSettings
     *
     * @return array<int|string, mixed>
     */
    public function listForms(array $formSettings, SearchCriteria $searchCriteria): array
    {
        return $this->inner->listForms($formSettings, $searchCriteria);
    }

    /**
     * @param array<string, mixed> $formSettings
     */
    public function hasForms(array $formSettings): bool
    {
        return $this->inner->hasForms($formSettings);
    }

    public function getUniquePersistenceIdentifier(string $storage, string $formIdentifier, ?string $savePath): string
    {
        return $this->inner->getUniquePersistenceIdentifier($storage, $formIdentifier, $savePath);
    }

    public function getUniqueIdentifier(string $identifier): string
    {
        return $this->inner->getUniqueIdentifier($identifier);
    }

    public function isAllowedStorageLocation(string $storageLocation): bool
    {
        return $this->inner->isAllowedStorageLocation($storageLocation);
    }

    public function isAllowedPersistenceIdentifier(string $persistenceIdentifier): bool
    {
        return $this->inner->isAllowedPersistenceIdentifier($persistenceIdentifier);
    }

    public function hasValidFileExtension(string $fileName): bool
    {
        return $this->inner->hasValidFileExtension($fileName);
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getAccessibleStorageAdapters(): array
    {
        return $this->inner->getAccessibleStorageAdapters();
    }
}
