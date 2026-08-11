# Composed designs

`ComposedDesign` is the immutable rendered-design record between an AI `GenerationAsset` and a future production `PrintAsset`. Each render snapshots the artwork session, variant, personalisation, template identity/version, target dimensions, and private file keys. Rerenders always create a new row and new files.

`RenderComposedDesign` currently supports only the normalized `bottle-wrap-v1` layer vocabulary: `solid`, `personalisation_text_pattern`, and `generation_asset`. Output dimensions are resolved from the selected variant's active Prodigi mapping and requested print area. Missing dimensions are an error; there is no default output size.

Version 3 uses a fixed editorial pattern of normalized text placements modelled on the bottle reference artwork. Each item selects one of the deliberate `bold`, `serif`, or `script` roles and defines its own size, rotation, x, and y. The solid layer maps `variant.options.colour` to a fixed background tone. The top-level `character` block controls `scale`, `offset_x`, `offset_y`, normalized centre coordinates, and maximum bounds; the renderer clamps the box to the canvas and always uses `contain`.

Full PNGs and WebP previews are stored on the private `local` disk below `artwork-sessions/{public_id}/composed-designs/`. Both are served only through the artwork-session ownership boundary. Preview derivatives have a maximum edge of 1200 pixels.

## Server-side type

The v2 bottle pattern resolves three offline roles: script, serif bold, and sans bold. Project-local `Cattie-*.ttf` files take precedence; the current development environment uses Segoe Script, Georgia Bold, and Arial Bold, with DejaVu equivalents on Linux. Missing offline fonts fail the render safely. The renderer makes no remote font request and requires no rendering framework. Any future redistributable binaries and their licences belong in `resources/fonts/` and do not require template changes.
