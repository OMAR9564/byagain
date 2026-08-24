# Implementation Plan: Tekrar Akışı Düzeltmeleri ve Tarayıcı Hatırlatması

**Branch**: `002-review-flow-fixes` | **Date**: 2026-08-24 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/002-review-flow-fixes/spec.md`

## Summary

Dört issue, tek teslimat. Üçü mevcut kodda somut kök nedene oturuyor, biri yeni
bir kanal:

1. **#4 — tamamlanan gün kendini yeniden açıyor.** `ReviewController::show()`
   tamamlanmış turu görünce `daily_review_limit` dolmadıysa kendiliğinden
   `buildNextRound()` çağırıyor (`app/Http/Controllers/ReviewController.php:47-53`).
   Otomatik tur kaldırılır; yeni tur yalnızca `POST /review/again` ile açılır.
   Tamamlanma ekranının "daha fazlası var mı" bilgisi bugün turun kendiliğinden
   açılmasından okunuyordu; artık açıkça hesaplanır.
2. **#1 — kod bloğu kaydırılamıyor, kart kayıyor.** `[data-swipe-surface]`
   üzerindeki `touch-action: pan-y` (`resources/css/app.css:58-60`) tüm alt
   ağaçta yatay panlamayı kapatıyor; `pre`/`table` kendi `overflow-x: auto`
   kutusunu kaydıramıyor, hareketi `review.js` swipe olarak yiyor. CSS'te
   kaydırılabilir kutulara yatay pan geri verilir, `review.js` dokunuşu
   kaydırılabilir bir ata içinde başladıysa jesti hiç başlatmaz.
3. **#2 — alt menü kaydırırken yerinde durmuyor.** `x-bottom-nav` `position: fixed`
   ama sayfa `html.h-full` / `body.min-h-full` yükseklik zinciri ve
   `env(safe-area-inset-bottom)` ile birlikte mobil tarayıcıda kayıyor. Önce
   cihazda tekrar üretilir, sonra R-202'deki sıralı düzeltme uygulanır.
4. **#3 — tarayıcı hatırlatması.** Günlük e-postadan 60 dakika sonra tekrar hâlâ
   bitmemişse tek bir web push gider. Mevcut `MailDispatcher` deseni birebir
   taklit edilir: `UNIQUE (user_id, dedupe_key)` üzerine `insertOrIgnore`, iş
   kuyruğa girer, işçi göndermeden önce dünyayı yeniden okur.

Teknik yaklaşım tek cümlede: hiçbir yeni istemci çatısı yok, sunucuda tek yeni
bağımlılık (`minishlink/web-push`), iki yeni tablo, mevcut `sw.js` ve mevcut
beş dakikalık süpürme yeniden kullanılır.

## Technical Context

**Language/Version**: PHP 8.3+ (Laravel 13.x), tarayıcı tarafında vanilla ES modülleri

**Primary Dependencies**: Laravel 13, Fortify, Filament v5 (yalnızca `/admin`),
league/commonmark, ezyang/htmlpurifier, resend/resend-laravel. **Yeni**:
`minishlink/web-push` (VAPID + payload şifreleme) — lisans kurulumdan önce
doğrulanacak (R-203)

**Storage**: MySQL 8. Yeni tablolar: `push_subscriptions`, `push_deliveries`.
`users` tablosuna tek boolean sütun (`push_enabled`)

**Testing**: PHPUnit (`php artisan test`), `tests/Feature/*`, `tests/Unit/*`;
zaman içeren her testte `Carbon::setTestNow()`. Ek olarak gerçek cihazda 375px
elle doğrulama (Ana Yasa I)

**Target Platform**: Mobil tarayıcı öncelikli — iOS Safari 16.4+ (ana ekrana
eklenmiş PWA), Chrome/Android. Sunucu tarafı: PHP-FPM + `database` kuyruk sürücüsü

**Project Type**: Laravel monolit (Blade + Tailwind + proje içi vanilla JS)

**Performance Goals**: Tekrar ekranı kart geçişi ağa gitmez (mevcut davranış
korunur); push gönderimi kuyruktadır, istek yolunda değildir; bildirim hedefi
e-postadan sonra 60 dakika ±5 dakika (mevcut süpürme penceresi)

**Constraints**: Kullanıcı tarafı varlık bütçesi 150KB; `.env` dosyasına
dokunulmaz (yalnızca `.env.example`); çalışmış migration düzenlenmez; Larastan
level 6 temiz; Pint temiz

**Scale/Scope**: Tek haneli kullanıcı sayısı, cihaz başına bir abonelik, günde
kullanıcı başına en fazla bir bildirim. Dokunulan yüzey: 1 controller, 1 servis
ailesi (yeni `app/Services/Push/`), 2 migration, 1 config bloğu, 2 blade, 2 JS
dosyası, `public/sw.js`

## Constitution Check

*GATE: Phase 0 öncesi geçmeli, Phase 1 sonrası yeniden bakılır.*

| İlke | Kapı | Durum |
|---|---|---|
| I. Ürün değeri dokunulmaz | Değişiklik günlük ritüeli bozuyor mu? 375px'te doğrulandı mı? | **Geçer.** Dördü de doğrudan ritüeli onarıyor. Cihaz doğrulaması `quickstart.md` içinde zorunlu adım. |
| II. Yığın sabittir | Yeni çatı/kütüphane kullanıcı sayfasına giriyor mu? | **Geçer.** İstemcide yeni bağımlılık yok; push JS'i mevcut vanilla desenle ve mevcut `sw.js` içinde. Sunucuda tek yeni composer paketi — spec'te onaylı, lisans doğrulaması R-203'te. |
| III. Güvenlik ve veri bütünlüğü | `.env`, veri silme, çalışmış migration, `BelongsToUser`, `{!! !!}`, `$fillable`, ham SQL, Form Request | **Geçer, iki not:** (a) `.env` yalnızca `.env.example` üzerinden büyür, gerçek VAPID değerlerini insan girer; (b) geçersiz abonelik kaydı **silinir** — bu kullanıcı içeriği değil, cihazın geri çektiği bir yetkidir; kullanıcı verisi (highlight) hiçbir yerde silinmez. |
| IV. Katman disiplini ve test | İş mantığı `app/Services/` altında mı, testi var mı, zaman `LocalDayResolver` ile mi? | **Geçer.** Yeni mantık `app/Services/Push/` altında; controller ince. Zaman kararları `LocalDayResolver` ve `Carbon::setTestNow()` ile test edilir. |
| V. Sabitler ürün kararıdır | Sihirli sayı var mı, token değişiyor mu, danışılması gereken karar var mı? | **Geçer.** 60 dakika ve günlük bildirim tavanı `config/byagain.php` içinde adlandırılır. `tokens.css` değişmez — alt menü düzeltmesi düzen sorunudur, token sorunu değil. Şema + bağımlılık kararı 2026-08-24'te alınmıştır. |

**Kapı sonucu**: geçti. Gerekçelendirilmesi gereken tek sapma yeni bağımlılık ve
iki yeni tablodur; ikisi de spec'te kayıtlı onaya dayanır (Complexity Tracking).

### Phase 1 sonrası yeniden değerlendirme

Tasarım artefaktları (`data-model.md`, `contracts/`, `quickstart.md`) yazıldıktan
sonra kapılara yeniden bakıldı; yeni ihlal çıkmadı. Tasarımın doğrudan Ana
Yasa'ya bağlı üç noktası:

- **Kapsam ve IDOR** — `push_subscriptions` ve `push_deliveries` `BelongsToUser`
  kullanır; abonelik uçları başkasının kaydında 404 döner. Aynı `endpoint`'in
  başka bir hesaba devri bilinçli bir karardır ve `contracts/push.md` içinde
  gerekçesiyle yazılıdır: tek tarayıcı tek uç verir, iki satır tutmak bildirimi
  yanlış hesaba göndermek olurdu.
- **Sihirli sayı yok** — 60 dakika ve günlük tavan `config/byagain.php` içinde;
  `sw.js` içindeki tek sabit sürüm numarasıdır.
- **Kullanıcı metni JS'te değil** — bildirim başlığı ve gövdesi sunucuda
  `lang/en/` içinden çözülüp payload ile gönderilir; `sw.js` metin taşımaz.

İş büyüklüğü: dört issue tek dalda ilerler ama teslim sırası bağımsızdır
(#4 → #1 → #2 → #3). #3 tek başına 400 satırı geçerse Ana Yasa V uyarınca
bölünüp ayrı PR olur.

## Project Structure

### Documentation (this feature)

```text
specs/002-review-flow-fixes/
├── plan.md              # Bu dosya
├── research.md          # Phase 0 çıktısı
├── data-model.md        # Phase 1 çıktısı
├── quickstart.md        # Phase 1 çıktısı
├── contracts/           # Phase 1 çıktısı
│   ├── review-completion.md
│   ├── push.md
│   └── console-and-jobs.md
├── checklists/
│   └── requirements.md
└── tasks.md             # /speckit-tasks üretir — bu komut üretmez
```

### Source Code (repository root)

```text
app/
├── Console/Commands/
│   ├── DispatchDailyPipeline.php      # değişir: bildirim penceresi eklenir
│   └── GenerateVapidKeys.php          # yeni: anahtar üretir, insan .env'e yazar
├── Http/
│   ├── Controllers/
│   │   ├── ReviewController.php       # değişir: otomatik tur kalkar
│   │   ├── PushSubscriptionController.php   # yeni: store / destroy
│   │   └── SettingsController.php     # değişir: push_enabled
│   └── Requests/
│       ├── StorePushSubscriptionRequest.php # yeni
│       └── UpdateSettingsRequest.php  # değişir
├── Jobs/
│   └── SendReviewNudgePush.php        # yeni
├── Models/
│   ├── PushSubscription.php           # yeni
│   ├── PushDelivery.php               # yeni
│   └── User.php                       # değişir: ilişkiler + push_enabled
└── Services/
    ├── Push/
    │   ├── PushDispatcher.php         # yeni: dedupe + kuyruğa alma
    │   └── WebPushSender.php          # yeni: kütüphaneyi saran tek yer
    └── Review/ReviewBuilder.php       # değişir: hasMaterialFor()

database/migrations/
├── 2026_08_24_000100_create_push_subscriptions_table.php   # yeni
├── 2026_08_24_000200_create_push_deliveries_table.php      # yeni
└── 2026_08_24_000300_add_push_enabled_to_users_table.php   # yeni

config/byagain.php                     # değişir: push bloğu
config/services.php                    # değişir: vapid anahtarları

resources/
├── css/app.css                        # değişir: touch-action + alt menü
├── js/
│   ├── review.js                      # değişir: kaydırılabilir kutu koruması
│   └── push.js                        # yeni: izin + abonelik akışı
└── views/
    ├── components/layouts/app.blade.php   # değişir (R-202 sonucuna göre)
    ├── components/bottom-nav.blade.php    # değişir (R-202 sonucuna göre)
    ├── review/done.blade.php              # değişir: yeni durum matrisi
    └── settings/edit.blade.php            # değişir: bildirim anahtarı

lang/en/                               # değişir: bildirim ve tamamlanma metinleri
public/sw.js                           # değişir: push + notificationclick

tests/
├── Feature/Review/                    # tamamlanma davranışı
├── Feature/Push/                      # abonelik uçları, gönderim, dedupe
├── Feature/Console/                   # süpürme penceresi
└── Unit/Push/                         # dispatcher kararları
```

**Structure Decision**: Mevcut Laravel monolit düzeni korunur. Yeni hiçbir üst
düzey dizin açılmaz; push mantığı `app/Services/Push/` altında kendi ailesinde
toplanır, çünkü Ana Yasa IV controller'da iş mantığını yasaklıyor ve mevcut
`app/Services/Mail/` ailesi zaten kopyalanacak deseni veriyor.

## Complexity Tracking

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| Yeni composer bağımlılığı (`minishlink/web-push`) | VAPID imzası ve payload şifrelemesi (ECDH + AES-GCM) elle yazılacak bir şey değil; hatası sessiz teslim edilmeme olur | Elle uygulama reddedildi: kriptografi kendi yazılmaz. Üçüncü taraf bildirim servisi reddedildi: pasaj başlığı bile olsa kullanıcı verisi dışarı çıkmamalı |
| İki yeni tablo (`push_subscriptions`, `push_deliveries`) | Abonelik cihaz başına saklanmalı; gönderim tekilleştirmesi veritabanı garantisi olmalı (mevcut `email_deliveries` deseni) | `email_deliveries`'e kanal sütunu eklemek reddedildi: `recipient` sütunu e-posta adresidir, çalışmış migration düzenlenemez ve iki kanalın durum sözlüğü aynı değil |
| `users` tablosuna sütun | Bildirim varsayılan kapalı; tercih kullanıcıya ait | `settings` tablosunda saklamak reddedildi: orada kurulum düzeyi ayarlar var, kullanıcı tercihleri `users`'ta |
