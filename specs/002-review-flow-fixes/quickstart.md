# Quickstart — Doğrulama Rehberi

**Feature**: `002-review-flow-fixes` · **Dal**: `002-review-flow-fixes`

Bu belge "çalışıyor mu" sorusunun cevabıdır. Uygulama kodu içermez; her bölüm
bir issue'yu baştan sona doğrular.

## Önkoşullar

- PHP 8.3+, MySQL 8, Node 20+, çalışan bir kuyruk işçisi
- `php artisan migrate` uygulanmış bir geliştirme veritabanı
- `npm run dev` (veya `npm run build`) ile derlenmiş varlıklar
- Bildirim bölümü için: HTTPS (veya `localhost`) ve gerçek bir tarayıcı
- Ana Yasa I: **375px genişlikte gerçek bir telefon** — masaüstünde çalışan bir
  şey "çalışıyor" sayılmaz

## Kurulum

```bash
composer install
npm ci
php artisan migrate
php artisan queue:work        # ayrı bir terminalde
```

VAPID anahtarları (yalnızca bir kez, bildirim çalışması için):

```bash
php artisan byagain:vapid-keys
```

Çıkan üç satırı **elle** `.env` dosyasına yaz (komut dosyaya yazmaz — Ana Yasa
III). Anahtarlar yoksa uygulama çalışmaya devam eder; ayarlardaki bildirim
anahtarı devre dışı görünür.

---

## 1. Biten tekrar bitmiş kalır (issue #4)

**Hazırlık**: bir kullanıcıya en az iki tur açacak kadar pasaj gir ve
`daily_review_limit` değerini 2 yap.

1. `/review` aç, günün tüm kartlarını karara bağla.
2. Tamamlanma ekranını gör.
3. Alt menüden **Library**'ye geç, sonra **Review** sekmesine dön.

**Beklenen**: yine tamamlanma ekranı. Kart yok.

```bash
# Kanıt: bugün için ikinci bir tur satırı açılmamış olmalı
php artisan tinker --execute="dump(App\Models\Review::where('review_date', today())->pluck('round'));"
```

4. "Bir tur daha" düğmesine bas.

**Beklenen**: kartlar gelir, veritabanında `round = 2` satırı vardır.

5. İkinci turu da bitir, sayfayı yenile.

**Beklenen**: kota dolduğu için düğme yok, gün kapalı anlatılıyor. Tamamlanma
ekranından karar kartlarına dönen hiçbir bağlantı yok (FR-107).

6. Yarım bırakma denemesi: yeni bir gün kur, iki kartı karara bağla, sekme
   değiştir, geri dön.

**Beklenen**: üçüncü karttan devam (FR-106).

---

## 2. Kart içindeki yatay kaydırma (issue #1)

**Hazırlık**: içinde satıra sığmayan bir kod bloğu olan pasaj kaydet, örneğin
tek satırda 120 karakterlik bir komut. Aynısını geniş bir markdown tablosu için
tekrarla.

Telefonda `/review` aç ve karta gel:

1. Parmağını **kod bloğunun üzerinde** sola kaydır.
   **Beklenen**: kod kayar, kart yerinde durur, sakla/çıkar ipucu görünmez.
2. Kaydırmayı sona kadar götür, parmağını kaldırmadan aynı yöne devam et.
   **Beklenen**: kart yine kımıldamaz.
3. Parmağını kaldır.
   **Beklenen**: hiçbir karar kaydedilmemiş — kart hâlâ karar bekliyor.
4. Aynı kartta, kod bloğunun **dışında** kararlı bir sağa kaydırma yap.
   **Beklenen**: normal "sakla" davranışı, geri alma çubuğu görünür.
5. Kod bloğunda dikey kaydır.
   **Beklenen**: sayfa kayar, kart kımıldamaz.

Bu adımı hem iOS Safari'de hem Chrome/Android'de yap — `touch-action`
davranışı iki motorda ayrı.

**Durum (2026-08-24): YÜRÜTÜLMEDİ.** Uygulama ortamında fiziksel telefon yok.
Düzeltme iki parçalı olarak uygulandı (R-201): `app.css` kaydırma kutularına
`touch-action: auto` verir, `review.js` dokunuş kaydırılabilir bir kutuda
başladıysa jesti hiç kurmaz. Otomatik kapı `tests/Feature/Content/HighlightRenderTest.php`
ile işaretleme ve CSS kuralı düzeyinde duruyor; **jestin kendisi doğrulanmadı**.
Bu beş adım gerçek cihazda yürütülmeden #1 kapatılmamalıdır (Ana Yasa I).

---

## 3. Alt menü sabit kalır (issue #2)

**Hazırlık**: ekran boyunun en az iki katı uzunlukta bir pasaj kaydet.

1. Telefonda `/review` aç, pasajı sonuna kadar kaydır.
   **Beklenen**: alt menü her an ekranın altında.
2. Adres çubuğunun daralıp genişlediği hızda yukarı-aşağı kaydır.
   **Beklenen**: menü yerinden oynamaz, içerikle birlikte yukarı çıkmaz.
3. Sayfanın en altına in.
   **Beklenen**: son satır menünün arkasında kalmaz.
4. Aynı üç adımı kitaplık, seri ve ayarlar ekranlarında tekrarla — düzeltme
   kabuğa ait, tek ekrana değil.
5. Geri alma çubuğu görünürken kaydır.
   **Beklenen**: çubuk menünün üstünde durur, ikisi üst üste binmez.

**Durum (2026-08-24): YÜRÜTÜLMEDİ.** Fiziksel telefon yok. R-202'nin **2. adımı**
uygulandı: `html.h-full` / `body.min-h-full` yükseklik zinciri kaldırıldı,
menünün güvenli alan boşluğu ve gövdenin alt boşluğu tek bir değişkende
(`--bottom-nav-space`, `app.css`) toplandı, menüye `translateZ(0)` ile kendi
katmanı verildi. `100vh` hiçbir yerde kullanılmıyor.

R-202'nin **3. adımı (T022) uygulanmadı**: tetikleyicisi "2. adım cihazda
yetmezse" ve o ölçüm yapılamadı. Kabuğu `100dvh` flex sütuna çevirmenin bedeli
var (adres çubuğu artık gizlenmez, dikey alan kaybedilir), tahminle ödenmez.
Bu beş adım gerçek cihazda yürütülmeden #2 kapatılmamalıdır; kayma sürüyorsa
T022 ayrı bir commit olarak yapılır.

---

## 4. Tarayıcı bildirimi (issue #3)

### 4a. Abonelik

1. `/settings` aç, bildirim anahtarını aç.
   **Beklenen**: tarayıcı izin sorar (kendiliğinden değil, dokunuşla).
2. İzin ver.

```bash
php artisan tinker --execute="dump(App\Models\PushSubscription::count());"
```

**Beklenen**: 1. Anahtarı kapat → gönderim durur, satır silinmez.

Desteklemeyen bir tarayıcıda (veya ana ekrana eklenmemiş iOS'ta) anahtar devre
dışı görünmeli, hata vermemeli.

### 4b. Gönderim

Süpürmeyi elle, sahte saatle çalıştır. Kullanıcının `daily_email_at` değeri
`08:00` ise bildirim penceresi `09:00`'dır:

```bash
# Önce günlük e-posta penceresi (bildirim buna bağlı)
php artisan byagain:dispatch-daily --user=1 --now="2026-08-24 08:00"

# Bir saat sonra: tekrar yapılmadıysa bildirim
php artisan byagain:dispatch-daily --user=1 --now="2026-08-24 09:00"
```

**Beklenen**: ikinci çalıştırma `push_queued=1` raporlar, telefonda bildirim
belirir, dokununca `/review` açılır.

```bash
php artisan byagain:dispatch-daily --user=1 --now="2026-08-24 09:05"
```

**Beklenen**: ikinci bildirim **yok** (`skipped: push:already_queued`).

### 4c. Susma koşulları

Her biri ayrı ayrı denenir; hiçbirinde bildirim gitmemeli:

| Koşul | Nasıl kurulur |
|---|---|
| Tekrar tamamlanmış | 09:00 süpürmesinden önce tekrarı bitir |
| Ayar kapalı | `/settings` üzerinden kapat |
| O gün günlük e-posta gitmemiş | 08:00 süpürmesini atla |
| Abonelik yok | Tarayıcıdan izni geri al |

Bildirim metninde pasaj içeriği **görünmemeli** (FR-148) — kilit ekranında
kontrol et.

**Durum (2026-08-24): YÜRÜTÜLMEDİ.** İki ayrı engel var ve ikisi de ortama ait:

1. **Veritabanı yok.** Bu makinede MySQL çalışmıyor (`127.0.0.1:3306` kapalı,
   `mysqld` ve Docker kurulu değil). `RefreshDatabase` kullanan tüm testler ve
   `byagain:dispatch-daily`'nin gerçek çalıştırması bu yüzden koşturulamadı.
2. **Cihaz yok.** Gerçek bir tarayıcıya push teslimi doğrulanamaz.

Yazılan ama **koşturulmamış** testler:
`tests/Feature/Push/PushSubscriptionTest.php`,
`tests/Feature/Push/ReviewNudgeTest.php`,
`tests/Unit/Push/PushDispatcherTest.php`,
`tests/Feature/Console/DispatchDailyPushWindowTest.php`,
`tests/Feature/Review/ReviewCompletionTest.php`,
`tests/Unit/Review/ReviewBuilderTest.php`,
`tests/Feature/Content/HighlightRenderTest.php`.

MySQL bulunan bir makinede önce şunlar koşulmalı:

```bash
php artisan migrate
php artisan test
php artisan migrate:rollback --step=3   # üç yeni migration'ın down()'ı
```

`byagain:vapid-keys` çalıştığı doğrulandı (anahtar çifti üretildi). Windows'ta
`OPENSSL_CONF` tanımlı değilse `openssl_pkey_new` başarısız olur; bu PHP kurulum
sorunudur, komutun değil.

---

## Bitmiş sayılma kapıları

Ana Yasa "Bitmiş sayılma ölçütü" listesi bu iş için:

```bash
vendor/bin/pint --test
vendor/bin/phpstan analyse
php artisan test
composer audit
npm run build        # kullanıcı bundle'ı 150KB bütçesinin altında mı
```

Kapıların 2026-08-24 durumu:

| Kapı | Durum |
|---|---|
| `vendor/bin/pint --test` | ✅ temiz |
| `vendor/bin/phpstan analyse` | ✅ temiz (level 6, baseline'a ekleme yok) |
| `composer audit` | ✅ temiz |
| `npm run build` | ✅ kullanıcı bundle'ı bütçe altında; `push.js` ayrı chunk |
| `php artisan test` | ⛔ **koşturulamadı** — MySQL yok |

Ek olarak:

- [x] Yeni davranışların testi **yazıldı** (tamamlanma matrisi, push dedupe, abonelik uçları) — ama koşturulmadı
- [ ] Yeni migration'ların `down()`'ı çalışıyor (`php artisan migrate:rollback` ile dene) — **doğrulanmadı**, veritabanı yok
- [x] Kullanıcıya görünen yeni metinlerin hepsi `lang/en/` içinde (anahtarların çözüldüğü doğrulandı)
- [x] `.env.example` ve README yeni VAPID anahtarlarıyla güncellendi
- [ ] Dört issue de gerçek telefonda tekrar denendi ve kapanabilir durumda — **hiçbiri denenmedi**, cihaz yok
