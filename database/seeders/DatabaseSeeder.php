<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Crear roles
        $this->call(RoleSeeder::class);

        // Crear usuario administrador de ejemplo
        $admin = User::firstOrCreate(
            ['email' => 'admin@comurban.com'],
            [
                'name' => 'Administrador',
                'password' => bcrypt('password'),
            ]
        );

        // Asignar rol de administrador
        $adminRole = Role::where('slug', 'administrador')->first();
        if ($adminRole && !$admin->hasRole('administrador')) {
            $admin->roles()->attach($adminRole);
        }
    }
}
