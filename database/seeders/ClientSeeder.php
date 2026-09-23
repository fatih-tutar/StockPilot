<?php

namespace Database\Seeders;

use App\Models\Client;
use Illuminate\Database\Seeder;

class ClientSeeder extends Seeder
{
    public function run(): void
    {
        Client::query()->updateOrCreate(
            ['name' => 'Nova Build Ltd'],
            [
                'phone' => '+90 212 555 0101',
                'email' => 'orders@novabuild.example',
                'address' => 'Istanbul, Turkey',
                'notes' => 'Demo wholesale customer',
                'is_active' => true,
            ],
        );

        Client::query()->updateOrCreate(
            ['name' => 'Harbor Fabrication'],
            [
                'phone' => '+90 232 555 0202',
                'email' => 'buy@harborfab.example',
                'address' => 'Izmir, Turkey',
                'notes' => null,
                'is_active' => true,
            ],
        );

        Client::query()->updateOrCreate(
            ['name' => 'Inactive Demo Client'],
            [
                'phone' => null,
                'email' => 'legacy@example.com',
                'address' => null,
                'notes' => 'Kept for inactive-state demo',
                'is_active' => false,
            ],
        );
    }
}
