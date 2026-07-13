<?php

declare(strict_types=1);

use Baspa\FilamentCanary\Testing\InteractsWithCanary;

uses(InteractsWithCanary::class);

it('sweeps every Filament page for administrators and guests', function (): void {
    $this->canarySweep();
});
