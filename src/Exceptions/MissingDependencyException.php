<?php

namespace HasanAlyazidi\DataTables\Exceptions;

use RuntimeException;

/**
 * Thrown when a table or a request explicitly asks for an exporter whose
 * underlying composer package is not installed. Exporters that are merely
 * listed in the shared config are skipped silently instead — this exception
 * only fires on explicit intent, so the message can name the fix.
 */
class MissingDependencyException extends RuntimeException
{
    public static function forExporter(string $exporter, string $package): self
    {
        return new self(
            $exporter.' is configured for this table but its dependency is not installed. Run: composer require '.$package
        );
    }
}
