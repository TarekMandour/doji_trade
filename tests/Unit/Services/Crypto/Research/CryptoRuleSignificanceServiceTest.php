<?php

namespace Tests\\Unit\\Services\\Crypto\\Research;

use App\\Services\\Crypto\\Research\\CryptoRuleSignificanceService;
use InvalidArgumentException;
use PHPUnit\\Framework\\TestCase;

class CryptoRuleSignificanceServiceTest extends TestCase
{
    public function test_it_reports_insufficient_signal_observations(): void
    {
        $service = new CryptoRuleSignificanceService();

        $result = $service->evaluate([
            ['signal' => true, 'forward_return' => 0.01, 'benchmark_return' => 0.002],
            ['signal' => false, 'forward_return' => -0.01, 'benchmark_return' => -0.002],
        ]);

        $this->assertSame('insufficient_data', $result['status']);
        $this->assertSame(1, $result['signal_count']);
    }

    public function test_it_returns_statistics_for_signal_observations(): void
    {
        $service = new CryptoRuleSignificanceService();
        $rows = [];

        for ($i = 0; $i < 30; $i++) {
            $rows[] = [
                'signal' => $i % 2 === 0,
                'forward_return' => $i % 2 === 0 ? 0.012 : -0.003,
                'benchmark_return' => 0.001,
            ];
        }

        $result = $service->evaluate($rows, 100, 3);

        $this->assertSame('evaluated', $result['status']);
        $this->assertSame(15, $result['signal_count']);
        $this->assertArrayHasKey('p_value_two_sided', $result);
        $this->assertGreaterThanOrEqual(0, $result['p_value_two_sided']);
        $this->assertLessThanOrEqual(1, $result['p_value_two_sided']);
    }

    public function test_it_rejects_invalid_observations(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new CryptoRuleSignificanceService())->evaluate([
            ['signal' => true, 'forward_return' => 'not-a-number', 'benchmark_return' => 0.0],
        ]);
    }
}
