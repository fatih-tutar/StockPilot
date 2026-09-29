<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Company;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_belongs_to_a_company(): void
    {
        $company = Company::factory()->create();
        $category = Category::factory()->create(['company_id' => $company->id]);

        $this->assertTrue($category->company->is($company));
    }

    public function test_category_rejects_an_unknown_company(): void
    {
        $this->expectException(QueryException::class);

        Category::factory()->create(['company_id' => 999999]);
    }
}
