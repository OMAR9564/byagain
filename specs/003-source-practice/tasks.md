---

description: "Task list for feature implementation"
---

# Tasks: Kaynağa Göre Pratik, Sabit Kaynakla Ekleme ve LLM ile Tekrar

**Input**: Design documents from `/specs/003-source-practice/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/, quickstart.md

**Tests**: Test görevleri **zorunludur** (Ana Yasa IV: her servis için birim
testi, her akış için feature testi). Her user story'de testler önce yazılır ve
uygulamadan önce **kırmızı** olduğu görülür.

**Organization**: Görevler user story'lere göre gruplanmıştır. Fazlar, plan.md
"Teslim dilimleri" sırasıyla dizilmiştir — **US2 → US1 → US3** — çünkü US2 en
küçük dilimdir ve gerçek bir hatayı düzeltir. Story'ler birbirine bağlı değildir.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Paralel çalışabilir (farklı dosya, bekleyen bağımlılık yok)
- **[Story]**: US1 (pratik) · US2 (formda kal) · US3 (LLM dışa aktarma)
- Her görev dosya yolu taşır

## Path Conventions

Laravel monolit, depo kökü: `app/`, `resources/`, `routes/`, `lang/en/`,
`public/build/` (derlenmiş bundle **depoda izlenir**), `tests/`.

## Her görev için geçerli kurallar

- Ana Yasa'yı oku: `.specify/memory/constitution.md`. Özellikle: arayüz metni
  Blade'e/JS'e gömülmez, `lang/en/` altına gider; `{!! !!}` yalnız `content_html`;
  `withoutGlobalScope` yasak; doğrulama Form Request'te; `final class`; tip
  belirtimi zorunlu; yorumlar "neden"i anlatır ve spec'e atıf yapar (`FR-2xx`).
- Çevredeki kodun üslubuna uy (yorum yoğunluğu, adlandırma, `#[Test]`, test
  yardımcıları). Zaman içeren her testte `Carbon::setTestNow()`.
- `tokens.css`'e dokunma. Yeni ekranlar mevcut token'ları (`var(--color-…)`) kullanır.
- Her dilim sonunda: `vendor/bin/pint`, `vendor/bin/phpstan analyse`,
  `php artisan test` yeşil. JS/CSS değiştiyse `npm run build` ve `public/build`
  ayrı bir `build: recompile the bundle` commit'i.
- Commit mesajları İngilizce, conventional commit (`feat(practice): …`,
  `fix(editor): …`, `test(practice): …`), gövde "neden"i anlatır, son satır:
  `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`. Push yok.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Başlangıç durumunun yeşil olduğunu görmek ve ortak dil dosyasını açmak

- [X] T001 Başlangıç kapısı: `php artisan test`, `vendor/bin/pint --test`, `vendor/bin/phpstan analyse` — üçü de yeşil olmalı; değilse iş durur ve rapor edilir
- [X] T002 [P] `lang/en/practice.php` — boş iskeletle oluştur (`declare(strict_types=1);`, dosya başı yorum: "Practising one source and exporting it for an LLM — outside the daily ritual (spec 003)."), `return ['practice' => [], 'export' => []];` biçiminde; anahtarlar story'lerde eklenir

---

## Phase 2: Foundational (Blocking Prerequisites)

Bu özellikte bütün story'leri bloklayan ortak altyapı yok: şema yok, bağımlılık
yok (research.md R-310). Phase 1 bittiyse story'ler başlayabilir.

---

## Phase 3: User Story 2 — Sabit kaynakla art arda ekleme (Priority: P2) · Dilim 1

**Goal**: Kaydettikten sonra kullanıcı ekleme formunda kalır, kaynak seçili kalır;
kaynak sayfasından ön seçimli ekleme; doğrulama hatasında kaynak kaybolmaz.

**Independent Test**: Ekleme formunda kaynak seçip kaydet → form boş, aynı kaynak
seçili; ikinci pasajı kaynağa dokunmadan kaydet → ikisi de aynı kaynakta.

### Tests for User Story 2 ⚠️ önce yaz, kırmızı gör

- [X] T003 [US2] `tests/Feature/Library/StickySourceTest.php` — yeni dosya, `RefreshDatabase`. Testler:
  (a) `POST /highlights` başarıda `route('highlights.create', ['source' => $source->id])`'ye yönlenir, oturumda `status` ve `saved_source_id` (= kaynak id) var;
  (b) `GET /add?source={id}` cevabında o kaynağın `<option>`'ı `selected`, diğerleri değil;
  (c) `?source=` arşivlenmiş kaynak → 200, hiçbir option `selected` değil;
  (d) `?source=` başka hesabın kaynağı → 200, hiçbir option `selected` değil ve o kaynağın başlığı sayfada geçmiyor (FR-227);
  (e) `?source=999999` ve `?source=abc` → 200, seçim yok;
  (f) doğrulama hatası: `from(route('highlights.create'))` ile `source_id` **string** olarak ve boş `content_md` gönder → hatayla geri döner; ardından `GET /add` cevabında o kaynak `selected` (bugün kırmızı — research.md R-306 bulgusu);
  (g) `PATCH /highlights/{id}` hâlâ `sources.show`'a döner (FR-217);
  (h) arşivlenmemiş kaynağın sayfasında `route('highlights.create', ['source' => id])` bağlantısı var; arşivlenmişte yok;
  (i) kayıttan sonra `GET /add` (oturumda `saved_source_id` varken) cevabında `route('sources.show', id)` bağlantısı var (FR-213)
- [X] T004 [US2] `tests/Feature/Content/BrowserPostsStringsTest.php:42` — beklenen yönlendirmeyi `route('highlights.create', ['source' => $source->id])` yap; testin yorumunu koru

### Implementation for User Story 2

- [X] T005 [US2] `app/Http/Controllers/HighlightController.php` — `create(Request $request)`: `?source` değerini `$request->integer('source')` ile oku; `> 0` ise `Source::query()->where('is_archived', false)->whereKey($id)->value('id')` ile (scope'lu) doğrula; geçersizse `null`. Görünüme `selectedSourceId` geçir. `store()`: `redirect()->route('highlights.create', ['source' => $highlight->source_id])->with('status', __('settings.saved'))->with('saved_source_id', $highlight->source_id)`. Kısa bir "neden" yorumu (FR-212, FR-215). `update()` değişmez
- [X] T006 [US2] `resources/views/components/editor/form.blade.php` — `@props`'a `'selectedSourceId' => null` ekle; satır 41'deki seçimi `@selected((int) old('source_id', $highlight?->source_id ?? $selectedSourceId) === $source->id)` yap ve üstüne neden-yorumu yaz: `old()` oturumdan string döner, katı karşılaştırma doğrulama hatasından sonra seçimi kaybediyordu (FR-216)
- [X] T007 [US2] `resources/views/editor/create.blade.php` — `<x-editor.form>`'a `:selected-source-id="$selectedSourceId"` geçir. `session('saved_source_id')` varsa ve kaynak `$sources` içinde bulunuyorsa formun üstünde o kaynağın sayfasına giden bir bağlantı göster (metin `__('editor.saved_to', ['source' => …])`, bağlantı `__('editor.view_source')`); stil mevcut bağlantılarla aynı token'lar
- [X] T008 [US2] `lang/en/editor.php` — `saved_to` ("Saved to :source.") ve `view_source` ("Open source") anahtarlarını ekle
- [X] T009 [US2] `resources/views/library/sources/show.blade.php` — `! $source->is_archived` iken başlığın altına `route('highlights.create', ['source' => $source])`'a giden ikincil bir `x-button` ("Add passage", `__('library.source.add_passage')`); `lang/en/library.php` → `source.add_passage` anahtarı. Dokunma hedefi ≥ 44px
- [X] T010 [US2] Dilim kapısı: T003 + bütün suite yeşil, Pint, Larastan. İki commit: `fix(editor): keep the chosen source after a validation error` (T006'daki int karşılaştırma ve T003(f)) ve `feat(editor): stay on the form with the source kept after saving` (geri kalanı). Blade/CSS değiştiyse `npm run build`, `public/build` değiştiyse ayrı build commit'i

**Checkpoint**: US2 tek başına kullanılabilir ve teslim edilebilir.

---

## Phase 4: User Story 1 — Kaynağa göre pratik (Priority: P1) 🎯 MVP · Dilim 2

**Goal**: Kaynak sayfasından tek dokunuşla, o kaynağın aktif pasajlarından
`review_size` kadarlık, hiçbir iz bırakmayan bir pratik.

**Independent Test**: Pratik başlat, bütün kartları geç; seri, tur, `shown_count`,
`last_shown_at`, kart planı değişmemiş olmalı (quickstart §1).

### Tests for User Story 1 ⚠️ önce yaz, kırmızı gör

- [X] T011 [P] [US1] `tests/Unit/Practice/PracticeSamplerTest.php` — `PracticeSampler::draw(User, Source)`: (a) `review_size` kadar döner; (b) aktif pasaj azsa hepsini; (c) çöpe atılmış pasaj asla gelmez; (d) başka kaynağın pasajı asla gelmez; (e) `last_shown_at = now()` olan pasaj da gelebilir (soğuma yok, FR-203); (f) `frequency = never` ve `is_archived = true` kaynakta da çalışır; (g) dönen her pasajın `source` ilişkisi yüklüdür (`relationLoaded`)
- [X] T012 [P] [US1] `tests/Unit/Practice/PracticeActionsTest.php` — `PracticeActions::apply(Source, Highlight, array $input)`: `discard` → `is_discarded = true`; `favorite: true` → `is_favorite = true`; `favorite: false` mevcut favoriyi **silmez**; `source_frequency` kaynağı günceller; `keep` hiçbir alanı değiştirmez; hiçbir durumda `shown_count` / `last_shown_at` değişmez; aynı girdiyle iki çağrı aynı sonucu verir
- [X] T013 [P] [US1] `tests/Feature/Practice/PracticeSessionTest.php` — `GET route('practice.show', $source)`: (a) 200, `data-review-card` sayısı `min(review_size, aktif)`; (b) yalnız o kaynağın pasajları; (c) `__('practice.practice.banner')` metni sayfada; (d) kökte `data-complete-url` **yok**, her kartın `data-action-url`'i `route('practice.action', [$source, $highlight])`; (e) aktif pasajı olmayan kaynak → `sources.show`'a yönlenir, `status` = `__('practice.practice.empty')`; (f) arşivlenmiş ve `never` kaynakta 200; (g) istekten sonra `reviews` ve `review_items` tablo sayıları değişmez; (h) sayfada tam bir `<h1>` var; (i) Filament/Livewire/Alpine varlığı yok
- [X] T014 [P] [US1] `tests/Feature/Practice/PracticeActionTest.php` — `POST route('practice.action', [$source, $highlight])` JSON: (a) `discard`, `favorite`, `source_frequency` kalıcı; (b) `keep` → 200, değişiklik yok; (c) geçersiz `action` → 422; (d) aynı kullanıcının **başka kaynağındaki** pasaj → 404; (e) **iz bırakmama** (SC-201): kullanıcıya `StreakDay`'ler, bugünün yarım kalmış bir `Review`'u + `ReviewItem`'ları, bir `MasteryCard`, `EmailDelivery` ve `PushDelivery` satırları kur; kaynağın bütün pasajlarına çeşitli eylemler gönder; önce/sonra karşılaştır: `streak_days` sayısı, `users` seri sütunları, `reviews`/`review_items` sayı ve `status`/`action` değerleri, her pasajın `shown_count` ve `last_shown_at`'i, kartın `half_life_days`/`last_reviewed_at`/`due_at`/`review_count`/`struggle_count`'u, `email_deliveries`/`push_deliveries` sayısı — data-model.md "Yazılmayan alanlar" listesinin tamamı
- [X] T015 [P] [US1] `tests/Feature/Security/IdorTest.php` — yeni test `the_practice_action_refuses_another_readers_highlight`: saldırgan kendi kaynağının id'siyle ve sahibin pasaj id'siyle `POST` → 404; sahibin kaynağı + sahibin pasajı → 404. Mevcut `twoAccounts()` yardımcısını kullan

### Implementation for User Story 1

- [X] T016 [P] [US1] `app/Services/Practice/PracticeSampler.php` — `final class`, `draw(User $user, Source $source): Collection` (research.md R-305): `$source->highlights()->where('is_discarded', false)->inRandomOrder()->limit($user->review_size)->with('source')->get()`. Sınıf yorumu: neden günlük `HighlightSampler` kullanılmıyor (FR-203)
- [X] T017 [P] [US1] `app/Services/Practice/PracticeActions.php` — `final class`, `apply(Source $source, Highlight $highlight, array $input): void` (research.md R-303, data-model.md "Yazılan alanlar"). `discard` için `HighlightWriter::discard()` kullan (zaten çöpe atılmışsa çağırma); favori yalnız `true` ile; sıklık `$source`'a. `shown_count`/`last_shown_at`'e dokunmadığını sınıf yorumunda açıkça söyle (FR-206)
- [X] T018 [P] [US1] `app/Http/Requests/PracticeActionRequest.php` — `ReviewItemActionRequest`'i örnek al: `authorize()` rotadaki `source` ve `highlight`'ın kullanıcıya ait olduğunu ve `highlight->source_id === source->id` olduğunu sınar; `rules()`: `action` (`keep|discard`, zorunlu), `favorite` (nullable boolean), `source_frequency` (nullable, `StoreSourceRequest::frequencies()`), `client_acted_at` (nullable date), `mastery_feedback` (nullable — kabul edilir, yok sayılır; contracts/routes.md)
- [X] T019 [US1] `resources/views/review/partials/highlight-card.blade.php` ve `resources/views/review/partials/undo-bar.blade.php` — `resources/views/review/show.blade.php`'den **davranış değiştirmeden** çıkar: pasaj kartının `data-swipe-surface` + `data-review-actions` bloğu (satır ~69-177; girdi: `$highlight`) ve geri alma çubuğu (satır ~224-241). `review/show.blade.php` bunları `@include` eder. `tests/Feature/Review/*`, `AccessibilityTest`, `AssetBudgetTest` yeşil kalmalı. Ayrı commit: `refactor(review): lift the passage card and undo bar into partials`
- [X] T020 [US1] `resources/js/review.js` — `completeReview()` ve `postCompletion()` yalnız `root.dataset.completeUrl` doluysa sunucuya gider; değilse tamamlama ekranı yine gösterilir ama istek atılmaz. Neden-yorumu: pratik ekranının tamamlama adresi yok, korumasız hâli `fetch("undefined")` yapardı (research.md R-302). Başka davranış değişmez
- [X] T021 [US1] `app/Http/Controllers/PracticeController.php` — `final class`, ince: `show(Request, Source)`: sampler'dan set; boşsa `redirect()->route('sources.show', $source)->with('status', __('practice.practice.empty'))`; değilse `view('practice.show', …)`. `action(PracticeActionRequest, Source, Highlight)`: `PracticeActions::apply`, `response()->json(['ok' => true])`
- [X] T022 [US1] `routes/web.php` — `auth`+`ensure.active` grubuna: `GET /library/sources/{source}/practice` → `practice.show` (`throttle:30,1`); `POST /library/sources/{source}/practice/{highlight}` → `practice.action` (`throttle:120,1`, `->scopeBindings()`). Mevcut rotalar gibi kısa neden-yorumu (FR-211)
- [X] T023 [US1] `lang/en/practice.php` → `practice` anahtarları: `title` ("Practice"), `start` ("Practise this source"), `banner` ("Practice — this does not count toward your day."), `empty` ("Nothing to practise here yet: this source has no active passages."), `complete.title`, `complete.body` ("Your day and streak are exactly as you left them."), `another_set`, `back_to_source`
- [X] T024 [US1] `resources/views/practice/show.blade.php` — `x-layouts.app`; başlık `$source->title`; afiş `practice.practice.banner` (`role="status"` değil, düz metin; token renkleri); kök `<div id="review" data-review data-start-index="0" data-csrf=… data-undo-seconds=…>` — **`data-complete-url` yok**; `data-review-copy` JSON'u `review/show` ile aynı; geri düğmesi ve `x-progress-bar` aynı; her pasaj için `<article class="review-card" data-review-card data-item-type="highlight" data-item-id="{{ $highlight->id }}" data-action-url="{{ route('practice.action', [$source, $highlight]) }}" data-acted="false" data-verdict="">` + `@include('review.partials.highlight-card')` + verdict bloğu; tamamlama bölümü `data-review-complete hidden`: `practice.practice.complete.*` metinleri, `route('practice.show', $source)`'a "Another set" ve `route('sources.show', $source)`'a "Back to source" (ikisi de ≥ 44px); `@include('review.partials.undo-bar')`; `@vite('resources/js/review.js')`
- [X] T025 [US1] `resources/views/library/sources/show.blade.php` (`SourceController::show` zaten çöpe atılmamış pasajları sayfalıyor; denetleyici değişmedi) — aktif pasaj sayısı `$highlights->total()` > 0 ise birincil "Practise this source" düğmesi (`route('practice.show', $source)`); değilse düğme yok. Arşivlenmiş kaynakta da görünür
- [X] T026 [US1] `tests/Feature/Admin/AssetIsolationTest.php:35` ve `tests/Feature/AccessibilityTest.php` sayfa listelerine `"/library/sources/{$source->id}/practice"` ekle (kaynağın en az bir pasajı olacak biçimde kurulum gerekiyorsa ekle); testler: `tests/Feature/Practice/PracticeButtonTest.php` düğmenin varlığını/yokluğunu sınar
- [X] T027 [US1] Dilim kapısı: T011–T015 + bütün suite yeşil, Pint, Larastan, `npm run build`. Commit'ler: T019 (refactor), `fix(review): only post completion where the page has somewhere to post it` (T020), `feat(practice): practise one source without touching the day` (geri kalanı), build commit'i

**Checkpoint**: US1 (MVP) tek başına çalışır; US2 ile birlikte de bağımsız.

---

## Phase 5: User Story 3 — LLM ile çalışmak için dışa aktarma (Priority: P3) · Dilim 3

**Goal**: Kaynak sayfasından dışa aktarma ekranı: sayılar, gizlilik notu,
textarea, Copy (üç kademeli yedek), Download (Markdown).

**Independent Test**: Kartı olan kaynakta indir; talimat + bütün aktif pasajlar +
aktif kartlar var, çöpe atılmış/emekli yok (quickstart §3).

### Tests for User Story 3 ⚠️ önce yaz, kırmızı gör

- [X] T028 [P] [US3] `tests/Unit/Practice/StudyExportBuilderTest.php` — contracts/study-export.md'nin her kuralı: talimat (`__('practice.export.instruction')`) en üstte; `# başlık`; yazar `null` ise yazar satırı yok; pasajlar `id` artan, numaralar 1'den kesintisiz (aradaki çöpe atılmış pasaj boşluk bırakmaz); `content_md` birebir (içinde ```` ``` ```` kod bloğu ve `<script>` olan pasajla dene — kaçışsız, aynen); `location` varsa `_…_` satırı; aktif kart yoksa `## Questions` yok; `paused`/`retired` kart yok; çöpe atılmış pasajın kartı yok; tek `\n` ile biter; `counts()` doğru sayıları döner
- [X] T029 [P] [US3] `tests/Feature/Practice/StudyExportTest.php` — (a) `GET route('sources.export', $source)` 200: pasaj/kart sayıları, gizlilik notu, `<textarea readonly>` içinde metin **HTML-kaçışlı** (`<script>` ham basılmaz), `data-copy-source` / `data-copy-button` öznitelikleri; (b) `GET route('sources.export.download', $source)`: `Content-Type` `text/markdown; charset=UTF-8`, `Content-Disposition` `attachment`, dosya adı `<slug>-<yerel YYYY-MM-DD>.md` (`Carbon::setTestNow` + kullanıcı `timezone` ile gün sınırını sına), gövde builder çıktısıyla aynı; (c) başlığı `!!!` olan kaynakta dosya adı `source-<id>-<tarih>.md`; (d) aktif pasajı olmayan kaynak → `sources.show`'a yönlenir; (e) ekran ve indirme sonrası `shown_count`/`last_shown_at`, `reviews` sayısı değişmez (FR-226)

### Implementation for User Story 3

- [X] T030 [P] [US3] `app/Services/Practice/StudyExportBuilder.php` — `final class`, `build(Source $source): string` ve `counts(Source $source): array{passages: int, cards: int}`; contracts/study-export.md'ye birebir uy. İki sorgu: aktif pasajlar (`id` artan) ve onların aktif kartları (`with()`/`whereIn`), N+1 yok. Talimat `__('practice.export.instruction')`
- [X] T031 [P] [US3] `lang/en/practice.php` → `export` anahtarları: `title` ("Study with an AI"), `start` (kaynak sayfası düğmesi), `summary` (`:passages passage(s) and :cards question(s)` — `trans_choice` ile), `privacy` ("This text goes to whichever AI service you paste it into. byagain sends it nowhere."), `copy`, `copied`, `copy_fallback` ("Selected — copy it with your device's menu."), `download`, `empty`, ve `instruction` — contracts/study-export.md "Instruction" bölümündeki yedi maddenin hepsini İngilizce, kısa, emir kipinde karşılayan çok satırlı metin
- [X] T032 [US3] `app/Http/Controllers/StudyExportController.php` — `final class`, ince: `show(Source)` (boş kaynak → `sources.show` + `status`), `download(Request, Source)` → `response()->streamDownload(fn () => print($text), $filename, ['Content-Type' => 'text/markdown; charset=UTF-8'])`; dosya adı `Str::slug($source->title)` + `LocalDayResolver` ile kullanıcının yerel günü; slug boşsa `source-{id}` (research.md R-308)
- [X] T033 [US3] `routes/web.php` — `GET /library/sources/{source}/export` → `sources.export`, `GET /library/sources/{source}/export/download` → `sources.export.download`, ikisi de `throttle:30,1`; `practice.show`'un yanında, kısa neden-yorumuyla
- [X] T034 [US3] `resources/js/copy.js` — yeni vanilla modül: `[data-copy-button]`'a tıklanınca `[data-copy-source]` (textarea) değerini `navigator.clipboard.writeText` ile yaz; API yoksa veya reddederse textarea'yı `select()` et ve `document.execCommand('copy')` dene; o da olmazsa seçili bırak. Sonucu `[data-copy-status]` (`role="status"`) içine yaz; metinler düğmenin `data-copied` / `data-fallback` özniteliklerinden okunur (JS'te gömülü metin yok). Neden-yorumu: `http://` LAN üzerinde pano API'si yok (R-308)
- [X] T035 [US3] `vite.config.js` — `input` dizisine `'resources/js/copy.js'` ekle; yorum: yalnız dışa aktarma ekranı yükler
- [X] T036 [US3] `resources/views/library/sources/export.blade.php` — `x-layouts.app`, başlık `practice.export.title`; özet satırı (sayılar); gizlilik notu; `<textarea readonly data-copy-source rows=…>{{ $text }}</textarea>` (kaçışlı, `{!! !!}` **yok**), `font-mono`, yatay taşma yok; "Copy" düğmesi (`data-copy-button`, `data-copied`, `data-fallback`) ve "Download" bağlantısı (`route('sources.export.download', $source)`), ikisi de ≥ 44px; `[data-copy-status]`; `@vite('resources/js/copy.js')`
- [X] T037 [US3] `resources/views/library/sources/show.blade.php` — aktif pasaj > 0 ise ikincil "Study with an AI" düğmesi (`route('sources.export', $source)`), pratik düğmesinin yanında; arşivlenmiş kaynakta da görünür
- [X] T038 [US3] `tests/Feature/AssetBudgetTest.php` — mevcut "push.js yalnız ayarlarda" testinin desenine uyarak `copy.js`'in `app.js` ve `review.js` girdilerine sızmadığını doğrulayan bir test ekle; `tests/Feature/Admin/AssetIsolationTest.php` sayfa listesine dışa aktarma ekranını ekle
- [X] T039 [US3] Dilim kapısı: T028, T029 + bütün suite yeşil, Pint, Larastan, `npm run build`. Commit: `feat(practice): export a source as a study prompt for an LLM` + build commit'i

**Checkpoint**: Üç story de bağımsız çalışır.

---

## Phase 6: Polish & Cross-Cutting Concerns

- [X] T040 [P] `docs/SPEC.md` — §2 rota tablosuna dört yeni rotayı ve `highlights.create`'in `?source=` parametresini ekle; yeni §10 "Practice and export": pratik neden saklanmaz (R-301), neyi yazar/yazmaz (data-model.md), dışa aktarma biçimi (contracts/study-export.md'ye bağlantı). Mevcut İngilizce üsluba uy
- [X] T041 [P] `README.md` — yalnız gerekiyorsa (yeni ortam değişkeni yok; büyük olasılıkla değişiklik yok — kontrol et, gereksizse dokunma)
- [X] T042 Son kapı: `php artisan test` (tam), `vendor/bin/pint --test`, `vendor/bin/phpstan analyse`, `composer audit`, `npm run build` (bütçe < 150KB, SC-207)
- [X] T043 Lighthouse mobil: `practice.show` ve `sources.export` ekranlarında performans ≥ 90, erişilebilirlik ≥ 95 (SC-208); bulguları düzelt — token değişikliği gerekiyorsa **dur ve sor** (Ana Yasa V)
- [ ] T044 `specs/003-source-practice/quickstart.md` — §1–§5'i 375px gerçek cihazda (iOS Safari, Chrome/Android) yürüt, sonucu belgeye işle (SC-202, SC-205, FR-229). Cihaz yoksa görev açık kalır ve nedeni yazılır
- [ ] T045 PR: `003-source-practice` → `main`; "ne değişti / neden / nasıl test edildi" doldurulur; bulunan iki hata (editör kaynak kaybı, `review.js` `fetch("undefined")`) ayrıca belirtilir

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (Setup)**: bağımlılık yok — hemen başlar
- **Phase 2 (Foundational)**: boş
- **Phase 3–5 (US2, US1, US3)**: Phase 1'den sonra; birbirine bağlı değil. Sıra plan.md "Teslim dilimleri"dir
- **Phase 6 (Polish)**: istenen story'ler bittikten sonra

### User Story Dependencies

- **US2**: bağımsız
- **US1**: bağımsız. T025 ve T037 aynı dosyaya (`sources/show.blade.php`) dokunur; US1 ve US3 aynı anda yürütülürse bu iki görev sırayla yapılır
- **US3**: bağımsız (kaynak sayfası notu yukarıda)
- T009 (US2), T025 (US1), T037 (US3) aynı Blade dosyası — hangi sırada gelirse önceki düğmeleri koru

### Within Each User Story

- Testler önce, kırmızı görülür
- Servisler → Form Request → controller → rota → görünüm
- T019 (partial çıkarma) T024'ten önce ve ayrı commit
- Dilim kapısı son görev

### Parallel Opportunities

- T002, T001 ile paralel
- US1: T011–T015 (beş test dosyası) paralel; T016–T018 (iki servis + request) paralel
- US3: T028–T029 paralel; T030–T031 paralel
- Story'ler arası: US2 ve US3 tamamen ayrı dosyalarda (kaynak sayfası hariç) — paralel yürütülebilir

---

## Parallel Example: User Story 1

```bash
# Testler birlikte:
Task: "T011 tests/Unit/Practice/PracticeSamplerTest.php"
Task: "T012 tests/Unit/Practice/PracticeActionsTest.php"
Task: "T013 tests/Feature/Practice/PracticeSessionTest.php"
Task: "T014 tests/Feature/Practice/PracticeActionTest.php"
Task: "T015 tests/Feature/Security/IdorTest.php"

# Sonra servisler birlikte:
Task: "T016 app/Services/Practice/PracticeSampler.php"
Task: "T017 app/Services/Practice/PracticeActions.php"
Task: "T018 app/Http/Requests/PracticeActionRequest.php"
```

---

## Implementation Strategy

### Dilim dilim teslim

1. Phase 1 → US2 (Dilim 1) → doğrula → commit'ler
2. US1 (Dilim 2, MVP değeri) → doğrula (quickstart §1) → commit'ler
3. US3 (Dilim 3) → doğrula (quickstart §3) → commit'ler
4. Phase 6 → tek PR

Her dilim kendi başına yeşil ve geri alınabilir; bir dilim takılırsa sonrakiler
bekletilmeden başka dilime geçilebilir.

### Model iş bölümü (depo sahibinin kuralı, 2026-10-02)

Her dilim önce **Haiku**'ya verilir; Opus sonucu (diff, testler, Ana Yasa
kuralları) denetler; sorun varsa **Sonnet** düzeltir; sorun sürerse Opus devralır.

---

## Notes

- [P] = farklı dosya, bekleyen bağımlılık yok
- Her görev dosya yolu ve kabul ölçütü taşır; görev metni tek başına uygulanabilir olmalı
- `.env`'e dokunulmaz; şema ve bağımlılık değişikliği bu özellikte **yok** — gerekli görünürse dur ve sor
- Toplam: 45 görev — Setup 2, US2 8, US1 17, US3 12, Polish 6
