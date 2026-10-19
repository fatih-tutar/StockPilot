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
            $table->foreignId('prepared_by')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->foreignId('invoiced_by')->nullable()->after('prepared_by')->constrained('users')->nullOnDelete();
        });

        DB::table('shipments')->orderBy('id')->chunkById(100, function ($rows): void {
            foreach ($rows as $row) {
                $status = match ($row->status) {
                    'draft', 'scheduled' => 'received',
                    'in_transit' => 'preparing',
                    'delivered' => 'invoiced',
                    'cancelled' => 'archived',
                    'received', 'preparing', 'invoiced', 'archived' => $row->status,
                    default => 'received',
                };

                DB::table('shipments')->where('id', $row->id)->update(['status' => $status]);
            }
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->dropUnique(['number']);
            $table->dropColumn('number');
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->string('number')->nullable()->after('company_id');
        });

        DB::table('shipments')->orderBy('id')->chunkById(100, function ($rows): void {
            foreach ($rows as $row) {
                DB::table('shipments')->where('id', $row->id)->update([
                    'number' => 'S-'.$row->id,
                    'status' => match ($row->status) {
                        'received' => 'scheduled',
                        'preparing' => 'in_transit',
                        'invoiced', 'archived' => 'delivered',
                        default => 'draft',
                    },
                ]);
            }
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->unique('number');
            $table->dropConstrainedForeignId('prepared_by');
            $table->dropConstrainedForeignId('invoiced_by');
        });
    }
};
