<?php

namespace Tests\Unit\Services;

use App\Models\Plan;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_trial_plan_applies_its_device_limit_to_a_new_user(): void
    {
        $plan = $this->makePlan([
            'group_id' => 7,
            'transfer_enable' => 5,
            'speed_limit' => 100,
            'device_limit' => 3,
        ]);
        admin_setting(['try_out_plan_id' => $plan->id, 'try_out_hour' => 24]);

        $user = $this->createUser(['email' => 'trial@example.test', 'password' => 'secret']);

        $this->assertSame($plan->id, $user->plan_id);
        $this->assertSame(7, $user->group_id);
        $this->assertSame(5 * 1073741824, (int) $user->transfer_enable);
        $this->assertSame(100, (int) $user->speed_limit);
        $this->assertSame(3, (int) $user->device_limit);
        $this->assertSame(3, (int) $user->fresh()->device_limit);
        $this->assertEqualsWithDelta(time() + 24 * 3600, $user->expired_at, 5);
    }

    public function test_trial_plan_without_a_device_limit_keeps_the_user_unlimited(): void
    {
        $plan = $this->makePlan(['device_limit' => null]);
        admin_setting(['try_out_plan_id' => $plan->id]);

        $user = $this->createUser(['email' => 'unlimited@example.test']);

        $this->assertSame($plan->id, $user->plan_id);
        $this->assertNull($user->fresh()->device_limit);
    }

    public function test_explicit_plan_applies_its_device_limit_to_a_new_user(): void
    {
        $plan = $this->makePlan(['device_limit' => 2, 'speed_limit' => 50]);
        admin_setting(['try_out_plan_id' => 0]);

        $user = $this->createUser([
            'email' => 'generated@example.test',
            'plan_id' => $plan->id,
            'expired_at' => time() + 86400,
        ]);

        $this->assertSame($plan->id, $user->plan_id);
        $this->assertSame(50, (int) $user->speed_limit);
        $this->assertSame(2, (int) $user->fresh()->device_limit);
    }

    public function test_no_trial_plan_leaves_plan_and_device_limit_unset(): void
    {
        admin_setting(['try_out_plan_id' => 0]);

        $user = $this->createUser(['email' => 'plain@example.test']);

        $this->assertNull($user->plan_id);
        $this->assertNull($user->fresh()->device_limit);
    }

    private function createUser(array $data): User
    {
        $user = app(UserService::class)->createUser($data);
        $this->assertTrue($user->save());

        return $user;
    }

    private function makePlan(array $overrides = []): Plan
    {
        return Plan::create(array_merge([
            'group_id' => null,
            'transfer_enable' => 10,
            'name' => 'Trial plan',
            'speed_limit' => null,
            'show' => 1,
            'sort' => 0,
            'renew' => 1,
            'prices' => [Plan::PERIOD_MONTHLY => 11],
            'reset_traffic_method' => Plan::RESET_TRAFFIC_MONTHLY,
            'capacity_limit' => null,
            'sell' => 1,
            'device_limit' => null,
            'created_at' => time(),
            'updated_at' => time(),
        ], $overrides));
    }
}
