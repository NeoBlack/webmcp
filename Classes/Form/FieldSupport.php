<?php

declare(strict_types=1);

/*
 * This file is part of the package neoblack/webmcp.
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Neoblack\Webmcp\Form;

/**
 * How the WebMCP form bridge treats a single TYPO3 form element type. This is the
 * single source of truth behind the supported/unsupported field matrix in the
 * documentation: {@see forType()} classifies every standard EXT:form element, and
 * anything unknown is treated as {@see Unsupported} on purpose (fail safe rather
 * than guess). A form that contains any Unsupported field is refused as a whole.
 */
enum FieldSupport
{
    /** Agent-fillable input mapped into the tool's JSON schema. */
    case Fillable;

    /** Display-only element (no input); skipped. */
    case DisplayOnly;

    /** Container whose children are walked (page, fieldset, grid). */
    case Structural;

    /** Not shown to the agent; submitted with its configured default value. */
    case Hidden;

    /** Ignored entirely (e.g. the honeypot the runtime adds itself). */
    case Ignored;

    /** Cannot be offered to an agent; its presence refuses the whole form. */
    case Unsupported;

    /**
     * Classify an EXT:form element by its ``type``. Unknown/custom types are
     * deliberately {@see Unsupported}.
     */
    public static function forType(string $type): self
    {
        return match ($type) {
            'Text', 'Textarea', 'Password', 'AdvancedPassword',
            'Email', 'Telephone', 'Url', 'Number',
            'Date', 'DatePicker', 'Checkbox',
            'RadioButton', 'SingleSelect', 'MultiSelect', 'MultiCheckbox',
            'CountrySelect' => self::Fillable,
            'StaticText', 'ContentElement' => self::DisplayOnly,
            'Form', 'Page', 'Fieldset', 'GridRow', 'GridColumn' => self::Structural,
            'Hidden' => self::Hidden,
            'Honeypot' => self::Ignored,
            // FileUpload, ImageUpload, SummaryPage and everything unknown:
            default => self::Unsupported,
        };
    }
}
