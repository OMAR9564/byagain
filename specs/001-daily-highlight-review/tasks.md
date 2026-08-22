---

description: "Task list for byagain — Günlük Pasaj Tekrarı (MVP)"
---

# Tasks: byagain — Günlük Pasaj Tekrarı (MVP)

**Input**: Design documents from `/specs/001-daily-highlight-review/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/, quickstart.md

**Tests**: Test görevleri **zorunludur** — Ana Yasa m. IV: "Her servis için birim testi,
her akış için feature testi zorunludur." Şunlar test edilmeden PR açılmaz: tekrar
oluşturma, 3 gün bloğu, yarı-ömür güncellemesi, streak gün sınırı, hatırlatma
tekilleştirme, IDOR.

**Organization**: Görevler kullanıcı hikâyelerine göre gruplanmıştır; her hikâye bağımsız
uygulanabilir ve test edilebilir.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Paralel çalışabilir (farklı dosya, bağımlılık yok)
- **[Story]**: US1…US6
- Her görevde tam dosya yolu vardır

## Path Conventions

Tek Laravel monoliti (plan.md "Structure Decision"): `app/`, `resources/`, `routes/`,
`database/`, `config/`, `lang/en/`, `tests/Feature/`, `tests/Unit/` — hepsi depo kökünde.

---

## Phase 0: Kapı — İnsan Onayı (BLOKE EDİCİ)

**Purpose**: Ana Yasa m. V "Dur ve Sor" kalemleri. Bunlar kapanmadan kod yazılmaz.

- [X] T001 Kaynak sıklık ağırlıklarının sayısal değerlerini insana onaylat (öneri: never=filtre, rare=0.25, low=0.5, normal=1.0, often=2.0, very_often=4.0) ve onaylanan değerleri `specs/001-daily-highlight-review/research.md` R-02 bölümüne "ONAYLANDI" olarak işle
- [X] T002 Yenilik çarpanı penceresini insana onaylat (öneri: 14 gün) ve `specs/001-daily-highlight-review/research.md` R-03 bölümüne işle
- [X] T003 E-posta sağlayıcısını insana sorup karara bağla (lisans + SPF/DKIM/DMARC planıyla), sonucu `specs/001-daily-highlight-review/research.md` R-11 bölümüne yaz
- [X] T004 Dil kararı çelişkisini kapat: ayrı bir PR ile `.specify/memory/constitution.md` içindeki `lang/tr/` ifadelerini `lang/en/` yap, `Blade'de Kaydet` örneğini İngilizceye çevir, sürümü 1.0.1'e yükselt (spec.md "Uygulama Öncesi Bekleyen Belge Güncellemeleri")

**Checkpoint**: Dört kalem kapandı — uygulama başlayabilir.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Boş depoya Laravel iskeletini kurmak ve kalite kapılarını çalışır hâle getirmek.

- [X] T005 Depo kökünde Laravel 13.x iskeletini oluştur (PHP 8.3+), `composer.json` ve `package.json` işlenmiş hâlde
- [X] T006 `.env.example` içine MySQL 8, `QUEUE_CONNECTION=database`, `SESSION_DRIVER=database`, `MAIL_MAILER` anahtarlarını ve açıklamalarını ekle (gerçek `.env` dosyasına dokunma — Ana Yasa m. III)
- [X] T007 [P] `pint.json` dosyasını Laravel preset ile ekle ve `composer lint` betiğini `composer.json` içine yaz
- [X] T008 [P] Larastan'ı level 6 ile kur: `phpstan.neon` + `composer analyse` betiği (baseline dosyası oluşturma)
- [X] T009 [P] `vite.config.js` içinde iki ayrı giriş noktası tanımla: kullanıcı (`resources/css/app.css`, `resources/js/app.js`) ve admin (`resources/css/admin.css`) — SC-018
- [X] T010 [P] `resources/css/tokens.css` dosyasını renk, tipografi ve boşluk token'larıyla oluştur (≥17px gövde, 44px dokunma hedefi ölçeği) ve `resources/css/app.css` içinden içe aktar
- [X] T011 [P] `config/byagain.php` dosyasını oluştur: `sampling.source_weights`, `sampling.cooldown_tau=21`, `sampling.novelty_multiplier=1.5`, `sampling.novelty_window_days`, `sampling.block_days=3`, `sampling.quality_min_chars=25`, `mastery.initial_half_lives=[7,14,28]`, `mastery.multipliers=[0.5,2.0,3.0]`, `mastery.min_half_life=1`, `mastery.max_half_life=365`, `mastery.struggle_threshold=6`, `review.default_size=8`, `review.source_quota_divisor=3`, `day.boundary_hour=4` (değerler T001/T002 onayından gelir)
- [X] T012 [P] `lang/en/` altında `actions.php`, `review.php`, `library.php`, `editor.php`, `mastery.php`, `streak.php`, `settings.php`, `mail.php`, `errors.php` dosyalarını iskelet olarak oluştur (FR-088)
- [X] T013 `app/Providers/AppServiceProvider.php` içinde yerel ortamda `Model::preventLazyLoading()` ve `Model::shouldBeStrict()` etkinleştir (Ana Yasa m. IV)

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Tüm hikâyelerin dayandığı şema, sahiplik savunması, zaman ve içerik boru hattı.

**⚠️ CRITICAL**: Bu faz bitmeden hiçbir kullanıcı hikâyesi başlayamaz.

### Şema

- [X] T014 `database/migrations/` içinde `users` tablosuna byagain kolonlarını ekleyen migration yaz: `role`, `status`, `timezone`, `review_size`, `mastery_ratio`, `quality_filter_enabled`, `equal_source_weighting`, `daily_email_*`, `reminder_email_*`, `consecutive_unopened_emails`, `current_streak`, `longest_streak`, `last_streak_day` — `down()` dahil (data-model.md)
- [X] T015 [P] `database/migrations/` içinde `sources` tablosu migration'ı: kolonlar + `(user_id, is_archived)` ve `(user_id, frequency)` indeksleri
- [X] T016 [P] `database/migrations/` içinde `highlights` tablosu migration'ı: `content_md/html/text`, `note`, `location`, `is_favorite`, `is_discarded`, `contains_code`, `char_count`, `shown_count`, `last_shown_at` + `(user_id, is_discarded, last_shown_at)` indeksi
- [X] T017 [P] `database/migrations/` içinde `mastery_cards` tablosu migration'ı + `(user_id, status, due_at)` indeksi
- [X] T018 [P] `database/migrations/` içinde `reviews` ve `review_items` tabloları migration'ı: `UNIQUE (user_id, review_date)` ve `UNIQUE (review_id, position)` (FR-025, SC-015)
- [X] T019 [P] `database/migrations/` içinde `streak_days` (`UNIQUE (user_id, day)`), `email_deliveries` (`UNIQUE (user_id, dedupe_key)`), `admin_action_logs` ve `settings` tabloları migration'ları

### Modeller ve sahiplik

- [X] T020 `app/Models/Concerns/BelongsToUser.php` trait'ini yaz: global scope + `creating` olayında `user_id` atama (Ana Yasa m. III)
- [X] T021 [P] `app/Models/Source.php` ve `app/Models/Highlight.php` modellerini `final class`, `$fillable`, tip belirtimli cast ve `BelongsToUser` ile yaz
- [X] T022 [P] `app/Models/Review.php`, `app/Models/ReviewItem.php`, `app/Models/MasteryCard.php` modellerini aynı kurallarla yaz
- [X] T023 [P] `app/Models/StreakDay.php`, `app/Models/EmailDelivery.php`, `app/Models/AdminActionLog.php` (değiştirilemez: `updating`/`deleting` olaylarında istisna) modellerini yaz
- [X] T024 `app/Models/User.php` içine byagain alanlarını, cast'leri ve `isAdmin()`/`isActive()` yardımcılarını ekle
- [X] T025 [P] `database/factories/` altında Source, Highlight, MasteryCard, Review, ReviewItem factory'lerini yaz
- [ ] T026 [P] `tests/Feature/Security/IdorTest.php` — başka kullanıcının kaynak/pasaj/kart/tekrar kimliğiyle her rotanın 404 döndüğünü doğrula (FR-010, SC-011) *(rotalar eklendikçe genişletilir)*

### Zaman

- [X] T027 `app/Services/Time/LocalDayResolver.php` yaz: `localDayFor()`, `windowForLocalDay()`, `isWithinSendWindow()` — 04:00 sınırı `config('byagain.day.boundary_hour')` (research.md R-01)
- [X] T028 [P] `tests/Unit/Time/LocalDayResolverTest.php` — 01:30 önceki güne yazılır, farklı zaman dilimleri, DST geçişi; `Carbon::setTestNow()` ile (FR-055)

### İçerik boru hattı

- [X] T029 `app/Services/Content/MarkdownRenderer.php` yaz: CommonMark (`html_input: escape`, GFM tablo/strikethrough) → HTMLPurifier beyaz listesi → `content_html`, ayrıca `content_text`; dış bağlantılara `rel="noopener noreferrer nofollow" target="_blank"` (FR-014..FR-017, research.md R-09)
- [X] T030 [P] `app/Services/Content/PastedTextCleaner.php` yaz: fazla satır sonu ve satır sonu tiresiyle bölünmüş kelime birleştirme (FR-022)
- [X] T031 [P] `tests/Unit/Content/MarkdownRendererTest.php` — `<script>alert(1)</script>` metin olarak kalır, tablo/kod bloğu korunur, `javascript:` bağlantısı düşer (SC-012)
- [X] T032 [P] `tests/Unit/Content/PastedTextCleanerTest.php` — PDF'ten yapıştırılmış tireli/kırık metin senaryoları (FR-022)

### Kimlik ve erişim

- [X] T033 Laravel Fortify'ı başsız kur: `config/fortify.php`, `app/Providers/FortifyServiceProvider.php`; parola kuralı `Password::min(10)->uncompromised()` (FR-005)
- [X] T034 `resources/views/auth/` altında Blade view'ları yaz: login, register, forgot-password, reset-password, verify-email (mobil öncelikli, `lang/en/` metinleriyle)
- [X] T035 `app/Providers/FortifyServiceProvider.php` içinde giriş ve parola sıfırlama için `RateLimiter` tanımla (FR-006) ve parola sıfırlama yanıtını adresten bağımsız tek tip yap (FR-004)
- [X] T036 [P] `app/Http/Middleware/EnsureUserIsActive.php` ve `app/Http/Middleware/EnsureUserIsAdmin.php` yaz, `bootstrap/app.php` içinde takma adlarını kaydet
- [X] T037 `app/Http/Controllers/AccountController.php` + `app/Http/Requests/DeleteAccountRequest.php`: parola onaylı hesap silme, içerik silme ve e-posta anonimleştirme (FR-008, research.md R-13)
- [X] T038 [P] `tests/Feature/Auth/AuthFlowTest.php` — kayıt, doğrulama süresi (24 sa), sıfırlama süresi (60 dk), tek tip yanıt, hız sınırı, hesap silme + anonimleştirme (FR-001..FR-008)

### Kabuk

- [X] T039 `resources/views/layouts/app.blade.php` yaz: 375px öncelikli kabuk, alt gezinme (baş parmak erişimi, ≥44×44px hedefler), karanlık mod (sistem + elle), `prefers-reduced-motion` (FR-077..FR-085)
- [X] T040 [P] `resources/views/components/` altında ortak bileşenler: `card`, `button`, `empty-state`, `bottom-nav`, `progress-bar`, `highlight-content` (`{{-- purified: MarkdownRenderer --}}` yorumlu tek `{!! !!}` kullanımı burada)
- [X] T041 `routes/web.php` iskeletini contracts/routes.md'deki adlar ve middleware grupları ile kur (henüz controller'sız rotalar açılmaz)

**Checkpoint**: Şema, sahiplik, zaman, içerik ve kimlik hazır — hikâyeler başlayabilir.

---

## Phase 3: User Story 1 — İçerik gir ve ilk tekrarını yap (Priority: P1) 🎯 MVP

**Goal**: Kullanıcı kaynak ve pasaj girer, ilk tekrarını kart kart tamamlar, seri 1 olur.

**Independent Test**: Temiz hesapla kaydol → 1 kaynak + 3 pasaj → `/review` → 3 kartı işle
→ tamamlanma ekranı ve seri = 1. E-posta, mastery ve admin olmadan uçtan uca çalışır.

### Tests for User Story 1

- [X] T042 [P] [US1] `tests/Feature/Review/FirstReviewFlowTest.php` — kayıt → kaynak → 3 pasaj → tekrar üretimi → 3 kart işleme → tamamlanma + seri 1 (US1 kabul senaryoları 1, 2, 6)
- [X] T043 [P] [US1] `tests/Feature/Library/HighlightCrudTest.php` — pasaj oluşturma, düzenleme, `discard` (silme yok), favori (FR-013, FR-011, FR-019)
- [X] T044 [P] [US1] `tests/Feature/Review/ReviewItemActionTest.php` — `keep` sayaç ve `last_shown_at` günceller, `discard` gizler, ikinci çağrı idempotenttir (FR-038, FR-039, contracts/review-actions.md)
- [X] T045 [P] [US1] `tests/Feature/Review/ResumePartialReviewTest.php` — yarım bırakılan tekrar işlenmemiş karttan devam eder (FR-040)

### Implementation for User Story 1

- [X] T046 [P] [US1] `app/Http/Requests/StoreSourceRequest.php` ve `UpdateSourceRequest.php` yaz (`authorize()` gerçek kontrol)
- [X] T047 [P] [US1] `app/Http/Requests/StoreHighlightRequest.php` ve `UpdateHighlightRequest.php` yaz
- [X] T048 [US1] `app/Http/Controllers/SourceController.php` yaz: index/create/store/show/edit/update (FR-012, FR-023)
- [X] T049 [US1] `app/Http/Controllers/HighlightController.php` yaz: create/store/edit/update/discard/favorite — kaydetmede `MarkdownRenderer` + `PastedTextCleaner` çağrılır, `char_count`/`contains_code` hesaplanır
- [X] T050 [US1] `app/Services/Review/ReviewBuilder.php` ilk sürümünü yaz: uygun pasajlardan `reviews` + `review_items` üretimi, `(user_id, review_date)` benzersizliğiyle idempotent, uygun pasaj yoksa üretmez (FR-025, FR-037, research.md R-07) *(ağırlıklandırma US2'de eklenir)*
- [X] T051 [US1] `app/Services/Review/ReviewItemActions.php` yaz: `keep`/`discard`, favori, idempotency, tüm kalemler işlendiğinde tekrarı tamamlama (FR-038..FR-040, FR-054)
- [X] T052 [US1] `app/Http/Controllers/ReviewController.php` yaz: `show` (bugünün tekrarı, yoksa üret), `complete` (contracts/routes.md)
- [X] T053 [US1] `app/Http/Controllers/ReviewItemActionController.php` + `app/Http/Requests/ReviewItemActionRequest.php` yaz: JSON yanıt sözleşmesi contracts/review-actions.md'e birebir uyar
- [X] T054 [US1] `resources/views/review/show.blade.php` yaz: kartlar sunucuda gömülü, ilerleme çubuğu (sayı değil), tamamlanma ekranı (FR-043)
- [X] T055 [US1] `resources/js/review.js` yaz (vanilla, <6KB): optimistic kart geçişi, kaydırma (mobil) + ok tuşları (masaüstü), başarısız istek için `localStorage` kuyruğu (FR-041, FR-042, research.md R-10)
- [X] T056 [US1] `resources/views/editor/create.blade.php` + `resources/js/editor.js` yaz: tek elle kullanım, klavye üstü biçimlendirme kısayolları, yaz/önizle geçişi, 500ms debounce ile `localStorage` taslağı (FR-020, FR-021)
- [X] T057 [P] [US1] `resources/views/library/index.blade.php` ve `sources/show.blade.php` yaz: kütüphane listesi + boş durum yönlendirmesi ("ilk kaynağını ekle") (FR-023, US1-1)
- [X] T058 [US1] `resources/views/components/highlight-content.blade.php` içinde 600 karakterden uzun pasajları kısaltma + genişletme davranışını uygula (FR-082) ve kod bloğu/tablo için kendi kabında yatay kaydırma (FR-081)
- [X] T059 [US1] `app/Services/Streak/StreakService::recordCompletion()` asgari sürümünü yaz ve tekrar tamamlanmasına bağla (yerel gün `LocalDayResolver`'dan) — takvim ve seri kırılma mantığı US5'te tamamlanır
- [X] T060 [P] [US1] `tests/Feature/Content/MobileRenderingTest.php` — 3.000 karakterlik başlık+liste+kod+tablo içeren pasajın render edildiğini ve `{!! !!}` kullanımının yalnızca `content_html` olduğunu doğrula (SC-008 elle doğrulama quickstart V1'de)

**Checkpoint**: US1 tek başına çalışır ve teslim edilebilir (MVP).

---

## Phase 4: User Story 2 — Günün doğru pasajlarını getir (Priority: P1)

**Goal**: Seçki ağırlıklı, tekrarsız ve kaynak dengeli üretilir.

**Independent Test**: Çok pasajlı hesapta ardışık günler için tekrar üret; tekrarlanma,
kaynak dağılımı ve ağırlık tercihlerinin etkisini karşılaştır.

### Tests for User Story 2

- [X] T061 [P] [US2] `tests/Unit/Review/HighlightSamplerTest.php` — cooldown, novelty ve kaynak ağırlığı çarpanlarının bileşimi (FR-027)
- [X] T062 [P] [US2] `tests/Feature/Review/ThirtyDaySimulationTest.php` — 30 gün simülasyonu: hiçbir pasaj 3 gün içinde iki kez çıkmaz, tek kaynağın payı `ceil(n/3)`'ü geçmez (SC-009, FR-029, FR-033)
- [X] T063 [P] [US2] `tests/Feature/Review/SourceFrequencyTest.php` — `never` hiç çıkmaz (%100), `very_often` belirgin daha sık, değişiklik bir sonraki tekrardan itibaren etkili (SC-010, FR-028)
- [X] T064 [P] [US2] `tests/Feature/Review/EqualSourceWeightingTest.php` — tercih açıkken pasaj sayısı seçilme şansını artırmaz (FR-032)
- [X] T065 [P] [US2] `tests/Feature/Review/ReviewImmutabilityTest.php` — tekrar üretildikten sonra boyut/oran değişimi bugünü etkilemez (FR-026, US2-6)
- [X] T066 [P] [US2] `tests/Feature/Review/SamplingPerformanceTest.php` — 20.000 pasajlı hesapta seçki üretimi <300ms (SC-004)

### Implementation for User Story 2

- [X] T067 [US2] `app/Services/Review/HighlightSampler.php` yaz: aday filtresi (`is_discarded`, arşiv, `never`, 3 gün bloğu, kalite filtresi) + ağırlık ifadesi + `ORDER BY -LOG(RAND())/weight` ile aşırı örnekleme (research.md R-04, R-05) — tüm sabitler `config('byagain.sampling.*')`
- [X] T068 [US2] `app/Services/Review/SourceQuota.php` yaz veya `ReviewBuilder` içine kota uygulamasını ekle: kaynak başına `ceil(n/3)` sınırı (FR-033)
- [X] T069 [US2] `app/Services/Review/ReviewBuilder.php` dosyasını `HighlightSampler` kullanacak biçimde güncelle; kısmi havuzda daha kısa tekrar üret, hata verme (FR-036, Edge Case)
- [X] T070 [P] [US2] `app/Http/Requests/UpdateSettingsRequest.php` içine tekrar boyutu (5–15), kalite filtresi ve eşit kaynak ağırlığı alanlarını ekle (FR-024, FR-031, FR-032)
- [X] T071 [P] [US2] `resources/views/settings/edit.blade.php` içine tekrar boyutu, kalite filtresi ve eşit kaynak ağırlığı denetimlerini ekle
- [X] T072 [US2] Kaynak sıklığı denetimini iki yerde bağla: `resources/views/library/sources/edit.blade.php` ve tekrar kartı aksiyon menüsü (`source_frequency` alanı — contracts/review-actions.md, FR-038)
- [X] T073 [P] [US2] `database/seeders/DemoSeeder.php` yaz: çok kaynaklı, 20.000 pasajlı performans senaryosu üretebilen seeder

**Checkpoint**: US1 + US2 birlikte "doğru pasaj doğru gün" değerini tam verir.

---

## Phase 5: User Story 3 — Mastery kartları (Priority: P2)

**Goal**: Pasajdan soru-cevap/cloze kartı; yarı-ömür tabanlı aralık.

**Independent Test**: Karttan geri bildirim ver, bir sonraki vade tarihini doğrula.
Normal pasaj örneklemesinden bağımsız çalışır.

### Tests for User Story 3

- [X] T074 [P] [US3] `tests/Unit/Mastery/MasterySchedulerTest.php` — ilk geri bildirim 7/14/28, sonrakiler ×0.5/×2.0/×3.0, `clamp(1,365)` (FR-047..FR-049)
- [X] T075 [P] [US3] `tests/Feature/Mastery/MasteryInReviewTest.php` — mastery kartları normal pasajlardan sonra sıralanır, oran aşılmaz, vade gelmemiş kart çıkmaz, boşluk normal pasajla dolar (FR-035, FR-036, FR-051)
- [X] T076 [P] [US3] `tests/Feature/Mastery/RetireAndStruggleTest.php` — `learned` → `retired` (silinmez), 6. "daha erken" sonrası ipucu (FR-050, FR-052)

### Implementation for User Story 3

- [X] T077 [US3] `app/Services/Mastery/MasteryScheduler.php` yaz: `applyFeedback()`, `recallProbability()`, `dueCards()` (en düşük `p` önce, eşitlikte rastgele) — sabitler `config('byagain.mastery.*')` (research.md R-06)
- [X] T078 [P] [US3] `app/Http/Requests/StoreMasteryCardRequest.php` ve `UpdateMasteryCardRequest.php` yaz (cloze biçimi doğrulaması dahil)
- [X] T079 [US3] `app/Http/Controllers/MasteryCardController.php` yaz: store (pasajdan), index, edit, update, retire (FR-044, FR-053)
- [X] T080 [US3] `app/Services/Review/ReviewBuilder.php` içine mastery karışımını ekle: `mastery_ratio` kadar vadesi gelmiş kart, normal pasajlardan sonra `position` (FR-034, FR-035, FR-036)
- [X] T081 [US3] `app/Services/Review/ReviewItemActions.php` içine `mastery_feedback` işlemesini ekle ve JSON yanıtına `mastery.half_life_days`/`due_at`/`hint` alanlarını doldur (contracts/review-actions.md)
- [X] T082 [P] [US3] `resources/views/review/partials/mastery-card.blade.php` yaz: önce soru, kullanıcı isteğiyle cevap; dört geri bildirim düğmesi (FR-045, FR-046)
- [X] T083 [P] [US3] `resources/views/mastery/index.blade.php` ve `edit.blade.php` yaz: kart listesi, düzenleme, kaldırma (FR-053)
- [X] T084 [P] [US3] `resources/views/settings/edit.blade.php` içine mastery oranı (%) denetimini ekle (FR-034)

**Checkpoint**: US1–US3 bağımsız çalışır.

---

## Phase 6: User Story 4 — Günlük e-posta ve hatırlatma (Priority: P2)

**Goal**: Kullanıcı yerel saatinde kartları gömülü e-postayla alır; tamamlamadıysa tek bir
nazik hatırlatma gelir.

**Independent Test**: Farklı zaman dilimlerinde kullanıcılar oluştur, zamanı ileri sar,
hangi e-postanın ne zaman gönderildiğini doğrula.

### Tests for User Story 4

- [X] T085 [P] [US4] `tests/Feature/Mail/DailyPipelineTest.php` — `America/New_York` 08:00 kullanıcısı doğru pencerede sıraya alınır (±5 dk, SC-013); uygun pasajı olmayan kullanıcıya tekrar ve e-posta yok (US2-7)
- [X] T086 [P] [US4] `tests/Feature/Mail/ReminderDedupeTest.php` — komut aynı gün 10 kez çalışsa da kullanıcı başına 1 daily + 1 reminder (FR-063, SC-014)
- [X] T087 [P] [US4] `tests/Feature/Mail/ReminderSkipTest.php` — gönderim anında tekrar tamamlanmışsa `skipped` yazılır, gönderilmez (FR-062, US4-5)
- [X] T088 [P] [US4] `tests/Feature/Mail/UnverifiedAndUnsubscribeTest.php` — doğrulanmamış e-postaya ritüel maili gitmez (FR-007); imzalı "kapat" bağlantısı girişsiz çalışır, imza bozulursa 403 (FR-065)
- [X] T089 [P] [US4] `tests/Feature/Mail/EmailMatchesAppTest.php` — e-postadaki kartlar `review_items` ile birebir aynı (FR-061, US4-2)

### Implementation for User Story 4

- [X] T090 [US4] `app/Console/Commands/DispatchDailyPipeline.php` yaz: 5 dakikalık pencere taraması, tekrar üretimi, `email_deliveries` `insertOrIgnore`, `--dry-run`/`--user`/`--now` seçenekleri (contracts/console-and-jobs.md)
- [X] T091 [US4] `routes/console.php` içinde `byagain:dispatch-daily` görevini her 5 dakikada `withoutOverlapping()` ile zamanla (FR-089)
- [X] T092 [US4] `app/Services/Mail/MailDispatcher.php` yaz: dedupe anahtarı üretimi, sıraya alma, gönderim öncesi durum yeniden kontrolü, `skipped` nedeni kaydı (FR-062, FR-063, FR-068)
- [X] T093 [P] [US4] `app/Jobs/SendDailyReviewEmail.php` yaz: `tries=5`, artan `backoff`, `failed()` kancasında `status=failed` (FR-067)
- [X] T094 [P] [US4] `app/Jobs/SendEveningReminderEmail.php` yaz: aynı sözleşme + tamamlanma yeniden kontrolü + haftada 1 kısıtı (FR-062, FR-064)
- [X] T095 [P] [US4] `app/Mail/DailyReviewMail.php` + `resources/views/mail/daily.blade.php` + düz metin sürümü: kartlar gömülü, tek çağrı, dar ekran + karanlık mod (FR-060, FR-066)
- [X] T096 [P] [US4] `app/Mail/EveningReminderMail.php` + `resources/views/mail/reminder.blade.php` + düz metin sürümü (FR-062, suçlayıcı olmayan dil)
- [X] T097 [US4] `app/Http/Controllers/UnsubscribeController.php` yaz: imzalı rota, girişsiz, idempotent tercih kapatma + onay ekranı (FR-065, research.md R-12)
- [X] T098 [US4] `app/Http/Controllers/MailWebhookController.php` yaz: sağlayıcı imzası doğrulaması → `email_deliveries.opened_at` ve `users.consecutive_unopened_emails` güncellemesi (FR-064)
- [X] T099 [P] [US4] `resources/views/settings/edit.blade.php` içine e-posta saatleri, zaman dilimi ve iki ayrı açma/kapama denetimini ekle (FR-059)

**Checkpoint**: Geri dönüş döngüsü çalışır.

---

## Phase 7: User Story 5 — Seri (streak) (Priority: P3)

**Goal**: Güncel seri, en uzun seri ve 90 günlük takvim; 04:00 gün sınırı.

**Independent Test**: Farklı saatlerde tekrar tamamla, sayacın ve takvimin doğru güne
yazdığını doğrula.

### Tests for User Story 5

- [X] T100 [P] [US5] `tests/Feature/Streak/StreakTest.php` — dün+bugün → +1; 01:30 önceki güne; atlanan gün → 1'den başlar ve `longest_streak` korunur; iki gün yapılmadıysa 0 görünür (FR-054..FR-057)
- [X] T101 [P] [US5] `tests/Feature/Streak/TimezoneChangeTest.php` — zaman dilimi değişimi geçmiş seri günlerini değiştirmez (Edge Case)

### Implementation for User Story 5

- [X] T102 [US5] `app/Services/Streak/StreakService.php` dosyasını tamamla: `recordCompletion()`, `currentStreakFor()` (arka plan işi olmadan, son tamamlanan günden hesap), `calendar(90)` (FR-056, FR-057)
- [X] T103 [US5] `app/Http/Controllers/StreakController.php` + `resources/views/streak/show.blade.php` yaz: sayaç, en uzun seri, 90 günlük takvim (FR-056, US5-5)
- [X] T104 [P] [US5] `resources/views/components/streak-badge.blade.php` yaz ve ana ekrana bağla; seri kırıldığında suçlayıcı olmayan metin `lang/en/streak.php` içinden gelir (FR-058)

---

## Phase 8: User Story 6 — Yönetim paneli (Priority: P3)

**Goal**: Operasyon görünürlüğü ve sınırlı yönetici işlemleri, kullanıcı tarafından tam izole.

**Independent Test**: Yönetici rolüyle panele gir, kullanıcı listele, e-posta yeniden
gönder; normal rolle erişimin reddedildiğini doğrula.

### Tests for User Story 6

- [X] T105 [P] [US6] `tests/Feature/Admin/AdminAccessTest.php` — `user` rolü `/admin` → 403; `admin` rolü erişir (FR-069)
- [X] T106 [P] [US6] `tests/Feature/Admin/AdminActionLogTest.php` — askıya alma ve yeniden gönderme kaydedilir; kayıt güncellenemez/silinemez (FR-076)
- [X] T107 [P] [US6] `tests/Feature/Admin/SchedulerHealthTest.php` — zamanlayıcı iki turdur çalışmamışsa uyarı görünür (FR-074, SC-017)

### Implementation for User Story 6

- [X] T108 [US6] Filament v5 panelini `/admin` prefix'i ve `EnsureUserIsAdmin` middleware ile kur (`app/Providers/Filament/AdminPanelProvider.php`)
- [X] T109 [P] [US6] `app/Filament/Resources/UserResource.php` yaz: listele, ara, askıya al, doğrulama e-postası yeniden gönder; kimliğe bürünme yok (FR-070)
- [X] T110 [P] [US6] `app/Filament/Resources/SourceResource.php` ve `HighlightResource.php` yaz: salt okunur, pasajda 120 karakterlik `content_text` önizlemesi (FR-071)
- [X] T111 [P] [US6] `app/Filament/Resources/EmailDeliveryResource.php` yaz: tür/durum süzme, hata detayı, elle yeniden gönderme aksiyonu (FR-072)
- [X] T112 [US6] `app/Filament/Widgets/` altında gösterge widget'ları: kayıt, aktif kullanıcı, tamamlanma oranı, ortalama seri, gönderilen/başarısız e-posta, bekleyen iş, zamanlayıcı yaşı + gecikme uyarısı (FR-073, FR-074)
- [X] T113 [P] [US6] `app/Filament/Pages/MaintenanceSettings.php` yaz: bakım modu, kayıt kapatma, varsayılan tekrar boyutu (FR-075)
- [X] T114 [US6] `app/Services/Admin/AdminActionLogger.php` yaz ve tüm Filament aksiyonlarına bağla (FR-076)
- [X] T115 [US6] Panelde `withoutGlobalScope()` kullanımının yalnızca `app/Filament/` altında kaldığını doğrula ve kullanıcı layout'unun hiçbir Filament/Livewire/Alpine varlığı yüklemediğini kontrol et (SC-018, Ana Yasa m. II)

---

## Phase 9: Polish & Cross-Cutting Concerns

- [ ] T116 [P] `public/manifest.json` ve `public/sw.js` yaz: ana ekrana eklenebilirlik, uygulama kabuğu önbelleği, API için network-first (FR-086, FR-087)
- [ ] T117 [P] `resources/js/app.js` içine çevrimdışı durum bandı ve `localStorage` istek kuyruğunun yeniden denemesini ekle (FR-087, SC-016)
- [ ] T118 [P] `app/Console/Commands/PruneEphemeralRecords.php` yaz ve günlük zamanla: süresi geçmiş token'lar, 30 günden eski `email_deliveries`, eski `failed_jobs` — kullanıcı içeriğine dokunmaz (FR-091)
- [ ] T119 [P] `app/Console/Commands/RerenderHighlights.php` yaz: `content_md` → `content_html`/`content_text` toplu yeniden üretim, chunk'lı, `--dry-run` (FR-092)
- [ ] T120 [P] `app/Console/Commands/PromoteAdmin.php` yaz: kurulumda ilk yöneticiyi elle yetkilendirme (Assumptions)
- [ ] T121 [P] `resources/views/components/footer.blade.php` içine kaynak koda bağlantı ekle (AGPL-3.0 gereği, FR-093)
- [ ] T122 Erişilebilirlik geçişi: odak görünürlüğü, kontrast ≥4.5:1, ikon düğmelerinde `aria-label`, form alanlarında 16px+ yazı tipi, `prefers-reduced-motion` (FR-084, FR-085, FR-080)
- [ ] T123 Varlık bütçesi denetimi: kullanıcı tarafı ilk yükleme ≤150KB (yazı tipleri hariç), sistem font yığını kullanımı (SC-006)
- [ ] T124 Lighthouse mobil denetimi: performans ≥90, erişilebilirlik ≥95 — bulguları düzelt (SC-007)
- [ ] T125 [P] `README.md` yaz: kurulum adımları quickstart.md ile birebir aynı, `.env.example` yapılandırma tablosu (SC-019)
- [ ] T126 [P] `docs/SPEC.md` yaz: rota listesi İngilizce adlarla, algoritma bölümleri (SPEC 4.1/4.2/6) koddaki yorum atıflarıyla eşleşecek şekilde
- [ ] T127 Depo geçmişinde gizli anahtar/ortam dosyası olmadığını doğrula ve `.gitignore` kapsamını kontrol et (SC-020)
- [ ] T128 `quickstart.md` V1–V7 doğrulama senaryolarını uçtan uca çalıştır; kalite kapılarını geçir (`pint --test`, `phpstan analyse`, `php artisan test`, `composer audit`)

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 0 (Kapı)**: Bloke edici — T001…T004 kapanmadan T005 başlamaz
- **Phase 1 (Setup)**: Phase 0'a bağlı
- **Phase 2 (Foundational)**: Phase 1'e bağlı — TÜM hikâyeleri bloke eder
- **Phase 3+ (Hikâyeler)**: Phase 2'ye bağlı
- **Phase 9 (Polish)**: İstenen hikâyelerin tamamlanmasına bağlı

### User Story Dependencies

- **US1 (P1)**: Phase 2 sonrası başlar — başka hikâyeye bağlı değil 🎯 MVP
- **US2 (P1)**: Phase 2 sonrası başlar; `ReviewBuilder`'ı US1 ile paylaşır (T050 → T069 aynı dosya, sıralı)
- **US3 (P2)**: Phase 2 sonrası başlar; tekrar karışımı için US1'in `ReviewBuilder`/`ReviewItemActions` dosyalarına dokunur (T080, T081 sıralı)
- **US4 (P2)**: Phase 2 sonrası başlar; tekrar üretimi için US1 gerekir (üretim olmadan gönderilecek kart yok)
- **US5 (P3)**: T059 (US1 asgari sürüm) üzerine kurulur; bağımsız test edilir
- **US6 (P3)**: Phase 2 sonrası tamamen bağımsız — veriye salt okur

### Aynı dosyaya dokunan görevler (paralel DEĞİL)

- `app/Services/Review/ReviewBuilder.php`: T050 → T069 → T080
- `app/Services/Review/ReviewItemActions.php`: T051 → T081
- `resources/views/settings/edit.blade.php`: T071 → T084 → T099
- `app/Services/Streak/StreakService.php`: T059 → T102

### Parallel Opportunities

- Phase 1: T007–T012 tümü paralel
- Phase 2: T015–T019 (farklı migration dosyaları), T021–T023, T025/T026, T028, T030–T032, T036/T038, T040 paralel
- Her hikâyenin test görevleri kendi içinde paralel
- Phase 9: T116–T121, T125–T126 paralel

---

## Parallel Example: User Story 1

```bash
# US1 testlerini birlikte başlat:
Task: "tests/Feature/Review/FirstReviewFlowTest.php"
Task: "tests/Feature/Library/HighlightCrudTest.php"
Task: "tests/Feature/Review/ReviewItemActionTest.php"
Task: "tests/Feature/Review/ResumePartialReviewTest.php"

# US1 Form Request'lerini birlikte başlat:
Task: "app/Http/Requests/StoreSourceRequest.php + UpdateSourceRequest.php"
Task: "app/Http/Requests/StoreHighlightRequest.php + UpdateHighlightRequest.php"
```

---

## Implementation Strategy

### MVP First (US1)

1. Phase 0 kapısını kapat (insan onayı)
2. Phase 1 Setup
3. Phase 2 Foundational (kritik — tüm hikâyeleri bloke eder)
4. Phase 3 US1
5. **DUR ve DOĞRULA**: quickstart.md V1 senaryosunu 375px'te elle geç
6. Teslim edilebilir

### Incremental Delivery

1. Setup + Foundational → temel hazır
2. US1 → MVP (içerik + tekrar döngüsü)
3. US2 → ürünün ikinci temel değeri (doğru pasaj doğru gün) — **gerçek v1 burasıdır**
4. US3 → öğrenme katmanı
5. US4 → geri dönüş döngüsü
6. US5 → alışkanlık pekiştirme
7. US6 → operasyon görünürlüğü
8. Phase 9 → PWA, erişilebilirlik, bütçe, belgeler

### Notes

- Ana Yasa m. V: iş 400 satırı geçecekse böl ve planı göster
- Her görev sonrası atomik commit; `main` dalına doğrudan commit yok
- "Bitmiş sayılma ölçütü" listesi her PR'ın kalite kapısıdır
- Test geçirmek için üretim kodu zayıflatılmaz, test `markTestSkipped` ile susturulmaz
