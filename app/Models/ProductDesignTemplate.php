<?php

namespace App\Models;

use App\Models\Concerns\UsesUlids;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class ProductDesignTemplate extends Model
{
    use UsesUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['version' => 'integer'];
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function composedDesigns()
    {
        return $this->hasMany(ComposedDesign::class);
    }

    public function definition(): array
    {
        $root = realpath(resource_path('product-designs'));
        $path = realpath(resource_path('product-designs/'.$this->definition_path));

        if (! $root || ! $path || ! str_starts_with(strtolower($path), strtolower($root.DIRECTORY_SEPARATOR))) {
            throw new RuntimeException("Product design template [{$this->key}] definition is unavailable.");
        }

        $definition = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        if (($definition['key'] ?? null) !== $this->key
            || ($definition['version'] ?? null) !== $this->version
            || ($definition['coordinate_system'] ?? null) !== 'normalized'
            || ($definition['output_size']['source'] ?? null) !== 'variant_print_area'
            || ! is_array($definition['layers'] ?? null)
            || ! is_array($definition['safe_zones'] ?? null)) {
            throw new RuntimeException("Product design template [{$this->key}] definition is invalid.");
        }

        return $definition;
    }
}
