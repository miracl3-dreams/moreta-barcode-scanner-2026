<?php

namespace Tests\Unit;

use App\Repositories\BarcodeScannerRepository;
use App\Repositories\EirFormRepository;
use App\Support\LegacySchema;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class BarcodeScannerUpdateHeaderTest extends TestCase
{
    protected function tearDown(): void
    {
        LegacySchema::forgetColumn('eirtranfile1', 'accpt_dte');
        parent::tearDown();
    }

    public function test_return_save_writes_acceptance_even_when_schema_hides_the_column(): void
    {
        LegacySchema::rememberColumn('eirtranfile1', 'accpt_dte', false);

        $builder = Mockery::mock();
        $builder->shouldReceive('where')->once()->with('docnum', 'EIR-0000000000005930')->andReturnSelf();
        $builder->shouldReceive('update')->once()->with(Mockery::on(function (array $payload) {
            $this->assertSame('FULL', $payload['return']);
            $this->assertSame('2026-09-15 11:38:42', $payload['accpt_dte']);
            $this->assertSame('Gate in Laden', $payload['eirstatus']);

            return true;
        }))->andReturn(1);

        DB::shouldReceive('table')->once()->with('eirtranfile1')->andReturn($builder);

        $repo = new BarcodeScannerRepository(Mockery::mock(EirFormRepository::class));
        $repo->updateHeader('EIR-0000000000005930', [
            'pickup' => null,
            'return' => 'FULL',
            'accpt_dte' => '2026-09-15 11:38:42',
            'eirstatus' => 'Gate in Laden',
        ]);
    }
}
