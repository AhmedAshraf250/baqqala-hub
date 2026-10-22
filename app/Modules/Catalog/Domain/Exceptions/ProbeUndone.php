<?php

namespace App\Modules\Catalog\Domain\Exceptions;

use RuntimeException;

/**
 * Thrown on purpose to undo a savepoint that only asked the database a
 * question — would it let this row go? — and must change nothing.
 */
final class ProbeUndone extends RuntimeException {}
