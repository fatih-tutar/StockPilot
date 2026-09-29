<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CategoryColumnDefinition;
use Database\Seeders\CategoryColumnDefinitionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryColumnLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_column_catalog_omits_the_unused_date_field(): void
    {
        $this->seed(CategoryColumnDefinitionSeeder::class);

        $this->assertDatabaseHas('category_column_definitions', ['name' => 'quantity']);
        $this->assertDatabaseMissing('category_column_definitions', ['name' => 'date']);
    }

    public function test_child_category_uses_the_parent_column_layout(): void
    {
        $this->seed(CategoryColumnDefinitionSeeder::class);

        $parent = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $parent->id]);
        $quantity = CategoryColumnDefinition::query()->where('name', 'quantity')->firstOrFail();

        $parent->columnDefinitions()->sync([$quantity->id]);

        $this->assertTrue(
            $child->activeColumnDefinitions()->where('category_column_definitions.id', $quantity->id)->exists(),
        );
        $this->assertSame(0, $child->columnDefinitions()->count());
    }
}
