<?php

declare(strict_types=1);

namespace App\Concerns;

use DomainException;
use Throwable;

trait HandlesDomainExceptions
{
    public function exception(Throwable $e, callable $stopPropagation): void
    {
        if (! ($e instanceof DomainException)) {
            return;
        }

        $stopPropagation();

        $this->dispatch('domain-error', message: $e->getMessage());
    }
}
