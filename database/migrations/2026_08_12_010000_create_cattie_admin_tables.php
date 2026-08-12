<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->boolean('is_admin')->default(false)->index());
        Schema::table('products', function (Blueprint $t) {
            $t->string('status')->default('draft')->index();
            $t->string('meta_title')->nullable();
            $t->unsignedBigInteger('default_price_minor')->nullable();
            $t->foreignUlid('default_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
        });
        Schema::table('product_variants', function (Blueprint $t) {
            $t->unsignedBigInteger('price_override_minor')->nullable();
            $t->boolean('is_default')->default(false)->index();
        });
        Schema::table('product_images', function (Blueprint $t) {
            $t->string('role')->default('gallery')->index();
            $t->boolean('is_primary')->default(false)->index();
            $t->boolean('is_active')->default(true)->index();
        });
        Schema::table('fulfilment_product_mappings', function (Blueprint $t) {
            $t->unsignedBigInteger('supplier_cost_minor')->nullable();
            $t->char('supplier_cost_currency', 3)->nullable();
            $t->string('supplier_vat_basis')->nullable();
            $t->string('availability')->nullable();
            $t->timestamp('last_synced_at')->nullable();
            $t->string('last_sync_status')->nullable()->index();
            $t->text('last_sync_error')->nullable();
        });
        Schema::table('product_design_templates', function (Blueprint $t) {
            $t->string('name')->nullable();
            $t->boolean('is_active')->default(true)->index();
        });
        Schema::create('design_template_versions', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignUlid('product_design_template_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('version');
            $t->string('status')->default('draft')->index();
            $t->json('configuration');
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('published_at')->nullable();
            $t->timestamp('last_test_render_at')->nullable();
            $t->string('last_test_render_status')->nullable();
            $t->text('last_test_render_error')->nullable();
            $t->string('test_render_disk')->nullable();
            $t->string('test_render_storage_key')->nullable();
            $t->timestamps();
            $t->unique(['product_design_template_id', 'version']);
        });
        Schema::create('design_template_assignments', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignUlid('product_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignUlid('product_variant_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignUlid('design_template_version_id')->constrained()->restrictOnDelete();
            $t->boolean('is_active')->default(true)->index();
            $t->timestamps();
            $t->unique(['product_id', 'is_active']);
            $t->unique(['product_variant_id', 'is_active']);
        });
        Schema::table('composed_designs', fn (Blueprint $t) => $t->foreignUlid('design_template_version_id')->nullable()->constrained()->nullOnDelete());
        Schema::create('product_slug_redirects', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignUlid('product_id')->constrained()->cascadeOnDelete();
            $t->string('old_slug')->unique();
            $t->timestamp('created_at')->useCurrent();
        });
        Schema::create('admin_audit_logs', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignId('admin_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('action')->index();
            $t->string('subject_type');
            $t->string('subject_id');
            $t->json('before')->nullable();
            $t->json('after')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['subject_type', 'subject_id']);
        });

        DB::table('products')->orderBy('id')->each(function ($product): void {
            DB::table('products')->where('id', $product->id)->update([
                'status' => $product->is_active ? 'published' : 'archived',
                'default_price_minor' => $product->base_price_minor,
            ]);
            $variant = DB::table('product_variants')->where('product_id', $product->id)->where('is_active', true)->orderBy('sort_order')->first();
            if ($variant) {
                DB::table('product_variants')->where('id', $variant->id)->update(['is_default' => true]);
                DB::table('products')->where('id', $product->id)->update(['default_variant_id' => $variant->id]);
            }
        });
        DB::table('product_images')->orderBy('product_id')->orderBy('sort_order')->get()->groupBy('product_id')->each(function ($images): void {
            DB::table('product_images')->where('id', $images->first()->id)->update(['is_primary' => true, 'role' => 'primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audit_logs');
        Schema::dropIfExists('product_slug_redirects');
        Schema::table('composed_designs', fn (Blueprint $t) => $t->dropConstrainedForeignId('design_template_version_id'));
        Schema::dropIfExists('design_template_assignments');
        Schema::dropIfExists('design_template_versions');
        Schema::table('product_design_templates', fn (Blueprint $t) => $t->dropColumn(['name', 'is_active']));
        Schema::table('fulfilment_product_mappings', fn (Blueprint $t) => $t->dropColumn(['supplier_cost_minor', 'supplier_cost_currency', 'supplier_vat_basis', 'availability', 'last_synced_at', 'last_sync_status', 'last_sync_error']));
        Schema::table('product_images', fn (Blueprint $t) => $t->dropColumn(['role', 'is_primary', 'is_active']));
        Schema::table('product_variants', fn (Blueprint $t) => $t->dropColumn(['price_override_minor', 'is_default']));
        Schema::table('products', function (Blueprint $t) {
            $t->dropConstrainedForeignId('default_variant_id');
            $t->dropColumn(['status', 'meta_title', 'default_price_minor']);
        });
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('is_admin'));
    }
};
