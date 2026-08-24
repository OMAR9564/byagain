# Feature Specification: Tekrar Akışı Düzeltmeleri ve Tarayıcı Hatırlatması

**Feature Branch**: `002-review-flow-fixes`

**Created**: 2026-08-24

**Status**: Draft

**Input**: User description: "GitHub issue #1, #2, #3, #4 hepsini kapsayan tek özellik: Today Review kart/scroll davranışı düzeltmeleri, alt menü sabitlenmesi, günlük review tamamlandıktan sonra geri dönüşün engellenmesi, ve review yapılmazsa 1 saat sonra tarayıcı bildirimi"

---

## Kapsam

Bu özellik, açık dört issue'yu tek bir teslimatta toplar. Üçü günlük ritüelin
kendisini bozan hata, biri ritüele geri çağıran yeni bir kanal.

| Issue | Başlık | Bu belgedeki karşılığı |
|---|---|---|
| #4 | Gunluk review | User Story 1 — biten tekrar bitmiş kalır |
| #1 | Today Review | User Story 2 — kart içinde yatay kaydırma |
| #2 | Today Review | User Story 3 — alt menü sabit kalır |
| #3 | Tarayici bildirimi | User Story 4 — tekrar yapılmazsa bildirim |

Ortak zemin: dördü de "sabah telefonda iki dakika" ritüelinin kendisiyle ilgili.
Ritüel ya yanlış bitiyor (#4), ya kart okunurken elden kaçıyor (#1, #2), ya da
hiç başlamıyor (#3).

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Biten tekrar bitmiş kalır (Priority: P1)

Kullanıcı günün tekrarını tamamlar ve "bugün bu kadar" ekranını görür. Ekranın
altındaki menüden başka bir sekmeye geçip tekrar sekmesine döndüğünde yine aynı
ekranı görür: gün bitmiştir. Yeni kart görmek istiyorsa bunu açıkça ister —
"bir tur daha" düğmesine basar. Günlük kota da dolduysa ekran son sözünü söyler
ve daha fazla tur açılmaz.

**Why this priority**: Ana Yasa I'in çekirdeği, ritüelin bitebilir olmasıdır.
Kendiliğinden yeni tur açan bir ekran, ürünü akışa (feed) çevirir; kullanıcı
"bitirdim" duygusunu kaybeder. Şu anki davranış bu değeri doğrudan bozuyor.

**Independent Test**: Bir hesapla günün tekrarını sonuna kadar tamamla, alt
menüden Library'ye geç, sonra tekrar sekmesine dön. Kart akışı değil, tamamlanma
ekranı gelmeli. Tek başına teslim edilse bile hatayı bitirir.

**Acceptance Scenarios**:

1. **Given** kullanıcı günün turunu tamamladı ve tamamlanma ekranında, **When** alt menüden tekrar sekmesine dokunur, **Then** yeni tur üretilmeden tamamlanma ekranı gösterilir.
2. **Given** kullanıcı tamamlanma ekranında ve günlük kotası dolmadı, **When** "bir tur daha" düğmesine basar, **Then** yeni bir tur açılır ve kartlar gösterilir.
3. **Given** kullanıcı günlük kotasını doldurdu, **When** tamamlanma ekranını açar, **Then** "bir tur daha" eylemi sunulmaz ve gün kapalı olarak anlatılır.
4. **Given** kullanıcı tamamlanma ekranında, **When** sayfayı yeniler veya uygulamaya sonra geri döner, **Then** aynı tamamlanma ekranı gelir; karar verilen kartlara düşmez.
5. **Given** kullanıcının yarım kalmış (tamamlanmamış) bir turu var, **When** tekrar sekmesine dokunur, **Then** karar vermediği ilk karttan devam eder — bu davranış değişmez.

---

### User Story 2 - Kart içindeki yatay kaydırma kartı oynatmaz (Priority: P1)

Kart üstünde kod bloğu, geniş bir tablo ya da satıra sığmayan uzun bir metin
vardır. Kullanıcı bu bölgeyi okumak için parmağını sola kaydırır. Kaydırılan şey
kod bloğudur; kart yerinde durur, hiçbir karar üretilmez.

**Why this priority**: Ana Yasa I: "girilen metnin telefonda kusursuz
görünmesi". Okunamayan kod bloğu pasajı okunamaz yapar; üstelik kaydırma denemesi
yanlışlıkla "sakla/çıkar" kararı üretiyorsa kullanıcı verisi de yanlış etkilenir.

**Independent Test**: İçinde 100+ karakterlik tek satır kod olan bir pasaj
kaydet, tekrarda karta gel, kod bloğunu sola kaydır. Kod kaymalı, kart
kımıldamamalı, karar üretilmemeli.

**Acceptance Scenarios**:

1. **Given** kartta yatay kaydırılabilir bir kod bloğu var, **When** kullanıcı parmağını bu blok üzerinde yatay hareket ettirir, **Then** blok kendi içinde kaydırılır, kart hareket etmez ve sakla/çıkar kararı verilmez.
2. **Given** kullanıcı kod bloğunu kaydırma sonuna kadar getirdi, **When** parmağını kaldırmadan aynı yönde hareketi sürdürür, **Then** aynı dokunuş boyunca kart yine hareket etmez.
3. **Given** kullanıcı kartın kaydırılabilir bölge dışındaki bir yerine dokundu, **When** kararlı bir yatay hareket yapar, **Then** mevcut sakla/çıkar davranışı aynı eşiklerle çalışır.
4. **Given** kart üstünde ekrana sığmayan içerik var, **When** kart görüntülenir, **Then** içeriğin yatay olarak kaydırılabilir olduğu görsel olarak bellidir.

---

### User Story 3 - Alt menü kaydırırken yerinde kalır (Priority: P2)

Uzun bir pasaj ekrana sığmaz. Kullanıcı okumak için aşağı kaydırır. Alt menü
ekranın altında kalır; içerikle birlikte yukarı kayıp kaybolmaz.

**Why this priority**: Alt menü, başparmak erişimindeki tek gezinme yoludur
(FR-078). Kaydırmada kaçan menü, kullanıcının uygulamada nerede olduğunu ve nasıl
çıkacağını kaybettirir. Hatanın kapsamı P1'ler kadar geniş değil — karar veya
veri bozmuyor — ama her uzun pasajda görülüyor.

**Independent Test**: Ekran boyundan uzun bir pasajı tekrarda aç, sayfayı sonuna
kadar kaydır. Menü her noktada ekranın altında görünür olmalı.

**Acceptance Scenarios**:

1. **Given** içerik ekrandan uzun, **When** kullanıcı aşağı kaydırır, **Then** alt menü ekranın altında sabit kalır.
2. **Given** kullanıcı sayfanın en altında, **When** son satırı okur, **Then** içerik alt menünün altında kalmaz; menü ve güvenli alan kadar boşluk bırakılmıştır.
3. **Given** tarayıcı adres çubuğu kaydırma sırasında daralıp genişler, **When** kullanıcı kaydırmaya devam eder, **Then** menü ekran altına yapışık kalır, içerikle birlikte yukarı çıkmaz.

---

### User Story 4 - Tekrar yapılmazsa tarayıcı hatırlatması (Priority: P3)

Sabah günlük e-posta gider. Bir saat sonra kullanıcı hâlâ tekrarını yapmamıştır.
Tarayıcı ona kısa bir bildirim gösterir; dokunduğunda doğrudan bugünün tekrar
ekranı açılır. Tekrarını çoktan yaptıysa hiçbir şey gelmez.

**Why this priority**: Yeni bir kanal, mevcut bir hatayı düzeltmiyor. E-posta
hatırlatması zaten çalışıyor; bu onun üstüne konan ikinci bir dürtme. İzin,
abonelik ve tarayıcı desteği gerektirdiği için teknik yüzeyi de en geniş olan
parça. Diğer üçü onsuz da teslim edilebilir.

**Independent Test**: Bir kullanıcı için günlük e-postayı gönder, tekrarı
yapmadan bir saat ilerlet, bildirimin gittiğini doğrula. Ardından aynı senaryoyu
tekrarı tamamlayarak yürüt ve hiç bildirim gitmediğini doğrula.

**Acceptance Scenarios**:

1. **Given** kullanıcı bildirimlere izin verdi ve günlük e-postası gönderildi, **When** gönderimden 60 dakika geçer ve tekrar hâlâ tamamlanmamıştır, **Then** tek bir tarayıcı bildirimi gösterilir.
2. **Given** kullanıcı bu arada tekrarını tamamladı, **When** 60 dakika dolar, **Then** bildirim gönderilmez.
3. **Given** kullanıcı bildirimi gördü, **When** bildirime dokunur, **Then** bugünün tekrar ekranı açılır.
4. **Given** kullanıcı bildirim iznini hiç vermedi veya reddetti, **When** gün boyunca gezinir, **Then** izin kendiliğinden yeniden sorulmaz ve hiçbir hata gösterilmez.
5. **Given** kullanıcı ayarlardan tarayıcı bildirimini kapattı, **When** koşullar oluşur, **Then** bildirim gönderilmez.
6. **Given** o gün için bir bildirim zaten gönderildi, **When** aynı gün koşullar yeniden oluşur, **Then** ikinci bildirim gönderilmez.

---

### Edge Cases

- Kullanıcı tekrarı **başka bir cihazda** tamamlamışsa bildirim yine engellenir mi? Evet — kontrol gönderim anında sunucudaki tekrar durumuna bakar, tarayıcıya değil.
- O gün **hiç günlük e-posta gitmediyse** (uygun pasaj yok, kullanıcı aboneliği kapalı) bildirim de gitmez: hatırlatma daima var olan bir tekrar hakkındadır.
- Kullanıcı bildirime **tekrar zaten bittikten sonra** dokunursa tamamlanma ekranını görür, yeni tur açılmaz (US1 ile aynı kural).
- **Tarayıcı desteklemiyorsa** (veya iOS'ta uygulama ana ekrana eklenmemişse) ayar seçeneği kapalı/gizli görünür; kullanıcıya çalışmayan bir anahtar sunulmaz.
- Kullanıcı **izni işletim sistemi düzeyinde geri alırsa** kayıtlı abonelik geçersizleşir; ilk başarısız gönderimde sessizce temizlenir ve kullanıcıya hata gösterilmez.
- **Geri alma (undo) penceresi açıkken** kullanıcı sekme değiştirirse mevcut davranış korunur: bekleyen karar gönderilir; tur son karttaysa tamamlanır ve tamamlanma ekranı gelir.
- **Çevrimdışıyken** verilen son kararlar kuyrukta beklerken tamamlanma ekranı gösterilir; bağlantı gelince kuyruk boşalır ve tur sunucuda da tamamlanmış olur.
- Kart içinde **hem dikey hem yatay** kaydırılabilir içerik varsa (uzun kod bloğu) dikey hareket sayfayı, yatay hareket bloğu kaydırır; ikisi de karar üretmez.
- **Klavye açıkken** (editör ekranı) alt menü içeriğin veya imlecin üstüne binmemelidir.
- Kullanıcı **günlük kotasını değiştirirse** açık olan tur etkilenmez; yeni sınır bir sonraki turdan itibaren geçerlidir.

## Requirements *(mandatory)*

Bu belgenin gereksinimleri `FR-1xx` ile numaralanır; `specs/001-daily-highlight-review`
içindeki `FR-0xx` numaralarıyla karışmaması için.

### Functional Requirements

**Tamamlanma ve tur açma (US1 / issue #4)**

- **FR-101**: Sistem, günün turu tamamlandığında kullanıcı istemeden yeni bir tur ÜRETMEMELİDİR; tekrar ekranı tamamlanma durumunu göstermelidir.
- **FR-102**: Yeni tur YALNIZCA kullanıcının açık bir eylemiyle ("bir tur daha") başlamalıdır.
- **FR-103**: Alt menüden tekrar sekmesine dönmek, tamamlanmış bir günü yeniden açmamalı; kullanıcıyı karar verilmiş kartlara geri götürmemelidir.
- **FR-104**: Günlük tur kotası dolduğunda sistem "bir tur daha" eylemini sunmamalı ve gün kapalı olarak anlatılmalıdır.
- **FR-105**: Tamamlanma ekranı gün içinde kararlı olmalıdır: yenileme, geri gelme veya sekme değiştirme aynı ekranı vermelidir.
- **FR-106**: Tamamlanmamış (yarım kalan) tur, karar verilmemiş ilk karttan devam ettirilmelidir — mevcut davranış korunur.
- **FR-107**: Tamamlanma ekranı son duraktır: tamamlanmış bir turun kartlarına geri dönülememeli, kartlar salt-okunur olarak da yeniden gösterilmemelidir. Ekrandan tek çıkış "bir tur daha" ya da başka bir sekmedir.
- **FR-108**: Kullanıcının açtığı ek turların toplam sayısı, ürün sabiti olan günlük üst sınırı (`max_rounds_per_day`) aşmamalıdır.

**Kart içi kaydırma (US2 / issue #1)**

- **FR-121**: Sistem, kart içindeki yatay kaydırılabilir bölgelerde (kod bloğu, geniş tablo, taşan uzun metin) yapılan yatay hareketi kaydırma olarak yorumlamalı; kartı hareket ettirmemeli ve karar üretmemelidir.
- **FR-122**: Bir dokunuş kaydırma olarak başladıysa, aynı dokunuş boyunca — kaydırma sınırına ulaşılsa bile — karar üretilmemelidir.
- **FR-123**: Kaydırılabilir bölge dışında yapılan hareketlerde mevcut sakla/çıkar eşikleri ve geri bildirimleri değişmeden çalışmalıdır.
- **FR-124**: Ekrana sığmayan içerik, yatay olarak kaydırılabilir olduğunu görsel olarak belli etmelidir.
- **FR-125**: Sistem, aynı korumayı hem highlight kartlarında hem mastery kartlarındaki kod/tablo içeriğinde uygulamalıdır.

**Alt menü (US3 / issue #2)**

- **FR-131**: Alt menü, sayfa dikey olarak kaydırılırken ekranın altında sabit kalmalıdır.
- **FR-132**: Sayfa içeriği, alt menü yüksekliği ve cihaz güvenli alanı kadar alttan boşluk bırakmalı; son satır menünün arkasında kalmamalıdır.
- **FR-133**: Menü, mobil tarayıcının adres çubuğu daralıp genişlerken de görünür kalmalı ve yerinden oynamamalıdır.
- **FR-134**: Menünün sabitlenmesi kart kaydırma hareketini engellememeli, dokunma hedefi boyutlarını küçültmemelidir.

**Tarayıcı bildirimi (US4 / issue #3)**

- **FR-141**: Sistem, günlük e-posta gönderiminden 60 dakika sonra o günün tekrarı tamamlanmamışsa bir tarayıcı bildirimi göndermelidir.
- **FR-142**: Gönderim anında tekrar tamamlanmışsa bildirim gönderilmemelidir.
- **FR-143**: Kullanıcı başına yerel gün içinde en fazla bir tarayıcı bildirimi gönderilmelidir.
- **FR-144**: O gün için günlük e-posta gönderilmemişse bildirim de gönderilmemelidir.
- **FR-145**: Bildirim izni yalnızca kullanıcının açık eylemiyle (ayarlardaki anahtar) istenmeli; sayfa açılışında otomatik istenmemelidir.
- **FR-146**: Kullanıcı ayarlardan tarayıcı bildirimini açıp kapatabilmelidir; kapalıyken gönderim yapılmamalıdır.
- **FR-147**: Bildirime dokunmak bugünün tekrar ekranını açmalıdır.
- **FR-148**: Bildirim metni pasaj içeriği taşımamalıdır; kilit ekranında görünen tek şey kısa bir hatırlatma olmalıdır.
- **FR-149**: Desteklenmeyen veya izni reddedilmiş ortamda arayüz hata göstermemeli, özellik sessizce devre dışı kalmalıdır.
- **FR-150**: Geçersizleşen abonelikler ilk başarısız gönderimde temizlenmeli, kuyrukta tekrar tekrar denenmemelidir.
- **FR-151**: Tarayıcı bildirimi, mevcut akşam e-posta hatırlatmasını değiştirmemeli; iki kanal birbirinden bağımsız çalışmalı ve ayrı ayrı kapatılabilmelidir.
- **FR-152**: Bildirim, uygulama kapalıyken de ulaşan gerçek bir push bildirimi olmalıdır: kullanıcının cihazı/tarayıcısı adına bir abonelik saklanır ve bildirim, kullanıcı uygulamayı açmamış olsa bile gösterilir. (Depo sahibi 2026-08-24'te onayladı: yeni şema, yeni bağımlılık ve yeni ortam anahtarları bu kapsam dahilindedir.)
- **FR-153**: Kullanıcı birden çok cihazdan izin verebilmeli; bildirim izin verdiği her geçerli cihaza gitmeli, gün başına tekilleştirme kullanıcı düzeyinde uygulanmalıdır (FR-143).

### Key Entities

- **Bildirim aboneliği**: Bir kullanıcının bir tarayıcı/cihazı için bildirim alma hakkı. Kullanıcıya bağlıdır, birden çok olabilir, izin geri alındığında geçersizleşir.
- **Bildirim gönderim kaydı**: Bir kullanıcıya, bir yerel gün için, bir kanaldan yapılan gönderim. Aynı gün ikinci bir bildirimi engelleyen tekilleştirme kaydıdır (mevcut e-posta gönderim kaydıyla aynı mantık).
- **Tekrar turu (mevcut)**: Günün tamamlanma durumu ve o gün açılmış tur sayısı; US1'in tüm kararları bu iki bilgiye dayanır.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-101**: 375px genişlikte, içinde satıra sığmayan kod bloğu olan bir kartta yapılan 10 yatay kaydırmanın 10'unda kart yerinde kalır ve hiçbir sakla/çıkar kararı üretilmez.
- **SC-102**: Tekrarını tamamlayan kullanıcı, alt menüden tekrar sekmesine döndüğünde her seferinde tamamlanma ekranını görür; istem dışı açılan tur sayısı sıfırdır.
- **SC-103**: Ekran boyundan uzun bir pasaj boyunca yapılan kaydırmanın her noktasında alt menü görünür kalır ve son satır menünün arkasında kalmaz.
- **SC-104**: Tekrarını yapmamış ve bildirime izin vermiş kullanıcıların bildirimi, günlük e-postadan 60 dakika sonra ±5 dakika içinde ulaşır.
- **SC-105**: Tekrarını tamamlamış kullanıcılara giden bildirim sayısı sıfırdır.
- **SC-106**: Kullanıcı tarafındaki toplam varlık bütçesi 150KB sınırının altında kalır.
- **SC-107**: Dört issue de kapanır: her biri için, sorunu tarif eden adımlar yeniden uygulandığında sorun görülmez.

## Assumptions

- Günlük tur kotası (`daily_review_limit`) artık kendiliğinden tur açan bir sayaç değil, kullanıcının isteyerek açabileceği tur sayısının üst sınırıdır; sert tavan `max_rounds_per_day` olarak kalır.
- "Geri dönüş olmasın" kuralı yalnızca **tamamlanmış** turlar için geçerlidir; yarım kalan tur kaldığı yerden devam eder.
- Bildirim zamanlaması, mevcut beş dakikalık süpürmeyle ve kullanıcının yerel saatiyle hesaplanır; ayrı bir kullanıcı-başına zamanlayıcı kurulmaz.
- 60 dakikalık gecikme bir ürün sabitidir ve yapılandırmada adlandırılmış bir değer olarak durur (Ana Yasa V); ilk değeri 60 dakikadır.
- Bildirim varsayılan olarak kapalıdır; kullanıcı ayarlardan açana kadar hiçbir izin istenmez.
- iOS'ta tarayıcı bildirimi yalnızca ana ekrana eklenmiş uygulamada çalışır; bu ortam dışında özellik kapalı görünür.
- Mevcut akşam e-posta hatırlatması ve haftada bir sınırı korunur; bu özellik onu değiştirmez.
- Arayüz dili İngilizcedir (Ana Yasa): bu belge Türkçe olsa da eklenen tüm kullanıcı metinleri `lang/en/` altında yaşar.
- Kart içi kaydırma ve alt menü düzeltmeleri yalnızca mevcut yığınla yapılır; yeni bir istemci bağımlılığı eklenmez.
- Gerçek push için gereken şema değişikliği (abonelik + gönderim kaydı), sunucu tarafı bağımlılığı ve ortam anahtarları depo sahibince 2026-08-24'te onaylanmıştır; anahtarların gerçek değerlerini insan girer, `.env.example` ve README aynı PR'da güncellenir.
- Tamamlanmış tura geri bakış yoktur (FR-107); verilen kararı görmek isteyen kullanıcı ilgili pasajı kitaplıktan açar.
