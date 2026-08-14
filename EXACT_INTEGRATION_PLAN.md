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

### Fase D — Artikel-sync (één artikel per product)  ✅ gebouwd

1. Migratie sync-status op `products` + `exact_article_code` (terug op productniveau; backfill vanuit standaard-leverancier).
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

### Fase G — Beheer & robuustheid  ✅ gebouwd

- `exact_sync_logs` viewer in Filament; sync-status badges op Klant/Product/Factuur; **Opnieuw proberen**-actie op sync-log (dispatch naar queue).
- **Herbereken concept**-actie (`InvoiceService::recalculateInvoice`) vóór push.
- **Mislukte jobs** Filament-resource (`QueueFailedJobResource`) met retry via `queue:retry`.
- Dashboard toont mislukte syncs en Exact queue-jobs (24u).
- Mail-alert bij herhaald falen (`EXACT_ALERT_MAIL_TO`, drempel `EXACT_ALERT_FAILURE_THRESHOLD`) + bij definitief mislukte Exact queue-jobs.
- Productie: queue-worker als supervisor (buiten scope code; zie deployment).

### Fase H — Import uit Exact (Exact → app)  ✅ gebouwd

Bestaande stamgegevens staan al in Exact; die hoeven niet handmatig in de app te worden ingevoerd. Vanaf Fase C/D geldt **app → Exact** voor nieuwe records; Fase H vult de **tegenrichting** aan voor de eenmalige start en voor records die later in Exact worden aangemaakt.

#### Klanten (debiteuren)

1. `app/Services/Exact/ExactAccountToCustomerMapper.php`: Exact Account → `Customer`.
2. `app/Services/Exact/ExactCustomerImportService.php`: haal debiteuren op (`Status eq 'C'`), koppel of maak aan.
   - Match op `exact_account_id`, daarna `SearchCode` (`KOYLU-{id}`), daarna e-mail.
3. `app/Jobs/ImportCustomersFromExact.php` (queue) + Filament-knop op **Exact-koppeling** en **Verkoop → Klanten**.

#### Artikelen (producten)

1. `app/Services/Exact/ExactItemToProductMapper.php`: Exact Item → `Product` (naam, `exact_article_code`, `vat_category` uit BTW-code).
2. `app/Services/Exact/ExactProductImportService.php`: haal verkoopartikelen op (`IsSalesItem eq true`), koppel of maak aan.
   - Match op `exact_article_code`, daarna `KOYLU-P-{id}`, daarna productnaam.
   - Import zet `exact_synced_at`; **geen** terug-push naar Exact (observer slaat sync over bij import).
3. `app/Jobs/ImportProductsFromExact.php` (queue) + Filament-knop op **Exact-koppeling** en **Producten**.
4. Geïmporteerde producten hebben nog **geen** verpakking/leverancier — die stel je handmatig in voor shop-zichtbaarheid.

**Gebruik:** eenmalig bestaande Exact-data binnenhalen; daarna opnieuw draaien wanneer in Exact nieuwe debiteuren/artikelen zijn toegevoegd. Dagelijks nieuwe records blijven in de **app** aanmaken (Fase C/D pusht naar Exact). Import maakt **geen** shop-gebruikers aan.

### Later (buiten scope nu)

- Betaalstatus terug uit Exact (`sent` → `paid`) via poll/webhook.

---

## 5. Volgorde & afhankelijkheden

```
Fase A (kg + BTW)  ──►  Fase B (OAuth)  ──►  Fase C (klanten push)  ─┐
                                          └►  Fase D (artikelen) ─┴►  Fase E (boeken)  ──►  Fase G (beheer)
Fase F (retour-notitie) kan parallel, los van Exact.
Fase H (import uit Exact) na Fase C/D; eenmalig + herhaalbaar voor nieuwe Exact-debiteuren en -artikelen.
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
- [ ] Import uit Exact haalt debiteuren én artikelen binnen; herimport voegt nieuwe records toe zonder duplicaten.
- [ ] `vendor/bin/pint` schoon; volledige Pest-suite groen.

---

## 7. Risico's / aandachtspunten

- **kg-omrekening:** prijs/kg (4 dec.) vs. centen per regel — rond per regel, tel daarna op.
- **Meervoudige BTW:** groepeer per tarief; UBL vereist een `TaxSubtotal` per tarief.
- **Exact leidend nummer:** PDF/UBL pas definitief ná boeking; bewaak de volgorde.
- **Token-expiry / rate limiting:** proactief refreshen, retry met backoff, niet alles tegelijk pushen.
- **Idempotentie:** altijd `exact_invoice_id` + lock checken vóór boeken.
- **Snapshots:** prijs én btw-tarief vastleggen op `order_items` zodat latere wijzigingen historische facturen niet raken.

---

## 8. Contante betalingen — onderzoek & besluit (13-08-2026)

Onderzoek voor Trello-kaart #56, als voorwerk voor kaart #55 (chauffeur registreert contante betaling bij aflevering).

### 8.1 De vraag

Bij contante betaling moet er in Exact een factuur staan die **niet als openstaande post blijft hangen**. Dat vraagt twee dingen: de factuur boeken (doen we al) én de betaling registreren + afletteren tegen die factuur.

### 8.2 Wat de API's wel en niet kunnen

| Handeling | REST API | XML API |
|---|---|---|
| Verkoopfactuur boeken | ✅ `SalesInvoice` (gebouwd, Fase E) | — |
| Kasboeking aanmaken | ✅ `financialtransaction/CashEntries` + `CashEntryLines` | — |
| Betaling ↔ factuur afletteren | ❌ **geen endpoint** | ✅ topic `MatchSets` |

De picqer-client (v4.6) heeft `CashEntry` en `CashEntryLine`, maar géén match-endpoint — dat bestaat simpelweg niet in de REST API van Exact. `CashEntryLine` kent wel `OurRef` (factuurnummer); een bekend [issue op de picqer-client](https://github.com/picqer/exact-php-client/issues/268) laat zien dat dat veld de factuur **niet** automatisch aflettert: de post blijft open staan.

Afletteren kan alleen via de XML API, met een POST naar `XMLUpload.aspx?Topic=MatchSets`:

```xml
<eExact>
  <MatchSets>
    <MatchSet>
      <GLAccount code="1300" />        <!-- debiteurenrekening -->
      <Account code="16001092" />      <!-- debiteurnummer -->
      <MatchLines>
        <MatchLine finyear="2026" finperiod="8" journal="10" entry="..." amountdc="-121.00" />
        <MatchLine finyear="2026" finperiod="8" journal="50" entry="..." amountdc="121.00" />
      </MatchLines>
    </MatchSet>
  </MatchSets>
</eExact>
```

Beide boekingen moeten al bestaan; je matcht op **entry numbers** en gelijke bedragen met tegengesteld teken.

### 8.3 Opties

**A. Factuur + kasboeking, handmatig afletteren**
Wij boeken de factuur en de kasboeking; de boekhouder lettert periodiek af in Exact.
➕ volledig REST, geen legacy-afhankelijkheid, snel te bouwen.
➖ handwerk per contante levering.

**B. Factuur + kasboeking + automatisch afletteren via XML `MatchSets`** — *advies*
Als A, plus een afletterstap over de XML API.
➕ volledig geautomatiseerd, geen openstaande posten.
➖ afhankelijk van de legacy XML API; vereist entry numbers en financieel jaar/periode van beide boekingen.

**C. Alleen factuur met betalingsconditie "contant"**
➕ minste werk.
➖ lost niets op: de betalingsconditie bepaalt alleen de vervaldatum, de post blijft openstaan. **Afgevallen.**

### 8.4 Advies (achterhaald — zie 8.8 voor het genomen besluit)

Bouw **B in twee stappen**, met A als werkende tussenstand:

1. **Stap 1 (REST, veilig):** bij een contante levering de factuur boeken zoals nu, en direct erna een `CashEntry` aanmaken op het kasdagboek met `OurRef` = Exact-factuurnummer, `Account` = debiteur, `AmountFC` = factuurtotaal. Hiermee staat het geld administratief goed; alleen de match ontbreekt nog.
2. **Stap 2 (XML, aan/uit te zetten):** een `MatchSets`-upload die de kasboeking tegen de factuur aflettert, achter een config-vlag zodat we hem kunnen uitschakelen als Exact of de administratie dwarsligt.

Onze `ExactOnlineClient` kan dit aan: `Connection::getAccessToken()` en `getDivision()` zijn beschikbaar, dus de XML-call kan met hetzelfde OAuth-token binnen de bestaande `call()`-wrapper (inclusief token-refresh en retry).

⚠️ **Eerst verifiëren in de sandbox.** Bronnen spreken elkaar tegen over of `XMLUpload.aspx` server-to-server met een bearer token mag, of alleen via een handmatige upload in de webinterface. Dit moet in de testadministratie bewezen worden vóórdat stap 2 gebouwd wordt. Valt het tegen, dan blijft A de eindstand en lettert de boekhouder af.

### 8.5 Benodigde gegevens (aan te leveren door de klant/boekhouder)

| Nodig | Waarvoor | Config-sleutel |
|---|---|---|
| Code kasdagboek (journal, type Kas) | kasboeking — bij Koylu: `10` (Kasboek) | `EXACT_CASH_JOURNAL` |
| Grootboekrekening kas | alleen informatief; het dagboek is er al aan gekoppeld — bij Koylu: `1000` | `EXACT_CASH_GL_ACCOUNT` |
| Debiteurenrekening | tegenrekening van de kasboeking **en** `GLAccount` in MatchSet — bij Koylu: `1300` | `EXACT_RECEIVABLES_GL_ACCOUNT` |
| Code verkoopdagboek | `journal` in MatchSet (staat al als `EXACT_INVOICE_JOURNAL`, nu 70) | bestaand |
| Btw-vrij statiegeldartikel | statiegeldregels (kaart #54) | `EXACT_CRATE_DEPOSIT_ARTICLE_CODE` |

### 8.6 Uitzoekpunten bij de bouw van #55

- Het **EntryNumber** van de geboekte verkoopfactuur ophalen (`SalesEntry` filteren op `YourRef`/factuurnummer) — dat hebben we nodig voor de MatchSet en bewaren we bij de factuur.
- Financieel jaar en periode afleiden uit de factuurdatum.
- Wat te doen bij een gedeeltelijk contante betaling? Voorstel: alleen volledige contante betaling ondersteunen, restbedragen via de normale weg.
- Foutafhandeling: mislukt de match, dan blijft de factuur geboekt en de kasboeking staan — melden via de bestaande `ExactSyncLog` en niet opnieuw boeken (idempotent).

### 8.7 Praktijkbevindingen bij de eerste echte boekingen (13-08-2026)

De eerste facturen die daadwerkelijk in Exact geboekt zijn, legden vier dingen bloot:

1. **Eenheidscodes zijn administratiespecifiek.** De administratie kent `kg` en `stuk`, maar géén `st`. Een onbekende eenheid geeft `Error 500: Ongeldige referentie: Eenheid` — zowel bij het aanmaken van een artikel als bij een factuurregel. Geldige codes zijn op te vragen via het `Unit`-endpoint.
2. **Btw-codes kwamen nooit mee.** `config('exact.invoice.vat_codes.21.00')` splitst op de punt, dus de lookup gaf altijd `null`. Exact viel terug op de standaard-btw van het artikel. Opgelost door de array op te halen en de sleutel daaruit te lezen.
3. **`GLAccount` in een boekingsregel wil een GUID**, niet het rekeningnummer. Het GUID hoort bij een rekeningcode opgezocht te worden (en wordt gecached).
4. **De kasboekingsregel boekt tegen de debiteurenrekening**, niet tegen de kasrekening: het kasdagboek is in Exact al aan de kasrekening gekoppeld. Boeken tegen 1000 geeft `Ongeldig: Type (Kas) - Grootboekrekening: 1000`.

**Let op bij wijzigingen in `.env`:** een draaiende `queue:work`-worker houdt de config in geheugen. Na een configwijziging is `php artisan queue:restart` nodig, anders blijft de worker met de oude waarden boeken.

### 8.8 Automatisch afletteren — gebouwd en in gebruik (14-08-2026)

De eerste inschatting was om afletteren handwerk te laten. Na doorvragen ("zolang het een oplossing is die we jaren kunnen gebruiken") is het alsnog gebouwd, omdat het vooronderzoek uitwees dat de XML API server-to-server werkt met ons eigen OAuth-token.

**Hoe het werkt:** na de kasboeking bouwt `ExactMatchService` een MatchSet en uploadt die via `ExactXmlClient` naar `XMLUpload.aspx?Topic=MatchSets`. De verkoopboeking gaat als plus, de kasboeking als min, beide tegen grootboek 1300.

**Twee eigenaardigheden van de XML API** — hier is uren op gezocht, dus goed onthouden:

1. **De XML moet als rauwe body mee, met een lege `Expect`-header.** Multipart (`file`-veld) levert `Data at the root level is invalid` op — Exact leest dan de hele multipart-envelop als XML. En zonder de `Expect`-header leeg te zetten blijft de verbinding hangen op `Expect: 100-continue`.
2. **Exact antwoordt altijd met HTTP 200, ook bij fouten**, én ook een geslaagde upload levert meldingen op. De ernst staat in het `type`-attribuut van `<Message>`:

   | type | betekenis | voorbeeld |
   |---|---|---|
   | 0 | fout | `entry=99999998 ... Niet gevonden!` |
   | 1 | waarschuwing | |
   | 2 | informatie (ook succes) | `Rekening: 1300 - Relatie: 715 - 2 Regels Afgeletterd.` |
   | 3 | fatale fout | `Data at the root level is invalid.` |

   Beoordeel het resultaat dus op `isError()` (type 0 en 3), **niet** op het aantal meldingen.

**Gegevens voor een MatchSet** — alle via REST op te halen:

| Gegeven | Bron |
|---|---|
| Debiteurnummer (`Account code`) | `Account` filteren op het opgeslagen GUID → `Code` |
| Verkoopboeking | `SalesEntry` op `InvoiceNumber` → `EntryNumber`, `ReportingYear`, `ReportingPeriod`, `Journal` |
| Kasboeking | `CashEntry` op het opgeslagen `EntryID` → `EntryNumber`, `FinancialYear`, `FinancialPeriod`, `JournalCode` |
| `GLAccount code` | `EXACT_RECEIVABLES_GL_ACCOUNT` (1300) |

**Vangnet:** het afletteren zit achter `EXACT_AUTO_MATCH_CASH` en draait ná de boeking, in een eigen `try/catch`. Mislukt het, dan blijft de factuur gewoon geboekt en verstuurd; de fout komt in `exact_match_error` en de admin kan het overdoen met de actie "Afletteren in Exact". Zet de schakelaar uit als de XML API ooit stukgaat — de rest van de koppeling draait door.

**Bewezen op 14-08-2026:** F2026-0013 (26700006) en F2026-0014 (26700005) zijn via deze weg afgeletterd. Exact meldde per factuur "2 Regels Afgeletterd." en beide verdwenen uit `ReceivablesList`; alleen 26700001 t/m 26700004 staan nog open.

**Blijvend risico:** de XML API is legacy en Exact zet in op REST. Zolang Exact hem ondersteunt werkt dit; verdwijnt hij, dan valt alleen het afletteren weg (schakelaar uit, handmatig verder) en blijft het boeken werken. Het is de moeite waard bij Exact support na te vragen of afletteren op de REST-roadmap staat.
