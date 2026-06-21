<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get Super Admin role
        $superAdminRole = Role::where('name', 'super_admin')->first();

        // Create Super Admin user
        $superAdmin = User::firstOrCreate(
            ['email' => 'admin@sentraguard.local'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'role_id' => $superAdminRole->id,
                'email_verified_at' => now(),
            ]
        );

        $this->command->info('✅ Super Admin user created!');
        $this->command->info('   Email: admin@sentraguard.local');
        $this->command->info('   Password: password');
        $this->command->warn('⚠️  Change the default password immediately in production!');
    }
}
