---

description: "Task list for feature implementation"
---

# Tasks: Tekrar Akışı Düzeltmeleri ve Tarayıcı Hatırlatması

**Input**: Design documents from `/specs/002-review-flow-fixes/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/, quickstart.md

**Tests**: Test görevleri **zorunludur**. Ana Yasa IV: "Her servis için birim
testi, her akış için feature testi zorunludur." Bu ürün için test isteğe bağlı
değil, kalite kapısıdır.

**Organization**: Görevler issue'lara karşılık gelen user story'lere göre
gruplanmıştır; her biri tek başına teslim edilebilir.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Paralel çalışabilir (farklı dosya, bekleyen bağımlılık yok)
- **[Story]**: US1 (#4) · US2 (#1) · US3 (#2) · US4 (#3)
- Her görev dosya yolu taşır

## Path Conventions

Laravel monolit, depo kökü: `app/`, `resources/`, `routes/`, `config/`,
`database/migrations/`, `public/`, `tests/`, `lang/en/`.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: İşin başlayabilmesi için gereken doğrulamalar ve ortam

- [X] T001 `composer.json` — `minishlink/web-push` paketinin güncel sürümünü, **lisansını** (beklenen MIT, AGPL-3.0 ile uyumlu) ve Laravel 13.x / PHP 8.3 uyumunu kur öncesi doğrula; uyumsuzsa iş durur ve depo sahibine sorulur (research.md R-203, Ana Yasa V)
- [ ] T002 [P] `specs/002-review-flow-fixes/quickstart.md` — geliştirme ortamını "Önkoşullar" bölümüne göre ayağa kaldır (`php artisan queue:work`, `npm run dev`, telefon aynı ağda `php artisan serve --host=0.0.0.0`) ve eksik adım varsa belgeyi düzelt

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Birden çok story'yi kesen ön işler

**⚠️ CRITICAL**: T003 tamamlanmadan US1'e dokunulmaz — mevcut testler bugünkü
(yanlış) davranışı doğruluyor olabilir ve bunu kırmadan önce bilmek gerekir

- [X] T003 `tests/Feature/Review/` — `GET /review`'in tamamlanmış günde otomatik tur ürettiğini varsayan mevcut testleri bul ve listeyi `specs/002-review-flow-fixes/research.md` altına "mevcut test etkisi" olarak yaz
- [ ] T004 [P] `specs/002-review-flow-fixes/research.md` — R-202 için cihaz bulgusunu kaydet: iOS Safari ve Chrome/Android'de alt menünün uzun pasajda ne yaptığı, adres çubuğu daralırken davranışı; düzeltme adımı bu bulguya göre seçilir

**Checkpoint**: Zemin hazır — story'ler başlayabilir

---

## Phase 3: User Story 1 - Biten tekrar bitmiş kalır (Priority: P1) 🎯 MVP

**Goal**: Tamamlanan gün kendini yeniden açmaz; yeni tur yalnızca açık istekle
gelir (issue #4, FR-101…FR-108)

**Independent Test**: Günün turunu bitir, alt menüden Library'ye geç, Review'e
dön — tamamlanma ekranı gelmeli ve veritabanında yeni `reviews` satırı
olmamalı

### Tests for User Story 1 ⚠️

> Önce yazılır, uygulamadan önce **kırmızı** olduğu görülür

- [X] T005 [US1] `tests/Feature/Review/ReviewCompletionTest.php` — karar matrisinin beş senaryosu (contracts/review-completion.md): tamamlanmış gün + kota boş → `review.done` ve yeni tur yok; `POST /review/again` → `round = 2`; malzeme yok → düğme yok + `exhausted` metni; kota dolu → düğme yok; yarım tur → ilk kararsız karttan devam. Zaman kuran her senaryoda `Carbon::setTestNow()`
- [X] T006 [P] [US1] `tests/Unit/Review/ReviewBuilderTest.php` — `hasMaterialFor()` birim testi: malzeme varken `true`, blok/soğuma nedeniyle uygun kart kalmadığında `false`, ve **hiçbir satır yazmadığı** doğrulanır

### Implementation for User Story 1

- [X] T007 [US1] `app/Services/Review/ReviewBuilder.php` — salt-okunur `hasMaterialFor(User $user, CarbonImmutable $day): bool` ekle; örnekleyiciye bir kartlık soru sorar, `reviews`/`review_items` yazmaz
- [X] T008 [US1] `app/Http/Controllers/ReviewController.php` — `show()` içindeki otomatik `buildNextRound()` çağrısını kaldır (satır 47-53); `doneState()` dizisine `hasMaterial` ekle ve `canRepeat`'i `rounds < max_rounds_per_day && rounds < limit` olarak hesapla
- [X] T009 [US1] `resources/views/review/done.blade.php` — üç sonlu durum: düğme / malzeme bitti metni / gün kapalı; karar kartlarına dönen hiçbir bağlantı bırakma (FR-107)
- [X] T010 [P] [US1] `lang/en/review.php` — kota dolduğunda gösterilecek "gün kapalı" metni ve gerekiyorsa `again` yardım metninin düzeltmesi (arayüz dili İngilizce)
- [X] T011 [US1] `tests/Feature/Review/` — T003'te bulunan mevcut testleri yeni davranışa göre güncelle; hiçbirini `markTestSkipped` ile susturma (Ana Yasa IV)
- [X] T012 [US1] `docs/SPEC.md` — tur açma davranışının değiştiğini işle (otomatik tur yok, tek yol `POST /review/again`); Ana Yasa "SPEC değiştiyse aynı PR'da güncellenir" kuralı

**Checkpoint**: #4 kapanabilir durumda; ürün tek başına bu değişiklikle teslim edilebilir

---

## Phase 4: User Story 2 - Kart içindeki yatay kaydırma (Priority: P1)

**Goal**: Kod bloğu/tablo kendi içinde kaydırılır, kart kımıldamaz, yanlış karar
üretilmez (issue #1, FR-121…FR-125)

**Independent Test**: 120 karakterlik tek satır kod içeren pasajı telefonda aç,
kod bloğunu sola kaydır — kod kayar, kart durur, karar kaydedilmez

### Tests for User Story 2 ⚠️

> Dokunma jesti bu yığında otomatik test edilemez (kullanıcı tarafında JS test
> koşucusu yok; eklemek yeni bağımlılıktır — Ana Yasa V, önce sorulur).
> Otomatik kapsam işaretleme düzeyinde, jest doğrulaması cihazda elle.

- [X] T013 [P] [US2] `tests/Feature/Content/HighlightRenderTest.php` — render edilen pasajda kod bloğunun ve tablonun kendi kaydırma kutusuyla çıktığını doğrula (`pre` / `table` beklenen işaretleme ile); regresyonu yakalayan ucuz kapı

### Implementation for User Story 2

- [X] T014 [US2] `resources/css/app.css` — `.highlight-content .highlight-body pre` ve `… table` için `touch-action: auto` (veya `pan-x pan-y`) ver; `[data-swipe-surface]` üzerindeki `pan-y` kuralı yerinde kalır (research.md R-201)
- [X] T015 [US2] `resources/js/review.js` — `bindSwipe`: `touchstart` hedefi kendi içinde hâlâ kaydırılabilecek yatay bir ata içindeyse (`scrollWidth > clientWidth`) jesti hiç başlatma; başlayan bir dokunuş kaydırma sayıldıysa `touchend`'e kadar karar üretme
- [X] T016 [US2] `resources/css/app.css` — taşan içeriğin kaydırılabilir olduğunu belli eden görsel ipucu (kenar gölgesi veya benzeri), `tokens.css` değiştirmeden (FR-124, Ana Yasa V)
- [ ] T017 [US2] `specs/002-review-flow-fixes/quickstart.md` — bölüm 2'nin beş adımını iOS Safari ve Chrome/Android'de yürüt, sonucu belgeye işle

**Checkpoint**: #1 kapanabilir durumda

---

## Phase 5: User Story 3 - Alt menü sabit kalır (Priority: P2)

**Goal**: Alt menü kaydırma boyunca ekranın altında kalır, içerik menünün
arkasına düşmez (issue #2, FR-131…FR-134)

**Independent Test**: Ekran boyunun iki katı bir pasajı telefonda sonuna kadar
kaydır — menü her an görünür, son satır menünün arkasında değil

- [X] T018 [US3] `resources/views/components/layouts/app.blade.php` — `html.h-full` / `body.min-h-full` yükseklik zincirini kaldır; kaydırma kabı belirsizliğini bitir (research.md R-202, adım 2)
- [X] T019 [US3] `resources/css/app.css` — gövde alt boşluğunu ve menü yüksekliğini `env(safe-area-inset-bottom)` değişse de sabit kalacak biçimde yaz (`max()` ile), `100vh` kullanma
- [X] T020 [US3] `resources/views/components/bottom-nav.blade.php` — menüye kendi katmanını ver ve sabitlemeyi bozan bir kural kalmadığını doğrula; dokunma hedefi boyutları küçülmesin (FR-134)
- [ ] T021 [US3] `specs/002-review-flow-fixes/quickstart.md` — bölüm 3'ün beş adımını iki tarayıcıda yürüt; geri alma çubuğuyla üst üste binme kontrolü dahil
- [ ] T022 [US3] `resources/views/components/layouts/app.blade.php` — **koşullu**: T021 hâlâ kaymayı gösteriyorsa kabuğu yeniden kur (`100dvh` flex sütun, `main` kendi kaydırma kutusu, menü akışta kardeş); ayrı commit, adres çubuğu davranışındaki kaybı PR açıklamasına yaz (research.md R-202, adım 3)

**Checkpoint**: #2 kapanabilir durumda

---

## Phase 6: User Story 4 - Tarayıcı hatırlatması (Priority: P3)

**Goal**: Günlük e-postadan 60 dakika sonra tekrar hâlâ bitmemişse tek bir web
push (issue #3, FR-141…FR-153)

**Independent Test**: `byagain:dispatch-daily --user=1 --now="… 08:00"` sonra
`--now="… 09:00"` — telefonda bildirim belirir, dokununca `/review` açılır;
tekrar tamamlanmışsa hiç gitmez

> **Boyut uyarısı**: Bu faz tek başına 400 satırı geçerse Ana Yasa V uyarınca
> bölünür ve ayrı PR olur (plan.md, Phase 1 sonrası değerlendirme).

### Tests for User Story 4 ⚠️

- [X] T023 [P] [US4] `tests/Feature/Push/PushSubscriptionTest.php` — abonelik oluşturma (201), aynı `endpoint` tazeleme (200), başka hesaba devir, silme (204), doğrulama hataları (422), VAPID anahtarı yokken (503), başkasının aboneliğine erişimde 404 (contracts/push.md)
- [X] T024 [P] [US4] `tests/Feature/Push/ReviewNudgeTest.php` — pencere içinde tam bir `push_deliveries` satırı ve tam bir kuyruk işi; ikinci süpürme ikinci satır açmaz; tekrar tamamlanmış / ayar kapalı / günlük e-posta gitmemiş / abonelik yok → gönderim yok; `410` dönen abonelik silinir
- [X] T025 [P] [US4] `tests/Unit/Push/PushDispatcherTest.php` — karar sırasının birim testi; `WebPushSender` sahtelenir, testte ağ yok
- [X] T026 [P] [US4] `tests/Feature/Console/DispatchDailyPushWindowTest.php` — `daily_email_at + nudge_delay_minutes` penceresi, gece yarısını aşan saatlerde `dedupe_key`'in **e-postanın ait olduğu yerel günü** taşıması, `--dry-run` hiçbir şey yazmaz (contracts/console-and-jobs.md)

### Data layer for User Story 4

- [X] T027 [P] [US4] `database/migrations/2026_08_24_000100_create_push_subscriptions_table.php` — data-model.md'deki sütunlar, `unique(endpoint)`, `index(user_id)`, çalışan `down()`
- [X] T028 [P] [US4] `database/migrations/2026_08_24_000200_create_push_deliveries_table.php` — `unique(user_id, dedupe_key)`, `index(user_id, status)`, çalışan `down()`
- [X] T029 [P] [US4] `database/migrations/2026_08_24_000300_add_push_enabled_to_users_table.php` — `boolean push_enabled` varsayılan `false`, `reminder_email_at` sonrası, çalışan `down()`
- [X] T030 [P] [US4] `app/Models/PushSubscription.php` — `final class`, `BelongsToUser`, açık `$fillable`, `casts()`
- [X] T031 [P] [US4] `app/Models/PushDelivery.php` — durum sabitleri (`queued`/`sent`/`failed`/`skipped`), `dedupeKeyFor()`, açık `$fillable` (`EmailDelivery` deseni)
- [X] T032 [US4] `app/Models/User.php` — `pushSubscriptions()` ve `pushDeliveries()` ilişkileri, `push_enabled` alanı ve cast'i
- [X] T033 [P] [US4] `database/factories/PushSubscriptionFactory.php` ve `database/factories/PushDeliveryFactory.php` — testlerin ihtiyacı

### Configuration for User Story 4

- [X] T034 [US4] `composer.json` — T001 onayından sonra `composer require minishlink/web-push`; sürüm ve lisans PR açıklamasına yazılır
- [X] T035 [P] [US4] `config/byagain.php` — `push` bloğu: `nudge_delay_minutes => 60`, `max_per_day => 1`; her ikisi de yorumla ve FR atfıyla (Ana Yasa V, sihirli sayı yok)
- [X] T036 [P] [US4] `config/services.php` — `vapid` bloğu (`public_key`, `private_key`, `subject`), `.env`'den okunur
- [X] T037 [P] [US4] `.env.example` ve `README.md` — `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, `VAPID_SUBJECT` anahtarları ve yapılandırma tablosu; **gerçek değerler yazılmaz**, `.env` dosyasına dokunulmaz (Ana Yasa III)
- [X] T038 [US4] `app/Console/Commands/GenerateVapidKeys.php` — `byagain:vapid-keys`; anahtar çiftini üretir, ekrana basar, hiçbir dosyaya yazmaz

### Services and endpoints for User Story 4

- [X] T039 [US4] `app/Services/Push/WebPushSender.php` — kütüphanenin dokunulduğu tek yer; gönderir, `404`/`410` dönen aboneliği silinmek üzere bildirir, testte sahtelenebilir arayüz sunar
- [X] T040 [US4] `app/Services/Push/PushDispatcher.php` — `queueReviewNudge()`: karar sırası (ayar → günlük e-posta gitti mi → round 1 var ve tamamlanmamış mı → abonelik var mı), sonra `insertOrIgnore` ile slot kapma; `markSent`/`markSkipped`/`markFailed` (`MailDispatcher` deseni)
- [X] T041 [US4] `app/Jobs/SendReviewNudgePush.php` — yük olarak `push_deliveries.id`, `tries = 1`; göndermeden önce durumu yeniden okur; metinleri `lang/en/` üzerinden çözer
- [X] T042 [P] [US4] `app/Http/Requests/StorePushSubscriptionRequest.php` — `endpoint` (https URL, ≤512), `keys.p256dh`, `keys.auth`, `content_encoding` beyaz listesi; `authorize()` gerçek kontrol yapar
- [X] T043 [US4] `app/Http/Controllers/PushSubscriptionController.php` — `store()` (201/200/503) ve `destroy()` (her durumda 204); controller ince, iş `PushDispatcher`/model tarafında
- [X] T044 [US4] `routes/web.php` — `POST /push/subscriptions` (`push.subscribe`) ve `DELETE /push/subscriptions` (`push.unsubscribe`), `auth` + `ensure.active` + `throttle:20,1`
- [X] T045 [US4] `app/Console/Commands/DispatchDailyPipeline.php` — üçüncü pencere: `daily_email_at + nudge_delay_minutes`; `push_queued` sayacı, `--dry-run` yazmaz, atlama nedenleri raporlanır
- [X] T046 [US4] `app/Console/Commands/PruneEphemeralRecords.php` — eski `push_deliveries` satırlarını `email_deliveries` ile aynı saklama süresiyle temizle; `push_subscriptions`'a dokunma (contracts/console-and-jobs.md)

### Client for User Story 4

- [X] T047 [US4] `public/sw.js` — `push` ve `notificationclick` dinleyicileri; `VERSION` `v2`; payload çözülemezse sabit başlıkla göster; hiçbir kullanıcı metnini dosyada tutma
- [X] T048 [P] [US4] `resources/js/push.js` — izin akışı: yalnızca kullanıcı dokununca `Notification.requestPermission()`, abonelik `POST /push/subscriptions`, kapatma `DELETE`; destek yoksa sessizce devre dışı (FR-145, FR-149)
- [X] T049 [US4] `resources/views/settings/edit.blade.php` — bildirim anahtarı; desteklenmeyen ortamda devre dışı görünür ve iOS için "ana ekrana ekle" açıklaması (research.md R-206); `push.js` yalnızca bu sayfada yüklenir (varlık bütçesi)
- [X] T050 [US4] `app/Http/Requests/UpdateSettingsRequest.php` ve `app/Http/Controllers/SettingsController.php` — `push_enabled` alanı
- [X] T051 [P] [US4] `lang/en/` — bildirim başlığı/gövdesi ve ayar metinleri; payload'da pasaj içeriği geçmez (FR-148)
- [ ] T052 [US4] `specs/002-review-flow-fixes/quickstart.md` — bölüm 4a/4b/4c'yi gerçek cihazda yürüt ve sonucu işle

**Checkpoint**: #3 kapanabilir durumda; dört issue de kapalı

---

## Phase 7: Polish & Cross-Cutting Concerns

- [X] T053 [P] `vendor/bin/pint --test` temiz
- [X] T054 [P] `vendor/bin/phpstan analyse` temiz (level 6, baseline'a ekleme yok)
- [X] T055 `php artisan test` yeşil; yeni davranışların hepsinin testi var
- [X] T056 [P] `composer audit` temiz
- [X] T057 `npm run build` — kullanıcı bundle'ı 150KB bütçesinin altında (SC-106); `push.js`'in tekrar ekranına sızmadığını doğrula
- [X] T058 `database/migrations/` — `php artisan migrate:rollback` ile üç yeni migration'ın `down()`'ı çalışıyor mu
- [X] T059 [P] `lang/en/` — kullanıcıya görünen yeni metnin hiçbiri Blade veya JS içinde gömülü değil (Ana Yasa yasaklı desen listesi)
- [ ] T060 `specs/002-review-flow-fixes/quickstart.md` — tüm bölümleri baştan sona bir kez daha yürüt (375px gerçek cihaz)
- [ ] T061 GitHub issue #1, #2, #3, #4 — her birine hangi commit'in kapattığını yaz; PR açıklamasında "ne değişti / neden / nasıl test edildi" kutularını doldur

---

## Kalan görevler ve neden kaldıkları (2026-08-24)

İşaretlenmemiş on görev vardı; ikisi 2026-10-02'de kapandı. Hiçbiri atlanmadı — hepsi bu makinede
bulunmayan bir şeye bağlı.

**Veritabanı gerektiren iki görev kapandı (2026-10-02)**: MySQL 8.4 Docker'da
kuruldu. T055 — ilk koşuda 301 testten 9'u kırıldı; hepsi test kurgusundandı
(`Queue::fake()` sabah e-postası işini de yutuyordu, mastery kartı saat
dondurulmadan önce oluşturuluyordu), üretim kodunda hata çıkmadı. Düzeltmeden
sonra 301/301 yeşil. T058 — `migrate:rollback --step=3` ve yeniden `migrate`
temiz.

**Gerçek telefon gerektiriyor** (Ana Yasa I: 375px cihaz doğrulaması):

| Görev | Ne gerekiyor |
|---|---|
| T002 | Geliştirme ortamının ayağa kaldırılması (kuyruk işçisi + telefon aynı ağda) |
| T004 | R-202 cihaz bulgusu — alt menünün iki tarayıcıdaki davranışı |
| T017 | quickstart bölüm 2 (yatay kaydırma jesti) |
| T021 | quickstart bölüm 3 (alt menü) |
| T052 | quickstart bölüm 4a/4b/4c (bildirim teslimi) |
| T060 | quickstart'ın baştan sona tekrarı |

**Koşullu, tetiklenmedi**:

| Görev | Durum |
|---|---|
| T022 | Tetikleyicisi "T021 hâlâ kaymayı gösteriyorsa". T021 yürütülemediği için karar verilemez. Kabuğu `100dvh` flex sütuna çevirmenin bedeli var (adres çubuğu gizlenmez, dikey alan kaybedilir) — ölçüm olmadan ödenmez |

**İnsan işi**:

| Görev | Durum |
|---|---|
| T061 | Issue kapatma notları ve PR açıklaması — commit'ler atıldıktan sonra |

Uygulanan kodun statik kapıları temiz: `pint`, `phpstan` (level 6),
`composer audit`, `npm run build`. Ayrıntı: `quickstart.md` "Bitmiş sayılma
kapıları".

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: bağımsız, hemen başlar. T001 → T034'ü kilitler (onay gelmeden `composer require` yok)
- **Foundational (Phase 2)**: T003 → US1'i kilitler; T004 → US3'ü kilitler
- **User Stories (Phase 3-6)**: birbirinden bağımsız; öncelik sırası US1 → US2 → US3 → US4
- **Polish (Phase 7)**: teslim edilecek tüm story'ler bittikten sonra

### User Story Dependencies

- **US1 (#4, P1)**: T003 dışında bağımsız
- **US2 (#1, P1)**: tümüyle bağımsız — CSS + JS
- **US3 (#2, P2)**: T004 (cihaz bulgusu) sonrası; US2 ile aynı dosyaya (`app.css`) dokunduğu için sıralı gitmeli
- **US4 (#3, P3)**: bağımsız; en büyük faz, tek başına PR olabilir

### Within Each User Story

- Testler önce yazılır ve kırmızı olduğu görülür
- Migration → model → factory → servis → controller/rota → istemci
- `docs/SPEC.md` ve `README` güncellemesi aynı PR'da

### Parallel Opportunities

- T005 ve T006 birlikte (farklı test dosyaları)
- T023-T026 birlikte (dört ayrı test dosyası)
- T027-T029 birlikte (üç ayrı migration)
- T030, T031, T033 birlikte (farklı model/factory dosyaları)
- T035, T036, T037 birlikte (farklı yapılandırma dosyaları)
- T053, T054, T056 birlikte (birbirinden bağımsız kapılar)
- **Paralel olamaz**: T014 ve T016 (ikisi de `app.css`), T019 (yine `app.css`), T008 ve T009 (aynı akışın iki ucu), T045 ve T046 (aynı komut ailesi, ardışık okunur)

---

## Parallel Example: User Story 4

```bash
# Testler önce, dördü birlikte:
Task: "tests/Feature/Push/PushSubscriptionTest.php"
Task: "tests/Feature/Push/ReviewNudgeTest.php"
Task: "tests/Unit/Push/PushDispatcherTest.php"
Task: "tests/Feature/Console/DispatchDailyPushWindowTest.php"

# Sonra şema, üçü birlikte:
Task: "create_push_subscriptions_table migration"
Task: "create_push_deliveries_table migration"
Task: "add_push_enabled_to_users_table migration"
```

---

## Implementation Strategy

### MVP First (User Story 1)

1. Phase 1 Setup → 2. Phase 2 Foundational → 3. Phase 3 (US1)
4. **DUR ve DOĞRULA**: quickstart bölüm 1'i gerçek telefonda yürüt
5. PR aç, merge et — #4 kapanır. Ürün bu tek değişiklikle daha doğru çalışır

### Incremental Delivery

1. US1 → #4 kapanır (ritüel bitebilir hale gelir)
2. US2 → #1 kapanır (kod bloğu okunur)
3. US3 → #2 kapanır (menü yerinde durur)
4. US4 → #3 kapanır (hatırlatma gelir)

Her adım kendi PR'ı olabilir; hiçbiri diğerinin merge edilmesini beklemez.
`main`'e doğrudan commit yok (Ana Yasa).

---

## Notes

- [P] = farklı dosya, bekleyen bağımlılık yok
- Her görev sonrası veya mantıksal grup sonrası commit; commit özeti ≤72 karakter, İngilizce, emir kipi
- LLM'in yazdığı her commit `Co-Authored-By: Claude <noreply@anthropic.com>` ile biter
- Test susturulmaz, üretim kodu testi geçmek için zayıflatılmaz
- İş 400 satırı geçecekse böl ve planı göster (Ana Yasa V)
