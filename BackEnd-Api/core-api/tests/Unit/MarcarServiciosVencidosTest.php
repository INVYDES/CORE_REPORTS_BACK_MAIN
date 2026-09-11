<?php

namespace Tests\Unit;

use App\Jobs\MarcarServiciosVencidos;
use PHPUnit\Framework\TestCase;

class MarcarServiciosVencidosTest extends TestCase
{
    public function test_job_has_handle(): void
    {
        $job = new MarcarServiciosVencidos();
        $this->assertTrue(method_exists($job, 'handle'));
    }

    public function test_job_uses_queueable(): void
    {
        $job = new MarcarServiciosVencidos();
        $this->assertTrue(method_exists($job, 'handle'));
        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $job);
    }
}
