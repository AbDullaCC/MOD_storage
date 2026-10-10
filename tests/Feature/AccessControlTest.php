<?php

namespace Tests\Feature;

use App\Models\ProductIn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $role = 'operator', bool $active = true): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => $active]);
    }

    private function accountData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Entry User', 'username' => 'entry_user',
            'role' => 'operator', 'is_active' => '1',
            'password' => 'entry-password', 'password_confirmation' => 'entry-password',
        ], $overrides);
    }

    public function test_every_inventory_and_account_route_requires_login(): void
    {
        foreach ([
            ['GET', '/storage'], ['GET', '/storage/create'], ['GET', '/storage/report'], ['GET', '/storage/export'],
            ['POST', '/storage'], ['POST', '/storage/out'], ['POST', '/storage/addition'],
            ['PUT', '/storage/item/1'], ['DELETE', '/storage/item/1'],
            ['PUT', '/storage/out/1'], ['DELETE', '/storage/out/1'],
            ['PUT', '/storage/addition/1'], ['DELETE', '/storage/addition/1'],
            ['GET', '/users'], ['GET', '/users/create'], ['POST', '/users'],
            ['GET', '/users/1/edit'], ['PUT', '/users/1'],
            ['GET', '/account/password'], ['PUT', '/account/password'],
            ['GET', '/storage/item/1/history'], ['POST', '/storage/item/1/restore'],
        ] as [$method, $url]) {
            $this->call($method, $url)->assertRedirect('/login');
        }
        $this->assertDatabaseCount('product_ins', 0);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_login_and_logout_work_and_public_registration_is_absent(): void
    {
        $user = $this->account();
        $this->get('/login')->assertOk()->assertSee('اسم المستخدم')->assertDontSee('name="email"', false);
        $this->get('/storage')->assertRedirect('/login');
        $this->post('/login', ['email' => 'admin@example.test', 'password' => 'password'])->assertSessionHasErrors('username');
        $this->assertGuest();
        $this->post('/login', ['username' => ' '.strtoupper($user->username).' ', 'password' => 'password'])
            ->assertRedirect('/storage');
        $this->assertAuthenticatedAs($user);
        $this->get('/login')->assertRedirect('/storage');
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
        $this->get('/storage')->assertRedirect('/login');
        $this->get('/register')->assertNotFound();
        $this->post('/register', $this->accountData())->assertNotFound();
    }

    public function test_invalid_logins_are_rejected_and_rate_limited(): void
    {
        $user = $this->account();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $username = $attempt % 2 ? ' '.strtoupper($user->username).' ' : $user->username;
            $this->post('/login', ['username' => $username, 'password' => 'wrong'])->assertSessionHasErrors('username');
        }
        $this->post('/login', ['username' => $user->username, 'password' => 'password'])->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_disabled_accounts_cannot_login_or_continue_an_existing_session(): void
    {
        $user = $this->account('operator', false);
        $this->post('/login', ['username' => $user->username, 'password' => 'password'])->assertSessionHasErrors('username');
        $this->assertGuest();
        $this->actingAs($user)->get('/storage')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_operator_can_read_inventory_and_perform_stock_entries(): void
    {
        $this->actingAs($this->account());
        $this->get('/storage/create')->assertOk()->assertViewIs('storage.create');
        $this->from('/storage/create')->post('/storage', ['name' => 'Draft item', 'category' => 'Test', 'quantity' => 0, 'added_at' => now()->subMinute()->toDateTimeString()])
            ->assertRedirect('/storage/create')->assertSessionHasErrors('quantity')->assertSessionHasInput('name', 'Draft item');
        $this->assertDatabaseCount('product_ins', 0);
        $this->get('/storage/create')->assertOk()->assertSee('value="Draft item"', false);
        $this->post('/storage', ['name' => 'Test Item', 'category' => 'Test', 'quantity' => 10, 'added_at' => now()->subMinute()->toDateTimeString()])
            ->assertSessionHasNoErrors()->assertRedirect('/storage');
        $item = ProductIn::firstOrFail();
        $this->post('/storage/addition', ['product_in_id' => $item->id, 'quantity' => 5, 'date' => now()->subMinute()->toDateTimeString()])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->post('/storage/out', ['product_in_id' => $item->id, 'quantity' => 3, 'date' => now()->subMinute()->toDateTimeString(), 'destination' => 'Office'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(12, $item->fresh()->current_stock);
        $this->get('/storage')->assertOk()->assertDontSee('id="delete-item-form"', false)->assertDontSee('id="new-item-form"', false)->assertSee('href="'.route('storage.create').'"', false)->assertDontSee('إدارة المستخدمين');
        $this->get('/storage')->assertOk()->assertDontSee('href="'.route('storage.report').'"', false)->assertDontSee('id="export-btn"', false);
        $this->get('/storage/report')->assertForbidden();
        $this->get('/storage/report?show_all=1&search=Test')->assertForbidden();
        $this->get('/storage/export')->assertForbidden();
    }

    public function test_admin_can_access_the_operations_log_and_export_inventory(): void
    {
        ProductIn::create(['name' => 'Report item', 'category' => 'Test', 'quantity' => 10, 'added_at' => now()->subMinute()]);
        $this->actingAs($this->account('admin'));
        $this->get('/storage')->assertOk()->assertSee('href="'.route('audits.index').'"', false)->assertDontSee('href="'.route('storage.report').'"', false)->assertSee('id="export-btn"', false);
        $this->get('/storage/report')->assertRedirect('/audits');
        $this->get('/audits')->assertOk()->assertSee('Report item');
        $this->get('/storage/export')->assertOk()->assertDownload();
    }

    public function test_operator_cannot_edit_delete_or_manage_accounts_even_with_direct_requests(): void
    {
        $user = $this->account();
        $item = ProductIn::create(['name' => 'Protected', 'category' => 'Test', 'quantity' => 10, 'added_at' => now()]);
        $out = $item->outs()->create(['quantity' => 1, 'date' => now()]);
        $addition = $item->additions()->create(['quantity' => 2, 'date' => now()]);
        $this->actingAs($user);
        foreach ([
            ['PUT', '/storage/item/'.$item->id], ['DELETE', '/storage/item/'.$item->id],
            ['PUT', '/storage/out/'.$out->id], ['DELETE', '/storage/out/'.$out->id],
            ['PUT', '/storage/addition/'.$addition->id], ['DELETE', '/storage/addition/'.$addition->id],
            ['GET', '/users'], ['GET', '/users/create'], ['POST', '/users'],
            ['GET', '/users/'.$user->id.'/edit'], ['PUT', '/users/'.$user->id],
            ['GET', '/storage/item/'.$item->id.'/history'], ['POST', '/storage/item/'.$item->id.'/restore'],
        ] as [$method, $url]) {
            $this->call($method, $url, $this->accountData(['role' => 'admin']))->assertForbidden();
        }
        $this->assertSame('operator', $user->fresh()->role);
        $this->assertSame('Protected', $item->fresh()->name);
        $this->assertDatabaseCount('outs', 1);
        $this->assertDatabaseCount('additions', 1);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_admin_can_create_edit_disable_and_reset_an_account(): void
    {
        $this->actingAs($this->account('admin'));
        $this->get('/storage/create')->assertOk()->assertViewIs('storage.create');
        $this->get('/users')->assertOk();
        $this->get('/users/create')->assertOk();
        $this->post('/users', $this->accountData(['username' => 'ENTRY_USER']))->assertRedirect('/users');
        $user = User::where('username', 'entry_user')->firstOrFail();
        $this->assertTrue(Hash::check('entry-password', $user->password));
        $this->get('/users/'.$user->id.'/edit')->assertOk();
        $this->put('/users/'.$user->id, $this->accountData(['role' => 'admin', 'is_active' => '0', 'password' => 'replacement-password', 'password_confirmation' => 'replacement-password']))
            ->assertSessionHasNoErrors()->assertRedirect('/users');
        $this->assertFalse($user->fresh()->is_active);
        $this->assertSame('admin', $user->fresh()->role);
        $this->assertTrue(Hash::check('replacement-password', $user->fresh()->password));
        $this->put('/users/'.$user->id, $this->accountData(['password' => '', 'password_confirmation' => '']))->assertRedirect('/users');
        $this->assertTrue(Hash::check('replacement-password', $user->fresh()->password));
    }

    public function test_admin_cannot_disable_or_demote_their_own_account(): void
    {
        $admin = $this->account('admin');
        $this->actingAs($admin);
        foreach ([['role' => 'operator'], ['role' => 'admin', 'is_active' => '0']] as $change) {
            $this->put('/users/'.$admin->id, $this->accountData([...$change, 'username' => $admin->username]))->assertSessionHasErrors('role');
        }
        $this->assertTrue($admin->fresh()->isAdmin());
    }

    public function test_account_validation_rejects_duplicate_username_invalid_role_and_weak_password(): void
    {
        $admin = $this->account('admin');
        $this->actingAs($admin)->post('/users', $this->accountData([
            'username' => strtoupper($admin->username), 'role' => 'owner', 'password' => 'short', 'password_confirmation' => 'short',
        ]))->assertSessionHasErrors(['username', 'role', 'password']);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_usernames_reject_email_addresses_spaces_and_invalid_input(): void
    {
        $this->actingAs($this->account('admin'));
        foreach (['', 'ab', 'entry@example.test', 'entry man', '-entry', str_repeat('a', 51), ['entry']] as $username) {
            $this->post('/users', $this->accountData(['username' => $username]))->assertSessionHasErrors('username');
        }
        $this->assertDatabaseCount('users', 1);
    }

    public function test_changing_username_preserves_password_and_allows_login_with_the_new_identifier(): void
    {
        $user = $this->account();
        $originalUsername = $user->username;
        $originalPassword = $user->password;
        $this->actingAs($this->account('admin'))
            ->put('/users/'.$user->id, $this->accountData(['username' => ' موظف_المستودع ', 'password' => '', 'password_confirmation' => '']))
            ->assertSessionHasNoErrors()->assertRedirect('/users');
        $this->assertSame('موظف_المستودع', $user->fresh()->username);
        $this->assertSame($originalPassword, $user->fresh()->password);
        $this->post('/logout');
        $this->post('/login', ['username' => $originalUsername, 'password' => 'password'])->assertSessionHasErrors('username');
        $this->assertGuest();
        $this->post('/login', ['username' => 'موظف_المستودع', 'password' => 'password'])->assertRedirect('/storage');
        $this->assertAuthenticatedAs($user);
    }

    public function test_admin_can_edit_and_cancel_inventory_without_erasing_it(): void
    {
        $item = ProductIn::create(['name' => 'Before', 'category' => 'Test', 'quantity' => 10, 'added_at' => now()->subMinute()]);
        $out = $item->outs()->create(['quantity' => 1, 'date' => now()->subMinute()]);
        $addition = $item->additions()->create(['quantity' => 2, 'date' => now()->subMinute()]);
        $this->actingAs($this->account('admin'));
        $this->get('/storage')->assertOk()->assertSee('id="delete-item-form"', false)->assertSee('إدارة المستخدمين');
        $this->put('/storage/item/'.$item->id, ['name' => 'After', 'category' => 'Test', 'added_at' => now()->subMinute()->toDateTimeString(), 'reason' => 'Correct name'])->assertSessionHasNoErrors();
        $this->assertSame('After', $item->fresh()->name);
        $this->put('/storage/out/'.$out->id, ['date' => now()->subMinute()->toDateTimeString(), 'note' => 'Updated', 'reason' => 'Correct note'])->assertSessionHasNoErrors();
        $this->put('/storage/addition/'.$addition->id, ['date' => now()->subMinute()->toDateTimeString(), 'note' => 'Updated', 'reason' => 'Correct note'])->assertSessionHasNoErrors();
        $this->delete('/storage/out/'.$out->id, ['reason' => 'Incorrect withdrawal'])->assertSessionHasNoErrors()->assertRedirect();
        $this->delete('/storage/addition/'.$addition->id, ['reason' => 'Incorrect restock'])->assertSessionHasNoErrors()->assertRedirect();
        $this->delete('/storage/item/'.$item->id, ['reason' => 'Archive item'])->assertSessionHasErrors('inventory');
        $this->assertDatabaseCount('product_ins', 1);
        $this->assertNotNull($out->fresh()->cancelled_at);
        $this->assertNotNull($addition->fresh()->cancelled_at);
    }

    public function test_users_can_change_password_but_cannot_promote_themselves(): void
    {
        $user = $this->account();
        $this->actingAs($user)->get('/account/password')->assertOk();
        $this->put('/account/password', ['current_password' => 'wrong', 'password' => 'new-password', 'password_confirmation' => 'new-password'])->assertSessionHasErrors('current_password');
        $this->put('/account/password', ['current_password' => 'password', 'password' => 'new-password', 'password_confirmation' => 'new-password', 'role' => 'admin'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
        $this->assertSame('operator', $user->fresh()->role);
        $this->get('/storage')->assertOk();
    }

    public function test_password_reset_invalidates_an_existing_session(): void
    {
        $user = $this->account();
        $oldHash = $user->password;
        $user->password = 'replacement-password';
        $user->save();
        $this->actingAs($user)->withSession(['password_hash_web' => $oldHash])->get('/storage')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_admin_creation_command_creates_a_hashed_admin_without_overwriting_existing_users(): void
    {
        $this->artisan('app:create-admin', ['username' => 'admin', '--name' => 'Admin'])
            ->expectsQuestion('Password (at least 8 characters)', 'admin-password')
            ->expectsQuestion('Confirm password', 'admin-password')
            ->expectsOutput('Admin account created: admin')->assertSuccessful();
        $user = User::where('username', 'admin')->firstOrFail();
        $this->assertTrue($user->isAdmin());
        $this->assertTrue(Hash::check('admin-password', $user->password));
        $this->artisan('app:create-admin', ['username' => 'admin'])
            ->expectsQuestion('Password (at least 8 characters)', 'replacement-password')
            ->expectsQuestion('Confirm password', 'replacement-password')->assertFailed();
        $this->assertTrue(Hash::check('admin-password', $user->fresh()->password));
    }
}
