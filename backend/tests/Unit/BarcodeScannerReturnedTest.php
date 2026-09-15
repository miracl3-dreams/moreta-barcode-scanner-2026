<?php

namespace Tests\Unit;

use App\Services\BarcodeScannerService;
use Tests\TestCase;

class BarcodeScannerReturnedTest extends TestCase
{
    public function test_full_and_mt_returns_are_blocked(): void
    {
        $this->assertTrue(BarcodeScannerService::isReturnedMove('FULL'));
        $this->assertTrue(BarcodeScannerService::isReturnedMove('mt'));
        $this->assertTrue(BarcodeScannerService::isReturnedMove(' Full '));
        $this->assertFalse(BarcodeScannerService::isReturnedMove(''));
        $this->assertFalse(BarcodeScannerService::isReturnedMove(null));
    }
}
