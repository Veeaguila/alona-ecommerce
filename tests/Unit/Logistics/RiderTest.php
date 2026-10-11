<?php

namespace Tests\Unit\Logistics;

use App\Models\Rider;
use Carbon\Carbon;
use Tests\TestCase;

class RiderTest extends TestCase
{
    public function test_age_is_computed_from_birthday(): void
    {
        $rider = new Rider(['birthday' => Carbon::now()->subYears(25)->subDay()]);

        $this->assertSame(25, $rider->age);
    }

    public function test_age_is_null_without_a_birthday(): void
    {
        $rider = new Rider;

        $this->assertNull($rider->age);
    }
}
