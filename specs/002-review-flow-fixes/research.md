# Phase 0 — Araştırma: Tekrar Akışı Düzeltmeleri ve Tarayıcı Hatırlatması

**Feature**: `002-review-flow-fixes` · **Tarih**: 2026-08-24

Spec'te açık bırakılan iki soru `/speckit-specify` sırasında depo sahibi
tarafından kapatıldı (gerçek web push; tamamlanma ekranı son duraktır). Burada
kalan iş, kök nedenleri doğrulamak ve uygulama kararlarını gerekçesiyle
kaydetmektir.

---

## R-201 — Kart içindeki yatay kaydırma neden çalışmıyor

**Karar**: Kök neden CSS'tir, JS eşiği değil. `resources/css/app.css:58-60`:

```css
[data-swipe-surface] {
    touch-action: pan-y;
}
```

`touch-action` alt ağaca miras gibi davranır: yüzeyin **içindeki** her şey için
yatay panlama tarayıcıdan alınır. `pre` ve `table` kutuları `overflow-x: auto`
olmasına rağmen (`app.css:171-177`) parmakla kaydırılamaz; hareket
`review.js` `bindSwipe` içindeki `touchmove` dinleyicisine düşer ve
`Math.abs(dx) >= Math.abs(dy)` olduğu için swipe sayılır (`review.js:408-424`).

Düzeltme iki parçalıdır ve ikisi de gereklidir:

1. **CSS** — kaydırılabilir kutulara yatay pan geri verilir:
   `.highlight-content .highlight-body pre`, `… table` için `touch-action: auto`
   (veya `pan-x pan-y`). Böylece tarayıcı kendi doğal kaydırmasını yapar.
2. **JS** — `touchstart` hedefi, kendi içinde hâlâ kaydırılabilecek yatay bir
   kutunun (`scrollWidth > clientWidth`) içindeyse jest hiç başlatılmaz;
   başlatılmış bir dokunuş kaydırma olarak işaretlendiyse aynı dokunuş boyunca
   (`touchend`'e kadar) karar üretmez.

**Gerekçe**: Yalnız CSS düzeltilirse tarayıcı kaydırır ama JS aynı anda kartı da
sürükler — iki hareket üst üste biner. Yalnız JS düzeltilirse `touch-action`
hâlâ tarayıcının kaydırmasını engeller, blok kıpırdamaz. İkisi birlikte:
kaydırılabilir kutu kendi işini yapar, kart hiç karışmaz.

**Değerlendirilen alternatifler**:

- *Swipe eşiğini büyütmek* — reddedildi: `touch-action` engeli sürdüğü için kod
  bloğu yine kaydırılamaz; yalnızca yanlış kararı zorlaştırır, sorunu çözmez.
- *Swipe'ı tümüyle kaldırmak* — reddedildi: jest ürünün kendi tasarımı
  (`review.js` başlığı, FR-041), kaldırmak issue'nun istemediği bir kayıp.
- *Kaydırma yerine kod bloğunu satır kaydırmalı (`white-space: pre-wrap`) yapmak*
  — reddedildi: kod satırının kırılması pasajın anlamını bozar; `pre` bilerek
  `white-space: pre` (`app.css:185-189`).

---

## R-202 — Alt menü neden kayıyor ve nasıl sabitlenir

**Karar**: Önce cihazda tekrar üret, sonra en ucuz düzeltmeden başla. Sıra:

1. **Doğrula**: iOS Safari ve Chrome/Android'de, ekrandan uzun bir pasajda,
   adres çubuğu daralırken/genişlerken menünün ne yaptığını kaydet. Hangi
   tarayıcıda olduğu düzeltmeyi belirler.
2. **İlk düzeltme (yapısal değişiklik yok)**: `html.h-full` + `body.min-h-full`
   yükseklik zincirini kaldır (`components/layouts/app.blade.php:12,44`);
   `100vh` yerine dinamik viewport birimi kullan; alt menünün yüksekliğini
   `env(safe-area-inset-bottom)` değişse de sabit tutacak biçimde
   `min-height: var(--size-bottom-nav)` + `padding-bottom: max(env(safe-area-inset-bottom), 0px)`
   olarak yaz; menüye kendi katmanını ver (`transform: translateZ(0)`).
3. **Yetmezse**: kabuk yeniden kurulur — `100dvh` yüksekliğinde flex sütun,
   `main` kendi kaydırma kutusu (`overflow-y: auto; overscroll-behavior: contain`),
   alt menü akış içinde kardeş öğe. Bu, sabit konumlandırmayı tümüyle ortadan
   kaldırır.

**Gerekçe**: Menü `position: fixed` ve üstünde `transform`/`filter` taşıyan bir
ata yok (`bottom-nav.blade.php:43-47`, `app.blade.php:84`), yani klasik
"içeren blok" hatası değil. Geriye mobil tarayıcının dinamik araç çubuğu ve
güvenli alan davranışı kalıyor — bunlar cihazda ölçülmeden seçilecek düzeltme
tahmin olur. 3. adım kesin çözümdür ama bedeli vardır: gövde kaydırmadığı için
adres çubuğu artık gizlenmez ve dikey alan kaybedilir; kaydırma konumu
geri yükleme davranışı da değişir. Bu yüzden son çare.

**Değerlendirilen alternatifler**:

- *`position: sticky; bottom: 0`* — akışta duran menü, içerik kısa olduğunda
  ekranın ortasında kalır; boş durumlarda (kitaplık boşken) yanlış görünür.
- *JS ile `scroll` olayında yeniden konumlandırma* — reddedildi: her karede
  düzen okumak, ürünün en çok kaydırılan ekranında takılma üretir.
- *Menüyü üste taşımak* — reddedildi: başparmak erişimi ürün kararıdır (FR-078).

---

## R-203 — Web push nasıl gönderilir

**Karar**: `minishlink/web-push` doğrudan kullanılır ve **tek bir sarmalayıcı
sınıfın** (`app/Services/Push/WebPushSender.php`) arkasında durur. Abonelik
modeli, tekilleştirme ve iş kuyruğu bize aittir; kütüphane yalnızca VAPID
imzası ve payload şifrelemesi yapar.

**Kurulumdan önce doğrulanacak** (`composer require` öncesi, Ana Yasa V):

- Paketin güncel sürümü ve **lisansı** (beklenen: MIT — AGPL-3.0 ile uyumlu).
  `composer show minishlink/web-push` ve packagist sayfası ile teyit edilir.
- Laravel 13.x / PHP 8.3 uyumu ve gerekli PHP eklentileri (`ext-openssl`
  zorunlu; `ext-gmp`/`ext-bcmath` performans için önerilir). Eksik eklenti
  varsa README ve `.env.example` değil, kurulum belgesi güncellenir.

**Gerekçe**: Sarmalayıcı, kütüphanenin tüm yüzeyini tek dosyada tutar — sürüm
değişince dokunulacak yer bellidir ve testte sahtelenecek tek nokta orasıdır.

**Değerlendirilen alternatifler**:

- *`laravel-notification-channels/webpush`* — hazır model ve migration getirir,
  ama Laravel 13 uyumu paketin sürüm takvimine bağlı ve kendi
  `push_subscriptions` şemasını dayatır. Bizim tekilleştirme desenimiz
  (`email_deliveries`) zaten var; ikinci bir desen taşımaya değmez.
- *Firebase / OneSignal gibi bir servis* — reddedildi: kullanıcı verisinin (en
  azından kimin ne zaman tekrar yapmadığının) dışarı çıkması, tek kullanıcılık
  bir üründe kabul edilemez bir bedel.
- *Yalnızca sayfa açıkken bildirim* — spec aşamasında değerlendirildi ve
  reddedildi (bkz. FR-152 kararı).

---

## R-204 — Bildirim ne zaman gönderilir

**Karar**: Yeni zamanlayıcı yok. `byagain:dispatch-daily` (her beş dakikada bir)
üçüncü bir pencere kazanır: kullanıcının `daily_email_at` saatine
`config('byagain.push.nudge_delay_minutes')` (60) eklenmiş yerel saat,
`LocalDayResolver::isWithinSendWindow()` ile karşılaştırılır.

Gönderim kararı sırayla: bildirim açık mı → o gün için gönderilmiş **günlük
e-posta** var mı → o günün turu (round 1) var mı ve tamamlanmamış mı → bugün
için push kaydı açılabiliyor mu (`insertOrIgnore`). Hepsi geçerse iş kuyruğa
girer; işçi göndermeden hemen önce tamamlanma durumunu **yeniden** okur.

**Gerekçe**: Ana Yasa IV ve mevcut komutun kendi yorumu: kullanıcı başına cron
yoktur, süpürme timezone'u tek başına çözer. "Gönderim anında yeniden oku",
`SendEveningReminderEmail` ile aynı desendir ve kullanıcı arada tekrarı
bitirdiğinde bildirimi susturan tek güvenilir yoldur.

**Değerlendirilen alternatifler**:

- *E-posta işinin sonunda `delay(60 dakika)` ile push işi kuyruğa almak* —
  reddedildi: kuyruk `database` sürücüsü; 60 dakika bekleyen iş, kuyruk
  temizlenirse veya işçi yeniden başlarsa sessizce kaybolur ve durumu
  gözlemlenemez. Süpürme her koşulda kendini toparlar.
- *Sabit sunucu saati (örn. 09:00 UTC)* — reddedildi: kullanıcının yerel günü
  ürünün temel kavramı (SPEC 6).

---

## R-205 — Tamamlanmış gün ve "bir tur daha"

**Karar**: `ReviewController::show()` içindeki otomatik tur üretimi kaldırılır.
Tamamlanma ekranının durumu üç bilgiden hesaplanır:

| Bilgi | Kaynak |
|---|---|
| Bugün kaç tur yapıldı | `ReviewBuilder::roundsToday()` |
| Kullanıcının kendi sınırı doldu mu | `users.daily_review_limit` |
| Sert tavan doldu mu | `config('byagain.review.max_rounds_per_day')` |
| Yeni tur için malzeme var mı | `ReviewBuilder::hasMaterialFor()` (yeni, salt-okunur) |

`done.blade.php` bugün "malzeme bitti" durumunu `$rounds < $limit` olmasından
çıkarıyor (`done.blade.php:40-46`) — bu çıkarım yalnızca otomatik tur üretimi
varken doğruydu. Otomatik üretim kalkınca malzeme sorusu açıkça sorulmalıdır,
yoksa ekran "bir tur daha" düğmesini gösterir, düğme boş dönüş yapar.

**Gerekçe**: Ana Yasa I — ritüel bitebilmeli. `POST /review/again` zaten var,
throttle'lı ve niyeti açık (`web.php:56-58`); tek yol o olur.

**Değerlendirilen alternatifler**:

- *`show()` içinde otomatik turu koruyup alt menü bağlantısına bir sorgu
  parametresi eklemek* — reddedildi: davranış bağlantının nereden gelindiğine
  bağlı hale gelir; sayfa yenilemede yine yanlış çalışır.
- *Tamamlanmış turu salt-okunur göstermek* — spec kararıyla reddedildi (FR-107).

---

## R-206 — iOS kısıtı ve izin akışı

**Karar**: Bildirim ayarı yalnızca `'serviceWorker' in navigator && 'PushManager' in window`
doğruyken etkin görünür. iOS'ta bu koşul ancak uygulama ana ekrana eklendiğinde
sağlanır; sağlanmadığında anahtar devre dışı ve yanında tek satırlık açıklama
durur ("ana ekrana ekle" yönlendirmesi). İzin `Notification.requestPermission()`
yalnızca kullanıcının anahtara dokunmasıyla istenir; `denied` dönerse anahtar
kapalıya döner ve bir daha sorulmaz.

**Gerekçe**: FR-145 ve FR-149. Çalışmayan bir anahtar sunmak, kullanıcının
ürüne güvenini bozmanın en ucuz yoludur.

**Değerlendirilen alternatifler**:

- *Sayfa açılışında izin istemek* — reddedildi: tarayıcılar bunu spam sayar,
  kullanıcı kalıcı olarak reddedince özellik bir daha açılamaz.

---

## R-207 — VAPID anahtarları ve yapılandırma

**Karar**: `php artisan byagain:vapid-keys` komutu anahtar çiftini üretir ve
ekrana basar; **hiçbir dosyaya yazmaz**. İnsan değerleri `.env`'e girer.
`.env.example` üç anahtarla büyür:

```
VAPID_PUBLIC_KEY=
VAPID_PRIVATE_KEY=
VAPID_SUBJECT=mailto:
```

`config/services.php` içinde `vapid` bloğu olarak okunur; anahtar eksikse
abonelik ucu 503 döner ve arayüzde ayar devre dışı görünür.

**Gerekçe**: Ana Yasa III — `.env` dosyasına dokunulmaz. Komut, insanın elle
`openssl` çağırmasından daha az hata üretir ve gerçek değeri yine insan girer.

---

## R-208 — Varlık bütçesi ve service worker

**Karar**: Push istemcisi ayrı ve küçük bir modül olur (`resources/js/push.js`,
yalnızca ayarlar sayfasında yüklenir). `public/sw.js` iki dinleyici kazanır:
`push` (bildirimi göster) ve `notificationclick` (açık sekme varsa ona odaklan,
yoksa `/review`'i aç). Service worker sürümü `VERSION = 'v2'` olarak artırılır
ki eski kurulum yeni dinleyicileri alsın.

**Gerekçe**: `sw.js` Vite dışında, elle yazılan bir dosya (`public/sw.js`) —
bütçeye dahil değil ama sürümlenmesi gerekiyor, yoksa eski service worker
push olayını hiç duymaz. `push.js` yalnızca ayarlarda yüklendiği için tekrar
ekranının bütçesi hiç etkilenmez (SC-106).

---

## Açık kalan riskler

| Risk | Nasıl kapanır |
|---|---|
| `minishlink/web-push` lisansı veya Laravel 13 uyumu beklenenden farklı çıkarsa | `composer require` öncesi doğrulama görevi (`tasks.md` ilk görevlerden biri); uyumsuzsa iş durur ve depo sahibine dönülür |
| Alt menü düzeltmesi 2. adımda çözülmezse | 3. adım (kabuk yeniden kurulumu) ayrı bir görev olarak planlanır; kapsam büyürse bölünür (Ana Yasa V, 400 satır kuralı) |
| iOS'ta push teslimi test edilemezse (fiziksel cihaz yoksa) | Sunucu tarafı sözleşme testlerle kapatılır; teslim öncesi gerçek cihaz doğrulaması `quickstart.md` kontrol listesinde zorunlu adım kalır |
