<?php

namespace App\Filament\Resources\ProductCategories\Schemas;

use App\Models\ProductCategory;
use App\Support\ReservedSlugs;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Placeholder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ProductCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Category')->schema([
                TextInput::make('name')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn ($state, $set, $get) => filled($get('slug')) ? null : $set('slug', Str::slug((string) $state))),

                TextInput::make('slug')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->live(onBlur: true)
                    ->helperText('Lowercase words separated by single hyphens.')
                    ->rule(fn (Get $get) => function (string $attribute, $value, \Closure $fail) use ($get) {
                        if (preg_match('/^'.ReservedSlugs::SLUG_PATTERN.'$/', (string) $value) !== 1) {
                            $fail('The slug must be lowercase words separated by single hyphens.');

                            return;
                        }

                        // Top-level categories own the first path segment, so their
                        // slug must not collide with a real application route.
                        if (blank($get('parent_id')) && ReservedSlugs::has((string) $value)) {
                            $fail("\"{$value}\" is reserved by the site and cannot be a top-level category slug.");
                        }
                    }),

                Select::make('parent_id')
                    ->label('Parent category')
                    ->relationship(
                        name: 'parent',
                        titleAttribute: 'name',
                        // Only top-level records may be chosen, which makes a third
                        // level impossible to create through the form.
                        modifyQueryUsing: fn (Builder $query, ?ProductCategory $record) => $query
                            ->whereNull('parent_id')
                            ->when($record, fn (Builder $query) => $query->whereKeyNot($record->getKey()))
                            ->orderBy('sort_order')->orderBy('name'),
                    )
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->live()
                    ->placeholder('None — this is a top-level category')
                    // A category that already has children must stay top level.
                    ->disabled(fn (?ProductCategory $record): bool => (bool) $record?->children()->exists())
                    ->dehydrated(fn (?ProductCategory $record): bool => ! $record?->children()->exists())
                    ->helperText(fn (?ProductCategory $record): string => $record?->children()->exists()
                        ? 'This category has subcategories, so it must stay top level.'
                        : 'Leave empty for a top-level category. Only two levels are supported.')
                    ->rule(fn (?ProductCategory $record) => function (string $attribute, $value, \Closure $fail) use ($record) {
                        if (blank($value)) {
                            return;
                        }
                        if ($record && $value === $record->getKey()) {
                            $fail('A category cannot be its own parent.');
                        }
                        if (ProductCategory::query()->whereKey($value)->whereNotNull('parent_id')->exists()) {
                            $fail('That category is already a subcategory. Only two levels are supported.');
                        }
                        if ($record?->children()->exists()) {
                            $fail('This category has subcategories and cannot become a subcategory itself.');
                        }
                    }),

                Placeholder::make('url_preview')
                    ->label('Canonical URL')
                    ->content(function (Get $get): string {
                        $slug = trim((string) $get('slug'));

                        if ($slug === '') {
                            return '—';
                        }

                        $parentSlug = filled($get('parent_id'))
                            ? ProductCategory::query()->whereKey($get('parent_id'))->value('slug')
                            : null;

                        return ProductCategory::urlFor($parentSlug, $slug);
                    }),

                TextInput::make('sort_order')->required()->numeric()->default(0),
                Toggle::make('is_active')->label('Published')->default(true),
            ])->columns(2)->columnSpanFull(),

            Section::make('Content and SEO')->schema([
                Textarea::make('short_description')
                    ->label('Short description / landing intro')
                    ->rows(3)
                    ->helperText('Shown under the heading on the category page, and used as the meta description fallback.')
                    ->columnSpanFull(),
                Textarea::make('description')
                    ->label('Supporting content')
                    ->rows(6)
                    ->helperText('Optional. Rendered below the products; leave empty to omit the section entirely.')
                    ->columnSpanFull(),
                TextInput::make('meta_title')->maxLength(255),
                Textarea::make('meta_description')->maxLength(160)->rows(2),
            ])->columns(2)->columnSpanFull(),

            Section::make('Card image')->schema([
                FileUpload::make('image_storage_key')
                    ->label('Image')
                    ->image()
                    ->disk('public')
                    ->directory('categories')
                    ->visibility('public')
                    ->imageEditor()
                    ->maxSize(4096)
                    ->helperText('Optional. Shown beside the copy on category cards. If empty, the first product in this category supplies the image; if there is no product either, a neutral block is shown.')
                    // Keep image_disk consistent with the column the file actually landed on.
                    ->afterStateUpdated(fn ($state, $set) => $set('image_disk', $state ? 'public' : null))
                    ->columnSpanFull(),
                TextInput::make('image_alt_text')
                    ->label('Alt text')
                    ->maxLength(255)
                    ->helperText('Leave empty when the image is purely decorative — the category name is already the card heading.')
                    ->columnSpanFull(),
                Hidden::make('image_disk'),
            ])->columnSpanFull(),
        ]);
    }
}
