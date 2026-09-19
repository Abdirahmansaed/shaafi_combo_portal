<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ActiveSubscriber;
use App\Services\ComboPurchaseQuery;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.mysql_portal', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        Schema::connection('mysql_portal')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('firstName')->nullable();
            $table->string('last_name')->nullable();
            $table->string('number', 20)->nullable();
            $table->string('password');
            $table->string('status');
            $table->string('role')->default('AGENT');
            $table->string('username')->unique();
            $table->rememberToken();
        });

        Schema::connection('mysql_portal')->create('active_subscribers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_purchase_id')->unique();
            $table->string('subscriber_number', 20);
            $table->string('package_tier');
            $table->dateTime('purchase_date');
            $table->dateTime('expire_date');
            $table->string('action_status')->default('PENDING');
            $table->unsignedBigInteger('done_by')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::connection('mysql_portal')->create('subscriber_actions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_id')->unique();
            $table->string('msisdn', 20);
            $table->string('agent_status')->default('PENDING');
            $table->unsignedBigInteger('done_by')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
        });

        config()->set('database.connections.mysql_business', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        Schema::connection('mysql_business')->create(ComboPurchaseQuery::TABLE, function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('msisdn');
            $table->string('product_id');
            $table->dateTime('purchase_date');
            $table->dateTime('expiry_date')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->unsignedInteger('amount_mb')->default(0);
        });
    }

    public function test_active_user_can_log_in_and_log_out(): void
    {
        $user = $this->portalUser('ACTIVE');

        $this->post(route('login.store'), ['username' => $user->username, 'password' => 'correct-password', 'remember' => true])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_init_and_deleted_users_cannot_log_in(): void
    {
        foreach (['INIT', 'DELETED'] as $status) {
            $user = $this->portalUser($status);
            $this->from(route('login'))->post(route('login.store'), ['username' => $user->username, 'password' => 'correct-password'])
                ->assertRedirect(route('login'))
                ->assertSessionHasErrors('username');
            $this->assertGuest();
        }
    }

    public function test_incorrect_password_is_rejected(): void
    {
        $user = $this->portalUser('ACTIVE');

        $this->from(route('login'))->post(route('login.store'), ['username' => $user->username, 'password' => 'wrong-password'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_portal_pages_require_authentication(): void
    {
        foreach (['dashboard', 'combo-purchases.index', 'subscribers.index', 'active-subscribers.index', 'reports.index', 'settings.index'] as $route) {
            $this->get(route($route))->assertRedirect(route('login'));
        }
    }

    public function test_users_are_read_from_portal_connection_and_name_has_a_safe_fallback(): void
    {
        $user = new User(['username' => 'portal-user']);

        $this->assertSame('mysql_portal', $user->getConnectionName());
        $this->assertSame('portal-user', $user->displayName());
    }

    public function test_superadmin_can_manage_agents_and_agents_cannot_access_settings(): void
    {
        $superadmin = $this->portalUser('ACTIVE', 'SUPERADMIN');
        $agent = $this->portalUser('ACTIVE');

        $this->actingAs($superadmin)->get(route('settings.index'))->assertOk()->assertSee('Create Agent User');
        $this->actingAs($superadmin)->post(route('settings.users.store'), [
            'firstName' => 'New', 'last_name' => 'Agent', 'number' => '252630000001',
            'username' => 'new-agent', 'password' => 'agent-password',
            'password_confirmation' => 'agent-password', 'status' => 'ACTIVE',
        ])->assertRedirect(route('settings.index'));

        $created = User::where('username', 'new-agent')->firstOrFail();
        $this->assertSame('AGENT', $created->role);
        $this->assertTrue(Hash::check('agent-password', $created->password));

        $this->actingAs($agent)->get(route('settings.index'))->assertForbidden();
        $this->actingAs($agent)->post(route('settings.users.store'), [])->assertForbidden();
        foreach (['dashboard', 'combo-purchases.index', 'subscribers.index'] as $route) {
            $this->actingAs($agent)->get(route($route))->assertOk();
        }
        $this->actingAs($agent)->get(route('reports.index'))->assertForbidden();
        $this->actingAs($agent)->get(route('reports.agent-performance.export-pdf'))->assertForbidden();
    }

    public function test_business_purchase_queries_use_the_business_connection(): void
    {
        $query = app(ComboPurchaseQuery::class)->base();

        $this->assertSame('mysql_business', $query->getConnection()->getName());
    }

    public function test_latest_valid_purchase_ignores_null_expiry_and_uses_full_expiry_datetime(): void
    {
        Carbon::setTestNow('2026-09-15 12:05:56');
        try {
            $database = \Illuminate\Support\Facades\DB::connection('mysql_business')->table(ComboPurchaseQuery::TABLE);
            $database->insert([
                ['id' => 36747801, 'msisdn' => '+252633025078', 'product_id' => '40720', 'purchase_date' => '2026-09-14 10:30:11', 'expiry_date' => '2026-09-15 10:30:11', 'price' => 1, 'amount_mb' => 1],
                ['id' => 36739974, 'msisdn' => '+252633025078', 'product_id' => '40720', 'purchase_date' => '2026-07-31 23:25:47', 'expiry_date' => null, 'price' => 1, 'amount_mb' => 1],
                ['id' => 36720000, 'msisdn' => '+252633025078', 'product_id' => '40721', 'purchase_date' => '2026-09-10 10:30:11', 'expiry_date' => '2026-09-17 10:30:11', 'price' => 1, 'amount_mb' => 1],
            ]);

            $purchase = app(ComboPurchaseQuery::class)->latestValidPurchaseForMsisdn('+252633025078');

            $this->assertSame(36747801, (int) $purchase->id);
            $this->assertSame('2026-09-15 10:30:11', $purchase->expiry_date);
            $this->assertSame('Expired', $purchase->status);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_dashboard_live_stats_are_fresh_and_use_each_subscribers_latest_valid_purchase(): void
    {
        Carbon::setTestNow('2026-09-15 12:00:00');
        try {
            $agent = $this->portalUser('ACTIVE');
            $business = \Illuminate\Support\Facades\DB::connection('mysql_business')->table(ComboPurchaseQuery::TABLE);
            $business->insert([
                ['id' => 1, 'msisdn' => '252630000001', 'product_id' => '40720', 'purchase_date' => '2026-09-14 10:00:00', 'expiry_date' => '2026-09-16 10:00:00', 'price' => 1, 'amount_mb' => 100],
                ['id' => 2, 'msisdn' => '252630000001', 'product_id' => '40721', 'purchase_date' => '2026-09-15 11:00:00', 'expiry_date' => '2026-09-15 11:30:00', 'price' => 2, 'amount_mb' => 200],
                ['id' => 3, 'msisdn' => '252630000002', 'product_id' => '40722', 'purchase_date' => '2026-09-15 10:00:00', 'expiry_date' => '2026-09-16 10:00:00', 'price' => 3, 'amount_mb' => 300],
                ['id' => 4, 'msisdn' => '252630000003', 'product_id' => '40720', 'purchase_date' => '2026-09-15 10:00:00', 'expiry_date' => null, 'price' => 1, 'amount_mb' => 100],
            ]);

            $response = $this->actingAs($agent)->getJson(route('dashboard.live'));
            $response->assertOk()
                ->assertHeader('Cache-Control')
                ->assertJsonPath('stats.Total Subscribers', 2)
                ->assertJsonPath('stats.Active Subscribers', 1)
                ->assertJsonPath('stats.Expired Subscribers', 1)
                ->assertJsonPath('stats.Total Combo Purchases', 3)
                ->assertJsonPath('stats.Daily Purchases', 1)
                ->assertJsonPath('stats.Weekly Purchases', 1)
                ->assertJsonPath('stats.Monthly Purchases', 1)
                ->assertJsonPath("stats.Today's Purchases", 2);

            $business->insert(['id' => 5, 'msisdn' => '252630000004', 'product_id' => '40720', 'purchase_date' => '2026-09-15 12:00:00', 'expiry_date' => '2026-09-16 12:00:00', 'price' => 1, 'amount_mb' => 100]);
            $this->actingAs($agent)->getJson(route('dashboard.live'))
                ->assertJsonPath('stats.Total Combo Purchases', 4)
                ->assertJsonPath('stats.Active Subscribers', 2);

            $this->actingAs($agent)->getJson(route('dashboard.live', ['from' => '2026-09-14', 'to' => '2026-09-14']))
                ->assertJsonPath('stats.Total Combo Purchases', 1)
                ->assertJsonPath('stats.Total Subscribers', 1)
                ->assertJsonPath('stats.Active Subscribers', 1)
                ->assertJsonPath('stats.Expired Subscribers', 0)
                ->assertJsonPath("stats.Today's Purchases", 1)
                ->assertJsonCount(1, 'recent_purchases')
                ->assertJsonPath('recent_purchases.0.id', 1);

            $this->actingAs($agent)->getJson(route('dashboard.live', ['from' => '2026-09-14', 'to' => '2026-09-15']))
                ->assertJsonPath('stats.Total Combo Purchases', 4)
                ->assertJsonPath('stats.Total Subscribers', 3)
                ->assertJsonPath('stats.Active Subscribers', 2)
                ->assertJsonPath('stats.Expired Subscribers', 1)
                ->assertJsonPath('stats.Daily Purchases', 2)
                ->assertJsonPath('stats.Weekly Purchases', 1)
                ->assertJsonPath('stats.Monthly Purchases', 1);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_authenticated_agent_completion_is_written_only_to_portal_subscriber_actions(): void
    {
        $agent = $this->portalUser('ACTIVE');
        \Illuminate\Support\Facades\DB::connection('mysql_business')->table(ComboPurchaseQuery::TABLE)->insert([
            'id' => 900001, 'msisdn' => '+252633025078', 'product_id' => '40720',
            'purchase_date' => '2026-09-14 10:30:11', 'expiry_date' => '2026-09-15 10:30:11',
            'price' => 1, 'amount_mb' => 1,
        ]);

        $this->actingAs($agent)->post(route('subscribers.complete', 900001))->assertRedirect();

        $this->assertDatabaseHas('subscriber_actions', [
            'purchase_id' => 900001, 'agent_status' => 'COMPLETED', 'done_by' => $agent->id,
        ], 'mysql_portal');
        $this->assertSame('2026-09-15 10:30:11', \Illuminate\Support\Facades\DB::connection('mysql_business')->table(ComboPurchaseQuery::TABLE)->where('id', 900001)->value('expiry_date'));
    }

    public function test_authenticated_agent_can_complete_a_pending_subscriber_once(): void
    {
        $agent = $this->portalUser('ACTIVE');
        $record = ActiveSubscriber::create([
            'business_purchase_id' => 12345,
            'subscriber_number' => '252630000000',
            'package_tier' => 'DAILY',
            'purchase_date' => now(),
            'expire_date' => now()->addDay(),
        ]);

        $this->actingAs($agent)->postJson(route('active-subscribers.complete', $record))
            ->assertOk()
            ->assertJsonPath('status', 'COMPLETED')
            ->assertJsonPath('done_by', 'Portal Tester');

        $record->refresh();
        $this->assertSame('COMPLETED', $record->action_status);
        $this->assertSame($agent->id, $record->done_by);
        $this->assertNotNull($record->completed_at);

        $this->actingAs($agent)->postJson(route('active-subscribers.complete', $record))->assertStatus(409);
    }

    private function portalUser(string $status, string $role = 'AGENT'): User
    {
        return User::create([
            'firstName' => 'Portal',
            'last_name' => 'Tester',
            'username' => strtolower($status).'-'.uniqid(),
            'password' => Hash::make('correct-password'),
            'status' => $status,
            'role' => $role,
        ]);
    }
}
