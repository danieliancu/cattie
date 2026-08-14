# Cattie.uk — raport tehnic viu al aplicației

> **Statutul documentului:** sursa vie de adevăr pentru starea tehnică și funcțională a aplicației.
>
> **Regulă de întreținere:** acest fișier trebuie actualizat la fiecare modificare care schimbă funcționalitatea, arhitectura, schema de date, configurarea, interfața, asseturile, integrările, testele sau limitările cunoscute. O schimbare nu este considerată documentată complet până când secțiunile afectate și jurnalul de la final reflectă noua stare.
>
> **Ultima actualizare:** 14 august 2026.

## 1. Rezumat executiv

Cattie.uk este o aplicație e-commerce pentru produse personalizate. Clientul alege produsul, varianta și stilul artistic, încarcă o fotografie, primește un personaj generat cu AI, îl poziționează într-un design de produs, aprobă rezultatul, îl adaugă în basket și finalizează checkout-ul.

Aplicația include în prezent:

- storefront public, catalog, categorii, căutare și pagini informaționale;
- configurare produs, upload privat și sesiuni guest fără cont obligatoriu;
- generare AI reală prin OpenAI sau provider fake pentru dezvoltare/testare;
- două stiluri: `storybook-cartoon` și `hand-drawn`;
- eliminare locală a fundalului și generare de preview WebP;
- renderer server-side WYSIWYG pentru designurile de produs;
- editor pentru mutarea și redimensionarea personajului;
- PNG transparent, full-resolution, cu metadata 300 PPI pentru tipografie;
- basket, checkout UK și plăți fake;
- modele și stări pentru producție/fulfilment;
- integrare de catalog și quotes Prodigi și webhook-uri TreatPod;
- panou administrativ Filament;
- audit, analytics, retenție și curățare periodică.

Pipeline-ul de fulfilment nu este complet: fișierul de tipar există și este legat de comandă prin `ComposedDesign`, dar nu este încă materializat ca `PrintAsset` și nu este trimis automat către TreatPod sau Prodigi după plată.

## 2. Stack tehnologic și limbaje

### Backend

- PHP `^8.2`; mediul local verificat rulează PHP `8.2.24`.
- Laravel `12.x`.
- Eloquent ORM, migrations, database queue, scheduler, service container și Blade.
- Filament `5.7` pentru administrare.
- GD pentru citire, compoziție, redimensionare, alpha, text TrueType, WebP și PNG.
- SQLite în configurația locală implicită; schema este construită prin migrations Laravel.
- PHPUnit `11.5` pentru testare.

### Frontend

- Blade pentru rendering server-side.
- JavaScript ES modules.
- Alpine.js `3.15` pentru stare și interacțiuni în browser.
- Tailwind CSS `4` prin pluginul Vite.
- Vite `6` pentru build și HMR.
- Lucide pentru iconografie.
- Axios este instalat; bootstrap-ul frontend îl configurează pentru utilizare generală.

### Alte formate și tehnologii

- JSON pentru template-uri de design, configurații și snapshot-uri.
- SQL este generat și administrat prin migrations/Eloquent.
- Python este invocat extern pentru modelul local de background removal; nu există logică principală Python în repository.
- PNG, WebP, JPEG și SVG sunt folosite pentru artwork și marketing.
- TTF și licențe OFL sunt incluse local în template-urile printabile.
- Markdown este folosit pentru documentație.

## 3. Structura proiectului

```text
app/
├── Console/Commands        comenzi de mentenanță, smoke tests și integrări
├── Contracts               interfețe pentru AI, plăți și background removal
├── Domain                  acțiuni de business grupate pe domenii
│   ├── Admin
│   ├── Artwork
│   ├── Cart
│   ├── Catalogue
│   ├── Fulfilment
│   ├── Orders
│   └── Payments
├── Enums                   stări persistente și tipuri de domeniu
├── Filament                resurse, pagini și widgets admin
├── Http                    controllere storefront și webhook-uri
├── Integrations/Prodigi    client, DTO-uri, produse și quotes
├── Jobs                    normalizare, generare artwork și sync provider
├── Models                  modelele Eloquent
├── Providers               bindings și providerii AI/plăți
├── Services                prompt builder, background removal etc.
└── Support                 guest context și suport transversal

resources/
├── ai/style-references     imagini de referință artistică versionate
├── css/app.css             tema Tailwind și componentele globale
├── js/app.js               Alpine și Lucide
├── product-assets          asseturi furnizori/marketing
├── product-designs         template-uri JSON și fonturi locale
└── views                   storefront Blade

database/
├── factories
├── migrations
└── seeders

storage/app/private         uploaduri, generări și designuri private
storage/app/public          asseturi publice de catalog
tests/Feature              teste Admin, Artwork, Catalogue, Commerce etc.
```

## 4. Design vizual, fonturi și asseturi

### Storefront

Fonturile încărcate din Bunny Fonts sunt:

- **DM Sans** — font sans-serif principal;
- **Fraunces** — titluri, branding și elemente display.

Tokenurile principale Tailwind:

| Token | Valoare |
|---|---:|
| cream | `#fffaf3` |
| ink | `#302c2a` |
| muted | `#716966` |
| coral | `#d76955` |
| rose | `#e8b7ad` |
| blush | `#f3ded7` |
| sand | `#eee2d3` |
| sage | `#a9bca7` |
| sky | `#bcdade` |

Componente CSS reutilizabile: `shell`, `brand`, `nav-link`, `skip-link`, `eyebrow`, `button-primary`, `section-heading`, `selection-card`, `style-card`, `form-label`, `form-control`.

Interfața include focus rings, skip link, stări hover/focus, layout responsive și `x-cloak` pentru evitarea flash-urilor Alpine.

### Fonturi de tipar

Fiecare template printabil include local fonturile și licențele sale:

- **Anton Regular** — text condensat, de regulă uppercase;
- **Bodoni Moda 18pt Regular** — serif display;
- **Fredoka SemiBold** — display rotunjit;
- **Allura Regular** — script/caligrafic.

Rendererul folosește fișierele TTF locale, nu fonturile web, pentru rezultate deterministe și reproductibile offline.

### Referințe AI

- `storybook-cartoon-v3.png`
- `storybook-cartoon-v4.png`
- `hand-drawn-v3.png`
- `hand-drawn-v4.png`

Configurația păstrează SHA-256, MIME și rolul `style_only`. Prompturile curente sunt `v5`, dar referințele vizuale rămân intenționat `v4`.

## 5. Catalog și produse

Catalogul seed-uit include produse demo și produse cu integrare/template real:

- Children's Storybook Wall Print;
- Our Family Art Print;
- Little Moments Mug;
- Cuddle Close Cushion;
- Best Friend Pet Portrait;
- Cattie Water Bottle, 650 ml;
- Water Bottle with Red Flip Lid, 750 ml;
- Small Plastic Lunchbox;
- Personalised Stationery & Pencil Tin.

Categorii definite: `School & Lunch`, `Kids Drinkware`, `School Accessories`.

Produsele reale templated au variante, SKU intern, mapping de fulfilment, rezoluții exacte de tipar, galerii pe variantă și personalizare. Exemple importante:

- Red Flip Bottle: `2717 × 2008 px`, 300 PPI, suprafață declarată `230 × 170 mm`;
- Lunchbox: `1949 × 1205 px`, 300 PPI, suprafață `165 × 102 mm`;
- Pencil Tin: `2185 × 898 px`, 300 PPI, suprafață `185 × 76 mm`.

Providerii de catalog mappați sunt Prodigi și TreatPod. O variantă publicabilă trebuie să aibă exact un mapping activ și o rezoluție validă pentru print area.

## 6. Fluxul artwork și AI

### Stările sesiunii

```text
awaiting_upload
→ preparing_photo
→ generating
→ preview_ready
→ approved
```

Stări terminale alternative: `failed`, `expired`.

Etapele vizibile de procesare:

1. `Preparing your photo…` — 10%;
2. `Creating your illustration…` — 30%;
3. `Removing the background…` — 55%;
4. `Preparing your preview…` — 80%;
5. `Your artwork is ready` — 100%.

Browserul face polling la 3 secunde implicit. Bara avansează continuu între praguri, dar nu ajunge la 100% înainte de `ready`.

### Modalul de progres

- blochează scrollul documentului cât este montat;
- desktop: fundal negru complet, fără fotografie și fără diamonduri;
- mobil: fotografia originală în fundal, overlay negru 80% și diamonduri colorate doar pe margini;
- toate etapele au checkbox și bifă verde la finalizare;
- la succes rulează confetti și apare `View Your Design`;
- cancel, retry și upload nou rămân disponibile conform stării;
- la navigare sunt restaurate stilurile de scroll ale documentului.

### Upload și normalizare

- formate acceptate: JPEG, PNG, WebP;
- maximum implicit: 10 MB;
- dimensiuni acceptate: 512–8000 px;
- normalizare până la maximum 2048 px;
- orientarea și inputul normalizat sunt stocate privat;
- accesul la fotografia originală și asseturi este protejat prin tokenul sesiunii.

### Generare AI

Provideri:

- `fake` pentru dezvoltare și teste deterministe;
- `openai` pentru Image Edit, implicit model `gpt-image-2`, quality `medium`, `1024x1536`.

Fotografia clientului este trimisă prima, iar referința artistică a doua și este declarată strict `style_only`.

Prompturile `v5` pentru ambele stiluri:

- prioritizează identitatea, părul, culoarea ochilor când este vizibilă, vârsta aproximativă, hainele și trăsăturile distinctive;
- păstrează postura dacă fotografia arată clar o postură completă și utilizabilă;
- folosesc postura frontală neutră cu brațele relaxate pentru bust, crop, corp ascuns sau postură ambiguă;
- păstrează natural dispozitivele și poziționările asociate mobilității;
- solicită subiect complet, izolat, fără text, logo, decor sau umbre de fundal când produsul cere acest lucru;
- interzic copierea identității sau posturii din referința de stil.

### Background removal și asseturi generate

După provider:

1. rezultatul original este păstrat privat;
2. modelul local `isnet-general-use` elimină fundalul;
3. rezultatul transparent devine `composition_source` PNG;
4. se produce un `web_preview` WebP, maximum 1400 px;
5. pentru produsele templated se generează automat preview-ul de design.

Jobul are protecție la retry, curăță fișierele parțiale și salvează costul/usage metadata când sunt disponibile.

## 7. Template-uri și randare WYSIWYG

Template-uri existente:

- `bottle-wrap-v1`;
- `red-flip-bottle-wrap-v1`;
- `small-lunchbox-v1`;
- `stationery-pencil-tin-v1`.

Schema folosește coordonate normalizate, `output_size`, `safe_zones`, `character`, `character_clip`, `preview_surface` și `layers`.

### Manifest înghețat

Serverul generează un `ResolvedDesignManifest` pentru fiecare `ComposedDesign`. Acesta păstrează template-ul și versiunea, varianta, configurația rezolvată, suprafața personajului și clipping-ul. `render_fingerprint` include manifestul, assetul, personalizarea și ajustările personajului.

Fingerprint-ul împiedică aprobarea sau adăugarea în basket a unui preview depășit.

### Clipping

- `canvas`: personajul este tăiat doar la marginile canvasului total;
- `custom`: este aplicat un dreptunghi normalizat explicit;
- `characterBox` stabilește poziția și mărimea inițială, nu este implicit mască de clipping;
- modurile necunoscute și dreptunghiurile invalide sunt respinse la validarea template-ului.

### Fundaluri

- rendererul începe întotdeauna de la canvas complet transparent;
- layerele `solid` full-canvas nu sunt permise în producție;
- culoarea/substratul produsului este `preview_surface`, vizibil doar în UI;
- `preview_surface` nu intră în PNG-ul tipografiei;
- Water Bottle with Red Flip Lid, lunchbox și pencil tin produc artwork transparent.

### Editorul personajului

- chenarul și cele șase mânere sunt active permanent; nu mai există buton `Adjust character`;
- drag pentru mutare și resize proporțional;
- feedback instant în browser;
- la pointer-up serverul validează și generează preview nou cu rendererul real;
- Add to basket rămâne blocat până când fingerprint-ul serverului corespunde stării curente;
- personajul poate fi coborât până când rămân vizibile marginea și mânerele superioare; UI rezervă 18 px, iar serverul folosește aceeași limită geometrică;
- schimbarea numelui sau variantei produce un nou preview și fingerprint fără un nou apel AI.

## 8. Fișierele de randare

### Randare intermediară

```text
ComposedDesign preview
├── storage_key: null
├── preview_storage_key: WebP transparent, max 1200 px
└── editor_background_storage_key: WebP fără personaj
```

PNG-ul intermediar a fost eliminat deoarece nu era consumat de flux.

### Randare finală

La aprobare sau Add to basket se produce:

```text
ComposedDesign final
├── storage_key: PNG transparent full-resolution
├── preview_storage_key: WebP transparent derivat din același canvas
└── editor_background_storage_key: WebP pentru editor
```

PNG-ul final:

- are dimensiunea exactă cerută de fulfilment mapping;
- are alpha și suprafață neimprimată complet transparentă;
- conține metadata `300 × 300 PPI` prin chunk-ul PNG `pHYs`;
- ia DPI-ul din `configuration.print_areas.{area}.dpi`, cu fallback 300;
- respinge DPI invalid;
- nu este resamplat suplimentar pentru metadata PPI.

Exemplu: `2185 × 898 px / 300 PPI = aproximativ 185 × 76 mm`.

## 9. Basket, checkout și comenzi

### Basket

- guest cart identificat prin cookie/token protejat;
- quantity este validată server-side și prețurile sunt reîmprospătate autoritativ;
- preview-ul basket-ului este WebP-ul aceluiași `ComposedDesign` și fingerprint ca PNG-ul final;
- fundalul de prezentare este aplicat în UI sub WebP-ul transparent;
- Change artwork readuce sesiunea în workspace;
- Remove elimină itemul conform regulilor de domeniu;
- pe desktop blocul Quantity este coborât pentru a evita suprapunerea;
- pe mobil rândurile Quantity/thumbnail și actions/preț sunt ridicate cu 50 px.

### Checkout și plăți

- checkout UK cu adresă, shipping și tax resolution;
- strategii curente: `free_uk` și `zero_uk`;
- protecție prin pricing hash și idempotency key;
- providerul implementat pentru plăți este `fake`;
- flow-ul poate ajunge la comandă `Paid` în dezvoltare;
- nu există în prezent Stripe sau alt provider real de plată configurat în bindings.

### Stări de comandă

Sunt modelate stările de la draft și artwork până la paid, print asset, fulfilment, production, shipping, delivery, failure, cancellation și refund. Tranzițiile sunt auditate prin `OrderStatusTransition`.

## 10. Fulfilment și integrări

### Prodigi

Există:

- client HTTP sandbox/production configurabil;
- lookup produse;
- quotes;
- DTO-uri pentru produse, variante, print areas, bani și opțiuni;
- excepții tipizate pentru auth, config, network, not found, validation și server response;
- comenzi CLI de inspectare produs și quote.

Nu există încă submit automat de comandă Prodigi cu artwork-ul final.

### TreatPod

Există:

- mapping-uri manuale de catalog și SKU;
- webhook protejat pentru evenimente de comandă;
- verificare AppId/Signature și persistență evenimente;
- actualizare stări/shipment pe baza evenimentelor suportate.

Nu există încă pipeline-ul outbound care creează comanda TreatPod și transmite PNG-ul.

### Gap de producție rămas

Modelele `PrintAsset`, `FulfilmentSubmission` și `Shipment` există, dar fluxul următor nu este implementat complet:

```text
payment succeeded
→ create/validate PrintAsset
→ checksum și URL/upload accesibil providerului
→ create FulfilmentSubmission
→ submit către provider
→ persist external_order_id
→ monitorizare producție și expediere
```

Prin urmare, **generarea fișierului print-ready este făcută**, iar **livrarea automată către tipografie este nefăcută**.

## 11. Persistență și modele principale

Modelele acoperă:

- catalog: Product, ProductVariant, ProductImage, ProductCategory, ArtworkStyle, personalisation fields;
- template-uri: ProductDesignTemplate, DesignTemplateVersion, assignments;
- artwork: ArtworkSession, Upload/UploadAsset, Generation/GenerationAsset, ComposedDesign;
- commerce: Cart/CartItem, Order/OrderItem, Payment;
- producție: PrintAsset, FulfilmentProductMapping, FulfilmentSubmission, Shipment;
- observabilitate: AnalyticsEvent, AdminAuditLog, WebhookEvent, OrderStatusTransition;
- platformă: User, slug redirects.

Snapshot-urile de personalizare și artwork sunt păstrate pe entitățile tranzacționale, astfel încât schimbările ulterioare de catalog să nu modifice comanda istorică.

## 12. Storage, acces și retenție

- uploadurile și artwork-ul sunt stocate pe disk-ul privat/local;
- rutele de originale, asseturi, preview-uri și editor background verifică proprietarul sesiunii;
- `storage_key`-urile sensibile sunt ascunse din serializarea modelelor;
- asseturile publice de marketing sunt pe disk-ul public;
- directoarele sesiunilor sunt sub `storage/app/private/artwork-sessions/{publicId}`;
- sesiunile expirate, fără cart/order și neaprobate, sunt curățate zilnic la 03:30;
- purge șterge uploaduri, generation assets, PNG/WebP și editor backgrounds;
- retenția implicită pentru artwork este 30 de zile.

## 13. Securitate și robustețe

- tokenurile guest sunt comparate prin hash și cookie-ul este HttpOnly/SameSite Lax;
- accesul la resurse private este verificat per sesiune;
- CSRF este activ pe formularele storefront/admin;
- adminul Filament are autentificare proprie;
- webhook-ul TreatPod verifică semnătura;
- prompturile separă datele clientului de instrucțiuni și tratează personalizarea ca date citate;
- requesturile AI au timeout, clasificare retryable/permanent și curățare la eșec;
- idempotency este folosită la generation, checkout și payment;
- prețurile, ownership-ul, varianta și artwork-ul aprobat sunt reverificate server-side;
- fișierele de tipar și preview-urile nu sunt expuse direct ca storage paths publice.

## 14. Admin Filament

Panoul este disponibil sub `/admin` și include:

- Products și imagini/variante/relații;
- Product Categories;
- Product Design Templates și versiuni;
- Fulfilment Product Mappings;
- Admin Audit Logs;
- dashboard și Catalogue Overview;
- preview protejat al produsului;
- publicare cu readiness checks;
- duplicare și bootstrap catalog;
- test render pentru versiuni de template.

## 15. Rute și suprafețe publice

Aplicația are 53 de rute non-vendor. Grupurile principale:

- home, products, categories, related content și sitemap;
- artwork start/show/upload/status/assets/original/regenerate/cancel;
- design preview, editor background, layout, name și variant;
- approve, add to cart și change artwork;
- basket quantity/remove;
- checkout, payment și confirmation;
- pagini FAQ, delivery, returns, privacy, terms, payments și cookies;
- webhook TreatPod;
- resurse admin Filament.

## 16. Testare și starea verificărilor

Repository-ul conține 28 fișiere de test Feature, grupate în:

- Admin;
- Artwork;
- Catalogue;
- Commerce;
- Foundation;
- Integration.

Acoperirea verifică, între altele:

- provider fake/OpenAI și ordinea imaginilor;
- prompturi, style references și background removal;
- stări, retry și regenerare;
- ownership și resurse private;
- clipping canvas/custom și geometrie WYSIWYG;
- transparență, rezoluții exacte, PPI/pHYs și font rendering;
- variant/name/layout rerender fără apel AI;
- fingerprint și protecție anti-stale;
- basket, checkout, payment și journey end-to-end;
- catalogue, mappings și idempotent seeding;
- Prodigi client și TreatPod webhooks.

Verificări recente trecute:

- PNG 300 PPI, `pHYs`, alpha și dimensiuni fizice;
- Pencil Tin final și preview la schimbarea variantei;
- toate cele 6 teste Lunchbox (67 assertions);
- flow-urile basket/checkout și end-to-end;
- prompturile AI v5 și provider integration;
- workspace/progress polling.

Există două assertions vechi, de markup exact, cunoscute ca nealiniate cu UI-ul curent:

- un test caută exact markup-ul `750 ml / £16.50` într-o structură veche;
- un test caută exact `class="lg:sticky lg:top-24 lg:self-start"`, dar elementul curent are clase responsive suplimentare.

Acestea sunt datorii de test, nu erori demonstrate ale fluxurilor funcționale. Suita completă trebuie rulată și aceste expectations actualizate când se face curățarea testelor.

## 17. Dezvoltare locală

Configurare recomandată:

```env
AI_IMAGE_PROVIDER=fake
AI_IMAGE_FAKE_FAILURE=false
QUEUE_CONNECTION=database
PAYMENT_PROVIDER=fake
FAKE_PAYMENTS_ENABLED=true
CHECKOUT_SHIPPING_STRATEGY=free_uk
CHECKOUT_TAX_STRATEGY=zero_uk
```

Pornire:

```powershell
composer install
npm install
php artisan migrate --seed
php artisan storage:link
composer dev
```

`composer dev` pornește serverul Laravel, queue worker-ul și Vite. După schimbări `.env`, trebuie rulat `php artisan config:clear`, iar worker-ul trebuie restartat deoarece procesele queue sunt long-lived.

Comenzi utile:

```powershell
php artisan test
npm run build
php artisan artwork:purge-expired
php artisan artwork:openai-smoke
php artisan prodigi:product <SKU>
php artisan prodigi:quote <SKU>
```

## 18. Limitări și următoarele zone importante

1. Implementarea pipeline-ului `PrintAsset` → `FulfilmentSubmission` → provider.
2. Provider real de plată și reconciliere/refund.
3. URL semnat sau upload direct pentru artwork-ul consumat de tipografie.
4. Verificare automată suplimentară pentru profil de culoare/cerințe specifice fiecărui provider, dacă acesta le cere.
5. Probe vizuale reale pentru prompturile de postură v5.
6. Curățarea assertions de markup vechi și rularea periodică a suitei complete.
7. Monitorizare operațională pentru queue, AI failures, fulfilment și storage growth.

## 19. Protocol obligatoriu de actualizare

La orice schimbare viitoare:

1. se identifică secțiunile afectate din acest document;
2. se actualizează descrierea stării finale, nu doar jurnalul;
3. se actualizează testele/verificările și limitările cunoscute;
4. se adaugă o intrare scurtă în jurnalul de mai jos;
5. se modifică data `Ultima actualizare`.

Documentul trebuie să descrie codul care există efectiv. Funcțiile planificate se trec la limitări, nu la funcționalități implementate.

## 20. Jurnal de schimbări al raportului

### 14 august 2026 — inițializare

- creat raportul tehnic viu;
- adăugat în README punctul de intrare către raport și regula de actualizare;
- documentat stack-ul, structura, fonturile, catalogul, AI, rendererul WYSIWYG, storage-ul, basket-ul, checkout-ul, adminul și integrările;
- documentate modificările recente: progress modal, prompturi v5, manifest/fingerprint, clipping unic, PNG transparent, eliminarea PNG-ului intermediar, editor permanent, limita mânerelor și metadata 300 PPI;
- înregistrată situația reală a pipeline-ului de fulfilment și datoriile de test cunoscute.
