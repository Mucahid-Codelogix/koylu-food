# Exact Online integratie — build-out plan

Status: klant-antwoorden binnen, klaar om te bouwen. Stack: PHP 8.4, Laravel 13, Filament 5, Livewire 4, Pest, DomPDF. Queue-driver: `database`.

Dit document is leidend voor de implementatie. Lees ook `.cursor/rules/exact-online.mdc` voor de werkafspraken.

---

## 1. Vastgestelde keuzes (klant)

| # | Onderwerp | Beslissing | Gevolg voor de bouw |
|---|---|---|---|
| 1 | Artikelmodel | **Eén artikel per product** (model A) in Exact | Gebruik `products.exact_article_code`. Geen artikelcode per verpakking nodig. |
| 2 | Factuureenheid | **Per kg** (kg × prijs/kg) | Factuurregels in kg ombouwen (nu per verpakking). Zie Fase A. |
| 3 | BTW | **Per product** laag (9%) of hoog (21%); buitenlandse klant **vrijgesteld** (0%) | Nieuw `vat_category` op product + snapshot; meervoudige BTW in totalen/UBL. Zie Fase A. |
| 4 | Boekmoment | **Pas na goedkeuring admin** | Push gekoppeld aan admin-actie, niet automatisch na levering. |
| 5 | Factuurnummer | **Exact is leidend** | Exact genereert het nummer; terugschrijven en op PDF/UBL tonen. |
| 6 | Deellevering | Factureer wat geleverd is; creditnota's **handmatig** in Exact | Geen creditnota-koppeling. Wel: chauffeur retour-notitie (Fase F). |
| 7 | Leverancier op factuur | **Niet** op de klantfactuur (alleen in de shop) | Geen wijziging op de factuur. |

**Al afgestemd:** sandbox-account bestaat (moet nog ingericht); 1 administratie + 1 test-administratie; betaalstatus terug pas later; landcode `05`=NL / `005`=BE (`customers.exact_article_suffix`) toont herkomst en hangt samen met btw-vrijstelling buitenland; aanspreekpunt = klant zelf.

---

## 2. Wat er al staat (niet opnieuw bouwen)

- `app/Services/InvoiceLineCalculator.php` — enige bron voor regels + totalen (record/PDF/UBL).
- `app/Services/InvoiceService.php` — `createFromDelivery`, `recalculateInvoice`, `generatePdf`, `generateUbl`.
- `app/Services/OrderItemSnapshotBuilder.php` + `ProductPricingService.php` — snapshot van verpakking, leverancier, `price_per_kg`, `unit_price`, `box_weight_kg`, gewichten op `order_items`.
- `tests/Feature/InvoiceConsistencyTest.php` — borgt dat record = PDF = UBL.
- Bestaande velden: `invoices.exact_invoice_id`, `customers.vat_number / exact_article_suffix / is_vat_exempt`, `products.exact_article_code`.

---

## 3. Datamodel-wijzigingen (migraties)

| Tabel | Veld | Type | Doel |
|---|---|---|---|
| `products` | `vat_category` | enum/string (`low`,`high`) | 9% of 21% per product |
| `products` | `exact_synced_at`, `exact_sync_error` | timestamp / text (nullable) | Sync-status artikel |
| `order_items` | `vat_rate` | decimal(5,2) (nullable) | **Snapshot** btw-tarief bij bestelling (9/21/0) |
| `customers` | `exact_account_id` | string (nullable, indexed) | Debiteur in Exact |
| `customers` | `exact_synced_at`, `exact_sync_error` | timestamp / text (nullable) | Sync-status klant |
| `invoices` | `exact_document_number` | string (nullable) | Officieel factuurnummer uit Exact |
| `invoices` | `exact_synced_at`, `exact_sync_error` | timestamp / text (nullable) | Sync-status factuur |
| `delivery_items` | `return_note` | text (nullable) | Retour-notitie chauffeur (Fase F) |
| `exact_tokens` | — | tabel | OAuth access/refresh token (versleuteld) + `expires_at` |
| `exact_sync_logs` | — | tabel | Audit: `syncable_type/id`, `action`, `status`, `request`, `response`, `error`, `created_at` |

> Geleverd gewicht in kg wordt **afgeleid**: `delivered_quantity × order_items.box_weight_kg` (snapshot). Geen nieuw veld nodig.

---

## 4. Fasen

### Fase A — Facturatie ombouwen naar kg + meervoudige BTW  ⚠️ eerst doen

Dit raakt bestaande facturen, dus vóór alle Exact-code en met sterke tests.

1. Migratie `products.vat_category` + `order_items.vat_rate`. Zet `vat_rate` in `OrderItemSnapshotBuilder` (snapshot bij bestellen).
2. `InvoiceLineCalculator` herzien zodat per regel geldt:
   - `delivered_kg = delivered_quantity × box_weight_kg` (ontbrekende levering ⇒ 0).
   - `line_subtotal = round(delivered_kg × price_per_kg, 2)`.
   - `vat_rate = customer.is_vat_exempt ? 0 : order_item.vat_rate` (9 of 21).
   - `line_vat = round(line_subtotal × vat_rate/100, 2)`.
3. Totalen: subtotaal = som regels; **BTW per tarief groeperen** (0/9/21), niet één tarief over het geheel.
4. PDF (`resources/views/pdf/invoice.blade.php`): toon kg-hoeveelheid en prijs/kg; BTW uitgesplitst per tarief.
5. UBL (`generateUbl`): één `cac:TaxSubtotal` per BTW-tarief; regels in kg (`unitCode="KGM"`), juiste BTW-code per regel.
6. Tests uitbreiden: gemengde BTW (9 + 21 in één factuur), btw-vrij, deellevering, en record = PDF = UBL op de cent.

**Aanname (bevestigen):** voor variabel-gewicht producten (hele kip) is geleverd kg = `delivered_quantity × box_weight_kg`. Als de klant op werkelijk gewogen kg wil factureren, is een extra "geleverd gewicht"-invoer bij de chauffeur nodig.

### Fase B — Exact OAuth + client

1. `composer require picqer/exact-php-client` (of dunne Guzzle-wrapper) — eerst goedkeuren.
2. `config/exact.php` + `.env`: `EXACT_CLIENT_ID`, `EXACT_CLIENT_SECRET`, `EXACT_REDIRECT_URI`, `EXACT_DIVISION` (configureerbaar voor test/productie).
3. Tabel `exact_tokens`; tokens **versleuteld** opslaan; proactieve refresh (Exact-token verloopt ±10 min).
4. `app/Services/Exact/ExactOnlineClient.php`: auth, refresh, retry/backoff (429/5xx), nette `ExactApiException`.
5. OAuth callback-route + controller. Filament-pagina "Exact-koppeling": verbinden + verbinding testen.
6. Testen tegen de **test-administratie** (division).

### Fase C — Klant-sync (debiteuren)

1. Migratie `customers.exact_account_id` + sync-status.
2. `app/Services/Exact/ExactCustomerMapper.php`: naam, adres, e-mail, telefoon, `vat_number`, landcode (`exact_article_suffix`), `is_vat_exempt` → juiste Exact BTW-instelling.
3. `app/Jobs/SyncCustomerToExact.php` (queue, retry): upsert, GUID terug naar `exact_account_id`, fouten naar log + `exact_sync_error`.
4. Trigger: `CustomerObserver` (created/updated) + lazy fallback bij factuur-push.
5. Tests met **gemockte** `ExactOnlineClient`.

### Fase D — Artikel-sync (één artikel per product)

1. Migratie sync-status op `products`.
2. `app/Services/Exact/ExactItemMapper.php`: `Product` → Exact Item (code = `exact_article_code`, eenheid **kg**, BTW-categorie laag/hoog uit `vat_category`).
3. `app/Jobs/SyncProductToExact.php` + observer op `Product` + handmatige Filament-actie "Sync naar Exact".
4. Filament: sync-status badge (synced/failed + fout).
5. Tests met gemockte client.

> Verkoopprijs in Exact is hooguit indicatief. De **factuur gebruikt altijd de snapshotprijs** uit `order_items` via `InvoiceLineCalculator`.

### Fase E — Factuur boeken in Exact (na goedkeuring)

1. Migratie `invoices.exact_document_number` + sync-status.
2. `app/Services/Exact/ExactInvoiceMapper.php`: SalesInvoice uit `InvoiceLineCalculator::lines()/totals()`; debiteur = `customer.exact_account_id`; regel → `exact_article_code`, kg, prijs/kg, BTW-code per regel.
3. `app/Jobs/PushInvoiceToExact.php`:
   - **Idempotent**: skip als `exact_invoice_id` gevuld; `Cache::lock("exact-invoice-{id}")` tegen dubbel boeken.
   - Zorg dat klant + artikelen bestaan (lazy sync), anders eerst aanmaken.
   - Exact genereert het nummer → sla `exact_document_number` + `exact_invoice_id` + `exact_synced_at` op.
   - **Volgorde i.v.m. "Exact leidend nummer":** boeken in Exact → nummer terug → PDF/UBL (her)genereren met het Exact-nummer → daarna versturen.
4. Trigger: Filament-actie "Goedkeuren & boeken in Exact" op de Invoice-resource (vervangt/uitbreiding van de huidige "Goedkeuren & versturen"). Niet automatisch na levering.
5. Tests: idempotentie (2× push = 1 factuur), bedragen gelijk aan PDF/UBL, gemengde BTW, deellevering.

### Fase F — Chauffeur retour-notitie (klein, los)

1. Migratie `delivery_items.return_note` (of `deliveries.return_note` als het per levering mag).
2. Invoer toevoegen in `app/Filament/Driver/Pages/DriverDeliveryPhase.php` (notitie bij gedeeltelijke/retour-levering).
3. Tonen voor kantoor in de admin (Delivery/Invoice). Geen koppeling met Exact (creditnota blijft handmatig).

### Fase G — Beheer & robuustheid

- `exact_sync_logs` viewer in Filament; sync-status badges op Klant/Product/Factuur; "Opnieuw proberen"-actie.
- "Herbereken concept"-actie (`InvoiceService::recalculateInvoice`) vóór push.
- Failed-jobs monitoring; queue-worker als supervisor in productie; mail-alert bij herhaald falen.

### Later (buiten scope nu)

- Betaalstatus terug uit Exact (`sent` → `paid`) via poll/webhook.

---

## 5. Volgorde & afhankelijkheden

```
Fase A (kg + BTW)  ──►  Fase B (OAuth)  ──►  Fase C (klanten)  ─┐
                                          └►  Fase D (artikelen) ─┴►  Fase E (boeken)  ──►  Fase G (beheer)
Fase F (retour-notitie) kan parallel, los van Exact.
```

Fase A eerst (verandert bestaande facturen). Fase B kan starten zodra de sandbox is ingericht. Mappers/jobs (C/D/E) zijn met een gemockte client te bouwen en te testen zonder live Exact.

---

## 6. Acceptatie-checklist

- [ ] Factuurregels tonen kg × prijs/kg; totalen kloppen op de cent in record, PDF én UBL.
- [ ] Gemengde BTW (9% + 21%) in één factuur correct uitgesplitst.
- [ ] Buitenlandse/ vrijgestelde klant → 0% overal consistent.
- [ ] Deellevering factureert alleen geleverd; niet-geleverd = 0.
- [ ] Klant en artikelen verschijnen correct in de **test-administratie**.
- [ ] Factuur wordt pas geboekt na admin-goedkeuring; nummer komt uit Exact en staat op de PDF.
- [ ] Twee keer boeken levert één factuur in Exact (idempotent).
- [ ] Chauffeur kan een retour-notitie achterlaten; kantoor ziet die terug.
- [ ] `vendor/bin/pint` schoon; volledige Pest-suite groen.

---

## 7. Risico's / aandachtspunten

- **kg-omrekening:** prijs/kg (4 dec.) vs. centen per regel — rond per regel, tel daarna op.
- **Meervoudige BTW:** groepeer per tarief; UBL vereist een `TaxSubtotal` per tarief.
- **Exact leidend nummer:** PDF/UBL pas definitief ná boeking; bewaak de volgorde.
- **Token-expiry / rate limiting:** proactief refreshen, retry met backoff, niet alles tegelijk pushen.
- **Idempotentie:** altijd `exact_invoice_id` + lock checken vóór boeken.
- **Snapshots:** prijs én btw-tarief vastleggen op `order_items` zodat latere wijzigingen historische facturen niet raken.
