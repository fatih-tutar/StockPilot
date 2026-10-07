<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Company;
use App\Models\Inventory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_import_keeps_category_dimensions_and_skips_a_second_run(): void
    {
        $company = Company::factory()->create();
        $category = Category::factory()->create();
        $directory = storage_path('framework/testing/inventories-import');

        File::ensureDirectoryExists($directory);
        File::put($directory.'/inventories.csv', implode("\n", [
            'id,category_id,code,dimension_1,dimension_2,dimension_3,density,factory_name,is_deleted,company_id',
            "5,{$category->id},40-40,40,40,2,1.25,Profilhane,0,{$company->id}",
            "6,{$category->id},20-20,20,20,1.5,0.80,Profilhane,1,{$company->id}",
            '7,999999,10-10,10,10,1,0.40,Profilhane,0,'.$company->id,
        ]));

        try {
            $this->artisan('stockpilot:import-legacy', [
                'path' => $directory,
                '--only' => 'inventories',
            ])->assertSuccessful();

            $this->artisan('stockpilot:import-legacy', [
                'path' => $directory,
                '--only' => 'inventories',
            ])->assertSuccessful();
        } finally {
            File::deleteDirectory($directory);
        }

        $kept = Inventory::query()->find(5);
        $this->assertNotNull($kept);
        $this->assertSame($category->id, $kept->category_id);
        $this->assertSame($company->id, $kept->company_id);
        $this->assertSame('40-40', $kept->code);
        $this->assertSame('40', $kept->dimension_1);
        $this->assertSame('2', $kept->dimension_3);
        $this->assertSame('1.25', $kept->density);
        $this->assertSame('Profilhane', $kept->factory_name);

        $removed = Inventory::withTrashed()->find(6);
        $this->assertNotNull($removed);
        $this->assertSoftDeleted($removed);
        $this->assertNull(Inventory::withTrashed()->find(7));
        $this->assertSame(2, Inventory::withTrashed()->count());
    }
}
