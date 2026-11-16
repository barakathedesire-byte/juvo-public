<?php

namespace App\Services\Rules;

use RuntimeException;

/**
 * Thrown when a rule configured with failure behaviour `block` resolves to an
 * invalid value. Consumers must treat this as a hard stop rather than falling
 * back to a default.
 */
class RuleBlockedException extends RuntimeException {}
