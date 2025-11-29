<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Store;
use App\Models\Staff;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class UserApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_users()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        User::factory()->count(5)->create();

        $response = $this->getJson('/api/users');

        $response->assertStatus(200)
            ->assertJsonCount(6, 'data'); // 1 auth user + 5 created
    }

    public function test_can_search_users()
    {
        $user = User::factory()->create(['first_name' => 'John', 'last_name' => 'Doe']);
        $this->actingAs($user);

        User::factory()->create(['first_name' => 'Jane', 'last_name' => 'Doe']);
        User::factory()->create(['first_name' => 'Bob', 'last_name' => 'Smith']);

        $response = $this->getJson('/api/users?filter[search]=John');

        $response->assertStatus(200)
            ->assertJsonFragment(['first_name' => 'John'])
            ->assertJsonMissing(['first_name' => 'Bob']);
    }

    public function test_can_search_users_with_q_param()
    {
        $user = User::factory()->create(['first_name' => 'John', 'last_name' => 'Doe']);
        $this->actingAs($user);

        User::factory()->create(['first_name' => 'Jane', 'last_name' => 'Doe']);
        User::factory()->create(['first_name' => 'Bob', 'last_name' => 'Smith']);

        $response = $this->getJson('/api/users?q=John');

        $response->assertStatus(200)
            ->assertJsonFragment(['first_name' => 'John'])
            ->assertJsonMissing(['first_name' => 'Bob']);
    }

    public function test_can_filter_staff()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $staffUser = User::factory()->create();
        $store = Store::factory()->create();
        Staff::create(['user_id' => $staffUser->id, 'store_id' => $store->id]);

        $nonStaffUser = User::factory()->create();

        $response = $this->getJson('/api/users?filter[is_staff]=true');

        $response->assertStatus(200);
        $data = $response->json('data');
        // Should contain staffUser, might contain auth user if they are staff (unlikely by default factory)
        
        $ids = collect($data)->pluck('id');
        $this->assertTrue($ids->contains($staffUser->id));
        $this->assertFalse($ids->contains($nonStaffUser->id));
    }

    public function test_can_filter_by_store()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $store1 = Store::factory()->create();
        $store2 = Store::factory()->create();

        $user1 = User::factory()->create();
        Staff::create(['user_id' => $user1->id, 'store_id' => $store1->id]);

        $user2 = User::factory()->create();
        Staff::create(['user_id' => $user2->id, 'store_id' => $store2->id]);

        $response = $this->getJson('/api/users?filter[by_store]=' . $store1->id);

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($user1->id));
        $this->assertFalse($ids->contains($user2->id));
    }
    
    public function test_n_plus_one_permissions()
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        
        $role = Role::create(['name' => 'Test Role', 'guard_name' => 'web']);
        $permission = Permission::create(['name' => 'Test Permission', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);
        
        $users = User::factory()->count(5)->create();
        foreach($users as $u) {
            $u->assignRole($role);
        }
        
        // Enable query log
        \DB::enableQueryLog();
        
        $response = $this->getJson('/api/users');
        $response->assertStatus(200);
        
        $queries = \DB::getQueryLog();
        // We expect a fixed number of queries, not proportional to users count.
        // 1. Count users (pagination)
        // 2. Select users
        // 3. Select roles for users
        // 4. Select permissions for roles
        // 5. Select permissions for users (direct)
        // 6. Select staff? (if included)
        
        // If N+1, we would see queries for permissions for EACH user.
        
        // Let's check if we have repeated queries for permissions table
        $permissionQueries = collect($queries)->filter(function($q) {
            return str_contains($q['query'], 'permissions');
        });
        
        // With eager loading, we should have very few permission queries (likely 2: roles.permissions and direct permissions)
        // If N+1, it would be 5+
        
        $this->assertLessThan(10, $permissionQueries->count(), "Too many permission queries, potential N+1");
    }
}
