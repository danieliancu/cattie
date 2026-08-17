<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The canonical catalogue taxonomy: four top-level categories, each owning its
 * own subcategories.
 *
 * Category copy lives in the database rather than in Blade templates so that it
 * stays editable from the admin. Everything here is keyed on slug, so running
 * the seeder repeatedly updates in place and never duplicates.
 */
class CategoryTaxonomySeeder extends Seeder
{
    /**
     * Legacy flat categories, retired by the hierarchy. Kept as a constant so
     * the removal step and the redirect config describe the same set.
     */
    public const LEGACY_SLUGS = ['school-lunch', 'kids-drinkware', 'school-accessories'];

    /**
     * Existing products keep every bit of their configuration — variants,
     * templates, AI setup, fulfilment mappings, pricing and artwork. Only their
     * taxonomy membership moves, and only where the mapping is unambiguous.
     * Products that cannot be classified confidently (the mug and the cushion)
     * are deliberately left unassigned rather than given invented taxonomy.
     */
    public const PRODUCT_ASSIGNMENTS = [
        'water-bottle-with-red-flip-lid' => ['personalised-water-bottles-for-kids'],
        'small-plastic-lunchbox' => ['personalised-lunch-boxes-for-kids'],
        'personalised-stationery-pencil-tin' => ['personalised-pencil-tins'],
        'childrens-storybook-wall-print' => ['personalised-wall-prints'],
        'best-friend-pet-portrait' => ['personalised-pet-portraits'],
        'our-family-art-print' => ['personalised-family-portraits'],
        'custom-a4-wall-print' => ['personalised-wall-prints'],
        'custom-a3-wall-print' => ['personalised-wall-prints'],
        'custom-a2-wall-print' => ['personalised-wall-prints'],
        'custom-landscape-a4-wall-print' => ['personalised-wall-prints'],
        'custom-landscape-a3-wall-print' => ['personalised-wall-prints'],
        'custom-landscape-a2-wall-print' => ['personalised-wall-prints'],
    ];

    /**
     * Curated marketing photography for the four top-level categories, shown in
     * the homepage circles. Unlike the abstract textures these are real scenes,
     * so they are written to the category's image columns (card artwork's top
     * priority) rather than served as a last-resort fallback — otherwise an
     * arbitrary descendant product photo would win over them.
     *
     * Assets live in seeders/assets/categories/cards/{slug}.jpg.
     *
     * @var array<string, string> slug => alt text
     */
    public const CARD_IMAGES = [
        'school-everyday' => 'Two children with personalised school backpacks and a water bottle featuring their own characters',
        'memories-keepsakes' => 'A child beside a framed storybook portrait and a matching personalised keepsake box',
        'pets-family' => 'A boy sitting beside his dog, holding a mug personalised with his pet',
        'gifts-occasions' => 'A child opening personalised birthday gifts with balloons and a keepsake set',
    ];

    public function run(): void
    {
        $leaves = $this->seedTree();
        $this->publishCategoryTextures();
        $this->assignCategoryCards();
        $this->assignProducts($leaves);
        $this->removeLegacyCategories();
    }

    /**
     * Publishes the brand textures used as the last-resort category artwork.
     *
     * These are abstract palette gradients, not photography. They are deliberately
     * NOT written to the category's image columns: leaving those empty means a real
     * product photo is preferred whenever one exists, an admin upload still wins
     * outright, and re-seeding can never touch admin data.
     *
     * @see ProductCategory::cardImage()
     */
    private function publishCategoryTextures(): void
    {
        foreach (glob(database_path('seeders/assets/categories/*.jpg')) as $asset) {
            Storage::disk('public')->put('categories/'.basename($asset), file_get_contents($asset));
        }
    }

    /**
     * Publishes the homepage card photography and points each top-level category
     * at it — but only where an admin has not uploaded their own image. Re-seeding
     * therefore keeps the shipped artwork current without ever clobbering a
     * deliberate admin upload, exactly as the texture step leaves image columns
     * untouched.
     *
     * @see ProductCategory::cardImage()
     */
    private function assignCategoryCards(): void
    {
        foreach (self::CARD_IMAGES as $slug => $alt) {
            $asset = database_path('seeders/assets/categories/cards/'.$slug.'.jpg');

            if (! is_file($asset)) {
                continue;
            }

            $key = 'categories/cards/'.$slug.'.jpg';
            Storage::disk('public')->put($key, file_get_contents($asset));

            $category = ProductCategory::query()->topLevel()->where('slug', $slug)->first();

            if ($category === null || filled($category->image_storage_key)) {
                continue;
            }

            $category->update([
                'image_disk' => 'public',
                'image_storage_key' => $key,
                'image_alt_text' => $alt,
            ]);
        }
    }

    /**
     * @return array<string, ProductCategory> subcategories keyed by slug
     */
    private function seedTree(): array
    {
        $leaves = [];

        foreach ($this->tree() as $position => [$name, $slug, $intro, $children]) {
            $parent = ProductCategory::query()->updateOrCreate(['slug' => $slug], [
                'name' => $name,
                'parent_id' => null,
                'short_description' => $intro,
                'meta_title' => $name.' | Personalised Gifts | Kattie.uk',
                'meta_description' => Str::limit($intro, 157),
                'is_active' => true,
                'sort_order' => $position,
            ]);

            foreach ($children as $childPosition => [$childName, $childSlug, $childIntro]) {
                $leaves[$childSlug] = ProductCategory::query()->updateOrCreate(['slug' => $childSlug], [
                    'name' => $childName,
                    'parent_id' => $parent->id,
                    'short_description' => $childIntro,
                    'meta_title' => $childName.' | Kattie.uk',
                    'meta_description' => Str::limit($childIntro, 157),
                    'is_active' => true,
                    'sort_order' => $childPosition,
                ]);
            }
        }

        return $leaves;
    }

    /**
     * @param  array<string, ProductCategory>  $leaves
     */
    private function assignProducts(array $leaves): void
    {
        foreach (self::PRODUCT_ASSIGNMENTS as $productSlug => $categorySlugs) {
            $product = Product::query()->where('slug', $productSlug)->first();

            if ($product === null) {
                continue;
            }

            $product->categories()->sync(collect($categorySlugs)
                ->mapWithKeys(fn (string $categorySlug, int $position) => [
                    $leaves[$categorySlug]->id => ['sort_order' => $position],
                ])->all());
        }
    }

    /**
     * The pre-hierarchy categories must not stay publicly visible alongside the
     * new structure. Their pivot rows are detached explicitly rather than
     * relying on the foreign key's delete behaviour, so the result is identical
     * on SQLite (where enforcement depends on a pragma) and on MySQL.
     */
    private function removeLegacyCategories(): void
    {
        foreach (ProductCategory::query()->whereIn('slug', self::LEGACY_SLUGS)->get() as $category) {
            $category->products()->detach();
            $category->delete();
        }
    }

    /**
     * @return array<int, array{0: string, 1: string, 2: string, 3: array<int, array{0: string, 1: string, 2: string}>}>
     */
    private function tree(): array
    {
        return [
            ['School & Everyday', 'school-everyday', 'Make everyday things feel completely theirs. Turn their photo into their own character and add it to personalised school essentials they’ll use, recognise and love every day.', [
                ['Personalised Water Bottles for Kids', 'personalised-water-bottles-for-kids', 'Make their water bottle impossible to mistake. Add their name and their own character to create a personalised bottle made especially for school, days out and everyday adventures.'],
                ['Personalised Lunch Boxes for Kids', 'personalised-lunch-boxes-for-kids', 'Lunchtime feels a little more special when the box is unmistakably theirs. Personalise it with their name and character for school, nursery and days out.'],
                ['Personalised Pencil Tins', 'personalised-pencil-tins', 'Give their pencils and little school essentials a home that feels completely personal, featuring their own name and illustrated character.'],
                ['Personalised School Backpacks', 'personalised-school-backpacks', 'A school bag that looks like nobody else’s. Turn their photo into a character and create a backpack they’ll be proud to carry.'],
                ['Personalised Lunch Bags for Kids', 'personalised-lunch-bags-for-kids', 'Practical for school, personal to them. Add their name and character to make their lunch bag easy to recognise and much more fun to carry.'],
            ]],
            ['Memories & Keepsakes', 'memories-keepsakes', 'Turn a face you love into something worth keeping. Create beautiful personalised artwork from a favourite photo and turn everyday memories into keepsakes for years to come.', [
                ['Personalised Storybook Portraits', 'personalised-storybook-portraits', 'Turn someone you love into the star of their own illustration. Created from your photo and transformed into warm, expressive storybook artwork.'],
                ['Personalised Wall Prints', 'personalised-wall-prints', 'Favourite faces, turned into artwork for your walls. Create a personalised print from a photograph you already treasure.'],
                ['Framed Personalised Portraits', 'framed-personalised-portraits', 'Turn a meaningful photograph into finished framed artwork, ready to give, display and keep.'],
                ['Personalised Desk Portraits', 'personalised-desk-portraits', 'Keep someone special close by with a personalised portrait designed for desks, shelves and little spaces around the home.'],
                ['Personalised Keepsake Boxes', 'personalised-keepsake-boxes', 'A special place for little things that matter, personalised with artwork created from the face you love.'],
            ]],
            ['Pets & Family', 'pets-family', 'Because some faces mean everything. Turn favourite family and pet photos into personal artwork and gifts created around the ones who make home feel like home.', [
                ['Personalised Pet Portraits', 'personalised-pet-portraits', 'Turn your favourite pet photo into a characterful personalised portrait that captures the face and personality you know so well.'],
                ['Personalised Pet Gifts', 'personalised-pet-gifts', 'Create something completely personal for a pet lover using artwork made from the animal they love most.'],
                ['Personalised Pet Bowls', 'personalised-pet-bowls', 'Make their everyday bowl unmistakably theirs with personalised artwork and their name.'],
                ['Personalised Family Portraits', 'personalised-family-portraits', 'Turn a favourite family photograph into warm personalised artwork created to celebrate the people who matter most.'],
            ]],
            ['Gifts & Occasions', 'gifts-occasions', 'Find something personal for the moment that matters. Birthday, Christmas or simply because — create a gift from a favourite photo and make it truly theirs.', [
                ['Birthday Gifts for Kids', 'birthday-gifts-for-kids', 'Make their birthday gift feel like it could only have been made for them. Turn their photo into their own character and create something completely personal.'],
                ['Christmas Gifts', 'personalised-christmas-gifts', 'Turn favourite faces and memories into personalised Christmas gifts made for the people — and pets — you know best.'],
                ['Gifts for Grandparents', 'personalised-gifts-for-grandparents', 'Give Grandma or Grandad something more personal than another ordinary present — artwork and keepsakes created from the faces they love most.'],
                ['New Baby Gifts', 'personalised-new-baby-gifts', 'Celebrate one of life’s smallest new arrivals with personalised keepsakes created from a photograph and made to last beyond the newborn days.'],
                ['Mother\'s Day Gifts', 'personalised-mothers-day-gifts', 'Turn the faces and moments she loves most into a personal Mother’s Day gift made especially for her.'],
                ['Father\'s Day Gifts', 'personalised-fathers-day-gifts', 'Create a Father’s Day gift around the people, pets and memories that mean the most to him.'],
            ]],
        ];
    }
}
