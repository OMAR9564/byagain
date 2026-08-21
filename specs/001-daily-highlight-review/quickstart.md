# Quickstart — Kurulum ve Doğrulama

**Feature**: 001-daily-highlight-review

Bu belge çalıştırma ve doğrulama rehberidir; uygulama kodu içermez. Kurulum adımları
SC-019 uyarınca README ile birebir aynı olmalıdır.

## Ön koşullar

| Gereksinim | Sürüm |
|---|---|
| PHP | 8.3+ (`bcmath`, `intl`, `mbstring`, `pdo_mysql`) |
| Composer | 2.x |
| Node | 20+ |
| MySQL | 8.0+ |

## Kurulum

```bash
composer install
npm install
cp .env.example .env          # gerçek değerleri insan girer (Ana Yasa m. III)
php artisan key:generate
php artisan migrate
php artisan db:seed --class=DemoSeeder   # yalnızca yerel geliştirme
npm run build
```

Çalıştırma (üç süreç):

```bash
php artisan serve
php artisan queue:work
php artisan schedule:work
```

İlk yönetici:

```bash
php artisan byagain:promote-admin you@example.com
```

## Kalite kapıları (Ana Yasa "Bitmiş sayılma ölçütü")

```bash
vendor/bin/pint --test
vendor/bin/phpstan analyse
php artisan test
composer audit
```

---

## Doğrulama senaryoları

Her senaryo bir kullanıcı hikâyesine karşılık gelir ve tek başına çalıştırılabilir.

### V1 — İçerik gir ve ilk tekrarını yap (US1)

```bash
php artisan test --filter=FirstReviewFlowTest
```

1. Temiz hesapla kaydol → ana ekranda "ilk kaynağını ekle" yönlendirmesi (boş liste değil).
2. Bir kaynak + 3 pasaj gir.
3. `/review` aç → 3 kartlık tekrar üretilir.
4. Üç kartı da işle → tamamlanma ekranı, seri = 1.

Elle kontrol (375px viewport): başlık + liste + kod bloğu + tablo içeren 3.000 karakterlik
pasaj kart sınırında kalır, sayfa yatay kaymaz (SC-008). `<script>alert(1)</script>` metni
birebir görünür, çalışmaz (SC-012).

### V2 — Örnekleme doğruluğu (US2)

```bash
php artisan test --filter=SamplingTest
php artisan test --filter=ThirtyDaySimulationTest
```

- 30 günlük simülasyonda hiçbir pasaj 3 gün içinde iki kez çıkmaz (SC-009, FR-029).
- Hiçbir tekrarda tek kaynağın payı `ceil(n/3)`'ü geçmez (SC-009, FR-033).
- `frequency = never` olan kaynaktan hiçbir pasaj gelmez (SC-010, %100).
- `equal_source_weighting` açıkken kaynağın pasaj sayısı seçilme şansını artırmaz (FR-032).
- Tekrar üretildikten sonra tercih değişimi bugünü etkilemez (FR-026).

Performans (SC-004):

```bash
php artisan test --filter=SamplingPerformanceTest
```

20.000 pasajlı hesapta seçki üretimi <300ms.

### V3 — Mastery zamanlaması (US3)

```bash
php artisan test --filter=MasterySchedulerTest
```

- Yeni kart: erken → 7 gün, sonra → 14 gün, ara → 28 gün (FR-047).
- Yerleşik kart: ×0.5 / ×2.0 / ×3.0, `clamp(1, 365)` (FR-048, FR-049).
- `learned` → `retired`, kayıt silinmez (FR-050).
- Vadesi gelmiş kartlar normal pasajlardan **sonra** sıralanır, oranı aşmaz (FR-035, FR-036).
- 6. "daha erken" sonrası sadeleştirme ipucu görünür; sistem kartı değiştirmez (FR-052).

### V4 — E-posta ve zamanlama (US4)

```bash
php artisan test --filter=DailyPipelineTest
php artisan test --filter=ReminderDedupeTest
```

Elle:

```bash
php artisan byagain:dispatch-daily --dry-run --now="2026-08-22 12:00:00"
```

- `America/New_York` kullanıcısı, 08:00 tercihi → doğru pencerede sıraya alınır (SC-013).
- Komutu aynı gün 10 kez çalıştır → kullanıcı başına en fazla 1 daily, 1 reminder (SC-014).
- Gönderimden önce tekrar tamamlanırsa hatırlatma `skipped` yazılır, gönderilmez (FR-062).
- Doğrulanmamış e-postaya ritüel maili gitmez (FR-007).
- E-postadaki kartlar uygulamadakiyle birebir aynı (FR-061).
- İmzalı "kapat" bağlantısı girişsiz çalışır; imza bozulursa 403 (FR-065).

Yerel posta kutusu: `MAIL_MAILER=log` veya Mailpit.

### V5 — Seri (US5)

```bash
php artisan test --filter=StreakTest
```

- Yerel 01:30'da tamamlanan tekrar önceki güne yazılır (FR-055).
- Bir gün atlandığında seri 1'den başlar, `longest_streak` korunur (FR-057).
- Zaman dilimi değişimi geçmiş seri günlerini değiştirmez.

### V6 — Yönetim ve izolasyon (US6)

```bash
php artisan test --filter=AdminAccessTest
php artisan test --filter=IdorTest
```

- `user` rolü `/admin` → 403 (FR-069).
- Panelde pasajın yalnızca 120 karakterlik önizlemesi (FR-071).
- Zamanlayıcı iki turdur çalışmamışsa panelde uyarı (FR-074, SC-017).
- Her admin işlemi değiştirilemez şekilde kaydedilir (FR-076).
- Başka kullanıcının kaynak/pasaj/kart/tekrar kimliğiyle her rota → 404 (SC-011).

Varlık izolasyonu (SC-018): kullanıcı tarafı bir sayfada ağ sekmesinde Filament/Livewire/
Alpine isteği **0** olmalı.

### V7 — Mobil, PWA ve çevrimdışı

Elle, gerçek cihaz veya 375px emülasyon:

- Uçak modunda uygulama açılır ve çevrimdışı olduğunu söyler; editördeki taslak kaybolmaz
  (SC-016, FR-087, FR-021).
- Ana ekrana eklenebilir (FR-086).
- Kart aksiyonu <100ms görsel karşılık verir (SC-005).
- Lighthouse mobil: performans ≥90, erişilebilirlik ≥95 (SC-007).
- İlk yükleme ≤150KB, yazı tipleri hariç (SC-006).

---

## Referanslar

- Şema ve alanlar: [data-model.md](./data-model.md)
- Rotalar ve JSON uçları: [contracts/routes.md](./contracts/routes.md),
  [contracts/review-actions.md](./contracts/review-actions.md)
- Komutlar ve işler: [contracts/console-and-jobs.md](./contracts/console-and-jobs.md)
- E-posta: [contracts/mail.md](./contracts/mail.md)
- Kararlar ve gerekçeler: [research.md](./research.md)
