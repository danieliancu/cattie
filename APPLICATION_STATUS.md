# Kattie.uk — raport tehnic viu al aplicației

> **Statutul documentului:** sursa vie de adevăr pentru starea tehnică și funcțională a aplicației.
>
> **Regulă de întreținere:** acest fișier trebuie actualizat la fiecare modificare care schimbă funcționalitatea, arhitectura, schema de date, configurarea, interfața, asseturile, integrările, testele sau limitările cunoscute. O schimbare nu este considerată documentată complet până când secțiunile afectate și jurnalul de la final reflectă noua stare.
>
> **Ultima actualizare:** 16 august 2026 (Arhitectura canonică de catalog — taxonomie ierarhică).

## 1. Rezumat executiv

Kattie.uk (rebranding din Cattie.uk) este o aplicație e-commerce pentru produse personalizate. Clientul alege produsul, varianta și stilul artistic, încarcă o fotografie, primește un personaj generat cu AI, îl poziționează într-un design de produs, aprobă rezultatul, îl adaugă în basket și finalizează checkout-ul.

Aplicația include în prezent:

- storefront public, catalog, categorii, căutare și pagini informaționale;
- configurare produs, upload privat și sesiuni guest fără cont obligatoriu;
- generare AI reală prin OpenAI sau provider fake pentru dezvoltare/testare;
- două stiluri: `storybook-cartoon` și `hand-drawn`;
- eliminare locală a fundalului și generare de preview WebP;
- renderer server-side WYSIWYG pentru designurile de produs;
- editor pentru mutarea și redimensionarea personajului;
- PNG transparent, full-resolution, cu metadata 300 PPI pentru tipografie;
- basket, checkout UK, plăți fake și Stripe Embedded Checkout fără catalog Stripe;
- conturi storefront opționale, autentificare pe sesiune și istoric privat de comenzi;
- profil de client (`CustomerProfile`) cu o adresă de livrare implicită, pagină `My Details` cu autosave și prefill automat al checkout-ului pentru clienții autentificați;
- lookup de postcode UK printr-un provider înlocuibil, cu `Postcodes.io` ca implementare gratuită implicită pentru validare/normalizare;
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
- Stripe PHP SDK `18.2` pentru Checkout Sessions, verificarea webhook-urilor și refund-uri.

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
- Kattie Water Bottle, 650 ml (placeholder inactiv);
- Water Bottle with Red Flip Lid, 750 ml;
- Small Plastic Lunchbox;
- Personalised Stationery & Pencil Tin.

### Taxonomia canonică (Categorie → Subcategorie → Produs)

`ProductCategory` este ierarhic, pe exact **două** niveluri, printr-un `parent_id` nullable self-referențial pe aceeași tabelă `product_categories` (fără tabele separate, fără câmp `type` — distincția este derivată din `parent_id === null`).

Cele patru categorii de nivel superior, în ordinea `sort_order`:

| # | Categorie | Slug | Subcategorii |
|---:|---|---|---:|
| 0 | School & Everyday | `school-everyday` | 5 |
| 1 | Memories & Keepsakes | `memories-keepsakes` | 5 |
| 2 | Pets & Family | `pets-family` | 4 |
| 3 | Gifts & Occasions | `gifts-occasions` | 6 |

Total: **4 categorii + 20 subcategorii**, seed-uite idempotent de `Database\Seeders\CategoryTaxonomySeeder` (apelat la finalul `CatalogueSeeder`, fiindcă atribuie produsele seed-uite înainte). Copy-ul vizibil este stocat în baza de date (`short_description`), nu hardcodat în Blade — adminul rămâne sursa de adevăr.

Reguli de integritate (invariante):

- adâncime maximă exact 2 — un părinte trebuie să fie el însuși top-level;
- o categorie nu poate fi propriul părinte;
- o categorie care are copii nu poate primi un părinte;
- o categorie cu copii nu poate fi ștearsă (`restrictOnDelete` + verificare în observer);
- slug-ul unei categorii top-level nu poate fi un slug rezervat al aplicației.

Validarea are **trei straturi distincte**: (1) formularul Filament validează înainte de save și produce erori inline pe câmp — acesta este stratul destinat utilizatorului; (2) `App\Observers\ProductCategoryObserver` este plasă de siguranță pentru scrieri care ocolesc formularul (seedere, tinker, CLI, acțiuni de domeniu) și aruncă `App\Exceptions\InvalidCategoryHierarchyException`; (3) foreign key-ul din bază. În uz normal observer-ul nu se declanșează niciodată.

**Categoriile legacy** `School & Lunch`, `Kids Drinkware`, `School Accessories` au fost eliminate. Seeder-ul migrează întâi asocierile, apoi detașează explicit orice rând rămas în `product_category` și abia apoi șterge rândul de categorie — fără a depinde de comportamentul de cascade al foreign key-ului, astfel încât rezultatul este identic pe SQLite (unde enforcement-ul depinde de un pragma) și pe MySQL.

Atribuirea produselor existente (numai maparea de taxonomie s-a schimbat; variante, template-uri, configurație AI, mapping-uri de fulfilment, prețuri și artwork au rămas neatinse):

| Produs | Subcategorie leaf |
|---|---|
| Water Bottle with Red Flip Lid | Personalised Water Bottles for Kids |
| Small Plastic Lunchbox | Personalised Lunch Boxes for Kids |
| Personalised Stationery & Pencil Tin | Personalised Pencil Tins |
| Children's Storybook Wall Print | Personalised Wall Prints |
| Best Friend Pet Portrait | Personalised Pet Portraits |
| Our Family Art Print | Personalised Family Portraits |

`Little Moments Mug` și `Cuddle Close Cushion` au rămas deliberat neatribuite — nu pot fi clasificate cu certitudine, iar inventarea de taxonomie ar fi fost mai dăunătoare decât absența ei.

Un produs poate aparține **mai multor subcategorii leaf** prin pivotul many-to-many existent (esențial pentru `Gifts & Occasions`, care își va redistribui produsele din celelalte familii). Produsul nu este niciodată duplicat: rămâne un singur record și un singur URL canonic `/products/{slug}`.

Produsele se atribuie normal doar subcategoriilor leaf; paginile de categorie de nivel superior agregă produsele copiilor lor.

Produsele reale templated au variante, SKU intern, mapping de fulfilment, rezoluții exacte de tipar, galerii pe variantă și personalizare. Exemple importante:

- Red Flip Bottle: `2717 × 2008 px`, 300 PPI, suprafață declarată `230 × 170 mm`;
- Lunchbox: `1949 × 1205 px`, 300 PPI, suprafață `165 × 102 mm`;
- Pencil Tin: `2185 × 898 px`, 300 PPI, suprafață `185 × 76 mm`.

Variantele templated fără exact un mapping activ rămân vizibile în pagina produsului pentru transparența catalogului, dar sunt marcate `Unavailable` și dezactivate atât în configurator, cât și în editor. Serverul respinge selecția înainte de upload sau generare AI, evitând generări care nu pot produce un preview ori un fișier de tipar.

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
- desktop: pagina site-ului rămâne vizibilă sub overlay-ul negru 80%, iar informația centrală stă într-un panou negru cu padding generos; fotografia încărcată este ascunsă, iar diamondurile mari sunt distribuite în benzile exterioare din jurul panoului;
- mobil: fotografia originală în fundal, overlay negru 80% și diamonduri colorate doar pe margini;
- toate etapele au checkbox și bifă verde la finalizare;
- la succes rulează confetti și apare `View Your Design`;
- cancel, retry și upload nou rămân disponibile conform stării;
- la navigare sunt restaurate stilurile de scroll ale documentului.
- overlay-ul folosește CSS explicit `rgba(0,0,0,.8)`, astfel încât nu depinde de generarea unei utilități Tailwind cu opacitate.

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
- după acceptarea datelor de checkout, basket-ul rămâne vizibil și complet editabil cât Order este AwaitingPayment; acțiunea din basket este întotdeauna `Continue to checkout`, astfel încât traseul obligatoriu rămâne Basket → Checkout → Payment chiar și la revenire;
- la quantity, remove, change artwork sau adăugarea altui artwork, checkout-ul pending este abandonat controlat: o sesiune Stripe deschisă este expirată, Order-ul vechi este anulat, Payment-ul pending este marcat cancelled, legătura basket–order este eliminată și următorul checkout creează un snapshot nou;
- basket-ul devine `converted` și dispare numai după confirmarea idempotentă a plății;
- pe desktop blocul Quantity este coborât pentru a evita suprapunerea;
- pe mobil rândurile Quantity/thumbnail și actions/preț sunt ridicate cu 50 px.

### Checkout și plăți

- checkout UK cu adresă, shipping și tax resolution;
- pagina datelor de livrare folosește layout-ul basket-ului: întregul bloc Checkout — introducerea și formularul — rămâne în stânga, iar sumarul complet al comenzii începe aliniat sus în dreapta și este sticky pe desktop; pe mobil revine în flux sub formular; sumarul include artwork-ul aprobat, produsul cu prețul unitar `£… each`, varianta, stilul, cantitatea, totalul liniei, subtotalul, livrarea UK, taxa provizorie, totalul și acțiunea `Continue to payment`;
- metodele de livrare UK sunt administrate în Filament per provider; Checkout afișează numai metodele compatibile cu toate variantele din basket, preselectează cea mai ieftină și păstrează alegerea într-un snapshot imuabil pe Order;
- taxa folosește în continuare strategia `zero_uk`;
- protecție prin pricing hash și idempotency key;
- providerii implementați sunt `fake` și `stripe`, selectați prin `PAYMENT_PROVIDER`;
- Stripe Embedded Checkout este montat în pagina Kattie și primește line items dinamice din snapshot-urile `OrderItem`; nu există Stripe Products, Prices sau sincronizare de catalog;
- suma produselor, shipping-ului și taxei este verificată împotriva `Order.total_minor`, iar reducerile nenule sunt respinse în această versiune;
- crearea Checkout Session păstrează Payment `Pending` și Order `AwaitingPayment`; succesul este stabilit numai prin reconciliere server-side;
- aceeași acțiune idempotentă reconciliază webhook-ul semnat și return-ul browserului, validând IDs, numărul comenzii, suma și moneda;
- sunt gestionate `checkout.session.completed`, `checkout.session.async_payment_succeeded`, `checkout.session.async_payment_failed` și `checkout.session.expired`;
- webhook-urile sunt deduplicate prin `WebhookEvent(provider, external_event_id)`, iar payload-ul persistent este normalizat și fără PII integral;
- refund-ul Stripe este disponibil la nivel de provider, prin PaymentIntent-ul sesiunii, fără UI/workflow administrativ nou;
- `client_secret` este returnat tranzitoriu browserului pentru montarea formularului și nu este persistat în Payment, WebhookEvent sau loguri;
- sesiunile Pending deschise sunt reutilizate la refresh, iar statusul browserului folosește aceeași reconciliere ca webhook-ul;
- flow-ul fake continuă să poată ajunge sincron la comandă `Paid` în dezvoltare.

### Contul clientului și istoricul comenzilor

- cumpărarea ca guest rămâne disponibilă; contul nu este cerut în configurator, basket sau checkout;
- înregistrarea cere numai email și parolă, autentifică imediat clientul și creează întotdeauna `is_admin=false`;
- loginul storefront este separat de Filament, suportă Remember me și păstrează destinația intenționată;
- basket-ul browserului are prioritate la autentificare, este asociat clientului și primește fără duplicare itemii altui basket activ al contului;
- comenzile create autentificat primesc `Order.user_id` server-side, iar emailul comenzii rămâne snapshot imuabil;
- `/account`, `/account/orders` și detaliul comenzii sunt protejate prin auth și interoghează exclusiv `orders.user_id`;
- statusurile au etichete publice prietenoase, iar thumbnails sunt livrate printr-o rută privată scoped la Order și OrderItem;
- o comandă guest poate fi revendicată individual numai când browserul dovedește tokenul existent și emailul contului coincide; nu există revendicare în masă după email.

### My Details și profilul de client

- `CustomerProfile` este un profil minimal per utilizator (`user_id` unic), cu prenume, nume, telefon opționale și o singură adresă de livrare implicită (`default_shipping_address`); nu există carte de adrese cu adrese multiple;
- adresa implicită este criptată la nivel de cast Eloquent (`encrypted:array`) și ascunsă din serializare, exact ca `Order.shipping_address`; emailul rămâne autoritativ pe `User.email`, nu este duplicat pe profil;
- `/account/details` ("My Details") afișează formularul comun de date client, populat din profil (dacă există) și din `User.email`; salvarea este autosave pe blur, câmp cu câmp, prin `PATCH /account/details`, care întoarce JSON și afișează `Saving…` / `✓ Saved` / `Couldn't save — try again`;
- validarea pentru My Details permite actualizări parțiale (`sometimes`/`nullable` pe fiecare câmp în afară de email) — clientul poate salva doar prenumele fără să fie obligat să completeze adresa; validarea de checkout rămâne strictă și neschimbată;
- schimbarea emailului din My Details normalizează lowercase, verifică unicitatea față de `users` excluzând contul curent, actualizează `User.email` fără logout și fără să modifice emailul istoric al comenzilor deja plasate; nu există verificare de email în această fază;
- formularul de date client (`resources/views/components/customer-details-form.blade.php`) este un singur component Blade partajat între Checkout și My Details, cu atribute `autocomplete` corecte pentru fiecare câmp;
- la checkout, pentru clienți autentificați, câmpurile sunt pre-completate cu prioritate: `old()` → adresa comenzii pending existente → profilul salvat → `User.email` → gol; pentru guest, lanțul se reduce exact la comportamentul anterior (`old()` → comandă pending → gol);
- checkout-ul NU suprascrie niciodată automat adresa implicită; când adresa introdusă diferă (comparație server-side normalizată, fără spații/case) de adresa salvată, apare opțional checkbox-ul `Save this as my default address` — profilul este actualizat numai dacă este bifat, după crearea comenzii; dacă adresa introdusă este deja identică cu cea salvată, checkbox-ul nu apare;
- `Order.shipping_address`/`email`/`phone` rămân snapshot imuabil per comandă; editările ulterioare ale `CustomerProfile` nu modifică niciodată o comandă deja plasată.

### Lookup de postcode UK

- `AddressLookupProvider` este o interfață înlocuibilă (`app/Contracts/AddressLookupProvider.php`), selectată prin `ADDRESS_LOOKUP_PROVIDER` (config `address_lookup.provider`), cu binding în `AppServiceProvider`, după același tipar ca `PaymentProvider`/`ImageGenerationProvider`;
- providerul implicit al repository-ului (fără configurare) este `PostcodesIoAddressLookupProvider`, fără cheie API, folosind Postcodes.io doar pentru validare/normalizare de postcode UK — nu întoarce niciodată o listă de adrese la nivel de proprietate individuală (`addresses` este întotdeauna gol pentru acest provider);
- este disponibil și un al doilea provider, la nivel de proprietate, `HomedataAddressLookupProvider` (Homedata, `https://api.homedata.co.uk`), care întoarce efectiv lista de adrese pentru un postcode prin endpoint-ul lor `GET /address/postcode/{postcode}/`; se activează prin `ADDRESS_LOOKUP_PROVIDER=homedata` + `HOMEDATA_API_KEY` (cont gratuit, fără card, 100 apeluri/lună); câmpurile Homedata (`building_number`, `street`, `building_name`, `sub_building`, `town`) sunt normalizate în `address_line_1`/`address_line_2`/`city`; Homedata nu întoarce comitat/county, astfel încât acest câmp rămâne gol (opțional, ca peste tot în aplicație); lipsa cheii API sau orice eșec al providerului degradează grațios la `valid:false`, fără să apeleze extern și fără să blocheze introducerea manuală;
- endpoint-ul `GET /address-lookup` (throttled `20,1`, fără autentificare, disponibil și la guest checkout) primește doar postcode-ul, normalizează local, respinge formatele invalide fără să apeleze providerul și întoarce mereu `{valid, postcode, addresses}`; eșecul/timeout-ul providerului extern nu produce niciodată 500 și nu blochează checkout-ul — răspunsul este `valid:false` cu mesaj prietenos pentru introducere manuală;
- componenta de formular distinge explicit cele două stări de eșec/succes fără adrese: postcode invalid/lookup eșuat → „We couldn't look up that postcode. You can enter your address manually below.”; postcode valid dar fără listă de proprietăți (cazul Postcodes.io) → „Postcode confirmed — enter the rest of your address below.”; lista de adrese (când există, ca la Homedata) apare într-un selector, cu opțiunea „Enter address manually” explicită;
- normalizarea de postcode este extrasă într-o singură clasă reutilizabilă (`App\Support\UkPostcode`), folosită identic de `CheckoutRequest`, `CustomerProfileController`/`UpdateCustomerProfile` și `AddressLookupController`;
- arhitectura este pregătită pentru înlocuirea/adăugarea altor provideri la nivel de proprietate (ex. Ideal Postcodes, getAddress.io) doar prin configurare, fără modificări în Checkout sau My Details — Homedata este dovada că acest tipar funcționează;
- county rămâne opțional peste tot și nu blochează niciodată checkout-ul sau lookup-ul.

### Stări de comandă

Sunt modelate stările de la draft și artwork până la paid, print asset, fulfilment, production, shipping, delivery, failure, cancellation și refund. Tranzițiile sunt auditate prin `OrderStatusTransition`.

### Order Support (Phase 4)

Flux simplu prin care un client (autentificat sau guest) semnalează o problemă cu o comandă; nu automatizează nimic — este exclusiv o cerere de revizuire umană.

- pagină publică `GET /order-support` (`order-support.create`), accesibilă și fără cont; formular `POST /order-support` (`order-support.store`, `throttle:10,60`); confirmare `GET /order-support/submitted` (`order-support.submitted`) — nu conține referința în URL, este citită dintr-un flash de sesiune, ca să nu poată fi ghicită/reîncărcată pentru resubmit;
- clienți autentificați: selector de comenzi populat exclusiv din `Order.user_id === auth()->id()`; parametrul `?order=` este doar o comoditate de preselecție, reverificat server-side față de comenzile proprii — o comandă a altui cont este ignorată silențios, nu expusă și nu eronată explicit; fără comenzi → mesaj prietenos, fără selector gol;
- clienți guest: câmpuri `order_number` + `email`; ownership dovedit prin `GuestContext` (același cookie/hash folosit la checkout) SAU prin potrivirea normalizată (lowercase/trim) a emailului cu `Order.email`; comandă inexistentă, email greșit sau ambele produc exact același mesaj generic — „We couldn't match those order details. Please check your order number and email address.” — fără nicio diferență de comportament care ar permite enumerarea comenzilor;
- domeniu nou, complet separat de comenzi: tabelul `order_support_requests` (ULID), model `App\Models\OrderSupportRequest` (`belongsTo Order`, `belongsTo User` nullable), enum propriu `App\Enums\OrderSupportStatus` (`open|reviewing|resolved|closed`, implicit `open`) — necuplat de `OrderStatus`; schimbarea statusului de suport nu modifică niciodată `Order`;
- câmpuri: `reference` (unic, format `SUP-XXXXXX`, generat de `App\Domain\Orders\Actions\CreateOrderSupportRequest`), `contact_email` (cast `encrypted` — snapshot, nu se scrie niciodată înapoi în `Order.email`/`CustomerProfile`/`User.email`), `message` (text simplu, escapat la afișare, fără HTML), `status`, plus `photo_disk`/`photo_storage_key`/`photo_mime_type`/`photo_size_bytes` (toate nullable — poza e opțională);
- poza: validare conținut real (`getimagesizefromstring`, aceeași convenție ca upload-ul de artwork), JPEG/PNG/WebP, maximum 10 MB, cheie de stocare aleatorie (`order-support/{id}/photo.{ext}`) pe disk-ul privat `local` — niciodată numele fișierului clientului, niciodată disk public; dacă stocarea fișierului eșuează, cererea de suport NU este creată (fără rând orfan cu poză lipsă);
- acțiunea `CreateOrderSupportRequest` este singura care scrie în `order_support_requests`; nu atinge niciodată `Order`, `Payment`, `PrintAsset`, `FulfilmentSubmission` sau artwork — trimiterea unui suport nu anulează, nu rambursează, nu creează înlocuire și nu regenerează artwork;
- vizibilitate: CTA `Get help with this order` pe `/account/orders/{orderNumber}`, link discret `Need help with this order? → Order Support` pe pagina de confirmare comandă (prefill automat al numărului comenzii pentru guest, via `GuestContext`), link `Order Support` roșu/subliniat în header (desktop + mobil) și în footer, sub Customer Service;
- admin Filament: resursă `Order Support` (`app/Filament/Resources/OrderSupportRequests`), grup de navigare „Customer Support”; listă cu referință, comandă, status (badge colorat), email de contact, dată; pagina de editare arată referința/comanda/emailul/mesajul (toate needitabile) și permite doar schimbarea statusului `Open → Reviewing → Resolved/Closed`; poza este accesibilă printr-o rută privată dedicată (`GET /admin/order-support/{orderSupportRequest}/photo`, `auth` + verificare `is_admin` în controller), niciodată direct în HTML;
- analytics: evenimente `order_support_opened`/`order_support_submitted` prin `RecordAnalyticsEvent`, subiect = `Order`, fără mesaj/email/cale poză în `properties`;
- limitări explicite ale acestei faze: nu există email de confirmare/notificare (Phase 5), nu există înlocuire automată de comandă, nicio acțiune TreatPod, niciun istoric de suport în contul clientului (`My Account`) — CTA-ul de pe pagina comenzii este singurul punct de intrare; toate cererile necesită revizuire manuală de admin.

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
- commerce: User, CustomerProfile, Cart/CartItem, Order/OrderItem, Payment, ShippingMethod și OrderSupportRequest;
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
- preview-urile din istoricul comenzilor sunt servite privat numai proprietarului autentificat al Order-ului.
- pozele Order Support sunt stocate privat sub `storage/app/private/order-support/{id}/photo.{ext}`, cu cheie aleatorie (nu numele fișierului clientului); accesul se face exclusiv prin ruta admin autentificată/verificată `is_admin`, niciodată direct în HTML.

## 13. Securitate și robustețe

- tokenurile guest sunt comparate prin hash și cookie-ul este HttpOnly/SameSite Lax;
- accesul la resurse private este verificat per sesiune;
- CSRF este activ pe formularele storefront/admin;
- adminul Filament are autentificare proprie, iar loginul storefront exclude explicit utilizatorii admin;
- parolele clienților folosesc hashing-ul Laravel, loginul are mesaj generic, sesiunea este regenerată la autentificare și invalidată la logout;
- istoricul folosește exclusiv `Order.user_id`; egalitatea emailului nu acordă acces și revendicarea guest cere tokenul browserului;
- webhook-ul TreatPod verifică semnătura;
- webhook-ul Stripe verifică raw body cu `Stripe-Signature` și secretul dedicat;
- prompturile separă datele clientului de instrucțiuni și tratează personalizarea ca date citate;
- requesturile AI au timeout, clasificare retryable/permanent și curățare la eșec;
- idempotency este folosită la generation, checkout și payment;
- prețurile, ownership-ul, varianta și artwork-ul aprobat sunt reverificate server-side;
- fișierele de tipar și preview-urile nu sunt expuse direct ca storage paths publice.
- `CustomerProfile.default_shipping_address` folosește aceeași convenție `encrypted:array` + `$hidden` ca `Order.shipping_address`;
- lookup-ul de adresă primește exclusiv postcode-ul — niciodată nume, email, telefon, date de comandă sau de artwork — și nu loghează postcode-ul în caz de eșec, doar operația și statusul HTTP;
- schimbarea emailului din My Details nu adaugă (re)verificare de email — rămâne în limitarea existentă de lipsă a verificării de email pentru conturile clienților.

## 14. Admin Filament

Panoul este disponibil sub `/admin` și include:

- Products și imagini/variante/relații; selectorul de categorii oferă **numai** subcategorii leaf, cu etichete contextuale `Părinte — Copil` (ex. `School & Everyday — Personalised Water Bottles for Kids`), selecție multiplă păstrată pentru reutilizarea produselor în colecțiile de ocazii;
- Product Categories, cu ierarhie completă:
  - formular: Name, Slug, Parent category, Short description / landing intro, Description / supporting content, SEO title, SEO description, Sort order, Published;
  - selectorul de părinte este searchable, oferă doar categorii top-level, se exclude pe sine și este dezactivat + `dehydrated(false)` pentru o categorie care are deja copii (dezactivarea singură nu ar opri un request fabricat);
  - fiecare invariantă are `->rule()` propriu, deci încălcările apar ca erori inline pe câmp, nu ca excepție;
  - preview read-only al URL-ului canonic, construit prin `ProductCategory::urlFor()` → `route()`, deci corect pe local/staging/producție;
  - tabel cu Name (copiii indentați), Type (`Category`/`Subcategory`, derivat din `parent_id`), Parent, Slug, calea canonică completă, numărul de produse, sort order și Published, sortat implicit astfel încât copiii apar sub părintele lor;
  - filtre: categorii de nivel superior / subcategorii / published;
  - ștergerea unei categorii cu copii este oprită cu notificare, înainte de a ajunge la foreign key;
- Product Design Templates și versiuni;
- Fulfilment Product Mappings;
- Shipping Methods, cu provider, service code, preț, estimare în zile lucrătoare, țară, status și ordine;
- Admin Audit Logs;
- Order Support (grup „Customer Support”) — listă/detaliu cereri de suport, schimbare status, acces securizat la poza atașată;
- dashboard și Catalogue Overview;
- preview protejat al produsului;
- publicare cu readiness checks;
- duplicare și bootstrap catalog;
- test render pentru versiuni de template.

## 15. Rute și suprafețe publice

Aplicația are 78 de rute non-vendor (76 anterior + 2 noi în această fază: `catalogue.category`, `catalogue.subcategory`; ruta `categories.show` există în continuare, dar a fost repurposată ca redirect 301).

### Structura canonică de URL-uri

```text
/{category-slug}                        → pagina de categorie
/{category-slug}/{subcategory-slug}     → pagina de colecție/subcategorie
/products/{product-slug}                → pagina de produs (neschimbată)
```

Exemple: `/school-everyday`, `/school-everyday/personalised-water-bottles-for-kids`, `/products/personalised-kids-water-bottle-750ml`.

Generarea URL-urilor este **centralizată**: `ProductCategory::url()` (și `ProductCategory::urlFor(?string $parentSlug, string $slug)` pentru cazurile fără model persistat, cum e preview-ul din admin). Nicio concatenare manuală de căi în Blade, niciun host hardcodat — totul trece prin `route()`, deci funcționează identic local, pe staging și în producție.

### Siguranța rutelor

Cele două rute dinamice sunt declarate **ultimele** în `routes/web.php` și au constrângeri regex pe primul segment care exclud explicit slug-urile rezervate (`App\Support\ReservedSlugs`). Rezultatul este că `/products/foo` nu poate fi *niciodată* interpretat ca `category=products, subcategory=foo`: routerul pur și simplu nu face match și trece mai departe la ruta reală, în loc să rezolve și să dea 404 în controller.

`ReservedSlugs` este o listă `const` (nu derivată la runtime din `Route::getRoutes()`, care ar fi circulară în timpul definirii rutelor și incompatibilă cu `route:cache`), completată de `SLUG_PATTERNS` pentru segmentele al căror nume variază între instalări — concret prefixul hash-uit al Livewire (`livewire-a36122cd`). Două teste de gardă verifică automat că lista rămâne sincronizată cu tabela de rutare reală și cu directoarele din `public/`.

Validarea de ownership pentru `/{category}/{subcategory}` este **structurală**: copilul este căutat *prin* părinte (`$parent->children()->active()->where('slug', …)`), deci un slug care există sub alt părinte nu poate rezolva aici. `/pets-family/personalised-water-bottles-for-kids` întoarce 404, nu redirect. Taxonomia inactivă (părinte sau copil) întoarce 404.

### Redirecturi legacy

`/collections/{slug}` nu mai este canonic; este exclusiv redirect permanent, servit de `LegacyCollectionRedirectController`. Rezolvarea: harta explicită din `config/catalogue.php` → căutare directă după slug în `product_categories` → 404. Ținta este mereu `$category->url()`, deci redirectul este corect la ambele niveluri și nu produce niciodată lanțuri.

| URL vechi | Redirect 301 |
|---|---|
| `/collections/school-lunch` | `/school-everyday` |
| `/collections/kids-drinkware` | `/school-everyday/personalised-water-bottles-for-kids` |
| `/collections/school-accessories` | `/school-everyday/personalised-pencil-tins` |

Niciun URL `/collections/...` nu apare ca `canonical` și niciunul nu intră în `sitemap.xml`.

Grupurile principale de rute:

- home, products, categorii/subcategorii, related content și sitemap;
- artwork start/show/upload/status/assets/original/regenerate/cancel;
- design preview, editor background, layout, name și variant;
- approve, add to cart și change artwork;
- basket quantity/remove;
- checkout, payment, Stripe embedded session/status, Stripe return și confirmation;
- register, login/logout, account overview, order history/detail și artwork privat per OrderItem;
- account details (My Details, `GET`/`PATCH /account/details`) și lookup de adresă UK (`GET /address-lookup`);
- Order Support: pagina publică, submit, confirmare și fotografia privată din admin;
- pagini FAQ, delivery, returns, privacy, terms, payments și cookies;
- webhook-uri Stripe și TreatPod;
- resurse admin Filament.

## 15b. Navigație, template-uri de catalog și SEO

### Sursa de date pentru navigație

`App\Support\CatalogueNavigation::topLevelWithChildren()` este singura interogare din spatele fiecărei suprafețe bazate pe taxonomie: header, homepage, chip-urile din `/products`, footer și ambele sitemap-uri. Două interogări în total, fără N+1; copiii primesc `setRelation('parent', …)` astfel încât `url()` să nu mai emită interogarea de fallback.

Condiția anterioară „ascunde categoriile care nu au produse active” a fost **eliminată deliberat** din navigație: structura trebuie să fie navigabilă înainte de importul produselor. Indexabilitatea este tratată separat (vezi mai jos), nu prin ascundere.

Notă de implementare importantă: listele de coloane trebuie să conțină `id` și `parent_id` — fără `id` relația `children` nu se hidratează, iar fără `parent_id` un copil nu își poate construi URL-ul.

### Meniu

- **Desktop:** dropdown-ul `Shop` a devenit un mega-menu pe patru coloane (categorie + subcategoriile ei), cu link explicit `Shop all` către `/products`.
- **Mobil:** acordeon pe două niveluri — fiecare categorie are propriul buton de expandare cu `aria-expanded`/`aria-controls`/`aria-label`, deci cele 20 de subcategorii nu sunt niciodată turnate într-o singură listă.
- Neschimbate: logo-ul Kattie.uk, `icon.gif`, `LITTLE FACES. BIG LOVE`, căutarea, linkul roșu `Order Support`, `My Account`, `Basket` și badge-ul de coș.
- **Footer:** coloană nouă `Shop` cu cele patru categorii de nivel superior plus `Shop all`; coloanele About Us / Customer Service / Contact Us au rămas intacte, iar grila a trecut de la patru la cinci coloane pe desktop.
- **Homepage:** secțiune nouă „Where would you like to start?” cu exact cele patru categorii de nivel superior, vizibile și cu zero produse; subcategoriile nu apar pe homepage. Fiecare categorie este un *tile* cu imaginea ei pe fundal (`object-cover`), peste care stă `.category-tile-overlay`, cu titlul și descrierea în alb și `Explore →` în coral/roz. Overlay-ul este **gradient, nu plat**: 30% sus → 88% jos. Un strat uniform de 80% negru s-a dovedit că șterge complet imaginea (tile-ul devenea un dreptunghi aproape uniform), așa că întunecarea este gradată — slabă în zona în care se vede fotografia, puternică în treimea inferioară unde stau titlul și descrierea. Imaginea are `alt=""` și `aria-hidden`, fiind pur decorativă — numele categoriei este deja titlul.
- **Secțiunea „How it works”** stă pe o bandă caldă proprie (`.home-how-it-works`, gradient vertical `cream → #f2d9c6 → cream`), iar cardurile de pas au devenit **transparente** — fără fundal alb, fără bordură și fără umbră — astfel încât banda secțiunii este cea care separă zona de restul paginii. Imaginile de pas folosesc `mix-blend-multiply`, deci fundalul lor alb dispare în bandă în loc să stea pe un card vizibil.

Fundalurile de categorie sunt **texturi abstracte din paleta brandului, generate procedural — nu fotografii**, cu contrast intenționat ridicat (highlight luminos + umbră adâncă) fiindcă un degrade pastelat se aplatizează complet sub overlay, fiindcă stau oricum sub un overlay negru de 80%. Sursele sunt versionate în `database/seeders/assets/categories/{slug}.jpg` (1400 × 900, JPEG) și publicate pe disk-ul `public` sub `categories/{slug}.jpg` de `CategoryTaxonomySeeder`, care **nu suprascrie niciodată** o imagine încărcată deja din admin (`whereNull('image_storage_key')`). Pot fi înlocuite oricând cu fotografii reale din Filament, fără modificări de cod.
- **`/products`:** chip-urile „Shop by category” listează acum cele patru categorii de nivel superior.

### Template Category (`storefront/categories/category.blade.php`)

Reia **exact layout-ul paginii `/products`**: eyebrow `Personalised gifts`, `H1 = category.name`, intro din `short_description`, apoi subcategoriile ca **pills orizontale** — același rând de chip-uri sticky, scrollabil pe mobil și wrap pe desktop, cu aceleași clase ca „Shop by category”. Fără imagini și fără descrieri pe pills: doar numele subcategoriei.

Dacă există produse active în subcategorii, ele apar dedesubt în grila standard de produse (fără titlu separat, ca pe `/products`). Dacă nu există, grila este pur și simplu omisă — nu se afișează niciodată o cutie „No products found” pe pagina de categorie. `BreadcrumbList` JSON-LD.

### Template Collection (`storefront/categories/show.blade.php`)

Breadcrumb `Home → Categorie → Subcategorie`, `H1 = subcategory.name`, intro din `short_description`, grila de produse cu sortarea și paginarea existente, iar `description` este randat doar dacă are conținut. Când colecția este goală, pagina **există în continuare** și afișează o stare prietenoasă („We're adding this collection soon.” + `Browse all gifts`), nu o pagină ruptă. Sub conținutul principal apar linkuri interne către subcategoriile-surori sub titlul `You may also like`. `BreadcrumbList` JSON-LD mereu; `ItemList` doar când există efectiv produse.

### Regula SEO pentru colecții goale

O subcategorie activă cu **zero** produse active primește `robots: noindex,follow`, își păstrează canonical-ul nested corect și **nu** intră în `sitemap.xml`, dar rămâne vizibilă în navigație, pe pagina de categorie, în sitemap-ul HTML și la navigare directă. În momentul în care i se atribuie cel puțin un produs activ, ambele comportamente se inversează automat — nu există niciun toggle manual de SEO.

Categoriile de nivel superior rămân indexabile chiar și fără produse directe, fiindcă au copy unic, ierarhie reală și linkuri către colecțiile-copil.

### Metadata

`meta_title` și `meta_description` rămân editabile din admin pentru ambele niveluri. Fallback-uri: titlu `{Nume} | Personalised Gifts | Kattie.uk` pentru categorie și `{Nume} | Kattie.uk` pentru subcategorie; descrierea cade pe `short_description`. Copy-ul furnizat nu este rescris și nu se injectează text SEO repetitiv. Canonical-ul folosește întotdeauna URL-ul nested nou, prin `CanonicalUrl::forPaginator($category->url(), $products)`.

Pagina de produs rămâne neschimbată ca arhitectură canonică: `/products/{slug}`, fără a alege arbitrar una dintre subcategoriile posibile drept părinte. Doar linkurile de categorie de pe pagina de produs și din workspace au trecut la `$category->url()`.

### Sitemap-uri

- **`/sitemap.xml`**: homepage, `/products`, categoriile active de nivel superior, subcategoriile active **care au cel puțin un produs activ**, produsele active și paginile de conținut. Exclude URL-urile `/collections/...`, taxonomia inactivă, subcategoriile goale/noindex și paginile tranzacționale. Apartenența la sitemap se calculează cu o singură interogare pe pivot, nu cu un `exists()` per subcategorie.
- **`/sitemap`** (HTML): arborele `Shop` complet — cele patru categorii, fiecare cu subcategoriile ei, inclusiv cele goale, fiindcă este și o pagină de navigare pentru client. Lista de produse rămâne într-o coloană separată.

## 16. Testare și starea verificărilor

Repository-ul conține 40 de fișiere de test Feature, grupate în:

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
- Stripe Checkout payload, idempotency, redirect/cancel, return race, semnătură, deduplicare, stări async, protecție cross-order și refund.
- Order Support: navigare/branding, ownership autentificat (fără preselecție cross-user, fără acceptare de order_number manipulat), verificare guest (GuestContext sau email, mesaj generic identic la orice eșec, fără enumerare), persistență (referință unică, status implicit Open, email snapshot, mesaj text simplu), poză (JPEG/PNG/WebP acceptate, tip/dimensiune invalidă respinsă, cheie de stocare aleatorie, acces cross-request blocat), admin (acces restricționat la is_admin, listare, inspecție, tranziții de status fără impact pe OrderStatus) și regresie (nicio mutație de totaluri/adresă/status/payment/print-asset/fulfilment la trimiterea unui suport).

Acoperirea taxonomiei ierarhice (această fază):

- `tests/Feature/Catalogue/CategoryHierarchyTest.php` (11 teste): `parent_id` null la nivel superior, apartenența copiilor, respingerea nivelului trei, self-parent, reparentarea unei categorii cu copii, ștergerea unei categorii cu copii, slug rezervat respins la top-level dar permis la subcategorie, slug malformat respins, ordonarea pe ambele niveluri, un produs în mai multe subcategorii leaf cu un singur canonical, generarea URL-urilor pe fiecare nivel;
- `tests/Feature/Catalogue/CatalogueRoutingTest.php` (12 teste): rezolvarea `/{categorie}` și `/{categorie}/{subcategorie}`, 404 pentru combinația greșită părinte/copil, 404 pentru subcategorie cerută la nivel superior, 404 pentru taxonomie inactivă (părinte sau copil), faptul că `/products`, `/products/{slug}`, `/account`, `/cart`, `/checkout`, `/order-support`, `/login`, `/register`, `/sitemap`, `/sitemap.xml`, `/faq`, `/artwork/*`, `/admin/login` și `/up` nu sunt capturate de catalog, cele trei redirecturi 301 legacy, redirectul de la prefixul legacy pentru slug-uri curente, 404 pentru un `/collections/...` inexistent, absența oricărui canonical `/collections/...`, plus două teste de gardă care verifică automat sincronizarea `ReservedSlugs` cu tabela de rutare și cu `public/`;
- `tests/Feature/Catalogue/CatalogueNavigationTest.php` (11 teste): headerul expune toate categoriile și subcategoriile, categoriile fără produse rămân vizibile, taxonomia inactivă nu apare, meniul mobil expune aceeași taxonomie accesibil, footerul listează cele patru categorii fără subcategorii, pagina de categorie randează copy-ul exact și copiii fără grilă ruptă, produsele featured sunt deduplicate, subcategoria randează copy, breadcrumb și linkuri către surori, colecția goală arată starea prietenoasă, conținutul editorial apare doar când există, iar navigația nu produce N+1;
- `tests/Feature/Catalogue/CatalogueSeoTest.php` (rescris, 6 teste): metadata/canonical/paginare pe subcategorie, fallback-urile de titlu și descriere pe ambele niveluri, conținutul exact al `sitemap.xml`, tranziția automată `noindex,follow` → indexabil la atribuirea unui produs, ierarhia din sitemap-ul HTML;
- `tests/Feature/Admin/ProductCategoryAdminTest.php` (10 teste): restricțiile de acces rămân intacte, crearea unei categorii top-level și a unei subcategorii, selectorul de părinte oferă doar top-level și se exclude pe sine, slug rezervat respins ca eroare de formular, o categorie cu copii nu poate deveni subcategorie, preview-ul de URL reflectă ierarhia, tabelul afișează Type și calea canonică, iar selectorul de categorii al produsului folosește etichete `Părinte — Copil` și doar recorduri leaf.

Verificări recente trecute:

- suita completă după această fază: **300 teste trecute, 1949 assertions**, cu 5 eșecuri preexistente (față de 251 trecute / 1729 assertions și 6 eșecuri preexistente înainte de fază);
- `npm run build`: 1607 module, build reușit;
- `php artisan migrate:fresh --seed` urmat de `php artisan db:seed`: taxonomia rămâne 4 categorii + 20 subcategorii, fără duplicate;
- migrarea `parent_id` verificată explicit pe o bază populată: rândurile din `product_category` supraviețuiesc (5 înainte, 5 după);
- `tests/Feature/Commerce` integral verde, deci basket/checkout/payment/account/order support nu au fost afectate.

- `tests/Feature/Commerce/OrderSupportTest.php` + `tests/Feature/Admin/OrderSupportAdminTest.php`: 42 teste, 92 assertions;

- testele Stripe, payment și cart: 19 teste, 172 assertions;
- build Vite production pentru Embedded Checkout;
- teste noi pentru My Details/checkout prefill/lookup de adresă: `CustomerProfileTest` (16 teste, 65 assertions), `CheckoutPrefillTest` (12 teste, 45 assertions), `AddressLookupTest` (7 teste, 32 assertions), plus 2 teste noi de regresie în `CartCheckoutTest`;
- suita `tests/Feature/Commerce` completă după această fază: 70 teste trecute, 452 assertions;
- suita completă curentă (incl. Order Support, Phase 4): 251 teste trecute, 1729 assertions, și aceleași 6 eșecuri preexistente/nelegate (artwork regeneration lineage și assertions de markup ale workspace-ului/catalogului/paginării categoriilor), verificate individual că nu s-au schimbat față de starea dinaintea acestei faze;

- PNG 300 PPI, `pHYs`, alpha și dimensiuni fizice;
- Pencil Tin final și preview la schimbarea variantei;
- toate cele 6 teste Lunchbox (67 assertions);
- flow-urile basket/checkout și end-to-end;
- prompturile AI v5 și provider integration;
- workspace/progress polling.

Există assertions vechi, de markup exact, cunoscute ca nealiniate cu UI-ul curent, plus o problemă separată de lineage la regenerare. Cele **5 eșecuri preexistente rămase**, verificate individual că au aceeași cauză ca înainte de această fază (niciunul nu ține de taxonomie):

| Test | Cauză |
|---|---|
| `ArtworkSessionTest > regeneration is immutable and has no generation limit` | `parent_generation_id=null` în locul generației anterioare |
| `ComposedDesignTest > artwork page uses variant specific supplier examples…` | caută exact markup-ul `750 ml / £16.50` într-o structură veche |
| `ProductArtworkWorkspaceTest > start redirects to product…` | caută exact `bg-black/80`, înlocuit cu CSS explicit `rgba(0,0,0,.8)` |
| `ProductArtworkWorkspaceTest > bottle product starts with colour name photo…` | caută exact `:disabled="submitting \|\| !photoReady() \|\| !nameValue.trim()"` |
| `StationeryPencilTinProductTest > seeding is idempotent and workspace left column is sticky…` | caută exact `class="lg:sticky lg:top-24 lg:self-start"`, dar elementul are clase responsive suplimentare |

Al șaselea eșec preexistent (`CatalogueSeoTest > category metadata canonical breadcrumbs and pagination are correct`) **a fost reparat** în această fază: testul a fost rescris pentru arhitectura nouă, iar assertion-ul `assertDontSee('utm_source')` — care era greșit, fiindcă linkurile de paginare poartă legitim query string-ul — a fost înlocuit cu o verificare precisă că parametrul de tracking nu ajunge în `canonical`.

Acestea sunt datorii de test, nu erori demonstrate ale fluxurilor funcționale.

Acestea sunt datorii de test, nu erori demonstrate ale fluxurilor funcționale. Suita completă trebuie rulată și aceste expectations actualizate când se face curățarea testelor.

## 17. Dezvoltare locală

Configurare recomandată:

```env
AI_IMAGE_PROVIDER=fake
AI_IMAGE_FAKE_FAILURE=false
QUEUE_CONNECTION=database
PAYMENT_PROVIDER=fake
FAKE_PAYMENTS_ENABLED=true
CHECKOUT_TAX_STRATEGY=zero_uk
```

Pentru Stripe test mode se folosesc `PAYMENT_PROVIDER=stripe`, `STRIPE_SECRET_KEY`, `STRIPE_PUBLISHABLE_KEY` și `STRIPE_WEBHOOK_SECRET`. Forwarding local: `stripe listen --forward-to http://127.0.0.1:8000/api/webhooks/stripe`. Cheia `pk_...` este publică pentru Stripe.js; cheile `sk_...` și `whsec_...` nu se salvează în repository.

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
2. Operaționalizarea Stripe în producție: chei live, endpoint webhook în Dashboard, alerte și procedură administrativă de refund.
3. URL semnat sau upload direct pentru artwork-ul consumat de tipografie.
4. Verificare automată suplimentară pentru profil de culoare/cerințe specifice fiecărui provider, dacă acesta le cere.
5. Probe vizuale reale pentru prompturile de postură v5.
6. Curățarea assertions de markup vechi și rularea periodică a suitei complete.
7. Monitorizare operațională pentru queue, AI failures, fulfilment și storage growth.
8. Password reset, email verification, 2FA, social login și hardening avansat pentru conturile clienților.
9. Nu există carte de adrese/adrese salvate multiple, etichete (Home/Work), adresă de facturare separată sau CRUD de adrese — `CustomerProfile` are o singură adresă de livrare implicită per client.
10. `Postcodes.io` (providerul implicit din `.env.example`) nu întoarce o listă de adrese la nivel de proprietate. Pentru dropdown real de adrese este disponibil `HomedataAddressLookupProvider` (`ADDRESS_LOOKUP_PROVIDER=homedata`), cu tier gratuit de 100 apeluri/lună fără card; peste acest volum, sau pentru date suplimentare (ex. county), e nevoie de un cont plătit Homedata sau de comutarea către alt provider (Ideal Postcodes, getAddress.io) prin aceeași variabilă.
11. Schimbarea emailului din My Details nu declanșează verificare de email (consistent cu punctul 8).
12. Order Support nu trimite niciun email (confirmare client sau notificare admin) — Phase 5 va adăuga arhitectura de email; câmpurile `contact_email`/`reference`/relația cu `Order`/`status` sunt deja pregătite pentru asta.
13. Order Support nu creează automat o comandă de înlocuire, nu rambursează și nu declanșează nicio acțiune TreatPod — orice rezultat rămâne o decizie de admin/om.
14. **PRODUSELE NOI NU AU FOST ÎNCĂ IMPLEMENTATE.** Această fază a livrat exclusiv arhitectura de informație a catalogului: taxonomie, admin, storefront, navigație și rutare/SEO. Nu au fost create produse noi, variante, prețuri, dimensiuni de tipar, mapping-uri de furnizor, template-uri de print sau înregistrări de fulfilment. Produsele planificate (Personalised Kids Water Bottle 750 ml, Personalised Lunch Box, Personalised Pencil Tin, Personalised School Backpack, Personalised Lunch Bag, Personalised Storybook Wall Print A3, Framed Storybook Portrait A3, Personalised Desk Portrait, Personalised Keepsake Box, Personalised Pet Portrait, Personalised Pet Bowl, Personalised Pet Christmas Ornament) aparțin fazei următoare de catalog/produse.
15. 17 dintre cele 20 de subcategorii sunt momentan goale, deci `noindex,follow` și în afara `sitemap.xml`. Devin automat indexabile și intră în sitemap la primul produs activ atribuit — fără intervenție manuală. Până atunci **nu sunt destinații pregătite pentru campanii Google Ads**.
16. `Gifts & Occasions` nu are produse proprii; subcategoriile ei vor redistribui produse din celelalte familii în faza următoare, prin pivotul many-to-many existent, fără duplicarea produselor.
17. Imaginea de categorie este **opțională** și nu blochează publicarea. Nu au fost create assets de marketing: în acest moment doar subcategoriile care conțin deja un produs afișează o imagine reală (moștenită din primul produs), restul afișează blocul neutru. Încărcarea imaginilor dedicate din admin rămâne o sarcină de conținut.
18. Nu există redirect automat pentru slug-urile de categorie schimbate din admin (echivalentul `ProductSlugRedirect` pentru categorii). Redenumirea unui slug de categorie rupe URL-ul vechi; harta din `config/catalogue.php` acoperă doar migrarea legacy `/collections/...`.
19. Nu există istoric de Order Support în `My Account` (deliberat, scop redus pentru această fază) — singurul punct de acces pentru client este CTA-ul de pe detaliul comenzii/confirmare/nav.

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

### 14 august 2026 — Stripe Checkout

- instalat Stripe PHP SDK 18 și adăugat gateway intern înlocuibil în teste;
- implementat Stripe-hosted Checkout cu line items dinamice din OrderItem și fără catalog Stripe;
- separat StartPayment în tranzacții scurte în jurul apelului extern și păstrat providerul fake;
- adăugat return guest-protected, webhook semnat, deduplicare și reconciliere idempotentă comună;
- implementate stările paid/pending/async failed/expired și refund-ul providerului;
- documentate configurarea test, Stripe CLI, sandbox purchase, securitatea și limitările.

### 14 august 2026 — corecție overlay modal artwork

- înlocuit utilitarul Tailwind de overlay cu o regulă CSS explicită: negru 80% pe mobil și negru solid pe desktop;
- eliminată situația în care fotografia rămânea aproape complet luminoasă din cauza clasei de opacitate absente din CSS-ul servit.
- pe desktop, conținutul este într-un panou central negru, cu padding și umbră; pagina site-ului, nu fotografia încărcată, rămâne vizibilă sub overlay.
- diamondurile desktop au dimensiuni de 16–32 px și sunt distribuite în jurul zonei centrale, fără să intre peste informație.

### 14 august 2026 — Stripe Embedded Checkout

- înlocuit redirectul către Stripe-hosted page cu formularul Embedded Checkout montat în pagina Kattie;
- adăugate cheia publică Stripe, endpointurile guest-protected pentru session/status și reutilizarea sesiunilor Pending deschise;
- păstrate Dynamic Payment Methods, return-ul pentru autorizări externe, webhook-ul și reconcilierea idempotentă;
- `client_secret` rămâne tranzitoriu și nu este salvat în metadata sau loguri;
- adăugate loading, retry și starea de confirmare cu polling la trei secunde.

### 14 august 2026 — retenția basket-ului până la plată

- basket-ul rămâne activ și vizibil după crearea comenzii, inclusiv pentru plăți Pending, Failed sau Cancelled;
- conținutul rămâne editabil în timpul plății, iar reluarea pornește întotdeauna prin pagina Checkout, nu direct prin Payment;
- basket-ul este marcat `converted` numai când plata finalizează comanda ca Paid.

### 14 august 2026 — basket editabil în timpul checkout-ului

- eliminată starea vizuală `Checkout in progress` care înlocuia controalele basket-ului;
- quantity, Change artwork și Remove rămân funcționale înainte de confirmarea plății;
- orice modificare invalidează în siguranță checkout-ul vechi și păstrează snapshot-ul Order-ului istoric nemodificat;
- sesiunile Stripe încă deschise sunt expirate înaintea modificării, prevenind plata accidentală a unui total vechi.
- butonul principal din basket duce întotdeauna la Checkout; o comandă pending nu mai schimbă linkul în `Continue payment` și nu mai poate sări peste formularul de checkout.

### 14 august 2026 — blocarea variantelor fără fulfilment mapping

- variantele de produse templated fără exact un mapping activ sunt afișate ca `Unavailable` și nu mai pot fi selectate în pagina produsului sau în editor;
- selecția este validată și server-side înainte de crearea ori reconfigurarea sesiunii artwork;
- prevenită situația în care un apel AI reușește, dar randarea preview-ului eșuează ulterior din lipsa rezoluției de producție.

### 14 august 2026 — sumar sticky în checkout

- formularul datelor de livrare și sumarul checkout-ului sunt afișate în două coloane pe desktop;
- sumarul păstrează poziția în dreapta în timpul completării formularului și conține butonul care trimite același formular;
- pe mobil, sumarul rămâne sub formular în fluxul normal al paginii.
- sumarul afișează fiecare artwork aprobat și defalcarea provizorie completă: subtotal, livrare UK, taxă și total.
- grila desktop începe de la partea superioară a secțiunii, astfel încât `Order summary` este poziționat în dreapta întregului bloc și aliniat cu începutul Checkout-ului, nu doar cu formularul.

### 14 august 2026 — metode de livrare administrabile

- introdus `ShippingMethod` ca sursă unică pentru Checkout, Order, Stripe și viitorul fulfilment;
- seed-uite pentru TreatPod Royal Mail 48 Tracked (£3.50, 5–8 zile), Royal Mail 24 Tracked (£4.13, 4–7 zile) și DPD (£7.49, 4 zile);
- adăugat CRUD Filament cu preț în pence, provider/service code, țară, interval, status și sort order;
- Checkout filtrează metodele după providerul comun al tuturor variantelor, preselectează cea mai ieftină și recalculează sumarul vizual;
- Order păstrează ID-ul și snapshot-ul imuabil al livrării, iar resolverul autoritativ calculează shipping-ul exclusiv din snapshot;
- Stripe primește metoda selectată ca line item separat, iar comenzile mixte fără provider comun sunt blocate înainte de payment.

### 14 august 2026 — layout payment în două coloane

- sumarul comenzii și formularul Stripe Embedded Checkout sunt aliniate unul lângă altul pe desktop și se așază vertical pe mobil;
- denumirea publică `Storybook Cartoon` este afișată simplificat ca `Cartoon` în toate etichetele storefront: homepage, configurator, Basket, Checkout și Payment;
- eliminat mesajul auxiliar despre traseul datelor cardului de sub formularul Stripe.
- iconul Basket din header folosește simbolul de cărucior, păstrând badge-ul cantității.

### 14 august 2026 — Customer Account și Order History

- adăugate registration, login, remember me și logout storefront, separate de autentificarea Filament;
- conturile de client folosesc numai email și parolă, au numele opțional și nu pot primi acces admin din request;
- adăugate overview, My Orders, detaliu privat, statusuri publice și thumbnails autorizate per comandă;
- comenzile autentificate sunt legate prin `user_id`, iar guest checkout rămâne neschimbat;
- basket-ul browserului este păstrat și reconciliat determinist la autentificare;
- confirmation oferă guest-ului revendicarea opțională a unei singure comenzi, protejată prin token și email;
- testele Account, Cart, Payment, Stripe și end-to-end au trecut împreună: 33 teste și 335 assertions; suita completă a încheiat cu 161 passed, 6 failed, fără eșec nou în funcționalitatea Account.
- formularele logout folosesc acțiune relativă pe hostul curent și submit explicit, evitând pierderea cookie-ului de sesiune când mediul local este accesat printr-un hostname diferit.

### 15 august 2026 — My Details, formular partajat de checkout și fundația de lookup postcode UK

- adăugat `CustomerProfile` (`user_id` unic, prenume/nume/telefon opționale, `default_shipping_address` criptat `encrypted:array`), relația `User::customerProfile()` și migrarea `2026_08_15_000100_create_customer_profiles`;
- extrasă normalizarea de postcode din `CheckoutRequest` în `App\Support\UkPostcode`, folosită acum identic de checkout, My Details și lookup-ul de adresă; comportamentul existent de checkout a rămas byte-identic, verificat prin suita `CartCheckoutTest` neschimbată;
- adăugat `App\Support\ShippingAddressComparator` pentru compararea server-side, normalizată (fără spații/case, fără nume, fără country) a adresei introduse față de adresa implicită salvată;
- adăugată fundația de lookup de adresă UK: interfața `AddressLookupProvider`, DTO-urile `AddressLookupResult`/`AddressLookupAddress`, providerul gratuit `PostcodesIoAddressLookupProvider` (fără cheie API, doar validare/normalizare, `addresses` mereu gol), `config/address_lookup.php`, binding în `AppServiceProvider` selectabil prin `ADDRESS_LOOKUP_PROVIDER`, și endpoint-ul public `GET /address-lookup` (throttled `20,1`), care nu blochează niciodată checkout-ul la eșec/timeout extern;
- adăugat componentul Blade partajat `resources/views/components/customer-details-form.blade.php` (contact + adresă, postcode-first cu „Find address” și fallback „Enter address manually”, atribute `autocomplete` corecte), folosit atât în Checkout, cât și în My Details — nu mai există două implementări separate ale formularului;
- adăugată pagina `My Details` (`GET /account/details`) cu autosave pe blur, câmp cu câmp, prin `PATCH /account/details` (`CustomerProfileController`, acțiunea `UpdateCustomerProfile`), cu validare parțială (`sometimes`/`nullable`) care permite salvarea unui singur câmp; schimbarea emailului normalizează, verifică unicitatea și actualizează `User.email` fără logout și fără să modifice emailul istoric al comenzilor; adăugat tab-ul „My Details” în navigarea contului, între „My Orders” și „Sign out” („My Characters” nu a fost adăugat — nu există pe `main`);
- `CheckoutController::show()` pre-completează formularul pentru clienți autentificați cu prioritatea `old()` → adresa comenzii pending → `CustomerProfile` → `User.email` → gol, fără să afecteze idempotența comenzii pending existente; `CheckoutController::store()` actualizează `CustomerProfile` numai când clientul bifează explicit „Save this as my default address” și adresa introdusă diferă de cea salvată — `Order.shipping_address`/`email`/`phone` rămân snapshot imuabil neschimbat de editările ulterioare ale profilului;
- teste noi: `CustomerProfileTest` (16 teste), `CheckoutPrefillTest` (12 teste), `AddressLookupTest` (7 teste), plus 2 teste de regresie în `CartCheckoutTest`; suita `tests/Feature/Commerce` a trecut integral (70 teste, 452 assertions), iar suita completă a înregistrat 198 teste trecute și aceleași 6 eșecuri preexistente, nelegate de această schimbare (workspace/catalogue markup și lineage de regenerare artwork).

### 15 august 2026 — corecție mesaj lookup și al doilea provider de adresă (Homedata)

- corectată componenta `customer-details-form`: mesajul „couldn't look up” apărea la fiecare căutare de postcode, inclusiv la succes, pentru că Postcodes.io întoarce mereu `addresses: []`; acum sunt distinse explicit postcode invalid/eșec (`valid:false`) de postcode valid fără listă de proprietăți (`valid:true`, `addresses: []`), fiecare cu mesajul potrivit;
- adăugat al doilea provider de lookup, `HomedataAddressLookupProvider` (`app/Providers/AddressLookup/HomedataAddressLookupProvider.php`), care întoarce efectiv adrese la nivel de proprietate din tier-ul gratuit Homedata (100 apeluri/lună, fără card); activabil prin `ADDRESS_LOOKUP_PROVIDER=homedata` + `HOMEDATA_API_KEY`, fără nicio modificare în Checkout, My Details sau componenta partajată — confirmă că abstracția `AddressLookupProvider` suportă comutarea de provider fără cuplare la Postcodes.io;
- teste noi: `HomedataAddressLookupProviderTest` (5 teste); suita `tests/Feature/Commerce` completă: 75 teste trecute, 463 assertions.

### 15 august 2026 — autocomplete de adresă cu Google Places (New) și rebranding Kattie.uk

- brand-ul storefront a fost redenumit `Kattie.uk` peste tot (titluri, meta, header, footer, email de contact); iconul SVG „cat" din header/footer a fost înlocuit cu `public/images/icon.gif`; tagline-ul footer devine „LITTLE FACES. BIG LOVE";
- UX-ul de căutare adresă a fost înlocuit cu autocomplete text-liber (Google Places API (New) — Autocomplete + Place Details), nu mai este postcode-first: clientul scrie adresa, primește sugestii live (debounce 300ms), alege una și câmpurile (address_line_1/2, city, county, postcode) se completează automat; câmpurile rămân oricând editabile manual;
- adăugate `GooglePlacesAddressLookupProvider::suggest()`/`resolve()` (metode publice specifice, în afara interfeței `AddressLookupProvider`, folosite direct de noul `AddressAutocompleteController`), rute `GET /address-autocomplete` (`throttle:60,1`) și `GET /address-autocomplete/{placeId}` (`throttle:60,1`), fără autentificare (disponibile și la guest checkout);
- providerul primește la `suggest()` doar textul introdus de client (fără nume/email/telefon/date comandă); niciun eșec al Google Places nu produce 500 — răspunsul e mereu `{"suggestions":[]}` sau `{"resolved":false}`, cu introducere manuală mereu disponibilă;
- constatare importantă: nici Postcodes.io, nici Google Places nu pot întoarce „toate adresele de la un cod poștal" — Postcodes.io validează doar, iar Google Places rezolvă un cod poștal la nivel de zonă, nu de proprietăți individuale; `HomedataAddressLookupProvider` (cu cheie server corectă, tip `prefix.secret`) rămâne singura opțiune pentru acel flux specific, dacă va fi nevoie ulterior;
- teste noi: `AddressAutocompleteTest` (6 teste); suita `tests/Feature/Commerce` completă: 81 teste trecute, 476 assertions; suita completă a aplicației: 209 teste trecute, aceleași 6 eșecuri preexistente nelegate de această schimbare.

### 15 august 2026 — Order Support (Phase 4)

- adăugat fluxul complet de suport pentru comenzi: pagină publică `GET /order-support`, formular `POST /order-support` (`throttle:10,60`), confirmare `GET /order-support/submitted` (referință citită din flash de sesiune, nu din URL — fără resubmit la refresh, fără reference ghicibil);
- domeniu nou: migrarea `2026_08_15_000200_create_order_support_requests_table`, modelul `App\Models\OrderSupportRequest` (ULID, `belongsTo Order`/`User` nullable), enum `App\Enums\OrderSupportStatus` (`Open|Reviewing|Resolved|Closed`, implementează `HasColor`/`HasLabel` pentru Filament), acțiunea tranzacțională `App\Domain\Orders\Actions\CreateOrderSupportRequest` (generează referința unică `SUP-XXXXXX`, stochează poza cu cleanup la eșec, înregistrează analytics) — nu scrie niciodată în `Order`/`Payment`/`PrintAsset`/`FulfilmentSubmission`;
- ownership: autentificat → strict `Order.user_id === auth()->id()` (parametrul `?order=` e doar preselecție, reverificată server-side, ignorată silențios dacă nu aparține userului); guest → `GuestContext` (același cookie ca la checkout) SAU email normalizat identic cu `Order.email`; orice combinație greșită (comandă inexistentă, email greșit, ambele) produce exact același mesaj generic, fără enumerare;
- poza este opțională, validată pe conținut real (JPEG/PNG/WebP, max 10 MB), stocată cu cheie aleatorie pe disk-ul privat `local` sub `order-support/{id}/photo.{ext}` — niciodată numele fișierului clientului, niciodată disk public; accesibilă doar prin ruta admin `GET /admin/order-support/{orderSupportRequest}/photo` (`auth` + verificare `is_admin`);
- resursă Filament nouă `OrderSupportRequestResource` (grup „Customer Support”): listă cu referință/comandă/status/email/dată, pagină de editare needitabilă în afară de `status`, acțiune „View photo” către ruta privată; nicio resursă Order nouă în Filament (nu exista una anterior — în loc de asta, un rezumat citit din relația `order` este afișat direct în formular);
- puncte de intrare: CTA „Get help with this order” pe `/account/orders/{orderNumber}`, link discret pe pagina de confirmare comandă (prefill automat pentru guest via `GuestContext`), link „Order Support” în header (desktop + mobil, cu dropdown propriu care conține și „Track Order”/„Something wrong?”) și în footer;
- fără email-uri (Phase 5), fără înlocuire automată de comandă, fără acțiune TreatPod — cererea rămâne exclusiv de revizuire umană;
- teste noi: `tests/Feature/Commerce/OrderSupportTest.php` + `tests/Feature/Admin/OrderSupportAdminTest.php` (42 teste, 92 assertions); adăugat `database/factories/OrderFactory.php` (nu exista anterior) pentru a reduce duplicarea fixture-urilor de test; suita completă a aplicației: 251 teste trecute, 1729 assertions, aceleași 6 eșecuri preexistente nelegate (verificate individual că sunt identice cu cele dinaintea acestei faze);
- curățare de brand: corectate mențiunile rămase „Cattie”/„Cattie.uk” în copy real customer-facing și documentație — `config/information-pages.php` (inclusiv adresele `support@cattie.uk` → `support@kattie.uk`), `config/commercial-policies.php`, `config/product-assets.php` (alt text), `database/seeders/CatalogueSeeder.php` (nume/descrieri/meta produse și categorii), `README.md`; nu au fost redenumite identificatori de cod/date istorice (`GuestContext::COOKIE = 'cattie_guest_token'`, prefixul `CAT-` al numărului de comandă, numele fișierului de test `CattieAdminV1Test.php`) — riscul de renaming depășea beneficiul pentru această fază;
- modificări de design cerute ulterior în aceeași fază: token-ul `--color-coral` schimbat la `#fc5997`; dropdown-uri hover pe desktop pentru Shop (categorii active cu produse), Order Support (Track Order + Something wrong?) și My Account (My Orders/My Details/Sign out, click pe etichetă navighează direct la `/account`), cu închidere reciprocă (un singur dropdown deschis la un moment dat) și echivalent pe mobil; secțiunea „How it works” de pe homepage rescrisă cu imaginile reale din `public/images/how/{01..04}.png`, lățime completă de ecran, layout număr+titlu sus / text+imagine jos; secțiunea „Choose the feeling” de pe homepage afișează acum imaginile reale ale stilurilor de artwork; crescute timeout-urile Google Places (`GOOGLE_PLACES_TIMEOUT=10`, `GOOGLE_PLACES_CONNECT_TIMEOUT=6`) după erori intermitente de conexiune observate în logs.

### 16 august 2026 — arhitectura canonică de informație a catalogului (taxonomie, admin, storefront, SEO)

- **NU au fost create produse noi.** Această fază a livrat exclusiv arhitectura de informație: taxonomie ierarhică, admin, template-uri de storefront, navigație și rutare/SEO. Produsele planificate aparțin fazei următoare;
- `product_categories` a devenit ierarhic prin migrarea `2026_08_16_000100_add_parent_id_to_product_categories`: `parent_id` nullable self-referențial, `cascadeOnUpdate`/`restrictOnDelete`, index `(parent_id, sort_order)`. Migrarea declară explicit `public $withinTransaction = false` — pe SQLite adăugarea unui foreign key se implementează prin reconstrucția tabelei, iar `PRAGMA foreign_keys` este ignorat în interiorul unei tranzacții, ceea ce ar fi făcut ca `drop table` intermediar să cascadeze și să șteargă **toate** rândurile din `product_category` pe o bază deja populată; verificat explicit că pivotul supraviețuiește (5 rânduri înainte, 5 după);
- `ProductCategory` a primit `parent()`, `children()`, `scopeTopLevel()`, `scopeSubcategories()`, `isTopLevel()`, plus `url()` și `urlFor()` ca **unică** sursă de generare a URL-urilor de categorie; niciun host hardcodat și nicio concatenare manuală de căi în Blade;
- invariantele ierarhiei (adâncime maximă 2, fără self-parent, fără reparentarea unei categorii cu copii, fără ștergerea unei categorii cu copii, slug rezervat interzis la top-level, format de slug) sunt validate în trei straturi: formularul Filament (erori inline, stratul pentru utilizator), `ProductCategoryObserver` (plasă de siguranță pentru seedere/CLI/tinker, aruncă `InvalidCategoryHierarchyException`) și foreign key-ul din bază;
- rutare nouă: `/{category-slug}` și `/{category-slug}/{subcategory-slug}`, declarate ultimele în `routes/web.php`, cu constrângeri regex construite din `App\Support\ReservedSlugs`, astfel încât rutele fixe nu pot fi capturate niciodată — routerul nu face match și trece mai departe, în loc să rezolve și să dea 404. `ReservedSlugs` este `const` (compatibil cu `route:cache`) plus `SLUG_PATTERNS` pentru prefixul hash-uit al Livewire; două teste de gardă verifică automat sincronizarea cu tabela de rutare și cu `public/`;
- validarea de ownership pentru subcategorii este structurală (copilul este căutat prin părinte), deci `/pets-family/personalised-water-bottles-for-kids` întoarce 404, nu redirect;
- `/collections/{slug}` a fost repurposat ca redirect 301 (`LegacyCollectionRedirectController`): hartă explicită în `config/catalogue.php` → căutare după slug → 404; ținta este mereu `$category->url()`, deci nu apar lanțuri de redirect. Niciun `/collections/...` nu mai este canonic și niciunul nu intră în `sitemap.xml`;
- taxonomia canonică seed-uită idempotent de `CategoryTaxonomySeeder`: 4 categorii de nivel superior și 20 de subcategorii, cu copy-ul exact furnizat, stocat în bază (nu în Blade);
- categoriile legacy `School & Lunch`, `Kids Drinkware`, `School Accessories` au fost eliminate prin secvența migrează → detașează explicit pivoturile rămase → șterge, fără a depinde de comportamentul de cascade al foreign key-ului; niciun produs nu a fost șters sau reconfigurat;
- șase produse existente au fost mutate în subcategoriile leaf evidente (bottle, lunchbox, pencil tin, wall print, pet portrait, family print); mug-ul și cushion-ul au rămas deliberat neatribuite;
- storefront: template nou de Categorie (`categories/category.blade.php`) și template refăcut de Colecție (`categories/show.blade.php`), cu breadcrumb-uri corecte pe fiecare nivel, `BreadcrumbList` JSON-LD, `ItemList` doar când există produse, stare prietenoasă pentru colecțiile goale și linkuri interne către subcategoriile-surori;
- regula SEO pentru colecții goale: subcategorie activă fără produse active → `robots: noindex,follow`, canonical nested propriu, exclusă din `sitemap.xml`, dar vizibilă în navigație, pe pagina de categorie, în sitemap-ul HTML și la navigare directă; se inversează automat la primul produs atribuit, fără toggle manual;
- navigația a fost mutată pe `App\Support\CatalogueNavigation::topLevelWithChildren()` (două interogări, fără N+1, cu back-link părinte pentru a evita interogarea de fallback din `url()`); condiția „ascunde categoriile fără produse” a fost **eliminată** deliberat, ca structura să fie navigabilă înainte de importul produselor;
- meniu desktop transformat în mega-menu pe patru coloane cu `Shop all`, meniu mobil transformat în acordeon pe două niveluri cu atribute ARIA corecte, coloană `Shop` nouă în footer, secțiune nouă de categorii pe homepage și chip-uri „Shop by category” comutate pe cele patru categorii de nivel superior; branding, căutare, Order Support, Account și Basket au rămas neatinse;
- sitemap XML restrâns la categoriile de nivel superior active plus subcategoriile active cu cel puțin un produs activ (apartenența calculată cu o singură interogare pe pivot); sitemap HTML transformat în arbore complet, inclusiv subcategoriile goale;
- admin Filament: formular de categorie complet (parent searchable limitat la top-level, exclus pe sine, dezactivat + `dehydrated(false)` când are copii, `->rule()` pentru fiecare invariantă, preview read-only al URL-ului canonic prin `route()`), tabel cu Type/Parent/cale canonică/număr de produse/filtre și ștergere blocată cu notificare pentru categoriile cu copii; selectorul de categorii al produsului oferă doar recorduri leaf, cu etichete `Părinte — Copil`;
- pagina de produs și fluxul de artwork au rămas **funcțional neschimbate**: Phase A (Create) și Phase B (Preview & Approve), upload, generare, workspace, selecție de variantă, personalizare, preview, regenerare, aprobare și adăugare în basket. Singura modificare a fost trecerea linkurilor de categorie pe `$category->url()`; canonicalul produsului rămâne `/products/{slug}`, fără a alege arbitrar un părinte;
- teste noi: `CategoryHierarchyTest` (11), `CatalogueRoutingTest` (12), `CatalogueNavigationTest` (11), `ProductCategoryAdminTest` (10); `CatalogueSeoTest` și `ProductCategoryTest` rescrise pentru arhitectura nouă; `LunchboxProductTest` și `StationeryPencilTinProductTest` actualizate la noile subcategorii;
- suita completă: **300 teste trecute, 1949 assertions**, cu 5 eșecuri preexistente verificate individual că au aceeași cauză ca înainte (lineage de regenerare artwork și assertions de markup vechi); al șaselea eșec preexistent, din `CatalogueSeoTest`, a fost reparat în cadrul rescrierii. `npm run build` reușit.

### 16 august 2026 — imagini pe cardurile de subcategorie

- adăugată migrarea `2026_08_16_000200_add_image_to_product_categories` (`image_disk`, `image_storage_key`, `image_alt_text`), cu aceeași convenție ca `product_images`;
- adăugat `ProductCategory::cardImage()`, care rezolvă cascada imagine de categorie → imaginea principală a primului produs activ din categorie → `null` (bloc neutru), fără să emită vreodată `<img src="">`;
- extras componentul `resources/views/components/category-card.blade.php`: pe desktop text 50% stânga / imagine 50% dreapta cu `object-fit: cover`, imaginea ascunsă pe mobil, descrierea `line-clamp-4` și imaginea plafonată la `max-h-[12.25rem]` (titlu + patru rânduri + `Explore`), astfel încât cardurile dintr-un rând nu se pot întinde reciproc;
- grila de subcategorii de pe pagina de categorie a trecut de la trei la două coloane, ca imaginea de 50% să aibă spațiu real;
- adăugat `FileUpload` opțional în formularul Filament de categorie (secțiunea „Card image”), cu `image_disk` sincronizat automat și helper text care explică fallback-ul;
- teste noi în `CatalogueNavigationTest`: prioritatea imaginii de admin față de imaginea de produs, ascunderea pe mobil, plafonarea înălțimii și absența unui `<img>` gol când nu există nicio imagine;
- suita completă: **302 teste trecute, 1959 assertions**, aceleași 5 eșecuri preexistente; `npm run build` reușit.

### 16 august 2026 — fundaluri pe homepage

- secțiunea „How it works” a primit un fundal discret prin clasa nouă `.home-how-it-works` (gradient vertical `#fffaf3 → #f6ebe0 → #fffaf3`), definită în `@layer components` alături de celelalte componente;
- cardurile de categorie de pe homepage au devenit tile-uri cu imagine de fundal: `<img>` absolut cu `object-cover`, peste el `.category-tile-overlay` (`rgba(0,0,0,.8)`, aceeași convenție explicită folosită deja pentru overlay-ul de artwork), iar deasupra titlul și descrierea în alb, cu `Explore →` în coral; imaginea este marcată `alt=""` + `aria-hidden`, fiind decorativă;
- generate patru texturi abstracte din paleta brandului (`school-everyday`, `memories-keepsakes`, `pets-family`, `gifts-occasions`), versionate în `database/seeders/assets/categories/` și publicate pe disk-ul `public` de `CategoryTaxonomySeeder`; seeder-ul nu suprascrie niciodată o imagine încărcată din admin. Nu sunt fotografii — pot fi înlocuite oricând din Filament;
- corectată o capcană de selecție de coloane în `CatalogueNavigation`: fără `image_disk`/`image_storage_key`/`image_alt_text` în lista de `select`, tile-urile de pe homepage rămâneau fără imagine (exact aceeași clasă de problemă ca `id`/`parent_id`);
- teste noi în `CatalogueNavigationTest`: tile-urile afișează imaginea categoriei sub overlay, toate cele patru primesc overlay-ul, imaginea este decorativă, iar secțiunea „How it works” are fundal;
- suita completă: **304 teste trecute, 1967 assertions**, aceleași 5 eșecuri preexistente; `npm run build` reușit.

### 16 august 2026 — ajustări vizuale homepage

- **Constatare importantă:** un overlay plat `rgba(0,0,0,.8)` peste tile-urile de categorie ștergea complet imaginea — tile-ul devenea un dreptunghi aproape uniform, indiferent de fotografie. Verificat prin compunerea efectivă a imaginii cu overlay-ul, nu prin estimare. `.category-tile-overlay` a devenit gradient (`30% sus → 45% → 78% → 88% jos`): fotografia rămâne vizibilă în partea superioară, iar titlul, descrierea și `Explore →` din treimea inferioară păstrează contrast complet;
- texturile de categorie au fost regenerate cu contrast mult mai mare (highlight luminos + umbră adâncă per paletă), fiindcă degradeurile pastelate inițiale se aplatizau sub orice întunecare; fiecare categorie are acum o identitate cromatică clar distinctă (teal, warm rose, olive, magenta);
- banda `.home-how-it-works` a fost întărită (`cream → #f2d9c6 → cream`), iar cardurile de pas au devenit transparente — fără `bg-white`, fără `border-ink/5`, fără `shadow-sm`;
- imaginile de pas (`h-40 w-2/3 rounded-2xl object-cover`) au primit `mix-blend-multiply`, astfel încât fundalul lor alb se topește în banda secțiunii;
- suita completă: **304 teste trecute, 1967 assertions**, aceleași 5 eșecuri preexistente; `npm run build` reușit.

### 17 august 2026 — homepage cu cercuri, pagina de categorie aliniată la `/products`

- secțiunea „Where would you like to start?” a fost refăcută: fiecare categorie este acum un **cerc mare** (`aspect-square` + `rounded-full`, `object-cover`) cu numele categoriei dedesubt la aceeași dimensiune (`font-display text-2xl`); descrierile și overlay-ul au dispărut, iar `.category-tile-overlay` a fost eliminată din CSS ca să nu rămână cod mort. Pe mobil grila este pe **două coloane** (`grid-cols-2`), pe desktop patru;
- `ProductCategory::cardImage()` are acum cascada: imagine încărcată din admin → **fotografie reală de produs** (proprie, iar pentru o categorie de nivel superior și din subcategoriile ei) → textura de brand generată pentru slug → bloc neutru. Texturile nu mai sunt scrise în coloanele de imagine ale categoriei, deci o fotografie reală are prioritate față de un placeholder, iar re-seedarea nu atinge niciodată date din admin;
- **regresie de performanță prinsă de propriul test de N+1 și reparată:** rezolvarea imaginilor interoga produse per categorie. `CatalogueNavigation::hydrateProductImages()` face acum o singură interogare pentru tot arborele. Testul a fost întărit: nu mai verifică un prag arbitrar, ci demonstrează că numărul de interogări este **identic** când taxonomia se triplează;
- corectat un `BadMethodCallException` pe homepage: `flatMap()` întoarce un `Support\Collection`, nu `Eloquent\Collection`, deci `modelKeys()` nu există — cheile se iau acum explicit;
- **pagina de categorie** (`/school-everyday`) folosește exact layout-ul de la `/products`: eyebrow, `H1`, intro, apoi subcategoriile ca pills orizontale sticky, urmate de grila de produse când există. Componenta `category-card` a fost ștearsă, nemaifiind folosită. **Pagina de subcategorie a rămas complet neschimbată**;
- „One photo. Four little steps.”: carduri transparente, imagini cu `mix-blend-multiply`, bandă `.home-how-it-works` mai pronunțată;
- „Choose the feeling”: capetele împinse spre dreapta (`pr-3` + `ml-auto`) și gap redus la `gap-2`, ca boxul de text să câștige lățime;
- hero pe mobil: titlul folosește dimensiune fluidă (`text-[10vw]`) ca să ocupe consecvent trei rânduri pe orice lățime de ecran, iar conținutul este aliniat sus (`items-start` + `pt-0`), eliminând spațiul gol de deasupra eyebrow-ului;
- suita completă: **304 teste trecute, 1967 assertions**, aceleași 5 eșecuri preexistente; `npm run build` reușit.

### 17 august 2026 — titlul și introul subcategoriei vizibile pe mobil

- pagina de subcategorie avea întregul bloc de antet `hidden sm:block`, deci pe mobil dispăreau **și H1-ul, și textul introductiv** — rămânea doar grila de produse. Comportament moștenit din template-ul vechi de categorie;
- corectat: blocul este vizibil pe toate dimensiunile, cu tipografie adaptată (`text-2xl` pe mobil, `text-6xl` de la `sm` în sus); doar eyebrow-ul cu numele categoriei părinte rămâne ascuns pe mobil, la fel ca pe `/products` — contextul părinte este oricum prezent în breadcrumb;
- motivul este funcțional, nu estetic: subcategoriile sunt landing pages pentru SEO și Google Ads, iar Google indexează mobile-first — un H1 ascuns cu `display:none` în versiunea mobilă contrazice exact scopul pentru care au fost construite paginile, iar majoritatea vizitatorilor nu ar vedea nicio explicație a paginii;
- adăugat testul `headings and intro copy are not hidden on mobile`, care verifică pe ambele niveluri că nici containerul antetului, nici `H1` nu poartă clasa `hidden` și că `short_description` este randat;
- suita completă: **305 teste trecute, 1979 assertions**, aceleași 5 eșecuri preexistente; `npm run build` reușit.

### 17 august 2026 — intro cu „See more” pe mobil

- adăugat componentul `resources/views/components/expandable-text.blade.php`, folosit pentru textul introductiv de pe paginile de categorie și subcategorie: pe mobil copy-ul este limitat la **două rânduri**, cu „… See more” inline la capătul rândului al doilea; la click se afișează textul complet, urmat de „See less”. Ambele controale sunt `font-semibold` (două trepte peste greutatea normală a textului);
- controalele apar **numai când textul chiar depășește două rânduri** — un intro scurt nu primește un buton inutil; măsurarea se reface la `resize`, fiindcă limitarea depinde de breakpoint;
- pe desktop (`sm` în sus) textul este afișat integral, fără niciun control;
- „… See more” este poziționat absolut peste capătul rândului al doilea, cu clasa `.see-more-fade` (gradient spre alb) ca textul să nu pară că intră direct în el;
- **impact SEO: niciunul.** `line-clamp` este exclusiv prezentațional (`overflow: hidden`), deci textul rămâne integral în DOM și randat — spre deosebire de `display: none`. `H1`, `title` și `meta_description` (care cade tot pe `short_description`) sunt neatinse;
- limitarea este aplicată ca **clasă statică**, nu prin binding Alpine: legată de Alpine, textul s-ar fi randat complet și s-ar fi colapsat după pornirea JS-ului, producând layout shift la fiecare încărcare (semnal Core Web Vitals). Există un test dedicat care blochează revenirea la varianta legată de JS;
- test nou `the mobile see more toggle never removes copy from the html`, care verifică prezența integrală a textului în markup, fallback-ul de `meta_description` și faptul că limitarea rămâne statică;
- suita completă: **306 teste trecute, 1986 assertions**, aceleași 5 eșecuri preexistente; `npm run build` reușit.
