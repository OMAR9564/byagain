# Implementation Plan: byagain — Günlük Pasaj Tekrarı (MVP)

**Branch**: `001-daily-highlight-review` | **Date**: 2026-08-22 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/001-daily-highlight-review/spec.md`

## Summary

byagain, kullanıcının kaydettiği pasajları her yerel gün için üretilen kalıcı bir seçki
(Review) hâlinde telefonda karşısına çıkarır. Çekirdek iki değer: (1) girilen metnin
telefonda kusursuz görünmesi, (2) doğru pasajın doğru gün gelmesi.

Teknik yaklaşım: Laravel 13 monolit. Sunucu tarafı Blade + Tailwind, tekrar ekranı vanilla
JS ile optimistic ve yerel; kart aksiyonları küçük JSON uçlarına arka planda yazılır.
Örnekleme (FR-027…FR-033) tek bir ağırlıklı SQL sorgusu + servis katmanında kaynak kotası
ile çalışır. Mastery zamanlaması yarı-ömür tabanlı (`p = 2^(-Δt/H)`), doğru/yanlış yok.
Günlük üretim ve e-posta sıraya alma 5 dakikada bir çalışan tek bir zamanlanmış komutla,
`(user_id, review_date)` ve `(user_id, dedupe_key)` benzersiz indeksleriyle idempotent.
Admin yalnızca `/admin` altında Filament v5.

Repo şu anda boştur (yalnızca `.specify`, `.claude`, `specs`). Bu plan uygulama iskeletinin
kurulmasını da kapsar.

## Technical Context

**Language/Version**: PHP 8.3+ (Ana Yasa m. II)

**Primary Dependencies**: Laravel 13.x, Laravel Fortify (başsız auth), Filament v5
(`/admin` altında, Livewire+Alpine yalnızca burada), Tailwind CSS, `league/commonmark`,
`ezyang/htmlpurifier`, Pint, Larastan (level 6), Pest veya PHPUnit (Laravel varsayılanı)

**Storage**: MySQL 8. Kuyruk `database` sürücüsü. Session/cache `database`.

**Testing**: `php artisan test` (feature + unit). Zaman içeren her testte
`Carbon::setTestNow()`. `Model::preventLazyLoading()` geliştirmede açık.

**Target Platform**: Modern mobil tarayıcı (iOS Safari / Chrome Android), 375px dikey
öncelikli; masaüstü aynı düzenin genişlemiş hâli. PWA (ana ekrana eklenebilir, çevrimdışı
açılış).

**Project Type**: Web application — tek Laravel monolit (ayrı frontend/backend yok).

**Performance Goals**: Seçki üretimi 20.000 pasajlık hesapta <300ms (SC-004). Kart
aksiyonu görsel karşılığı <100ms (SC-005). İlk anlamlı içerik 4G'de <2s, kullanıcı tarafı
ilk yükleme ≤150KB (yazı tipleri hariç) (SC-006). Lighthouse mobil: perf ≥90, a11y ≥95.

**Constraints**: Yatay kayma yok (kod bloğu/tablo kendi kabında). Kullanıcıya bakan hiçbir
sayfa Livewire/Alpine/Filament varlığı yüklemez (SC-018). Tüm zamanlar UTC saklanır;
kullanıcının "bugün"ü `users.timezone` + 04:00 kaydırmasıyla hesaplanır. Kullanıcı verisi
silinmez (`is_discarded`).

**Scale/Scope**: ~1.000 kullanıcı, kullanıcı başına ~20.000 pasaj. 6 kullanıcı hikâyesi,
93 fonksiyonel gereksinim, ~12 tablo, ~10 kullanıcı ekranı + Filament paneli.

## Constitution Check

*GATE: Phase 0 öncesi geçilmeli. Phase 1 sonrası yeniden değerlendirildi.*

| # | Kapı | Durum | Not |
|---|---|---|---|
| I | Ürün değeri: mobil-öncelik, iki dakikalık ritüel | ✅ PASS | 375px ve dokunma hedefi kabul ölçütü; tekrar ekranı optimistic |
| II | Yığın sabit | ✅ PASS | Plan tablodaki yığının dışına çıkmıyor; yeni bağımlılık önerisi yok (bkz. research.md R-11: e-posta sağlayıcısı insana sorulacak) |
| II | Filament/Livewire/Alpine yalnızca `/admin` | ✅ PASS | Ayrı Vite giriş noktası; kullanıcı layout'u Filament varlığı yüklemez |
| III | `.env`'e dokunulmaz | ✅ PASS | Yalnızca `.env.example` + README tablosu güncellenir |
| III | Veri silinmez | ✅ PASS | `is_discarded`, `is_archived`, `retired` durumları; `SoftDeletes` yalnızca hesap silmede sert silme (FR-008, kullanıcının kendi işlemi) |
| III | `BelongsToUser` global scope | ✅ PASS | `Source`, `Highlight`, `MasteryCard`, `Review`, `StreakDay` trait ile; `withoutGlobalScope` yalnızca `app/Filament/` |
| III | `{!! !!}` tek istisna | ✅ PASS | Yalnızca `content_html`, zorunlu `{{-- purified: MarkdownRenderer --}}` yorumuyla |
| III | Form Request doğrulama | ✅ PASS | `app/Http/Requests/` altında, `authorize()` gerçek kontrol |
| IV | İş mantığı `app/Services/`, testli | ✅ PASS | `ReviewBuilder`, `HighlightSampler`, `MasteryScheduler`, `StreakService`, `MarkdownRenderer`, `LocalDayResolver`, `MailDispatcher` |
| IV | Zaman UTC + 04:00 sınırı | ✅ PASS | Tek kaynak: `LocalDayResolver` |
| V | Sabitler `config/byagain.php` | ✅ PASS | Tüm sabitler adlandırılmış; sihirli sayı yok |
| V | Dur ve sor durumları | ⚠️ AÇIK | 4 kalem insan onayı bekliyor — aşağıdaki tabloya bakınız |

### İnsan onayı bekleyen kalemler (Ana Yasa m. V)

| Kalem | Neden LLM tek başına karar veremez | Nerede |
|---|---|---|
| Kaynak sıklık basamaklarının sayısal ağırlıkları | Algoritma sabiti (m. V) | research.md R-02 |
| Yenilik çarpanının uygulanma penceresi | Algoritma sabiti (m. V) | research.md R-03 |
| E-posta sağlayıcısı seçimi | Yeni bağımlılık + lisans/gizlilik (m. V) | research.md R-11 |
| Dil kararı (İngilizce arayüz + rotalar) ile Ana Yasa'nın `lang/tr/` metni çelişiyor | Ana Yasa ↔ SPEC çelişkisi (m. V, Governance) | spec.md "Uygulama Öncesi Bekleyen Belge Güncellemeleri" |

**Kapı hükmü**: Bu dördü çözülmeden kod yazımı başlamaz. Şema, ekran ve rota tasarımı
(bu plan) bunlardan bağımsız olarak ilerleyebilir; ağırlık değerleri `config/byagain.php`
içinde adlandırılmış sabit olduğundan sayısal karar kodu değil yalnızca config'i etkiler.

## Project Structure

### Documentation (this feature)

```text
specs/001-daily-highlight-review/
├── plan.md              # Bu dosya
├── spec.md              # Özellik spesifikasyonu
├── research.md          # Phase 0 çıktısı
├── data-model.md        # Phase 1 çıktısı
├── quickstart.md        # Phase 1 çıktısı
├── contracts/           # Phase 1 çıktısı
│   ├── routes.md
│   ├── review-actions.md
│   ├── console-and-jobs.md
│   └── mail.md
├── checklists/
└── tasks.md             # /speckit-tasks çıktısı — bu komut üretmez
```

### Source Code (repository root)

```text
app/
├── Console/Commands/
│   ├── DispatchDailyPipeline.php     # 5 dk'da bir; üretim + e-posta sıraya alma
│   └── PruneEphemeralRecords.php     # FR-091 temizlik
├── Filament/                         # yalnızca /admin (withoutGlobalScope izinli tek yer)
│   ├── Resources/
│   ├── Pages/
│   └── Widgets/
├── Http/
│   ├── Controllers/
│   │   ├── Auth/                     # Fortify view'ları
│   │   ├── SourceController.php
│   │   ├── HighlightController.php
│   │   ├── ReviewController.php
│   │   ├── ReviewItemActionController.php   # JSON, optimistic
│   │   ├── MasteryCardController.php
│   │   ├── StreakController.php
│   │   ├── SettingsController.php
│   │   └── UnsubscribeController.php        # imzalı, giriş gerektirmez
│   ├── Middleware/
│   └── Requests/
├── Jobs/
│   ├── SendDailyReviewEmail.php
│   └── SendEveningReminderEmail.php
├── Mail/
│   ├── DailyReviewMail.php
│   └── EveningReminderMail.php
├── Models/
│   ├── Concerns/BelongsToUser.php
│   ├── User.php  Source.php  Highlight.php  MasteryCard.php
│   ├── Review.php  ReviewItem.php  StreakDay.php
│   ├── EmailDelivery.php  AdminActionLog.php
├── Policies/
├── Services/
│   ├── Content/MarkdownRenderer.php        # commonmark + HTMLPurifier
│   ├── Content/PastedTextCleaner.php       # FR-022
│   ├── Review/ReviewBuilder.php            # FR-025, FR-033..FR-037
│   ├── Review/HighlightSampler.php         # FR-027..FR-032
│   ├── Review/ReviewItemActions.php        # FR-038, FR-039
│   ├── Mastery/MasteryScheduler.php        # FR-046..FR-052
│   ├── Streak/StreakService.php            # FR-054..FR-058
│   ├── Time/LocalDayResolver.php           # 04:00 gün sınırı
│   └── Mail/MailDispatcher.php             # FR-062..FR-068
config/
└── byagain.php                             # tüm algoritma sabitleri
database/
├── factories/  migrations/  seeders/
lang/en/                                    # arayüz metinleri (FR-088)
resources/
├── css/  tokens.css  app.css  admin.css
├── js/   app.js  review.js  editor.js      # vanilla, kullanıcı tarafı
└── views/
    ├── layouts/  components/
    ├── auth/  library/  editor/  review/  mastery/  streak/  settings/
    └── mail/
routes/
├── web.php  console.php
public/
├── manifest.json  sw.js                    # PWA (FR-086, FR-087)
tests/
├── Feature/  Unit/
```

**Structure Decision**: Tek Laravel monolit (yukarıdaki ağaç). Ayrı frontend/backend
gerekmez: kullanıcı arayüzü sunucu tarafında Blade ile üretilir, yalnızca tekrar ekranı ve
editör küçük vanilla JS modülleriyle zenginleştirilir. Admin aynı uygulamada ama ayrı
Vite giriş noktası ve ayrı layout ile `/admin` altında izole edilir — kullanıcı tarafı
sayfalar admin varlıklarını hiç yüklemez (SC-018).

## Complexity Tracking

Ana Yasa ihlali yok; bu bölüm boş bırakılmıştır. Açık kalan dört kalem ihlal değil,
"Dur ve Sor" kapsamındaki insan kararlarıdır (yukarıdaki tabloya bakınız).
