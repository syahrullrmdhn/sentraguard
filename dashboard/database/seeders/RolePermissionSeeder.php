<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Permissions
        $permissions = [
            // Dashboard
            ['name' => 'view_dashboard', 'display_name' => 'View Dashboard', 'group' => 'dashboard'],
            
            // Servers
            ['name' => 'view_servers', 'display_name' => 'View Servers', 'group' => 'servers'],
            ['name' => 'create_servers', 'display_name' => 'Create Servers', 'group' => 'servers'],
            ['name' => 'edit_servers', 'display_name' => 'Edit Servers', 'group' => 'servers'],
            ['name' => 'delete_servers', 'display_name' => 'Delete Servers', 'group' => 'servers'],
            ['name' => 'generate_token', 'display_name' => 'Generate Agent Token', 'group' => 'servers'],
            ['name' => 'revoke_agent', 'display_name' => 'Revoke Agent', 'group' => 'servers'],
            
            // Services
            ['name' => 'view_services', 'display_name' => 'View Services', 'group' => 'services'],
            ['name' => 'manage_allowlist', 'display_name' => 'Manage Service Allowlist', 'group' => 'services'],
            ['name' => 'start_service', 'display_name' => 'Start Service', 'group' => 'services'],
            ['name' => 'stop_service', 'display_name' => 'Stop Service', 'group' => 'services'],
            ['name' => 'restart_service', 'display_name' => 'Restart Service', 'group' => 'services'],
            ['name' => 'enable_service', 'display_name' => 'Enable Service Startup', 'group' => 'services'],
            ['name' => 'disable_service', 'display_name' => 'Disable Service Startup', 'group' => 'services'],
            
            // Commands
            ['name' => 'view_commands', 'display_name' => 'View Commands', 'group' => 'commands'],
            ['name' => 'retry_command', 'display_name' => 'Retry Command', 'group' => 'commands'],
            ['name' => 'cancel_command', 'display_name' => 'Cancel Command', 'group' => 'commands'],
            
            // Metrics
            ['name' => 'view_metrics', 'display_name' => 'View Metrics', 'group' => 'metrics'],
            
            // Audit Logs
            ['name' => 'view_audit_logs', 'display_name' => 'View Audit Logs', 'group' => 'audit'],
            
            // Users
            ['name' => 'view_users', 'display_name' => 'View Users', 'group' => 'users'],
            ['name' => 'create_users', 'display_name' => 'Create Users', 'group' => 'users'],
            ['name' => 'edit_users', 'display_name' => 'Edit Users', 'group' => 'users'],
            ['name' => 'delete_users', 'display_name' => 'Delete Users', 'group' => 'users'],
            
            // Roles
            ['name' => 'manage_roles', 'display_name' => 'Manage Roles & Permissions', 'group' => 'roles'],
            
            // Settings
            ['name' => 'manage_settings', 'display_name' => 'Manage System Settings', 'group' => 'settings'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission['name']],
                $permission
            );
        }

        // Create Roles
        $superAdmin = Role::firstOrCreate(
            ['name' => 'super_admin'],
            [
                'display_name' => 'Super Admin',
                'description' => 'Full system control',
            ]
        );

        $admin = Role::firstOrCreate(
            ['name' => 'admin'],
            [
                'display_name' => 'Admin',
                'description' => 'Server and service management',
            ]
        );

        $operator = Role::firstOrCreate(
            ['name' => 'operator'],
            [
                'display_name' => 'Operator',
                'description' => 'Service operations only',
            ]
        );

        $viewer = Role::firstOrCreate(
            ['name' => 'viewer'],
            [
                'display_name' => 'Viewer',
                'description' => 'Read-only access',
            ]
        );

        // Assign permissions to Super Admin (all permissions)
        $superAdmin->permissions()->sync(Permission::all()->pluck('id'));

        // Assign permissions to Admin
        $adminPermissions = Permission::whereIn('name', [
            'view_dashboard',
            'view_servers', 'create_servers', 'edit_servers', 'generate_token', 'revoke_agent',
            'view_services', 'manage_allowlist',
            'start_service', 'stop_service', 'restart_service', 'enable_service', 'disable_service',
            'view_commands', 'retry_command', 'cancel_command',
            'view_metrics',
            'view_audit_logs',
        ])->pluck('id');
        $admin->permissions()->sync($adminPermissions);

        // Assign permissions to Operator
        $operatorPermissions = Permission::whereIn('name', [
            'view_dashboard',
            'view_servers',
            'view_services',
            'start_service', 'stop_service', 'restart_service',
            'view_commands', 'retry_command', 'cancel_command',
            'view_metrics',
        ])->pluck('id');
        $operator->permissions()->sync($operatorPermissions);

        // Assign permissions to Viewer
        $viewerPermissions = Permission::whereIn('name', [
            'view_dashboard',
            'view_servers',
            'view_services',
            'view_metrics',
        ])->pluck('id');
        $viewer->permissions()->sync($viewerPermissions);

        $this->command->info('✅ Roles and permissions seeded successfully!');
    }
}
