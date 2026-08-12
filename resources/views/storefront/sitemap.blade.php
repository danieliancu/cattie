{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach($staticUrls as $url)
    <url><loc>{{ $url }}</loc></url>
@endforeach
@foreach($categories as $category)
    <url><loc>{{ route('categories.show', $category) }}</loc><lastmod>{{ $category->updated_at->toAtomString() }}</lastmod></url>
@endforeach
@foreach($products as $product)
    <url><loc>{{ route('products.show', $product->slug) }}</loc><lastmod>{{ $product->updated_at->toAtomString() }}</lastmod></url>
@endforeach
</urlset>
