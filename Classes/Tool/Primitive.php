<?php

declare(strict_types=1);

/*
 * This file is part of the package neoblack/webmcp.
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Neoblack\Webmcp\Tool;

/**
 * The execution behaviours the generic JavaScript runtime knows how to perform.
 * Every declarative tool maps to exactly one of these; anything that does not
 * fit uses a tool's own ES module instead (see Manifest::$moduleUrl).
 */
enum Primitive: string
{
    /** Navigate the browser to a URL chosen from a fixed set of options. */
    case Navigate = 'navigate';

    /** Fetch a same-origin JSON index, filter it client-side, return the hits. */
    case Search = 'search';

    /** Build a pre-filled mailto: link and return it (no server storage). */
    case Mailto = 'mailto';

    /** Return a curated, static list of items verbatim. */
    case StaticList = 'static';
}
