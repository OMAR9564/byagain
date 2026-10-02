# Implementation Plan: Kaynağa Göre Pratik, Sabit Kaynakla Ekleme ve LLM ile Tekrar

**Branch**: `003-source-practice` | **Date**: 2026-10-02 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/003-source-practice/spec.md`

## Summary

Üç küçük özellik, tek kural: kullanıcının seçtiği tek bir kaynakla çalışmak günlük
ritüele dokunmaz.

1. **Pratik (US1).** Kaynak sayfasından `GET /library/sources/{source}/practice`.
   Kaynağın aktif pasajlarından `review_size` kadar rastgele set çekilir, sayfaya
   gömülür, **hiçbir yere yazılmaz** (R-301). Ekran, tekrar ekranının kartını ve
   `review.js`'i olduğu gibi kullanır; her kart kendi eylem adresini zaten
   taşıdığı için betik farkı bilmez (R-302). Eylem rotası yalnız açık kararları
   uygular — çöpe at, favori, kaynak sıklığı — ve `shown_count` / `last_shown_at`'e
   dokunmaz (R-303).
2. **Formda kal (US2).** `highlights.store` artık `highlights.create?source={id}`'ye
   döner; `create()` bu parametreyle ön seçim yapar (R-306). Bu sırada bulunan
   gerçek hata da düzeltilir: doğrulama hatasından sonra kaynak seçimi bugün
   kayboluyor (`form.blade.php:41`, string/int katı karşılaştırma).
3. **Dışa aktarma (US3).** `GET …/export` ekranı: sayılar, gizlilik notu,
   salt-okunur textarea, "Copy" (`copy.js`, üç kademeli yedek) ve "Download"
   (`…/export/download`, Markdown dosyası). Metni `StudyExportBuilder` üretir
   (R-307, contracts/study-export.md). Uygulama hiçbir LLM'e bağlanmaz.

Teknik yaklaşım tek cümlede: şema yok, bağımlılık yok; iki yeni servis, iki yeni
controller, dört yeni rota, bir küçük JS modülü, `review.js`'te bir koruma.

## Technical Context

**Language/Version**: PHP 8.3+ (yerelde 8.5), Laravel 13.x; tarayıcıda vanilla ES modülleri

**Primary Dependencies**: Mevcut yığın (Laravel, Fortify, league/commonmark,
ezyang/htmlpurifier). **Yeni bağımlılık yok** (R-310)

**Storage**: MySQL 8. **Şema değişikliği yok** (data-model.md)

**Testing**: PHPUnit (`php artisan test`) — `tests/Feature/Practice/*`,
`tests/Feature/Library/*`, `tests/Unit/Practice/*`; zaman içeren testlerde
`Carbon::setTestNow()`. 375px gerçek cihazda kopyala/indir (SC-205)

**Target Platform**: Mobil tarayıcı öncelikli — iOS Safari, Chrome/Android

**Project Type**: Laravel monolit (Blade + Tailwind + proje içi vanilla JS)

**Performance Goals**: Pratik kart geçişi ağa gitmez (tekrar ekranıyla aynı);
pratik seti tek sorgu (`ORDER BY RAND() LIMIT n`, tek kaynak); dışa aktarma bir
kaynak için iki sorgu (pasajlar + kartlar, `with()`)

**Constraints**: Kullanıcı tarafı varlık bütçesi 150KB; Pint ve Larastan level 6
temiz; `tokens.css` değişmez (Ana Yasa V); yeni metin `lang/en/` altında

**Scale/Scope**: Kaynak başına yüzlerce pasaj. Dokunulan yüzey: 2 controller
(yeni), 1 controller (`HighlightController`), 2 servis (yeni), 1 Form Request
(yeni), 3 blade (yeni) + 3 blade (değişen) + 2 partial (çıkarılan), 1 JS (yeni),
`review.js` (koruma), `vite.config.js`, `routes/web.php`, `lang/en/practice.php`
(yeni), `docs/SPEC.md` §2

## Constitution Check

*GATE: Phase 0 öncesi geçmeli, Phase 1 sonrası yeniden bakılır.*

| İlke | Kapı | Durum |
|---|---|---|
| I. Ürün değeri dokunulmaz | Günlük ritüel bozuluyor mu? 375px? | **Geçer.** Pratik ve dışa aktarma ritüelin hiçbir kaydına yazmaz (data-model.md "Yazılmayan alanlar"); testler önce/sonra karşılaştırır. Yeni ekranlar 375px'te cihazda doğrulanır (quickstart). |
| II. Yığın sabittir | Yeni çatı/kütüphane? | **Geçer.** Yeni paket yok. Kullanıcı sayfasına Filament/Livewire/Alpine girmez; `copy.js` vanilla. |
| III. Güvenlik ve veri bütünlüğü | `.env`, silme, migration, scope, `{!! !!}`, `$fillable`, ham SQL, Form Request | **Geçer.** Silme yok (çöpe atma bayraktır). Migration yok. Bütün sorgular scope'tan geçer; `practice.action` `scopeBindings` ile pasajı kaynağa bağlar (R-304). `content_html` dışında `{!! !!}` yok; dışa aktarma metni textarea içinde `{{ }}` ile kaçışlı basılır. Yeni `PracticeActionRequest`. `inRandomOrder()` query builder'dır, ham SQL değil. |
| IV. Katman disiplini ve test | Mantık `app/Services/`'te mi, testi var mı? | **Geçer.** `PracticeSampler`, `PracticeActions`, `StudyExportBuilder` — her biri birim testli; her akış feature testli; IDOR iki parametreli rota için açık test. Liste sorgularında `with()`. |
| V. Sabitler ve "dur, sor" | Şema? Bağımlılık? Algoritma sabiti? Sessiz ürün kararı? 400 satır? | **Geçer, iki not:** (a) Algoritma sabiti değişmez; pratik boyutu mevcut `review_size`. Throttle sayıları mevcut rotalarla aynı düzeyde. (b) Toplam iş 400 satırı geçecek (görünümler + testler); bu yüzden üç user story ayrı commit/PR dilimi olarak planlanır (aşağıda "Teslim dilimleri"). Sessiz ürün kararları spec'te karara bağlandı (2026-10-02). |

**Ek not — `tokens.css`**: Bu özellik hiçbir token değiştirmez veya eklemez;
yeni ekranlar mevcut token'ları kullanır. (PR #6'daki `--color-ink-subtle`
değişikliği bu planın dışında, depo sahibinin onayını bekliyor.)

**Post-design re-check (Phase 1 sonrası)**: Değişiklik yok. Tasarım şema ve
bağımlılık gerektirmedi; `review.js` değişikliği bir koruma satırı ve davranışı
günlük tekrarda aynı bırakır (mevcut testler bunu doğrular).

## Project Structure

### Documentation (this feature)

```text
specs/003-source-practice/
├── plan.md              # This file
├── research.md          # Phase 0 — R-301…R-310
├── data-model.md        # Phase 1 — şema yok; okunan/yazılan/yazılmayan alanlar
├── quickstart.md        # Phase 1 — doğrulama adımları
├── contracts/
│   ├── routes.md        # Yeni ve değişen rotalar
│   └── study-export.md  # LLM metninin biçimi
├── checklists/
│   └── requirements.md
└── tasks.md             # /speckit-tasks çıktısı (henüz yok)
```

### Source Code (repository root)

```text
app/
├── Http/
│   ├── Controllers/
│   │   ├── PracticeController.php          # yeni: show, action
│   │   ├── StudyExportController.php       # yeni: show, download
│   │   └── HighlightController.php         # değişir: create(?source), store yönlendirmesi
│   └── Requests/
│       └── PracticeActionRequest.php       # yeni
└── Services/
    └── Practice/                           # yeni
        ├── PracticeSampler.php
        ├── PracticeActions.php
        └── StudyExportBuilder.php

resources/
├── js/
│   ├── review.js                           # değişir: tamamlama yalnız data-complete-url varsa
│   └── copy.js                             # yeni
└── views/
    ├── practice/
    │   └── show.blade.php                  # yeni
    ├── library/sources/
    │   ├── show.blade.php                  # değişir: Practice / Add passage / Study with an AI
    │   └── export.blade.php                # yeni
    ├── review/
    │   ├── show.blade.php                  # değişir: partial'ları kullanır
    │   └── partials/
    │       ├── highlight-card.blade.php    # yeni (show.blade.php'den çıkarılır)
    │       └── undo-bar.blade.php          # yeni (show.blade.php'den çıkarılır)
    ├── editor/create.blade.php             # değişir: kaydedilen kaynağa bağlantı
    └── components/editor/form.blade.php    # değişir: ön seçim + int karşılaştırma

lang/en/practice.php                        # yeni
routes/web.php                              # 4 rota
vite.config.js                              # copy.js girdisi
docs/SPEC.md                                # §2 rota tablosu, yeni §10 Practice and export

tests/
├── Feature/
│   ├── Practice/
│   │   ├── PracticeSessionTest.php         # set, boyut, kapsam, boş kaynak, iz bırakmama
│   │   ├── PracticeActionTest.php          # çöpe at/favori/sıklık, keep no-op, iz yok, 404'ler
│   │   └── StudyExportTest.php             # ekran, indirme başlıkları, içerik, iz yok
│   ├── Library/
│   │   └── StickySourceTest.php            # store→create, ?source, geçersiz kaynak, doğrulama hatası
│   └── Security/IdorTest.php               # değişir: practice.action için açık test
└── Unit/
    └── Practice/
        ├── PracticeSamplerTest.php
        ├── PracticeActionsTest.php
        └── StudyExportBuilderTest.php
```

**Structure Decision**: Mevcut Laravel monolit düzeni. Yeni iş mantığı
`app/Services/Practice/` altında tek bir ailede; controller'lar ince.

## Teslim dilimleri

Ana Yasa V (400 satır) gereği iş üç dilimde teslim edilir; her dilim kendi
testleriyle yeşil ve tek başına kullanılabilir:

| Dilim | User story | Kabaca boyut | Bağımlılık |
|---|---|---|---|
| 1 | US2 — formda kal + `form.blade.php` hatası | ~120 satır | yok |
| 2 | US1 — pratik (partial çıkarma dahil) | ~450 satır | yok |
| 3 | US3 — dışa aktarma | ~300 satır | yok |

Sıra: en küçük ve en az riskli olan US2 önce (hata düzeltmesi de içinde), sonra
P1 olan US1, sonra US3. Dilim 2'deki partial çıkarma tekrar ekranına dokunduğu
için ayrı bir commit olarak, davranış değiştirmeden önce yapılır; mevcut
`tests/Feature/Review/*` yeşil kalmalıdır.

## Complexity Tracking

Ana Yasa ihlali yok; tablo boş.
