<?php

use Livewire\Component;

new class extends Component {
    public int $presses = 0;

    public function press(): void
    {
        $this->presses++;
    }
}; ?>

<div>Pressed {{ $presses }} times</div>
