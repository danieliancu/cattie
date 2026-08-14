<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('composed_designs', function (Blueprint $table) {
            $table->json('resolved_manifest')->nullable()->after('character_adjustments');
            $table->char('render_fingerprint', 64)->nullable()->after('resolved_manifest')->index();
        });

        if (Schema::hasTable('design_template_versions')) {
            DB::table('design_template_versions')->orderBy('id')->each(function ($version): void {
                $configuration = json_decode($version->configuration, true);
                if (! is_array($configuration)) {
                    return;
                }
                $configuration['character_clip'] ??= ['mode' => 'canvas'];
                $layers = [];
                foreach ($configuration['layers'] ?? [] as $layer) {
                    if (($layer['type'] ?? null) === 'solid') {
                        $configuration['preview_surface'] ??= array_filter([
                            'colour' => $layer['colour'] ?? null,
                            'variant_option' => $layer['variant_option'] ?? null,
                            'colours_by_variant' => $layer['colours_by_variant'] ?? null,
                            'fallback_colour' => $layer['fallback_colour'] ?? null,
                        ], fn ($value) => $value !== null);
                    } else {
                        $layers[] = $layer;
                    }
                }
                $configuration['layers'] = $layers;
                DB::table('design_template_versions')->where('id', $version->id)->update(['configuration' => json_encode($configuration, JSON_THROW_ON_ERROR)]);
            });
        }
    }

    public function down(): void
    {
        Schema::table('composed_designs', function (Blueprint $table) {
            $table->dropColumn(['resolved_manifest', 'render_fingerprint']);
        });
    }
};
