# Feature Specification: byagain — Günlük Pasaj Tekrarı (MVP)

**Feature Branch**: `001-daily-highlight-review`

**Created**: 2026-08-22

**Status**: Draft

**Input**: User description: "byagain — Spesifikasyon (Laravel sürümü) v2.0 — Okuduklarından kaydettiğin pasajları her gün karşına çıkararak unutmanı engelleyen, telefonda kullanılmak üzere tasarlanmış kişisel tekrar uygulaması."

---

## Ürün Özeti

byagain, kullanıcının okuduklarından kendi seçtiği pasajları (highlight) kaydettiği ve
her gün küçük bir seçkiyi telefonunda karşısına çıkaran kişisel bir tekrar uygulamasıdır.
Amaç sınav geçmek değil, kaydedilen fikirle temasta kalmak. Günlük ritüel iki dakikadır:
sabah gelen e-posta, 5–15 kart, tamamlandı.

İki farklı tekrar mantığı bir arada çalışır:

1. **Normal pasajlar** — ezberletmez, ağırlıklı örneklemeyle karşına çıkarır.
2. **Mastery kartları** — kullanıcının ezberlemek istediği pasajlar için, hatırlama
   olasılığı sönümlenmesine dayalı aralıklı tekrar.

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 - İçerik gir ve ilk tekrarını yap (Priority: P1)

Yeni kullanıcı kaydolur, ilk kaynağını (kitap/makale/not) tanımlar ve içine birkaç pasaj
girer. Üç pasaj girdiği anda uygulama ona ilk tekrarını gösterir; kart kart ilerler, her
kartta pasajı okur ve saklamayı/çıkarmayı seçer. Tekrarı bitirdiğinde serisi 1. güne başlar.

**Why this priority**: Ürünün tamamı bu döngünün etrafında döner. İçerik girilemiyorsa
veya girilen metin telefonda kötü görünüyorsa geri kalan hiçbir özelliğin değeri yok.
Tek başına teslim edilse bile kullanılabilir bir ürün ortaya çıkar.

**Independent Test**: Temiz bir hesapla kaydol, bir kaynak ve üç pasaj gir, tekrarı aç,
üç kartı da işle, tamamla. Seri sayacının 1 olduğunu gör. E-posta, mastery kartı veya
admin paneli olmadan uçtan uca çalışır.

**Acceptance Scenarios**:

1. **Given** yeni kaydolmuş ve hiç içeriği olmayan kullanıcı, **When** ilk ekranını açar,
   **Then** boş bir liste değil, "ilk kaynağını ekle" yönlendirmesi görür.
2. **Given** kullanıcı bir kaynağa 3 pasaj girmiş, **When** tekrar ekranını açar,
   **Then** o pasajlardan oluşan bir tekrar hemen oluşturulur ve gösterilir.
3. **Given** başlık, liste, kod bloğu ve tablo içeren 3.000 karakterlik bir pasaj,
   **When** kart olarak gösterilir, **Then** içerik kart sınırları içinde kalır; kart
   ekranı taşırmaz ve sayfa yatay kaymaz.
4. **Given** kullanıcı pasaj metnine `<script>alert(1)</script>` yazmış, **When** pasaj
   görüntülenir, **Then** metin birebir görünür ve hiçbir kod çalışmaz.
5. **Given** kullanıcı bir pasajı yazarken uygulamadan çıkmış, **When** editöre geri döner,
   **Then** yazdığı taslak kaybolmamıştır.
6. **Given** kullanıcı tekrardaki tüm kartları işlemiş, **When** son kartı geçer,
   **Then** tamamlanma ekranı görür ve o gün seri gününe yazılır.

---

### User Story 2 - Günün doğru pasajlarını getir (Priority: P1)

Kullanıcının binlerce pasajı olabilir. Sistem her gün, kullanıcının seçtiği boyutta
(5–15 kart) bir seçki hazırlar. Seçki rastgele değil: kullanıcının kaynak başına
belirlediği sıklık tercihine, pasajın en son ne zaman gösterildiğine ve ne kadar yeni
olduğuna göre ağırlıklandırılır. Yeni gösterilmiş bir pasaj kısa süre içinde tekrar
çıkmaz; tek bir kaynak günü domine etmez.

**Why this priority**: "Doğru pasaj doğru gün" ürünün ikinci temel değeri. Seçki kötüyse
kullanıcı ritüeli bırakır. US1'in üstüne oturur ama ondan ayrı test edilir.

**Independent Test**: Çok sayıda pasaj içeren bir hesapta arka arkaya birkaç gün tekrar
üret; tekrarların içeriğini karşılaştır. Tekrarlanma, kaynak dağılımı ve ağırlık
tercihlerinin etkisi doğrulanabilir.

**Acceptance Scenarios**:

1. **Given** havuzda hiç gösterilmemiş pasajlar var, **When** ardışık günlerde tekrar
   üretilir, **Then** aynı pasaj 3 gün içinde ikinci kez gösterilmez.
2. **Given** bir kaynağın sıklığı "hiç gösterme" yapılmış, **When** sonraki tekrar
   üretilir, **Then** o kaynaktan hiçbir pasaj gelmez.
3. **Given** bir kaynağın sıklığı "çok sık" yapılmış, **When** çok sayıda tekrar üretilir,
   **Then** o kaynağın pasajları belirgin biçimde daha sık çıkar.
4. **Given** kullanıcı tercihini "her kaynak eşit şansa sahip olsun" olarak değiştirmiş,
   **When** tekrar üretilir, **Then** kaynağın pasaj sayısı seçilme şansını artırmaz.
5. **Given** n kartlık bir tekrar üretiliyor, **When** seçki hazırlanır, **Then** tek bir
   kaynaktan gelen pasaj sayısı `ceil(n/3)`'ü aşmaz.
6. **Given** kullanıcı bugünün tekrarını almış, **When** aynı gün içinde tekrar boyutu
   tercihini değiştirir, **Then** bugünkü tekrar değişmez; değişiklik yarından geçerlidir.
7. **Given** kullanıcının hiç uygun pasajı yok, **When** günlük üretim çalışır,
   **Then** tekrar oluşturulmaz ve e-posta gönderilmez.

---

### User Story 3 - Ezberlemek istediklerini Mastery kartına çevir (Priority: P2)

Kullanıcı bazı pasajları sadece hatırlamak değil, gerçekten öğrenmek ister. Bir pasajı
soru-cevap veya boşluk doldurma (cloze) kartına çevirir. Bu kartlar tekrar içinde,
normal pasajlardan sonra gösterilir: önce soru, sonra cevap. Kullanıcı doğru/yanlış
işaretlemez; kartı ne zaman tekrar görmek istediğini söyler ve aralık ona göre uzar/kısalır.

**Why this priority**: Ürünü "pasaj hatırlatıcı"dan "öğrenme aracı"na taşır, ama US1+US2
tek başına kullanılabilir bir ürün. Bu katman olmadan da günlük ritüel tamdır.

**Independent Test**: Bir pasajdan mastery kartı oluştur, geri bildirim ver, kartın bir
sonraki aday olma tarihini doğrula. Normal pasaj örneklemesinden bağımsız çalışır.

**Acceptance Scenarios**:

1. **Given** bir pasaj, **When** kullanıcı onu mastery kartına çevirir, **Then** kart
   varsayılan aralıkla oluşur ve bu aralık dolmadan tekrarda çıkmaz.
2. **Given** yeni bir mastery kartı, **When** kullanıcı "daha erken göster" der,
   **Then** kart 7 gün sonra aday olur.
3. **Given** yeni bir mastery kartı, **When** kullanıcı "bir ara" der, **Then** kart
   28 gün sonra aday olur.
4. **Given** aralığı yerleşmiş bir kart, **When** kullanıcı "daha sonra" der, **Then**
   aralık iki katına çıkar; üst sınırı (365 gün) aşamaz, alt sınırın (1 gün) altına inemez.
5. **Given** kullanıcı bir kart için "yeterince öğrendim" der, **When** sonraki tekrarlar
   üretilir, **Then** kart bir daha gösterilmez ama silinmez.
6. **Given** vadesi gelmiş mastery kartları var, **When** günlük tekrar kurulur, **Then**
   kartlar normal pasajlardan **sonra** sıralanır ve sayıları kullanıcının belirlediği
   oranı aşmaz.
7. **Given** vadesi gelmiş yeterli mastery kartı yok, **When** tekrar kurulur, **Then**
   boşluk normal pasajlarla doldurulur.
8. **Given** bir kart kullanıcıya defalarca zor gelmiş, **When** kart gösterilir,
   **Then** kartı sadeleştirmesini öneren bir ipucu görür; sistem kendiliğinden
   hiçbir şey değiştirmez.

---

### User Story 4 - Günlük e-posta ve hatırlatma (Priority: P2)

Kullanıcı seçtiği saatte, kendi yerel saatiyle, günün kartlarını içeren bir e-posta alır.
E-posta tıklanmadan da değerlidir: pasajlar içine gömülüdür. Akşam seçtiği saatte tekrarı
hâlâ yapmamışsa tek bir nazik hatırlatma alır. Tekrarını tamamladıysa hatırlatma gelmez.

**Why this priority**: Geri dönüş döngüsünün motoru. Ancak uygulama e-postasız da
kullanılabilir; bu yüzden P2.

**Independent Test**: Farklı zaman dilimlerinde kullanıcılar oluştur, zamanı ileri sar,
hangi e-postanın ne zaman gönderildiğini doğrula.

**Acceptance Scenarios**:

1. **Given** zaman dilimi `America/New_York` olan kullanıcı ve gönderim saati 08:00,
   **When** o yerel saat gelir, **Then** günlük e-posta o anda gönderilir.
2. **Given** günlük e-posta gönderilmiş, **When** kullanıcı uygulamayı açar, **Then**
   uygulamada gördüğü kartlar e-postadakiyle birebir aynıdır.
3. **Given** kullanıcı tekrarını akşam saatinden önce tamamlamış, **When** hatırlatma
   zamanı gelir, **Then** hatırlatma gönderilmez.
4. **Given** günlük üretim süreci gün içinde defalarca çalışır, **When** aynı gün için
   hatırlatma değerlendirilir, **Then** kullanıcıya günde en fazla 1 hatırlatma gider.
5. **Given** hatırlatma gönderilmek üzere sıraya alınmış, **When** gönderim anından önce
   kullanıcı tekrarını tamamlar, **Then** e-posta gönderilmez ve atlandığı kayda geçer.
6. **Given** kullanıcı arka arkaya 5 gün hiçbir e-postayı açmamış, **When** hatırlatma
   zamanı gelir, **Then** hatırlatma sıklığı haftada 1'e düşer.
7. **Given** kullanıcı e-postadaki "bu maili kapat" bağlantısına tıklar, **When** giriş
   yapmamış olsa bile, **Then** ilgili e-posta tercihi kapanır ve bağlantı taklit edilemez.
8. **Given** kullanıcı e-posta adresini henüz doğrulamamış, **When** gönderim zamanı gelir,
   **Then** ona pazarlama/ritüel e-postası gönderilmez.

---

### User Story 5 - Seri (streak) ve süreklilik (Priority: P3)

Kullanıcı arka arkaya kaç gün tekrarını tamamladığını görür. Gece geç saatte yapılan
tekrar önceki güne sayılır. Seri kırıldığında suçlayıcı bir dil kullanılmaz.

**Why this priority**: Alışkanlığı pekiştirir ama ürünün çekirdek değeri değil.

**Independent Test**: Zamanı ileri/geri sararak farklı saatlerde tekrar tamamla; sayacın
ve takvimin doğru güne yazdığını doğrula.

**Acceptance Scenarios**:

1. **Given** kullanıcı dün tekrarını tamamlamış, **When** bugün de tamamlar, **Then**
   seri 1 artar.
2. **Given** kullanıcı yerel saat 01:30'da tekrarını tamamlar, **When** gün hesaplanır,
   **Then** tekrar bir önceki güne yazılır.
3. **Given** kullanıcı bir günü atlamış, **When** tekrar tamamlar, **Then** seri 1'den
   yeniden başlar ve en uzun seri kaydı korunur.
4. **Given** kullanıcı dün ve bugün tekrar yapmamış, **When** ana ekranı açar, **Then**
   güncel seri 0 görünür.
5. **Given** kullanıcı seri sayacına dokunur, **When** takvim açılır, **Then** son 90
   günün hangi günlerinde tekrar yaptığı görünür.

---

### User Story 6 - Yönetim ve operasyon görünürlüğü (Priority: P3)

Uygulamayı işleten kişi, kullanıcı sayısını, tekrar tamamlanma oranını, e-posta
gönderim sağlığını ve arka plan işlerinin çalışıp çalışmadığını tek yerden görebilir.
Bir kullanıcıyı askıya alabilir, başarısız bir e-postayı yeniden gönderebilir.

**Why this priority**: Ürün çalışırken görünmezliği önler; son kullanıcıya değer taşımaz.

**Independent Test**: Yönetici rolüyle panele gir, kullanıcı listele, bir e-postayı
yeniden gönder. Normal kullanıcı rolüyle panele erişimin reddedildiğini doğrula.

**Acceptance Scenarios**:

1. **Given** yönetici olmayan bir kullanıcı, **When** yönetim paneline erişmeye çalışır,
   **Then** erişim reddedilir.
2. **Given** yönetici, **When** paneli açar, **Then** kayıt sayısı, aktif kullanıcı,
   tamamlanma oranı, gönderilen/başarısız e-posta ve arka plan işlerinin son çalışma
   yaşını görür.
3. **Given** arka plan zamanlayıcısı iki tur boyunca çalışmamış, **When** yönetici paneli
   açar, **Then** görünür bir uyarı vardır.
4. **Given** yönetici kullanıcı kayıtlarını listeler, **When** bir pasaja bakar,
   **Then** yalnızca kısa bir önizleme görür; pasajın tam metni panelde gösterilmez.
5. **Given** yönetici bir işlem yapar (askıya alma, yeniden gönderme), **When** işlem
   tamamlanır, **Then** kim/ne/ne zaman bilgisi geriye dönük silinemeyecek şekilde kaydedilir.

---

### Edge Cases

- **Sahiplik**: Kullanıcı başka birinin pasaj/kaynak/kart kimliğiyle bir adrese giderse
  içerik sızmaz; kayıt yokmuş gibi davranılır.
- **Boş havuz**: Tüm pasajlar çıkarılmış veya tüm kaynaklar arşivlenmişse tekrar
  oluşturulmaz; kullanıcı ne yapması gerektiğini anlatan bir durum mesajı görür.
- **Kısmi havuz**: İstenen kart sayısı kadar uygun pasaj yoksa tekrar daha kısa üretilir,
  hata verilmez.
- **Çift üretim**: Aynı yerel gün için ikinci bir tekrar oluşturulamaz (eşzamanlı iki
  istek gelse bile).
- **Yarım kalan tekrar**: Kullanıcı tekrarı yarıda bırakıp döndüğünde kaldığı yerden
  devam eder; işlediği kartlar tekrar sorulmaz.
- **Zayıf bağlantı**: Kart aksiyonu anında görünür; bağlantı koptuysa kullanıcı net bir
  durum görür ve verisi kaybolmaz.
- **Çevrimdışı açılış**: Uçak modunda uygulama açılır ve çevrimdışı olduğunu söyler.
- **Zaman dilimi değişimi**: Kullanıcı zaman dilimini değiştirirse gönderim saatleri yeni
  dilime göre hesaplanır; geçmiş seri günleri geriye dönük değişmez.
- **Kaynak silme/arşivleme**: Arşivlenen kaynağın pasajları havuzdan çıkar ama kaybolmaz.
- **Silinen içerik**: Bir tekrarda yer alan pasaj sonradan çıkarılırsa o günkü tekrar
  bozulmaz.
- **Hesap silme**: Kullanıcı hesabını silerse tüm içeriği birlikte gider ve e-posta
  adresi geri izlenemez hale gelir.
- **Yinelenen kayıt denemesi / var olmayan e-postayla şifre sıfırlama**: Yanıt, adresin
  sistemde kayıtlı olup olmadığını ele vermez.
- **Kalite filtresi**: Filtre açıkken çok kısa pasajlar havuza girmez; ama kod içeren
  kısa pasajlar girer.

---

## Requirements *(mandatory)*

### Functional Requirements

**Hesap ve kimlik**

- **FR-001**: Sistem kullanıcıların e-posta ve parolayla kaydolmasını, giriş yapmasını,
  çıkış yapmasını sağlamalıdır.
- **FR-002**: Sistem kayıt sonrası e-posta doğrulama bağlantısı göndermeli; bağlantı 24
  saat geçerli olmalıdır.
- **FR-003**: Kullanıcılar parolalarını 60 dakika geçerli, tek kullanımlık bir bağlantıyla
  sıfırlayabilmelidir.
- **FR-004**: Sistem parola sıfırlama isteğine, adres kayıtlı olsun olmasın aynı yanıtı
  dönmelidir.
- **FR-005**: Sistem en az 10 karakter uzunluğunda ve bilinen ihlal listelerinde yer
  almayan parolalar talep etmelidir; ek karmaşıklık kuralı dayatılmamalıdır.
- **FR-006**: Sistem giriş ve parola sıfırlama denemelerini hız sınırına tabi tutmalıdır.
- **FR-007**: E-postasını doğrulamamış kullanıcı uygulamayı kullanabilmeli, ancak ona
  ritüel e-postaları gönderilmemeli ve arayüzde kalıcı bir uyarı görmelidir.
- **FR-008**: Kullanıcı, parolasını onaylayarak hesabını silebilmeli; silme tüm içeriğini
  kaldırmalı ve e-posta adresini anonimleştirmelidir.
- **FR-009**: Sistem her kullanıcı için `user` ve `admin` olmak üzere iki rol ve `active`
  / `suspended` olmak üzere iki hesap durumu tutmalıdır.

**Sahiplik ve gizlilik**

- **FR-010**: Kullanıcıya ait her kayıt (kaynak, pasaj, mastery kartı, tekrar) yalnızca
  sahibi tarafından okunabilir ve değiştirilebilir olmalıdır; başkasının kaydına erişim
  denemesi kayıt yokmuş gibi sonuçlanmalıdır.
- **FR-011**: Kullanıcı içeriği yalnızca kullanıcının kendi işlemiyle kalıcı olarak
  silinmelidir; tekrardan "çıkarılan" pasajlar gizlenir, yok edilmez.

**İçerik**

- **FR-012**: Kullanıcılar kaynak oluşturabilmeli; kaynak en az başlık, isteğe bağlı yazar,
  tür (kitap/makale/not/podcast/kurs/diğer), sıklık tercihi ve arşiv durumu taşımalıdır.
- **FR-013**: Kullanıcılar bir kaynağa pasaj ekleyebilmeli, düzenleyebilmeli ve tekrar
  havuzundan çıkarabilmelidir.
- **FR-014**: Pasaj metni başlık, kalın/italik, sıralı ve sırasız liste, alıntı, kod bloğu,
  satır içi kod, yatay çizgi, tablo ve bağlantı biçimlendirmesini desteklemelidir.
- **FR-015**: Sistem kullanıcı girdisindeki ham HTML'i işlememeli, metin olarak
  göstermelidir; görüntülenen içerik güvenli etiket beyaz listesinden geçmelidir.
- **FR-016**: Pasaj içindeki dış bağlantılar yeni sekmede ve yönlendiren bilgisi
  sızdırmadan açılmalıdır.
- **FR-017**: Kullanıcının yazdığı ham metin doğruluk kaynağı olmalı; görüntüleme biçimi
  ondan yeniden üretilebilir olmalıdır.
- **FR-018**: Pasajlara isteğe bağlı bir kişisel not ve konum bilgisi (sayfa/bölüm)
  eklenebilmelidir.
- **FR-019**: Kullanıcılar pasajları favoriye alabilmelidir.
- **FR-020**: Metin girişi mobilde tek elle yapılabilmeli; biçimlendirme kısayolları
  klavyenin hemen üstünde erişilebilir olmalı ve yaz/önizle geçişi bulunmalıdır.
- **FR-021**: Yazılmakta olan pasaj taslağı otomatik olarak korunmalı; kullanıcı uygulamadan
  çıkıp dönerse metni kaybolmamalıdır.
- **FR-022**: Yapıştırılan metindeki fazla satır sonları ve satır sonu tiresiyle bölünmüş
  kelimeler otomatik olarak temizlenmelidir.
- **FR-023**: Kullanıcı kaynaklarını ve içindeki pasajları listeleyebilmeli (kütüphane).

**Günlük tekrar**

- **FR-024**: Kullanıcı günlük tekrar boyutunu 5 ile 15 arasında seçebilmelidir.
- **FR-025**: Sistem her yerel gün için kullanıcı başına en fazla bir tekrar oluşturmalı ve
  bu tekrarın içeriğini kalıcı olarak saklamalıdır.
- **FR-026**: Tekrar oluşturulduktan sonra tercih değişiklikleri o günkü tekrarı
  etkilememelidir.
- **FR-027**: Normal pasaj seçimi şu üç çarpanın bileşimiyle ağırlıklandırılmalıdır:
  kaynağın sıklık tercihi, pasajın son gösteriminden bu yana geçen süre (soğuma) ve
  pasajın yeni eklenmiş olması.
- **FR-028**: Kaynak sıklık tercihi altı basamaklı olmalıdır: hiç gösterme, nadiren, az,
  normal (varsayılan), sık, çok sık. Değişiklik bir sonraki tekrardan itibaren etkili olmalıdır.
- **FR-029**: Son 3 gün içinde gösterilmiş bir pasaj havuza girmemelidir.
- **FR-030**: Arşivlenmiş kaynaklara ait, çıkarılmış ve sıklığı "hiç gösterme" olan
  pasajlar havuza girmemelidir.
- **FR-031**: Kalite filtresi açıkken çok kısa (25 karakterin altı) ve kod içermeyen
  pasajlar havuza girmemelidir; filtre kullanıcı tarafından kapatılabilmelidir.
- **FR-032**: Varsayılan davranışta çok pasajı olan kaynak daha sık çıkmalı; kullanıcı
  "her kaynak eşit şansa sahip olsun" tercihini açtığında kaynak başına şans eşitlenmelidir.
- **FR-033**: Bir tekrarda tek bir kaynaktan gelen pasaj sayısı, tekrar boyutunun üçte
  birini (yukarı yuvarlanmış) aşmamalıdır.
- **FR-034**: Kullanıcı günlük tekrarın ne kadarının mastery kartı olacağını yüzde olarak
  belirleyebilmelidir.
- **FR-035**: Tekrar sıralaması önce normal pasajlar, sonra mastery kartları şeklinde olmalıdır.
- **FR-036**: Vadesi gelmiş mastery kartı yetersizse boşluk normal pasajlarla doldurulmalı;
  normal pasaj da yoksa tekrar daha kısa oluşturulmalıdır.
- **FR-037**: Hiç uygun pasaj yoksa tekrar oluşturulmamalıdır.
- **FR-038**: Kullanıcı her kartta şunları yapabilmelidir: sakla (varsayılan), tekrardan
  çıkar, favorile, mastery kartına çevir, pasajın kaynağının sıklığını değiştir.
- **FR-039**: Bir pasaj "saklandığında" gösterim sayısı artmalı ve son gösterim zamanı
  güncellenmelidir.
- **FR-040**: Kullanıcı tekrarı yarıda bıraktığında ilerlemesi korunmalı; döndüğünde
  işlenmemiş kartlardan devam etmelidir.
- **FR-041**: Kart aksiyonları anında geri bildirim vermeli (iyimser güncelleme); ağ
  gecikmesi kullanıcıyı bekletmemelidir.
- **FR-042**: Kullanıcı, kartları kaydırarak (mobil) veya ok tuşlarıyla (masaüstü)
  gezinebilmelidir.
- **FR-043**: Tekrar ekranında ilerleme sayı olarak değil, çubuk olarak gösterilmelidir.

**Mastery kartları**

- **FR-044**: Kullanıcılar bir pasajdan soru-cevap veya boşluk doldurma (cloze) türünde
  mastery kartı oluşturabilmelidir.
- **FR-045**: Mastery kartı gösteriminde önce soru/boşluklu metin, kullanıcının isteğiyle
  sonra cevap görünmelidir.
- **FR-046**: Kullanıcı doğru/yanlış işaretlemek yerine kartı ne zaman tekrar görmek
  istediğini seçmelidir: daha erken, daha sonra, bir ara, yeterince öğrendim.
- **FR-047**: Yeni kartlarda ilk geri bildirim mutlak aralık atamalıdır: daha erken → 7 gün,
  daha sonra → 14 gün, bir ara → 28 gün.
- **FR-048**: Sonraki geri bildirimlerde aralık çarpanla güncellenmelidir: daha erken ×0.5,
  daha sonra ×2.0, bir ara ×3.0.
- **FR-049**: Aralık 1 günün altına inmemeli, 365 günü aşmamalıdır.
- **FR-050**: "Yeterince öğrendim" seçilen kart bir daha tekrarda çıkmamalı fakat
  silinmemelidir.
- **FR-051**: Vadesi gelmiş kartlar arasından, hatırlanma olasılığı en düşük olanlar
  önce seçilmelidir; eşitlik durumunda sıra değişken olmalıdır.
- **FR-052**: Kullanıcı bir kart için tekrar tekrar "daha erken" dediyse (6 ve üzeri),
  sistem kartı sadeleştirmesini önermeli ama kendiliğinden değiştirmemelidir.
- **FR-053**: Kullanıcı mastery kartlarını ayrı bir ekranda listeleyebilmeli, düzenleyebilmeli
  ve kaldırabilmelidir.

**Seri**

- **FR-054**: Bir gün, o günün tekrarındaki tüm kartlar işlendiğinde tamamlanmış sayılmalıdır.
- **FR-055**: Gün sınırı kullanıcının yerel saatiyle 04:00 olmalıdır; bu saatten önce
  yapılan tekrar önceki güne yazılmalıdır.
- **FR-056**: Sistem güncel seriyi, en uzun seriyi ve tamamlanan günlerin takvimini
  (son 90 gün) tutmalı ve göstermelidir.
- **FR-057**: Seri kesintiye uğradığında bir sonraki tamamlamada 1'den başlamalı; ayrı bir
  arka plan işi gerektirmemelidir.
- **FR-058**: Seri kırıldığında kullanıcıya suçlayıcı olmayan bir dille yeni başlangıç
  gösterilmelidir.

**E-posta**

- **FR-059**: Kullanıcı günlük tekrar e-postasının ve akşam hatırlatmasının saatlerini
  kendi yerel saatiyle ayarlayabilmeli, her ikisini ayrı ayrı kapatabilmelidir.
- **FR-060**: Günlük e-posta o günün kartlarını gömülü olarak içermeli ve uygulamada
  tamamlamaya yönlendiren tek bir çağrı barındırmalıdır.
- **FR-061**: E-postadaki kartlar uygulamada gösterilen kartlarla birebir aynı olmalıdır.
- **FR-062**: Hatırlatma yalnızca o günün tekrarı tamamlanmamışsa gönderilmelidir; gönderim
  anında durum yeniden kontrol edilmeli, tamamlanmışsa gönderilmemeli ve atlandığı kaydedilmelidir.
- **FR-063**: Aynı kullanıcıya aynı gün için en fazla bir hatırlatma gönderilmelidir; bu
  garanti, üretim sürecinin sık çalışmasından bağımsız olmalıdır.
- **FR-064**: Arka arkaya 5 gün hiçbir e-postayı açmamış kullanıcıda hatırlatma sıklığı
  haftada 1'e düşmelidir.
- **FR-065**: Her e-postanın altında, giriş gerektirmeyen ve taklit edilemeyen tek tıklık
  bir "bu e-postayı kapat" bağlantısı bulunmalıdır.
- **FR-066**: E-postalar hem biçimli hem düz metin sürümü içermeli, dar ekranda ve karanlık
  modda okunabilir olmalıdır.
- **FR-067**: E-posta gönderimi kullanıcı isteği içinde senkron yapılmamalı; başarısız
  gönderimler artan aralıklarla yeniden denenmelidir.
- **FR-068**: Sistem her gönderim denemesinin türünü, alıcısını, durumunu ve varsa hatasını
  kaydetmelidir.

**Yönetim**

- **FR-069**: Yalnızca `admin` rolündeki kullanıcılar yönetim paneline erişebilmelidir.
- **FR-070**: Yönetici kullanıcıları listeleyebilmeli, arayabilmeli, askıya alabilmeli ve
  doğrulama e-postasını yeniden gönderebilmelidir; başka bir kullanıcı gibi oturum açamamalıdır.
- **FR-071**: Yönetici kaynak ve pasajları yalnızca okuyabilmeli; pasajın tam metni yerine
  kısa bir önizleme görmelidir.
- **FR-072**: Yönetici e-posta gönderimlerini türe/duruma göre süzebilmeli, hata detayını
  görebilmeli ve elle yeniden gönderebilmelidir.
- **FR-073**: Panel şu göstergeleri sunmalıdır: dönemsel kayıt sayısı, tekrarını tamamlayan
  aktif kullanıcı, tamamlanma oranı, ortalama seri, gönderilen/başarısız e-posta, bekleyen
  arka plan işi sayısı ve zamanlayıcının son çalışma yaşı.
- **FR-074**: Zamanlayıcı beklenen iki tur boyunca çalışmadıysa panelde belirgin bir uyarı
  görünmelidir.
- **FR-075**: Yönetici bakım modunu açıp kapatabilmeli, yeni kayıtları durdurabilmeli ve
  varsayılan tekrar boyutunu ayarlayabilmelidir.
- **FR-076**: Her yönetici işlemi kim/ne/ne zaman bilgisiyle, geriye dönük değiştirilemez
  biçimde kaydedilmelidir.

**Arayüz ve erişilebilirlik**

- **FR-077**: Uygulama telefon-dikey öncelikli olmalı; masaüstü aynı düzenin genişlemiş
  hali olmalıdır.
- **FR-078**: Ana gezinme ekranın altında, baş parmakla erişilebilir olmalıdır.
- **FR-079**: Dokunma hedefleri en az 44×44 piksel, komşu düğmeler arası boşluk en az
  8 piksel olmalıdır.
- **FR-080**: Gövde metni en az 17 piksel, satır uzunluğu 60–72 karakter olmalıdır; form
  alanları mobil tarayıcının kendiliğinden yakınlaştırmasına yol açmamalıdır.
- **FR-081**: Sayfa yatay kaymamalı; yatay kaydırma yalnızca kod bloğu ve tablo içinde
  ve kendi kabı içinde olmalıdır.
- **FR-082**: 600 karakterden uzun pasajlar kısaltılmış gösterilmeli, kullanıcı isteğiyle
  açılmalıdır.
- **FR-083**: Uygulama karanlık modu hem sistem tercihinden almalı hem elle
  değiştirilebilmelidir.
- **FR-084**: Hareket azaltma tercihi olan kullanıcılarda animasyonlar devre dışı kalmalıdır.
- **FR-085**: Odak görünür olmalı, metin kontrastı en az 4.5:1 olmalı, ikon düğmelerinin
  erişilebilir adı bulunmalıdır.
- **FR-086**: Uygulama telefona ana ekran uygulaması olarak eklenebilmelidir.
- **FR-087**: Bağlantı yokken uygulama açılmalı, çevrimdışı olduğunu açıkça belirtmeli ve
  kullanıcının girdiği veriyi kaybetmemelidir.
- **FR-088**: Arayüzde görünen metinler koda gömülmemeli, ayrı bir çeviri kaynağından
  gelmelidir. Varsayılan arayüz dili İngilizcedir; kullanıcıya görünen adresler de
  İngilizcedir.

**Operasyon**

- **FR-089**: Günlük tekrarların üretimi ve e-posta sıraya alma işlemleri, kullanıcıların
  farklı zaman dilimlerindeki gönderim saatlerini en fazla birkaç dakikalık sapmayla
  yakalayacak sıklıkta çalışmalıdır.
- **FR-090**: Aynı üretim süreci eşzamanlı iki kez çalışsa bile yinelenen tekrar veya
  yinelenen e-posta üretilmemelidir.
- **FR-091**: Sistem süresi geçmiş doğrulama/sıfırlama kayıtlarını, 30 günden eski gönderim
  kayıtlarını ve eski başarısız iş kayıtlarını düzenli olarak temizlemelidir.
- **FR-092**: Görüntüleme biçimi bozulursa ham metinden toplu olarak yeniden üretilebilmelidir.
- **FR-093**: Kullanıcı, uygulama içinden kaynak koda ulaşan bir bağlantı bulabilmelidir.

### Key Entities

- **Kullanıcı**: Hesap sahibi. Zaman dilimi, rol, hesap durumu, tekrar tercihleri
  (boyut, mastery oranı, kalite filtresi, eşit kaynak ağırlığı), e-posta saatleri ve
  tercihleri, seri bilgileri, e-posta etkileşim sağlığı.
- **Kaynak**: Pasajların geldiği yer (kitap, makale, not, podcast, kurs, diğer). Başlık,
  yazar, tür, sıklık tercihi, arşiv durumu. Bir kullanıcıya aittir.
- **Pasaj (Highlight)**: Kullanıcının kaydettiği metin. Ham metin, görüntülenebilir biçim,
  düz metin karşılığı, isteğe bağlı not ve konum, favori/çıkarılmış durumu, gösterim sayısı
  ve son gösterim zamanı. Bir kaynağa aittir.
- **Mastery Kartı**: Bir pasajdan türetilmiş, ezberlenmek istenen kart. Tür (soru-cevap /
  cloze), soru, cevap, tekrar aralığı, son tekrar ve bir sonraki vade zamanı, tekrar ve
  zorlanma sayacı, durum (etkin/duraklatılmış/emekli).
- **Tekrar (Review)**: Bir kullanıcının belirli bir yerel günü için üretilmiş, kalıcı kart
  seçkisi. Boyut, durum (bekliyor/tamamlandı), başlama ve tamamlanma zamanları.
- **Tekrar Kalemi (Review Item)**: Tekrardaki tek bir kart. Sıra, tür (pasaj/mastery),
  hangi kayda işaret ettiği, kullanıcının aksiyonu ve mastery geri bildirimi.
- **Seri Günü**: Kullanıcının tekrarını tamamladığı yerel gün kaydı; takvim görünümünün
  kaynağı.
- **E-posta Gönderimi**: Tür, alıcı, tekilleştirme anahtarı, durum (sıraya alındı/gönderildi/
  başarısız/atlandı), hata ve gönderim zamanı.
- **Yönetici İşlem Kaydı**: Hangi yöneticinin hangi kayıt üzerinde ne zaman hangi işlemi
  yaptığı; değiştirilemez.

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Yeni bir kullanıcı, kayıttan ilk tekrarını tamamlamaya kadar olan yolu
  5 dakikanın altında bitirebilir.
- **SC-002**: Günlük ritüel telefonda ortalama 2 dakikadan kısa sürer (8 kartlık tekrar).
- **SC-003**: Kullanıcı ilk oturumunda hiçbir noktada işlevsiz boş ekranla karşılaşmaz.
- **SC-004**: 20.000 pasajı olan bir hesapta günlük tekrar seçkisi kullanıcı bekletmeden
  hazırlanır (0,3 saniyenin altında).
- **SC-005**: Tekrar ekranında kart aksiyonu, ağ gecikmesinden bağımsız olarak 100
  milisaniyenin altında görsel karşılık verir.
- **SC-006**: Uygulamanın ilk açılışı 4G bağlantıda 2 saniyenin altında ana içeriği gösterir
  ve kullanıcı tarafı ilk yükleme 150KB'ı aşmaz (yazı tipleri hariç).
- **SC-007**: Bağımsız mobil denetimde performans puanı ≥90, erişilebilirlik puanı ≥95.
- **SC-008**: Başlık, liste, kod bloğu ve tablo içeren 3.000 karakterlik bir pasaj, 375
  piksel genişlikte hiçbir taşma veya yatay kayma üretmez.
- **SC-009**: Ardışık 30 günlük simülasyonda hiçbir pasaj 3 gün içinde iki kez gösterilmez
  ve hiçbir tekrarda tek kaynağın payı üçte biri geçmez.
- **SC-010**: Sıklığı "hiç gösterme" yapılan kaynaktan sonraki hiçbir tekrarda pasaj gelmez
  (%100).
- **SC-011**: Herhangi bir kullanıcı, herhangi bir kimlik tahminiyle başka bir kullanıcının
  içeriğine erişemez (sızıntı oranı %0).
- **SC-012**: Kullanıcı girdisi hiçbir koşulda yürütülebilir içerik olarak işlenmez
  (%100 etkisiz hale getirme).
- **SC-013**: Farklı zaman dilimlerindeki kullanıcılar günlük e-postalarını seçtikleri
  yerel saate göre en fazla 5 dakika sapmayla alır.
- **SC-014**: Kullanıcı başına günde en fazla 1 hatırlatma gönderilir (%100); tekrarını
  tamamlamış kullanıcıya hatırlatma gitme oranı %0.
- **SC-015**: Aynı kullanıcı ve aynı yerel gün için ikinci bir tekrar hiçbir koşulda
  oluşmaz (%100).
- **SC-016**: Uçak modunda uygulama açılır ve çevrimdışı durumunu bildirir; girilen taslak
  metin kaybolmaz.
- **SC-017**: Arka plan işleri iki tur boyunca çalışmazsa yönetici bunu panelde 10 dakika
  içinde görür.
- **SC-018**: Kullanıcı tarafındaki hiçbir sayfa yönetim panelinin arayüz varlıklarını
  yüklemez (0 istek).
- **SC-019**: Temiz bir makinede README adımları birebir izlenerek proje ayağa kalkar;
  belgelenen her komut çalışır.
- **SC-020**: Depo geçmişinde hiçbir gizli anahtar veya ortam dosyası bulunmaz.

---

## Assumptions

- **Ölçek**: ~1.000 kullanıcı, kullanıcı başına ~20.000 pasaj hedeflenir. Bunun ötesi
  bu spesifikasyonun kapsamı dışıdır (50.000 pasajın üzerinde seçki için ön filtre
  gerekeceği bilinen bir risktir).
- **Kapsam dışı (v1.1+)**: Kindle/Instapaper içe aktarma, Notion/Obsidian dışa aktarma,
  temalı tekrarlar, etiketler, tam metin arama, yapay zekâ özellikleri, web push
  bildirimleri, görsel yükleme, ödeme, passkey ile giriş, yerel mobil uygulama.
- **Tek kullanıcılık gizlilik**: Pasajlar özeldir; paylaşım, ortak kütüphane veya sosyal
  özellik yoktur.
- **Zaman**: Tüm zamanlar sistemde tek bir evrensel referansla saklanır; kullanıcının
  "bugün"ü kendi zaman dilimi ve 04:00 gün sınırıyla hesaplanır.
- **Sabitler ürün kararıdır** (Ana Yasa m. V): 3 günlük blok, soğuma sabiti (21 gün),
  yenilik çarpanı (1.5), yarı-ömür değerleri (7/14/28), çarpanlar (0.5/2.0/3.0), aralık
  sınırları (1–365), kalite filtresi eşiği (25 karakter), zorlanma eşiği (6). Bunlar
  yapılandırılabilir olmalı ve değerleri sormadan değiştirilmemelidir.
- **Varsayılan tercihler**: tekrar boyutu 8, mastery oranı %50, günlük e-posta 08:00,
  hatırlatma 20:00, kalite filtresi açık, eşit kaynak ağırlığı kapalı.
- **E-posta teslimi**: Harici bir gönderim sağlayıcısı kullanılır ve alan adı kimlik
  doğrulaması (SPF/DKIM/DMARC) canlıya çıkmadan tamamlanır. Bu tamamlanmadan yayına
  geçilmez.
- **E-posta açılma takibi**: "Açılmamış e-posta" sayacı, sağlayıcının sunduğu standart
  açılma sinyaline dayanır; bu sinyalin doğası gereği yaklaşık olduğu kabul edilir.
- **Barındırma**: Arka plan işleri ve zamanlayıcı sürekli çalışabilen bir ortamda barınır.
- **Lisans**: Proje AGPL-3.0-only altındadır; uygulama içinden kaynak koda bağlantı
  verilmesi bir lisans gerekliliğidir, nezaket değil.
- **Yönetici hesabı**: İlk yönetici hesabı arayüzden değil, kurulum sırasında elle
  yetkilendirilir.
- **Dil (karara bağlandı)**: Arayüz İngilizcedir. Kullanıcıya görünen adresler de
  İngilizcedir; girdi belgesindeki Türkçe rota listesi (`/tekrar`, `/kutuphane`, `/ekle`,
  `/ayarlar`, `/seri`) İngilizce karşılıklarıyla değiştirilir. Depo public olduğu ve dış
  katkıya açık olduğu için tek dil İngilizcedir. Çok dillilik kapsam dışıdır.

---

## Uygulama Öncesi Bekleyen Belge Güncellemeleri

Dil kararı (İngilizce arayüz + İngilizce rotalar) Ana Yasa'nın mevcut metniyle çelişiyor.
Ana Yasa m. Governance uyarınca bu belgeyi LLM tek başına değiştirmez. Kod yazımı
başlamadan önce, ayrı bir PR ile şunlar güncellenmelidir:

1. `.specify/memory/constitution.md` → "Ek Kısıtlar ve Kod Standartları": `lang/tr/`
   ifadeleri `lang/en/` olur; "Blade'de `Kaydet` → `{{ __('actions.save') }}`" satırındaki
   örnek İngilizceye çevrilir. Sürüm PATCH artışı (1.0.1) yeterlidir — ilke değişmiyor,
   yalnızca hedef dil düzeltiliyor.
2. `docs/SPEC.md` (yazıldığında) → rota listesi İngilizce adlarla yazılır.
