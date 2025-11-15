<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PenaltySettingsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_can_update_penalty_settings(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->from(route('settings.penalties.edit'))
            ->put(route('settings.penalties.update'), [
                'grace_days' => 7,
                'rate_percent' => 2.5,
                'reminder_cadence' => 4,
            ]);

        $response->assertRedirect(route('settings.penalties.edit'));
        $response->assertSessionHas('success');

        $this->assertEquals(7, Setting::getValue('penalty.grace_days'));
        $this->assertEquals(2.5, Setting::getValue('penalty.rate_percent_per_day'));
        $this->assertEquals(4, Setting::getValue('reminder.cadence_days'));
    }

    #[Test]
    public function non_admin_users_are_forbidden(): void
    {
        $staff = User::factory()->create([
            'role' => 'staff',
            'is_active' => true,
        ]);

        $this->actingAs($staff)
            ->get(route('settings.penalties.edit'))
            ->assertForbidden();

        $this->actingAs($staff)
            ->put(route('settings.penalties.update'), [
                'grace_days' => 5,
                'rate_percent' => 1.8,
                'reminder_cadence' => 3,
            ])
            ->assertForbidden();
    }
}


