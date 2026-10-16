<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->foreignId('vehicle_id')
                ->nullable()
                ->after('client_id')
                ->constrained()
                ->nullOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('
                UPDATE shipments AS s
                SET vehicle_id = v.id
                FROM vehicles AS v
                WHERE s.vehicle_id IS NULL
                  AND s.vehicle_plate IS NOT NULL
                  AND s.vehicle_plate <> \'\'
                  AND v.license_plate = s.vehicle_plate
            ');
        } else {
            $plates = DB::table('shipments')
                ->whereNull('vehicle_id')
                ->whereNotNull('vehicle_plate')
                ->where('vehicle_plate', '!=', '')
                ->distinct()
                ->pluck('vehicle_plate');

            foreach ($plates as $plate) {
                $vehicleId = DB::table('vehicles')
                    ->where('license_plate', $plate)
                    ->value('id');

                if ($vehicleId === null) {
                    continue;
                }

                DB::table('shipments')
                    ->whereNull('vehicle_id')
                    ->where('vehicle_plate', $plate)
                    ->update(['vehicle_id' => $vehicleId]);
            }
        }

        Schema::table('shipment_items', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }

    public function down(): void
    {
        Schema::table('shipment_items', function (Blueprint $table) {
            $table->string('description')->default('')->after('product_id');
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vehicle_id');
        });
    }
};
