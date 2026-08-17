<?php

namespace Tests\Feature\Catalogue;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CatalogueNavigationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, ProductCategory>
     */
    private function taxonomy(): array
    {
        $categories = [];
        $tree = [
            'school-everyday' => ['School & Everyday', ['personalised-water-bottles-for-kids' => 'Personalised Water Bottles for Kids', 'personalised-pencil-tins' => 'Personalised Pencil Tins']],
            'memories-keepsakes' => ['Memories & Keepsakes', ['personalised-wall-prints' => 'Personalised Wall Prints']],
            'pets-family' => ['Pets & Family', ['personalised-pet-portraits' => 'Personalised Pet Portraits']],
            'gifts-occasions' => ['Gifts & Occasions', ['birthday-gifts-for-kids' => 'Birthday Gifts for Kids']],
        ];

        foreach ($tree as $slug => [$name, $children]) {
            $parent = ProductCategory::factory()->create([
                'name' => $name, 'slug' => $slug, 'sort_order' => count($categories),
            ]);
            $categories[$slug] = $parent;

            foreach (array_values($children) as $position => $childName) {
                $childSlug = array_keys($children)[$position];
                $categories[$childSlug] = ProductCategory::factory()->create([
                    'name' => $childName, 'slug' => $childSlug, 'parent_id' => $parent->id, 'sort_order' => $position,
                ]);
            }
        }

        return $categories;
    }

    public function test_header_exposes_every_category_and_subcategory(): void
    {
        $categories = $this->taxonomy();

        $response = $this->get(route('home'))->assertOk();

        foreach ($categories as $category) {
            $response->assertSee('href="'.$category->url().'"', false);
        }

        $response->assertSee('Shop all')->assertSee('href="'.route('products.index').'"', false);
    }

    public function test_navigation_shows_categories_that_have_no_products_yet(): void
    {
        $categories = $this->taxonomy();
        $this->assertSame(0, DB::table('product_category')->count());

        $this->get(route('home'))->assertOk()
            ->assertSee('href="'.$categories['gifts-occasions']->url().'"', false)
            ->assertSee('href="'.$categories['birthday-gifts-for-kids']->url().'"', false);
    }

    public function test_inactive_taxonomy_never_appears_in_navigation(): void
    {
        $categories = $this->taxonomy();
        $categories['pets-family']->update(['is_active' => false]);
        $categories['personalised-pencil-tins']->update(['is_active' => false]);

        $response = $this->get(route('home'))->assertOk();

        $response->assertDontSee('href="'.$categories['pets-family']->url().'"', false);
        $response->assertDontSee('href="'.$categories['personalised-pencil-tins']->url().'"', false);
        $response->assertSee('href="'.$categories['school-everyday']->url().'"', false);
    }

    public function test_mobile_navigation_exposes_the_same_taxonomy_accessibly(): void
    {
        $categories = $this->taxonomy();

        $content = $this->get(route('home'))->assertOk()->getContent();
        $mobile = Str::between($content, 'id="mobile-navigation"', '</nav>');

        $this->assertStringContainsString('href="'.$categories['school-everyday']->url().'"', $mobile);
        $this->assertStringContainsString('id="mobile-subcategories-school-everyday"', $mobile);
        $this->assertStringContainsString('href="'.$categories['personalised-water-bottles-for-kids']->url().'"', $mobile);
        // Subcategories sit behind a labelled, expandable control rather than
        // being dumped into one long list.
        $this->assertStringContainsString('aria-controls="mobile-subcategories-school-everyday"', $mobile);
        $this->assertStringContainsString('aria-label="Toggle School &amp; Everyday subcategories"', $mobile);
    }

    public function test_footer_links_the_four_top_level_categories(): void
    {
        $categories = $this->taxonomy();

        $content = $this->get(route('home'))->assertOk()->getContent();
        $footer = Str::between($content, '<footer', '</footer>');

        foreach (['school-everyday', 'memories-keepsakes', 'pets-family', 'gifts-occasions'] as $slug) {
            $this->assertStringContainsString('href="'.$categories[$slug]->url().'"', $footer);
        }

        // The footer stays compact: no subcategories, and existing columns remain.
        $this->assertStringNotContainsString($categories['personalised-wall-prints']->url(), $footer);
        $this->assertStringContainsString('Customer Service', $footer);
        $this->assertStringContainsString('About Us', $footer);
    }

    public function test_category_page_renders_its_copy_and_children(): void
    {
        $categories = $this->taxonomy();
        $category = $categories['school-everyday'];
        $category->update(['short_description' => 'Make everyday things feel completely theirs.']);

        $this->get($category->url())->assertOk()
            ->assertSee('<h1 class="font-display text-2xl sm:mt-4 sm:text-6xl">School &amp; Everyday</h1>', false)
            ->assertSee('Make everyday things feel completely theirs.')
            ->assertSee('Shop School &amp; Everyday', false)
            ->assertSee('Personalised Water Bottles for Kids')
            ->assertSee('Personalised Pencil Tins')
            ->assertSee('BreadcrumbList')
            // An empty category must not show a broken or empty product grid.
            ->assertDontSee('No products found')
            ->assertDontSee('Featured products');
    }

    public function test_category_page_shows_featured_products_aggregated_from_children_without_duplicates(): void
    {
        $categories = $this->taxonomy();
        $product = Product::factory()->create(['name' => 'Shared Bottle']);
        // The same product in two children of the same category must appear once.
        $product->categories()->attach([
            $categories['personalised-water-bottles-for-kids']->id,
            $categories['personalised-pencil-tins']->id,
        ]);

        $content = $this->get($categories['school-everyday']->url())->assertOk()->getContent();

        $this->assertSame(1, substr_count($content, 'href="'.route('products.show', $product->slug).'"'));
    }

    public function test_homepage_shows_each_category_as_a_circle_with_its_name_beneath(): void
    {
        $categories = $this->taxonomy();
        $categories['school-everyday']->update([
            'image_disk' => 'public',
            'image_storage_key' => 'categories/school-everyday.jpg',
        ]);

        $content = $this->get(route('home'))->assertOk()->getContent();
        $section = Str::between($content, 'Where would you like to start?', '</section>');

        // One circular frame per category, each holding its image.
        $this->assertSame(4, substr_count($section, 'aspect-square w-full overflow-hidden rounded-full'));
        $this->assertStringContainsString('categories/school-everyday.jpg', $section);
        $this->assertStringContainsString('object-cover', $section);
        // The name sits beneath the circle; the image itself is decorative.
        $this->assertStringContainsString('<h3 class="mt-6 font-display text-2xl transition group-hover:text-coral">School &amp; Everyday</h3>', $section);
        $this->assertStringContainsString('alt=""', $section);
        // The descriptions belong on the category pages, not under the circles.
        $this->assertStringNotContainsString($categories['school-everyday']->short_description, $section);
    }

    public function test_a_top_level_circle_falls_back_to_a_product_photo_from_its_subcategories(): void
    {
        $categories = $this->taxonomy();
        $product = Product::factory()->create();
        $product->images()->create(['disk' => 'public', 'storage_key' => 'demo/bottle.svg', 'alt_text' => 'Bottle', 'sort_order' => 0]);
        // Products hang off leaf subcategories, never the top-level category.
        $product->categories()->attach($categories['personalised-water-bottles-for-kids']);

        $section = Str::between(
            $this->get(route('home'))->assertOk()->getContent(),
            'Where would you like to start?',
            '</section>',
        );

        $this->assertStringContainsString('demo/bottle.svg', $section);
    }

    public function test_the_how_it_works_section_has_a_background(): void
    {
        $this->get(route('home'))->assertOk()
            ->assertSee('home-how-it-works', false)
            ->assertSee('One photo. Four little steps.');
    }

    public function test_category_page_lists_its_subcategories_as_pills(): void
    {
        $categories = $this->taxonomy();
        $category = $categories['school-everyday'];

        $content = $this->get($category->url())->assertOk()->getContent();
        $nav = Str::between($content, 'id="shop-school-everyday"', '</nav>');

        // Same horizontal, scrollable pill treatment as the shop page's chip row.
        foreach (['personalised-water-bottles-for-kids', 'personalised-pencil-tins'] as $slug) {
            $this->assertStringContainsString('href="'.$categories[$slug]->url().'"', $nav);
        }
        $this->assertStringContainsString('rounded-full border border-rose/30', $nav);
        $this->assertStringContainsString('overflow-x-auto', $nav);
        // Pills carry the name only — no imagery, no description.
        $this->assertStringNotContainsString('<img', $nav);
        $this->assertStringNotContainsString($categories['personalised-water-bottles-for-kids']->short_description, $nav);
    }

    public function test_subcategory_renders_copy_breadcrumb_and_sibling_links(): void
    {
        $categories = $this->taxonomy();
        $child = $categories['personalised-water-bottles-for-kids'];
        $child->update(['short_description' => 'Make their water bottle impossible to mistake.']);

        $this->get($child->url())->assertOk()
            ->assertSee('<h1 class="font-display text-2xl sm:mt-4 sm:text-6xl">Personalised Water Bottles for Kids</h1>', false)
            ->assertSee('Make their water bottle impossible to mistake.')
            ->assertSee('aria-label="Breadcrumb"', false)
            ->assertSee('href="'.$categories['school-everyday']->url().'"', false)
            ->assertSee('You may also like')
            ->assertSee('href="'.$categories['personalised-pencil-tins']->url().'"', false);
    }

    public function test_headings_and_intro_copy_are_not_hidden_on_mobile(): void
    {
        $categories = $this->taxonomy();
        $child = $categories['personalised-water-bottles-for-kids'];
        $child->update(['short_description' => 'Make their water bottle impossible to mistake.']);
        $parent = $categories['school-everyday'];
        $parent->update(['short_description' => 'Make everyday things feel completely theirs.']);

        // These are SEO and paid-search landing pages: a mobile visitor, and
        // mobile-first indexing, must both get the H1 and the intro. Only the
        // eyebrow is allowed to drop on small screens.
        foreach ([$parent, $child] as $category) {
            $content = $this->get($category->url())->assertOk()->getContent();

            $this->assertSame(1, preg_match('/<div class="([^"]*max-w-[^"]*)"><p class="eyebrow/', $content, $wrapper));
            $this->assertStringNotContainsString('hidden', $wrapper[1], 'The heading block must not be hidden on mobile.');

            $this->assertSame(1, preg_match('/<h1 class="([^"]*)"/', $content, $h1));
            $this->assertStringNotContainsString('hidden', $h1[1], 'The H1 must not be hidden on mobile.');

            $this->assertStringContainsString($category->short_description, $content);
        }
    }

    public function test_the_mobile_see_more_toggle_never_removes_copy_from_the_html(): void
    {
        $categories = $this->taxonomy();
        $intro = 'Make everyday things feel completely theirs. Turn their photo into their own character and add it to personalised school essentials.';
        $category = $categories['school-everyday'];
        $category->update(['short_description' => $intro, 'meta_description' => null]);

        $content = $this->get($category->url())->assertOk()->getContent();

        // The clamp is presentational only: the whole intro stays in the markup,
        // so it is still indexed and still feeds the meta description.
        $this->assertStringContainsString($intro, $content);
        $this->assertStringContainsString('<meta name="description" content="'.$intro.'">', $content);
        $this->assertStringContainsString('See more', $content);
        $this->assertStringContainsString('See less', $content);

        // Clamping must be a static class, not applied by Alpine after paint,
        // otherwise the copy renders in full and collapses — shifting the layout.
        $this->assertStringContainsString('class="line-clamp-2 leading-7', $content);
        $this->assertStringNotContainsString(':class="expanded ? \'\' : \'line-clamp-2', $content);
    }

    public function test_empty_subcategory_shows_a_friendly_state_rather_than_looking_broken(): void
    {
        $categories = $this->taxonomy();

        $this->get($categories['birthday-gifts-for-kids']->url())->assertOk()
            ->assertSee('We’re adding this collection soon.', false)
            ->assertSee('Browse all gifts')
            ->assertDontSee('No products found');
    }

    public function test_supporting_content_renders_only_when_present(): void
    {
        $categories = $this->taxonomy();
        $child = $categories['personalised-wall-prints'];

        $this->get($child->url())->assertOk()->assertDontSee('border-t border-rose/25 pt-10 leading-8 text-muted', false);

        $child->update(['description' => 'A longer editorial paragraph about wall prints.']);
        $this->get($child->url())->assertOk()->assertSee('A longer editorial paragraph about wall prints.');
    }

    public function test_navigation_query_count_does_not_grow_with_the_taxonomy(): void
    {
        $this->taxonomy();
        $small = $this->countCatalogueQueries();

        // Triple the taxonomy, including products so the image fallback has work to do.
        foreach (range(1, 8) as $i) {
            $parent = ProductCategory::factory()->create(['slug' => "extra-parent-{$i}", 'sort_order' => 10 + $i]);

            foreach (range(1, 4) as $j) {
                $child = ProductCategory::factory()->create(['slug' => "extra-child-{$i}-{$j}", 'parent_id' => $parent->id]);
                $product = Product::factory()->create();
                $product->images()->create(['disk' => 'public', 'storage_key' => "demo/{$i}-{$j}.svg", 'alt_text' => '', 'sort_order' => 0]);
                $product->categories()->attach($child);
            }
        }

        // The whole point: resolving names, URLs and fallback imagery for a much
        // larger tree must cost exactly the same number of queries.
        $this->assertSame($small, $this->countCatalogueQueries());
    }

    private function countCatalogueQueries(): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('home'))->assertOk();
        $count = collect(DB::getQueryLog())
            ->filter(fn ($query) => str_contains($query['query'], 'product_categor'))
            ->count();
        DB::disableQueryLog();

        return $count;
    }
}
