<?php

namespace Tests\Feature\User;

use App\Http\Controllers\V1\User\StatController;
use App\Models\Plan;
use App\Models\StatUser;
use App\Models\TrafficResetLog;
use App\Models\User;
use App\Utils\Helper;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\Request;

class TrafficLogTest extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', 'base64:' . base64_encode(str_repeat('a', 32)));
        config()->set('app.timezone', 'Asia/Shanghai');
        Carbon::setTestNow(Carbon::create(2026, 8, 14, 12, 0, 0, 'Asia/Shanghai'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_returns_only_daily_records_from_the_last_reset_day(): void
    {
        $user = $this->createUser([
            'last_reset_at' => $this->timestamp('2026-08-10 15:00:00'),
            'next_reset_at' => $this->timestamp('2026-09-10 15:00:00'),
        ]);

        $this->createStat($user, '2026-08-09', 'd');
        $resetDay = $this->createStat($user, '2026-08-10', 'd');
        $currentDay = $this->createStat($user, '2026-08-11', 'd');
        $this->createStat($user, '2026-08-11', 'm');

        $this->assertSame(
            [$currentDay->record_at, $resetDay->record_at],
            $this->trafficRecordDates($user)
        );
    }

    public function test_it_infers_the_cycle_from_the_next_monthly_reset(): void
    {
        $user = $this->createUser([
            'created_at' => $this->timestamp('2026-06-01 00:00:00'),
            'next_reset_at' => $this->timestamp('2026-08-20 00:00:00'),
        ]);

        $this->createStat($user, '2026-07-19', 'd');
        $cycleStart = $this->createStat($user, '2026-07-20', 'd');

        $this->assertSame([$cycleStart->record_at], $this->trafficRecordDates($user));
    }

    public function test_it_uses_the_latest_reset_log_when_user_reset_fields_are_missing(): void
    {
        $user = $this->createUser([
            'created_at' => $this->timestamp('2026-06-01 00:00:00'),
            'next_reset_at' => null,
            'last_reset_at' => null,
        ]);

        TrafficResetLog::query()->create([
            'user_id' => $user->id,
            'reset_type' => TrafficResetLog::TYPE_MANUAL,
            'reset_time' => Carbon::create(2026, 8, 5, 16, 0, 0, 'Asia/Shanghai'),
            'old_upload' => 100,
            'old_download' => 200,
            'old_total' => 300,
            'new_upload' => 0,
            'new_download' => 0,
            'new_total' => 0,
            'trigger_source' => TrafficResetLog::SOURCE_MANUAL,
        ]);

        $this->createStat($user, '2026-08-04', 'd');
        $resetDay = $this->createStat($user, '2026-08-05', 'd');

        $this->assertSame([$resetDay->record_at], $this->trafficRecordDates($user));
    }

    private function createUser(array $overrides = []): User
    {
        $now = $this->timestamp('2026-06-01 00:00:00');
        $plan = Plan::query()->create([
            'group_id' => 1,
            'transfer_enable' => 100,
            'name' => 'Monthly plan',
            'reset_traffic_method' => Plan::RESET_TRAFFIC_MONTHLY,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $user = User::query()->create(array_merge([
            'email' => Helper::guid(true) . '@example.com',
            'password' => password_hash('password', PASSWORD_DEFAULT),
            'uuid' => Helper::guid(true),
            'token' => Helper::guid(true),
            'plan_id' => $plan->id,
            'group_id' => 1,
            'expired_at' => $this->timestamp('2027-01-01 00:00:00'),
            'created_at' => $now,
            'updated_at' => $now,
        ], $overrides));

        // UserObserver calculates next_reset_at on create. Restore explicit
        // timestamps so each test controls the cycle boundary it exercises.
        $user->forceFill($overrides)->saveQuietly();

        return $user->refresh();
    }

    private function createStat(User $user, string $date, string $recordType): StatUser
    {
        return StatUser::query()->create([
            'user_id' => $user->id,
            'server_rate' => 1,
            'u' => 100,
            'd' => 200,
            'record_type' => $recordType,
            'record_at' => $this->timestamp("{$date} 00:00:00"),
            'created_at' => Carbon::now()->timestamp,
            'updated_at' => Carbon::now()->timestamp,
        ]);
    }

    private function trafficRecordDates(User $user): array
    {
        $request = Request::create('/api/v1/user/stat/getTrafficLog');
        $request->setUserResolver(fn () => $user);

        $response = app(StatController::class)->getTrafficLog($request);

        return array_column($response->getData(true)['data'], 'record_at');
    }

    private function timestamp(string $date): int
    {
        return Carbon::parse($date, 'Asia/Shanghai')->timestamp;
    }
}
