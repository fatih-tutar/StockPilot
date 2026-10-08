<?php

use App\Models\User;
use App\Support\AccessRoles;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('in_office')->default(false);
        });

        foreach (array_merge(...array_values(AccessRoles::flagPermissions())) as $name) {
            Permission::findOrCreate($name);
        }

        if (Schema::hasColumn('users', 'access_flags')) {
            DB::table('users')->orderBy('id')->select(['id', 'access_flags'])->each(function (object $row): void {
                $user = User::withTrashed()->find($row->id);

                if ($user === null) {
                    return;
                }

                $flags = $this->decodedFlags($row->access_flags);
                $user->forceFill([
                    'in_office' => filter_var($flags['office'] ?? false, FILTER_VALIDATE_BOOLEAN),
                ])->saveQuietly();
                AccessRoles::syncFlagPermissions($user, $flags);
            });

            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('access_flags');
            });
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('access_flags')->nullable();
        });

        User::withTrashed()->orderBy('id')->each(function (User $user): void {
            $flags = AccessRoles::storedFlags($user);
            $user->forceFill(['access_flags' => json_encode($flags)])->saveQuietly();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('in_office');
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function decodedFlags(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
};
