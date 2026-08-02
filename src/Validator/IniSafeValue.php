<?php

declare(strict_types=1);

/*
 * This file is part of Pitlane.
 *
 * (c) Maxime Valin
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Validator;

use Attribute;
use Symfony\Component\Validator\Constraint;

/**
 * Rejects a free-text value that could inject structure once written into an Assetto Corsa INI file
 * ({@see \App\Service\AcConfigService} emits `KEY=value` lines into `server_cfg.ini` / `entry_list.ini`).
 *
 * A value carrying a newline plus `[SECTION]` or another `KEY=` would rewrite the generated config,
 * and path-shaped values (`/`, `\`, `..`) could escape the configured servers directory. This guard
 * refuses line breaks, `[`/`]`, path separators, `..`, and leading or trailing whitespace on the
 * fields that stay free text (server name, join and admin passwords). It is defense-in-depth: the
 * content selectors close their own values by membership instead.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class IniSafeValue extends Constraint
{
    public string $message = 'This value must not contain line breaks, "[", "]", "/", "\\", "..", or leading or trailing whitespace.';
}
