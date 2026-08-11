# Cattie.uk architecture

## Recommendation and boundaries

Cattie.uk starts as a modular Laravel monolith. HTTP endpoints, queue workers and scheduled tasks deploy from one codebase and share one relational database. Domain behaviour lives in small actions and provider-neutral contracts; controllers validate and delegate. This is the fastest production-capable shape and preserves transactions around checkout. The strongest alternative is separate AI/commerce services, but their operational and consistency cost is unjustified until independent scaling is measured.

Code is grouped by business capability under `app/Domain`, with framework delivery code in Laravel's normal `Http`, `Jobs`, `Events`, `Listeners`, `Notifications`, and `Policies` directories. This keeps Laravel conventions visible without creating a package-per-domain abstraction.

- `Catalogue`: Product, ProductVariant, ProductImage, ProductPersonalisationField, ArtworkStyle, FulfilmentProductMapping.
- `Artwork`: Upload, Generation, GenerationAsset, prompt resolution, approval and generation limits.
- `Commerce`: Cart, CartItem, pricing snapshots, Order, OrderItem and audited transitions.
- `Payments`, `Print`, `Fulfilment`: provider contracts and lifecycle records.
- `Analytics`: append-only business events. Product analytics can later fan out to an external platform.
- `Admin`: policies and operational actions over the same domain, not a second domain model.

## Data model

Users own carts, uploads and orders; all customer-facing/workflow records use ULIDs. Products have images, variants, personalisation definitions and permitted artwork styles. Variants have provider mappings, keeping supplier SKUs outside commerce. Uploads are private objects and can feed many immutable generations. A generation records its resolved prompt/provider/model/cost and owns generated assets; one asset may be approved without overwriting history.

A cart owns items that retain product/variant, personalisation JSON, selected artwork, quantity and current price. Checkout copies immutable commercial, personalisation and artwork snapshots to order items. Orders own payments, print assets, fulfilment submissions, shipments and append-only state transitions. Webhook events are uniquely identified by provider plus external event id. Analytics events are append-only and may reference a session, user and subject.

Money is stored as integer minor units plus ISO currency. AI cost uses integer micros because sub-penny precision matters. Flexible provider payloads and product configuration use JSON, while fields involved in integrity or queries remain columns.

## Status model

Separate generation, payment, print, fulfilment, shipment and webhook statuses prevent one giant combinatorial state. `OrderStatus` remains the customer/operations summary and changes only through an explicit transition action that writes `order_status_transitions` in the same transaction.

## Customer journey and backend operations

1. Catalogue reads active products, variants and styles.
2. Upload validates content, strips unsafe metadata during processing, stores the original privately under a random key, records retention, and emits `upload.completed`.
3. Generation creation enforces session/customer limits, resolves a versioned prompt, creates a Pending generation, dispatches a unique queued generation job, and records analytics.
4. The provider adapter runs server-side; results become immutable assets with browser previews. Regeneration creates another generation.
5. Approval atomically marks exactly one asset and records the event. Product personalisation is validated from server-side definitions.
6. Cart pricing is calculated server-side. Checkout snapshots prices and content into an order.
7. A payment provider creates the intent. Only a validated, persisted and idempotently processed webhook can mark payment succeeded and transition the order to Paid.
8. Payment success dispatches print preparation. A validated print asset dispatches fulfilment once, guarded by a unique idempotency key.
9. Fulfilment webhooks update production/shipment records idempotently and notifications are queued.

## Async work and events

Jobs: process upload, generate artwork, create preview derivatives, build/validate print asset, submit fulfilment order, process persisted webhook, send notifications, purge expired uploads. Jobs use bounded retries/backoff, stable idempotency keys and structured context without image contents or personal data.

Domain events: UploadCompleted, GenerationStarted/Succeeded/Failed, ArtworkApproved, CheckoutStarted, PaymentCompleted, PrintAssetReady, FulfilmentSubmitted, OrderShipped. Events decouple analytics/notifications and dispatch after database commit when consumers require committed data.

## Provider contracts

- `ImageGenerationProvider`: generate, poll status where required, and download result using provider-neutral DTOs.
- `PaymentProvider`: create payment, retrieve/refund it, validate and normalise webhooks.
- `FulfilmentProvider`: create/get/cancel an order and validate/normalise webhooks.

Only provider adapters know external IDs, request shapes and SKU mappings. Contracts are useful at volatile external boundaries; repository interfaces around Eloquent would be unnecessary complexity.

### Prodigi catalogue and quote boundary

Prodigi Product Details and Quotes are accessed through `App\Integrations\Prodigi`. The low-level client owns authentication, transport, safe failures and JSON decoding; product and quote services map provider responses into immutable DTOs. The existing `FulfilmentProvider` contract remains limited to order lifecycle operations and is not used by these read/price-discovery operations.

`FulfilmentProductMapping` connects a Cattie variant to a Prodigi SKU and the exact provider attributes needed for that variant. Supplier responses and quotes are not copied into core product or order tables. For manual Sandbox verification, run `php artisan prodigi:product 650ML-WATER-BOTTLE`, followed by `php artisan prodigi:quote 650ML-WATER-BOTTLE --country=GB --color=black`. These commands never create or submit an order.

### Product design and asset boundaries

Official supplier imagery stays grouped by supplier SKU under `resources/product-assets/prodigi`. Its application roles are declared separately in `config/product-assets.php`; technical mockup sources are not catalogue images. Reusable Cattie design definitions live under `resources/product-designs/{template-key}` and are registered by `ProductDesignTemplate`, independently of any supplier SKU. A product may select a design template, while its selected variant resolves supplier attributes and required print-area dimensions through `FulfilmentProductMapping`.

`GenerationAsset` is an immutable AI illustration. A future `ComposedDesign` will combine that illustration, customer personalisation, and a versioned product design template into flat artwork at the selected variant's required resolution. A future `ProductMockup` will place a composed design onto supplier product photography. These are separate artifacts: customers will ultimately approve the composed design, and neither composed designs nor product mockups should be stored as generation assets. Customer uploads and generated files remain private runtime assets under the existing artwork/session ownership model; no customer-specific content belongs in `resources`.

## Security and privacy

Original photographs use a private disk, random object keys, content-derived MIME verification, size/pixel limits, and signed short-lived access after ownership checks. Downloads never expose storage paths. Processing should remove metadata from derived files. Records include expiry/deletion timestamps and a scheduled purge supports the documented retention policy and erasure requests. Logs exclude payload images, prompts containing personal data, addresses and provider secrets.

CSRF, secure sessions, policies, admin gates/MFA, encrypted transport, secrets outside source, least-privilege storage, rate limits and per-session generation budgets are required. Webhooks require raw-body signature verification, timestamp tolerance, unique event IDs and persisted processing outcomes. Payment card data never enters Cattie systems. Production adds backups, restoration drills, dependency scanning, CSP and privacy/consent documentation, including a DPIA because children's photographs may be processed.

## MVP boundary and delivery order

MVP includes one UK storefront, GBP, guest-capable personalisation, two configured styles, one AI adapter, one payment adapter, one fulfilment adapter, private uploads, preview/approval, cart/checkout, print pipeline, webhooks, notifications and a minimal authorised operations area.

Useful later: additional providers, prompt experiment UI, customer accounts/order history, automated abuse scoring, richer BI and multi-region storage. Unnecessary now: microservices, provider marketplaces, subscriptions, multi-currency, a prompt CMS, generic repositories and a SPA.

Implementation order follows dependencies: (1) foundation/schema/state contracts, (2) catalogue, (3) upload/generation/approval, (4) cart/checkout, (5) payment, (6) print pipeline, (7) fulfilment, (8) admin, (9) security/privacy/operational hardening. Each phase ends with migrations and risk-focused automated tests.

## Phase 3 AI generation

Production previews use OpenAI GPT Image 2 through `ImageGenerationProvider`; local and tests default to the deterministic fake adapter. Initial settings are medium quality, portrait 1024x1536, one candidate, and at most three immutable generations per artwork session. Storybook and hand-drawn prompts are code-backed and versioned.

The provider result is an artwork source plus a private browser preview, never a print-ready asset. Usage, model, quality, size, prompt version, provider request ID, pricing version and frozen actual/estimated cost are retained per generation. The £0.06–£0.08 range remains a planning target, not billing logic. Medium is the commercial starting point because likeness and approval rate matter more than isolated request cost.
