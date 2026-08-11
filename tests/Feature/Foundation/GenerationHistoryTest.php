<?php

namespace Tests\Feature\Foundation;

use App\Enums\GenerationStatus;
use App\Models\ArtworkStyle;
use App\Models\Generation;
use App\Models\Product;
use App\Models\Upload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerationHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_regeneration_is_a_new_record_linked_to_its_parent(): void
    {
        $product = Product::factory()->create();
        $style = ArtworkStyle::query()->create(['name' => 'Test', 'slug' => 'test', 'prompt_key' => 'test', 'prompt_version' => 1, 'is_active' => true]);
        $upload = Upload::query()->create(['disk' => 'private', 'storage_key' => 'uploads/a.jpg', 'mime_type' => 'image/jpeg', 'size_bytes' => 100, 'sha256' => str_repeat('a', 64)]);
        $data = ['upload_id' => $upload->id, 'product_id' => $product->id, 'artwork_style_id' => $style->id, 'prompt_key' => 'test', 'prompt_version' => 1, 'resolved_prompt' => 'resolved', 'provider' => 'fake', 'model' => 'fake-1', 'status' => GenerationStatus::Pending, 'cost_currency' => 'GBP'];
        $first = Generation::query()->create($data);
        $second = Generation::query()->create($data + ['parent_generation_id' => $first->id]);
        $this->assertNotSame($first->id, $second->id);
        $this->assertTrue($second->parent->is($first));
        $this->assertDatabaseCount('generations', 2);
    }
}
