# Feature Specification: Kaynağa Göre Pratik, Sabit Kaynakla Ekleme ve LLM ile Tekrar

**Feature Branch**: `003-source-practice`

**Created**: 2026-10-02

**Status**: Draft

**Input**: User description: "Üç ilişkili özellik (tek feature): 1) Kullanıcı belirli bir kategoriye (kaynak) göre tekrar yapabilsin; bu tekrar sayılmasın (günlük tekrar/seri sayılmaz). 2) Kullanıcı bir kategoriden kart eklerse, kaydet dedikten sonra tekrar kategori seçmesin; o kategori değişmeden kalsın. 3) Bir kategori tamamen LLM ile tekrar edilmek istenirse bir buton olsun; oradan LLM'e verilecek bir dosya indirilsin ya da panoya kopyalansın; kullanıcı bunu alıp bir LLM'e verir ve onunla tekrar yapar."

---

## Kapsam

Kullanıcının dilinde "kategori", uygulamada **kaynak**tır (kitap, makale — pasajların
bağlı olduğu kayıt). Bu belge "kaynak" der.

| İstek | Bu belgedeki karşılığı |
|---|---|
| Kaynağa göre tekrar, sayılmasın | User Story 1 — kaynağa göre pratik |
| Kaydettikten sonra kaynak değişmesin | User Story 2 — sabit kaynakla art arda ekleme |
| Kaynağı LLM ile tekrar etmek | User Story 3 — LLM için dışa aktarma |

Ortak zemin: üçü de günlük ritüelin **dışında** kalan, kullanıcının kendi seçtiği bir
kaynakla çalışma anlarıdır. Hiçbiri sabah seçkisini, seriyi veya günlük turu
değiştirmez (Ana Yasa I: ritüel dokunulmazdır).

### Karara bağlananlar (2026-10-02, depo sahibi)

- Pratik **hiç iz bırakmaz**: seri, günlük tur, son görülme zamanı, görülme sayısı ve
  mastery kartı planı değişmez. Yalnızca kullanıcının bilerek verdiği kararlar
  (çöpe at, favori, kaynak sıklığı) kalıcıdır.
- Kaydettikten sonra kullanıcı **ekleme formunda kalır**: form boşalır, aynı kaynak
  seçili kalır.
- LLM metni **talimat + pasajlar + mastery kartları** içerir; hem dosya olarak
  indirilebilir hem panoya kopyalanabilir.

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Kaynağa göre pratik (Priority: P1)

Kullanıcı kütüphanede bir kaynağın sayfasını açar ve "bu kaynağı çalış" der. O
kaynağın pasajlarından, günlük tekrar boyutu kadar kart, tanıdık tekrar ekranında
önüne gelir. Ekran bunun bir pratik olduğunu ve günü saymadığını açıkça söyler.
Kartları geçer; bitince "pratik bitti" ekranı gelir ve isterse bir set daha çeker ya
da kaynağa döner. Ertesi sabah gelen seçki, bu pratik hiç yapılmamış gibi seçilir.

**Why this priority**: Kullanıcının ilk ve en açık isteği. Sınavdan önce bir kitabı
yeniden gözden geçirmek, günlük ritüelin veremediği bir ihtiyaçtır. Pratiğin iz
bırakmaması, ürünün asıl değeri olan "doğru pasaj doğru gün" dengesini korur.

**Independent Test**: Pasajı olan bir kaynakla pratik başlat, bütün kartları geç.
Ardından seri sayısının, günlük turun ve pasajların son görülme zamanlarının
değişmediğini doğrula. Tek başına teslim edilse bile kullanıcıya değer verir.

**Acceptance Scenarios**:

1. **Given** kullanıcı en az bir aktif pasajı olan bir kaynağın sayfasında, **When** "bu kaynağı çalış" eylemine dokunur, **Then** yalnız o kaynağın aktif pasajlarından oluşan bir pratik seti tekrar ekranına benzer bir ekranda açılır.
2. **Given** kaynağın aktif pasaj sayısı kullanıcının tekrar boyutundan fazla, **When** pratik başlar, **Then** set tekrar boyutu kadar pasaj içerir ve pasajlar rastgele seçilir.
3. **Given** kaynağın aktif pasaj sayısı tekrar boyutundan az, **When** pratik başlar, **Then** set kaynağın bütün aktif pasajlarını içerir.
4. **Given** pratik ekranı açık, **When** kullanıcı ekrana bakar, **Then** bunun günü saymayan bir pratik olduğu açıkça yazar ve ekran günlük tekrarla karıştırılmaz.
5. **Given** kullanıcı pratiğin bütün kartlarını geçti, **When** son kart biter, **Then** "pratik bitti" ekranı gelir; buradan yeni bir set çekebilir veya kaynak sayfasına dönebilir.
6. **Given** kullanıcı bir pratiği bitirdi, **When** seriye, günlük tura ve takvime bakar, **Then** hiçbiri değişmemiştir; günün tekrarı yapılmadıysa hâlâ yapılmamış görünür.
7. **Given** kullanıcı pratikte bir pasajı çöpe atar veya favoriler, **When** pratik biter, **Then** bu karar kalıcıdır — tıpkı günlük tekrarda olduğu gibi.
8. **Given** kullanıcı pratikte pasajları geçti, **When** ertesi sabahın seçkisi hazırlanır, **Then** bu pasajlar pratik yapılmamış gibi değerlendirilir (soğuma, yenilik ve 3 günlük blok pratikten etkilenmez).

---

### User Story 2 - Sabit kaynakla art arda ekleme (Priority: P2)

Kullanıcı bir kitabı okurken altını çizdiği beş pasajı arka arkaya ekler. İlk
pasajda kaynağı seçer ve kaydeder. Form boşalır, başarı mesajı görünür, kaynak
seçimi aynı kalır; bir sonraki pasajı hemen yapıştırır. Kaynak sayfasından
"bu kaynağa pasaj ekle" dediğinde de form o kaynak seçili açılır.

**Why this priority**: Her pasajda kaynağı yeniden seçmek, toplu girişte en çok
tekrarlanan zahmettir; düzeltmesi küçük, etkisi her ekleme oturumunda hissedilir.
Ama pratik kadar yeni bir değer açmaz, bu yüzden P2.

**Independent Test**: Ekleme formunda bir kaynak seçip bir pasaj kaydet. Form boş,
aynı kaynak seçili gelmeli. Bir pasaj daha kaydet: kaynak seçmeden iki pasaj da
doğru kaynağa yazılmış olmalı.

**Acceptance Scenarios**:

1. **Given** kullanıcı ekleme formunda bir kaynak seçti ve pasajı yazdı, **When** kaydeder, **Then** ekleme formuna geri döner; içerik alanı boştur, aynı kaynak seçilidir ve kaydın başarılı olduğu söylenir.
2. **Given** kullanıcı kaydettikten sonra formda, **When** yeni pasajı yazıp kaynağa dokunmadan kaydeder, **Then** pasaj bir önceki ile aynı kaynağa yazılır.
3. **Given** kullanıcı kaydettikten sonra formda, **When** pasajın kaydedildiği kaynağı görmek ister, **Then** başarı mesajının yanında o kaynağın sayfasına giden bir bağlantı vardır.
4. **Given** kullanıcı bir kaynağın sayfasında, **When** "bu kaynağa pasaj ekle" eylemine dokunur, **Then** ekleme formu o kaynak seçili olarak açılır.
5. **Given** kaydetme doğrulama hatasıyla geri döndü, **When** form tekrar gösterilir, **Then** kullanıcının yazdığı içerik ve seçtiği kaynak kaybolmaz.
6. **Given** kullanıcı var olan bir pasajı düzenliyor, **When** kaydeder, **Then** davranış değişmez: kaynak sayfasına döner.

---

### User Story 3 - Kaynağı bir LLM ile çalışmak için dışa aktarma (Priority: P3)

Kullanıcı bir kaynağı tamamen, sohbet eden bir yapay zekâ ile çalışmak ister. Kaynak
sayfasında "bir LLM ile çalış" eylemine dokunur. Açılan ekranda bu metnin neleri
içerdiği (kaç pasaj, kaç kart) ve metnin kendi seçtiği bir dış hizmete gideceği
yazar. "Kopyala" ile metni panoya alır ya da "İndir" ile dosya olarak kaydeder;
sonra onu istediği LLM'e yapıştırır. Metnin başındaki talimat, LLM'e kullanıcıyı bu
pasajlardan sınamasını söyler.

**Why this priority**: Değerlidir ama uygulamanın kendi döngüsünün dışındadır ve
diğer ikisine bağlı değildir. Uygulama hiçbir LLM'e bağlanmaz; yalnız metni hazırlar.

**Independent Test**: Pasajı ve kartı olan bir kaynakta metni indir. Dosyada
talimat, kaynağın bütün aktif pasajları ve aktif kartların soru-cevapları olmalı;
çöpe atılmış pasaj ve emekli kart olmamalı. Metni bir LLM'e yapıştırınca LLM
soru sormaya başlamalı.

**Acceptance Scenarios**:

1. **Given** kullanıcı en az bir aktif pasajı olan bir kaynağın sayfasında, **When** "bir LLM ile çalış" eylemine dokunur, **Then** metnin içeriğini özetleyen (pasaj ve kart sayısı) ve metnin dış bir hizmete gönderileceğini belirten bir ekran açılır.
2. **Given** bu ekran açık, **When** kullanıcı "Kopyala"ya dokunur, **Then** metnin tamamı panoya kopyalanır ve bu kullanıcıya onaylanır.
3. **Given** cihaz panoya yazmaya izin vermiyor, **When** kullanıcı "Kopyala"ya dokunur, **Then** metin seçilip elle kopyalanabilecek biçimde gösterilir; kullanıcı eli boş kalmaz.
4. **Given** bu ekran açık, **When** kullanıcı "İndir"e dokunur, **Then** kaynağın adını ve tarihi taşıyan bir metin dosyası iner.
5. **Given** kaynakta çöpe atılmış pasajlar ve emekli kartlar var, **When** metin hazırlanır, **Then** bunlar metinde yer almaz.
6. **Given** metin hazırlandı, **When** kullanıcı onu bir LLM'e verir, **Then** talimat LLM'den kullanıcıyı pasajlardan tek tek sınamasını, cevabı kullanıcı denemeden vermemesini ve pasajların dilinde konuşmasını ister.
7. **Given** kullanıcı metni kopyaladı veya indirdi, **When** seriye, günlük tura ve pasajların görülme kayıtlarına bakar, **Then** hiçbiri değişmemiştir.

---

### Edge Cases

- **Aktif pasajı olmayan kaynak** (hiç pasaj yok ya da hepsi çöpe atılmış): pratik ve LLM eylemleri sunulmaz; yerine neden sunulmadığı kısaca söylenir.
- **Arşivlenmiş kaynak**: pratik ve LLM dışa aktarma yine sunulur — kullanıcı açıkça bu kaynağı seçmiştir. Ekleme formu arşivlenmiş kaynağı listelemediği için "bu kaynağa pasaj ekle" arşivlenmiş kaynakta sunulmaz.
- **Sıklığı "hiç gösterme" olan kaynak**: pratik ve dışa aktarma sunulur; bu ayar yalnız günlük seçkiyi yönetir.
- **Başka bir hesabın kaynağı**: pratik, dışa aktarma ve ön seçimli ekleme formu, var olmayan bir kayıtla aynı yanıtı verir (404); kaydın varlığı ele verilmez.
- **Ön seçim için geçersiz kaynak** (başka hesabın, arşivlenmiş ya da var olmayan): ekleme formu hata vermeden kaynak seçilmemiş olarak açılır.
- **Pratik sırasında sayfa yenilenir veya uygulama kapanır**: pratik kaydedilmediği için kaldığı yerden devam etmez; yeniden başlatılınca yeni bir set çekilir. Önceki kartlarda verilen kalıcı kararlar (çöpe at, favori) korunur.
- **Pratikte çöpe atılan pasaj**: aynı oturumda çekilen yeni sette yer almaz.
- **Günün tekrarı yarım kalmışken pratik**: günün tekrarı olduğu yerde, dokunulmadan bekler.
- **Çok büyük kaynak** (yüzlerce pasaj): dışa aktarma bütün aktif pasajları içerir; ekran pasaj sayısını gösterir ki kullanıcı LLM'in sınırını bilerek karar versin. Uygulama metni kesmez.
- **Pasajda kod bloğu, tablo veya liste**: dışa aktarmada kullanıcının yazdığı biçimiyle (işaretleme korunarak) yer alır.
- **Kaynak adı dosya adına uygun olmayan karakterler içeriyor**: dosya adı güvenli karakterlere indirgenir; dosyanın içi kaynak adını aslıyla taşır.

## Requirements *(mandatory)*

### Functional Requirements

**Kaynağa göre pratik (US1)**

- **FR-201**: Kullanıcı, en az bir aktif (çöpe atılmamış) pasajı olan kendi kaynağı için kaynak sayfasından bir pratik başlatabilmelidir.
- **FR-202**: Pratik seti yalnız o kaynağın aktif pasajlarından, kullanıcının tekrar boyutu kadar, rastgele seçilmelidir; aktif pasaj daha azsa hepsi alınmalıdır.
- **FR-203**: Pratik seçimi günlük seçkinin filtrelerine ve ağırlıklarına (soğuma, 3 günlük blok, yenilik, kaynak sıklığı, kısa pasaj filtresi, kaynak kotası) tabi olmamalıdır.
- **FR-204**: Pratik mastery kartı içermemelidir.
- **FR-205**: Pratik ekranı, tekrar ekranının kart geçme etkileşimini kullanmalı ve bunun günü saymayan bir pratik olduğunu ekranda açıkça belirtmelidir.
- **FR-206**: Pratik; seriyi, seri günlerini, günlük turu ve tur sayısını, tekrar geçmişini, pasajların son görülme zamanını ve görülme sayısını, mastery kartlarının planını değiştirmemelidir.
- **FR-207**: Pratik, sabah e-postasının, akşam hatırlatmasının ve tarayıcı bildiriminin "tekrar yapıldı mı" kararını etkilememelidir.
- **FR-208**: Pratikte verilen açık kararlar — çöpe at, favori, kaynak sıklığı değişikliği — günlük tekrardaki etkisiyle aynı biçimde kalıcı olmalıdır.
- **FR-209**: Pratik bitince kullanıcıya yeni bir set çekme ve kaynak sayfasına dönme seçenekleri sunulmalıdır.
- **FR-210**: Yeni set çekmek, o ana kadar çöpe atılmış pasajları dışarıda bırakmalıdır.
- **FR-211**: Pratik başlatma, kötüye kullanıma karşı istek sıklığıyla sınırlandırılmalıdır.

**Sabit kaynakla ekleme (US2)**

- **FR-212**: Ekleme formundan yapılan başarılı bir kayıttan sonra kullanıcı ekleme formuna dönmeli; içerik alanı boş, kaynak seçimi az önce kullanılan kaynak olmalıdır.
- **FR-213**: Kayıttan sonra gösterilen başarı mesajı, pasajın kaydedildiği kaynağın sayfasına bir bağlantı içermelidir.
- **FR-214**: Kaynak sayfası, arşivlenmemiş kaynaklarda, ekleme formunu o kaynak seçili açan bir eylem sunmalıdır.
- **FR-215**: Ekleme formu, ön seçim olarak verilen kaynak kullanıcıya ait ve arşivlenmemiş değilse hata vermeden kaynak seçilmemiş olarak açılmalıdır.
- **FR-216**: Doğrulama hatasında kullanıcının girdiği içerik ve seçtiği kaynak korunmalıdır.
- **FR-217**: Var olan pasajı düzenleyip kaydetme akışı değişmemelidir.

**LLM için dışa aktarma (US3)**

- **FR-218**: Kullanıcı, en az bir aktif pasajı olan kendi kaynağı için kaynak sayfasından dışa aktarma ekranını açabilmelidir.
- **FR-219**: Dışa aktarma ekranı, metnin kaç pasaj ve kaç kart içerdiğini ve metnin kullanıcının seçeceği bir dış hizmete gideceğini belirtmelidir.
- **FR-220**: Dışa aktarılan metin şu sırayla şunları içermelidir: (a) LLM'e verilen talimat, (b) kaynağın adı ve yazarı, (c) kaynağın bütün aktif pasajları numaralı olarak, kullanıcının yazdığı biçimiyle, (d) bu pasajlara bağlı aktif mastery kartlarının soru ve cevapları.
- **FR-221**: Talimat, LLM'den kullanıcıyı pasajlardan birer birer sınamasını, kullanıcı denemeden cevabı açıklamamasını, cevabı pasaja dayanarak değerlendirmesini ve pasajların dilinde konuşmasını istemelidir.
- **FR-222**: Çöpe atılmış pasajlar ve emekli kartlar metne girmemelidir.
- **FR-223**: Kullanıcı metni tek dokunuşla panoya kopyalayabilmeli; kopyalama başarısını görmeli; panoya yazılamıyorsa metni elle seçip kopyalayabileceği biçimde görmelidir.
- **FR-224**: Kullanıcı metni, adı kaynak adından ve tarihten türetilen bir metin dosyası olarak indirebilmelidir.
- **FR-225**: Uygulama metni hiçbir dış hizmete kendisi göndermemelidir.
- **FR-226**: Dışa aktarma, FR-206'daki kayıtların hiçbirini değiştirmemelidir.

**Ortak**

- **FR-227**: Pratik, dışa aktarma ve ön seçimli ekleme, başka bir hesabın kaynağı için var olmayan kayıtla aynı yanıtı (404) vermelidir.
- **FR-228**: Bu özelliğin eklediği kullanıcıya görünen bütün metinler arayüz dilinde (İngilizce) ve çeviri dosyalarında olmalıdır; dışa aktarılan talimat da İngilizcedir.
- **FR-229**: Eklenen her ekran 375px genişlikte yatay kaydırma olmadan kullanılabilmeli ve dokunma hedefleri mobil ölçütleri karşılamalıdır.

### Key Entities

- **Kaynak (Source)**: Var olan varlık. Pratiğin ve dışa aktarmanın kapsamını belirler. Yeni alan eklenmez.
- **Pasaj (Highlight)**: Var olan varlık. Pratikte gösterilir, dışa aktarmada yer alır. Pratik, görülme alanlarını değiştirmez.
- **Mastery kartı**: Var olan varlık. Yalnız dışa aktarmada (soru-cevap olarak) yer alır; pratikte gösterilmez, planı değişmez.
- **Pratik seti**: Kalıcı olmayan, yalnız o oturum boyunca var olan pasaj listesi. Tekrar geçmişine yazılmaz.
- **Dışa aktarma metni**: İstek anında kaynaktan üretilen, saklanmayan metin.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-201**: Bir pratik baştan sona tamamlandıktan sonra seri, seri günleri, günlük tur sayısı, tekrar geçmişi, pasajların son görülme zamanı ve görülme sayısı ile kartların planı pratikten öncekiyle birebir aynıdır (fark: sıfır kayıt).
- **SC-202**: Kullanıcı kaynak sayfasından pratiğin ilk kartına en fazla bir dokunuşla ulaşır.
- **SC-203**: Aynı kaynağa art arda beş pasaj eklemek, ilkinden sonra sıfır kaynak seçimi gerektirir.
- **SC-204**: Dışa aktarılan metin, kaynağın aktif pasajlarının ve aktif kartlarının %100'ünü, çöpe atılmış pasajların ve emekli kartların %0'ını içerir.
- **SC-205**: Kopyalama ve indirme, iOS Safari ve Chrome/Android'de 375px genişlikte çalışır.
- **SC-206**: Başka bir hesabın kaynağına yönelik pratik, dışa aktarma ve ön seçimli ekleme isteklerinin tamamı 404 ile sonuçlanır.
- **SC-207**: Kullanıcı tarafındaki toplam varlık bütçesi 150KB sınırının altında kalır.
- **SC-208**: Yeni ekranlar mobil erişilebilirlik denetiminde 95 ve üstü alır.

## Assumptions

- "Kategori" = kaynak. Uygulamada ayrı bir kategori veya etiket kavramı yoktur; etiketler hâlâ kapsam dışıdır.
- "Kart eklemek" = pasaj eklemek. Mastery kartı bir pasajdan oluşturulur ve kaynak seçimi gerektirmez; US2 onu değiştirmez.
- Pratik seti boyutu kullanıcının ayarlardaki tekrar boyutudur; ayrı bir ayar eklenmez.
- Pratik mastery kartı göstermez: kartların değeri zamanlamalarındadır ve pratik plana dokunmamalıdır. Kaynağın kartlarını çalışmak isteyen kullanıcı için kartlar LLM metninde yer alır.
- Pratik kaydedilmez; yarıda kalan pratik devam ettirilmez. Bu, pratiğin "iz bırakmaz" kuralının doğal sonucudur.
- Uygulama hiçbir yapay zekâ hizmetine bağlanmaz ve hiçbir veri göndermez; v1'deki "yapay zekâ özellikleri kapsam dışı" kararı korunur. Bu özellik yalnız metin hazırlar.
- Dışa aktarılan metin, pasajların ham (kullanıcının yazdığı) işaretleme biçimini kullanır; işlenmiş görünüm değil.
- Dışa aktarma için ayrı bir boyut sınırı konmaz; ölçek varsayımı (kaynak başına makul pasaj sayısı) v1'deki gibidir.
- Şema değişikliği beklenmez. Planlama sırasında gerekli olduğu ortaya çıkarsa Ana Yasa V gereği önce sorulur.
- Yeni bağımlılık beklenmez; pano ve dosya indirme mevcut yığınla yapılır.
