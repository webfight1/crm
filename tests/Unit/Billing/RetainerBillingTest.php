<?php

namespace Tests\Unit\Billing;

use App\Billing\RetainerBilling;
use App\Models\Deal;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RetainerBillingTest extends TestCase
{
    private function deal(array $attrs = []): Deal
    {
        $deal = new Deal();
        $deal->forceFill(array_merge([
            'id' => 64, 'revenue_model' => 'retainer', 'retainer_amount' => 300,
            'retainer_start' => '2026-10-01', 'retainer_months' => 6, 'retainer_day' => 5,
        ], $attrs));

        return $deal;
    }

    public function test_month_of_the_period_and_next_reminder(): void
    {
        $s = RetainerBilling::status($this->deal(), Carbon::parse('2026-12-03'));

        $this->assertSame(3, $s['month']);
        $this->assertSame('2026-12', $s['period']);
        $this->assertSame('2026-12-05', $s['next']->toDateString());
        $this->assertFalse($s['ended']);

        // Past this month's day → next month's.
        $this->assertSame('2027-01-05', RetainerBilling::status($this->deal(), Carbon::parse('2026-12-06'))['next']->toDateString());
    }

    public function test_period_end(): void
    {
        $last = RetainerBilling::status($this->deal(), Carbon::parse('2027-03-10'));
        $this->assertSame(6, $last['month']);
        $this->assertNull($last['next']);
        $this->assertFalse($last['ended']);

        $this->assertTrue(RetainerBilling::status($this->deal(), Carbon::parse('2027-04-01'))['ended']);
        $this->assertFalse(RetainerBilling::status($this->deal(['retainer_months' => null]), Carbon::parse('2030-01-01'))['ended']);
    }

    public function test_day_31_is_the_last_day_of_a_short_month(): void
    {
        $this->assertSame('2027-02-28', RetainerBilling::invoiceDay($this->deal(['retainer_day' => 31]), Carbon::parse('2027-02-10'))->toDateString());
    }

    public function test_not_a_monthly_deal(): void
    {
        $this->assertNull(RetainerBilling::status($this->deal(['revenue_model' => 'fixed_project'])));
        $this->assertNull(RetainerBilling::status($this->deal(['retainer_amount' => null])));
        $this->assertSame(0, RetainerBilling::status($this->deal(), Carbon::parse('2026-09-20'))['month']);
    }

    public function test_rmp_link(): void
    {
        $this->assertStringEndsWith('/invoices/from-crm?deal=64&month=2026-10', RetainerBilling::rmpUrl($this->deal(), '2026-10'));
    }
}
