<?php

namespace WireUi\WireUi;

use WireUi\Support\ComponentPack;

class Shadow extends ComponentPack
{
    protected function default(): string
    {
        return config('wireui.style.shadow') ?? 'base';
    }

    public function all(): array
    {
        return [
            'none'  => 'shadow-none',
            'sm'    => 'shadow-sm',
            'base'  => 'shadow',
            'md'    => 'shadow-md',
            'lg'    => 'shadow-lg',
            'xl'    => 'shadow-xl',
            '2xl'   => 'shadow-2xl',
            'inner' => 'shadow-inner',
        ];
    }
}
