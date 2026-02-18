<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'name' => 'Administrador',
                'slug' => 'administrador',
                'description' => 'Acceso completo al sistema',
            ],
            [
                'name' => 'Contable',
                'slug' => 'contable',
                'description' => 'Acceso a módulos contables y financieros',
            ],
            [
                'name' => 'Trabajador',
                'slug' => 'trabajador',
                'description' => 'Acceso básico al sistema',
            ],
            [
                'name' => 'Programador',
                'slug' => 'programador',
                'description' => 'Acceso a módulos de desarrollo y configuración',
            ],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(
                ['slug' => $role['slug']],
                $role
            );
        }
    }
}
