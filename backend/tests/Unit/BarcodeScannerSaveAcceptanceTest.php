<?php

namespace Tests\Unit;

use App\Repositories\BarcodeScannerRepository;
use App\Services\BarcodeScannerService;
use App\Services\EirFormService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class BarcodeScannerSaveAcceptanceTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_return_full_accepts_eir_with_barcode_save_datetime(): void
    {
        $this->assertSaveSetsAcceptance('return_full', 'FULL');
    }

    public function test_return_mt_accepts_eir_with_barcode_save_datetime(): void
    {
        $this->assertSaveSetsAcceptance('return_mt', 'MT', pickup: 'FULL');
    }

    public function test_pickup_does_not_set_acceptance_datetime(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-15 11:38:00', 'Asia/Manila'));
        $captured = null;
        $this->service($captured, pickup: 'MT')->save($this->payload('pickup_full'));

        $this->assertIsArray($captured);
        $this->assertSame('FULL', $captured['pickup']);
        $this->assertArrayNotHasKey('accpt_dte', $captured);
        $this->assertSame('Van out Empty', $captured['eirstatus']);
    }

    private function assertSaveSetsAcceptance(string $movetype, string $status, string $pickup = 'MT'): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-15 11:38:42', 'Asia/Manila'));
        $captured = null;
        $this->service($captured, pickup: $pickup)->save($this->payload($movetype));

        $this->assertIsArray($captured);
        $this->assertNull($captured['pickup']);
        $this->assertSame($status, $captured['return']);
        $this->assertSame('2026-09-15 11:38:42', $captured['accpt_dte']);
        $this->assertSame('Gate in Laden', $captured['eirstatus']);
    }

    /**
     * @param  array<string, mixed>|null  $captured
     */
    private function service(?array &$captured, string $pickup): BarcodeScannerService
    {
        $header = (object) [
            'docnum' => 'EIR-0000000000005930',
            'pickup' => $pickup,
            'return' => '',
            'dstcde' => 'BAC',
            'vannum' => 'MSLU2071013',
            'voynum' => '',
            'eirstatus' => '',
            'cuscde' => '',
            'concde' => '',
            'origin' => '',
        ];

        $repo = Mockery::mock(BarcodeScannerRepository::class);
        $repo->shouldReceive('findHeaderByDocnum')->andReturn($header);
        $repo->shouldReceive('destinations')->andReturn([
            ['dstcde' => 'BAC', 'dstdsc' => 'BACOLOD CITY'],
        ]);
        $repo->shouldReceive('voyageIsActive')->andReturn(false);
        $repo->shouldReceive('updateHeader')->once()->andReturnUsing(function (string $docnum, array $attributes) use (&$captured) {
            $this->assertSame('EIR-0000000000005930', $docnum);
            $captured = $attributes;
        });
        $repo->shouldReceive('replaceSketch')->once();
        $repo->shouldReceive('updateVanLastloc')->once();

        DB::shouldReceive('transaction')->once()->andReturnUsing(fn ($callback) => $callback());

        return new BarcodeScannerService($repo);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $movetype): array
    {
        $sketch = [];
        foreach (array_keys(EirFormService::SKETCH_PARTS) as $key) {
            $sketch[$key] = ['ok' => true];
        }

        return [
            'code' => 'EIR-0000000000005930',
            'movetype' => $movetype,
            'dstcde' => 'BAC',
            'voynum' => '',
            'sketch' => $sketch,
        ];
    }
}
