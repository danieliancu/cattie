<?php

namespace App\Exceptions;

use DomainException;

/**
 * Safety backstop for writes that bypass the Filament form entirely — seeders,
 * tinker, CLI commands and domain actions. Normal admin validation happens in
 * the form before save, so in day-to-day use this should never be thrown.
 */
class InvalidCategoryHierarchyException extends DomainException
{
    public static function selfParent(): self
    {
        return new self('A category cannot be its own parent.');
    }

    public static function missingParent(string $parentId): self
    {
        return new self("Parent category [{$parentId}] does not exist.");
    }

    public static function depthExceeded(string $parentName): self
    {
        return new self("Categories may only be two levels deep: [{$parentName}] is already a subcategory.");
    }

    public static function parentHasChildren(string $name): self
    {
        return new self("[{$name}] has subcategories and cannot itself become a subcategory.");
    }

    public static function reservedSlug(string $slug): self
    {
        return new self("The slug [{$slug}] is reserved by the application and cannot be used for a top-level category.");
    }

    public static function invalidSlug(string $slug): self
    {
        return new self("The slug [{$slug}] must be lowercase words separated by single hyphens.");
    }

    public static function hasChildren(string $name): self
    {
        return new self("[{$name}] cannot be deleted while it still has subcategories.");
    }
}
