# Phase 0 — Araştırma ve Kararlar

**Feature**: 001-daily-highlight-review | **Date**: 2026-08-22

Yığın Ana Yasa m. II ile sabit olduğu için burada teknoloji seçimi yapılmaz; kararlar
mekanizma, formül ve idempotency düzeyindedir. Her kalem: Karar / Gerekçe / Elenen
alternatifler.

---

## R-01 — Yerel gün ve 04:00 sınırı

**Karar**: Tek servis `LocalDayResolver`. Verilen bir UTC anı ve `users.timezone` için
yerel günü `localDayFor(CarbonImmutable $at, User $u): CarbonImmutable` şeklinde döner:
kullanıcının zaman dilimine çevir, saat < 04:00 ise bir gün geri al, tarih kısmını dön.
Ters yön `windowForLocalDay()` UTC aralığı verir. Tabloda tarih `DATE` tipinde
`review_date` / `day` kolonları olarak saklanır; zaman damgaları UTC `DATETIME`.

**Gerekçe**: Ana Yasa m. IV `now()`/`today()` karıştırılmasını yasaklıyor. Tek geçit
noktası olması, streak (FR-055), tekrar tekilliği (FR-025) ve e-posta zamanlaması
(FR-059) arasındaki tutarlılığı garanti eder. Geçmiş `streak_days` kayıtları saklanmış
tarih olduğu için zaman dilimi değişiminde geriye dönük değişmez (Edge Case).

**Elenenler**: Her serviste ad-hoc Carbon aritmetiği (üç yerde üç ayrı hata); DB
tarafında `CONVERT_TZ` (MySQL tz tablolarının yüklü olmasına bağımlılık).

---

## R-02 — Kaynak sıklık basamakları → sayısal ağırlık ⚠️ İNSAN ONAYI GEREKLİ

**Karar (öneri)**: `config('byagain.sampling.source_weights')` altında altı basamak:

| Basamak | Anahtar | Önerilen değer |
|---|---|---|
| Hiç gösterme | `never` | `0.0` (havuzdan tamamen çıkar) |
| Nadiren | `rare` | `0.25` |
| Az | `low` | `0.5` |
| Normal (varsayılan) | `normal` | `1.0` |
| Sık | `often` | `2.0` |
| Çok sık | `very_often` | `4.0` |

**Gerekçe**: İkiye katlanan ölçek "çok sık" ile "nadiren" arasında 16× fark üretir; FR-028
ve SC-010'un "belirgin biçimde daha sık" ölçütünü karşılar. `never` ayrı bir çarpan değil,
sorgu düzeyinde `WHERE` filtresidir (FR-030) — 0 ile çarpmak sıralamada belirsizlik bırakır.

**Durum**: Sayısal değerler algoritma sabitidir (Ana Yasa m. V). Yukarıdaki tablo
**önerdir**; insan onayı alınmadan `config/byagain.php` içine yazılmaz.

**Elenenler**: Doğrusal ölçek (1..6 → normalize) — uçlar arası fark yetersiz; kullanıcı
"çok sık" dediğinde farkı hissetmiyor.

---

## R-03 — Yenilik çarpanının penceresi ⚠️ İNSAN ONAYI GEREKLİ

**Karar (öneri)**: Yenilik çarpanı `1.5` (spec'te sabit). Uygulanma koşulu: pasaj **hiç
gösterilmemişse** (`last_shown_at IS NULL`) VEYA `created_at` son `14` gün içindeyse.

**Gerekçe**: Spec çarpanın değerini veriyor ama penceresini vermiyor. "Hiç
gösterilmemiş" koşulu tek başına yeni eklenen ama bir kez gösterilmiş pasajın hemen
sıradanlaşmasına yol açar; 14 gün, iki haftalık okuma seansının havuza yerleşme süresi.

**Durum**: `14` gün algoritma sabitidir — insan onayı bekler
(`config('byagain.sampling.novelty_window_days')`).

**Elenenler**: Yalnızca `last_shown_at IS NULL` koşulu (fazla keskin); yaşla sürekli
sönümlenen novelty (ikinci bir üstel terim, açıklanabilirliği düşürür).

---

## R-04 — Ağırlıklı örnekleme sorgusu (FR-027, SC-004)

**Karar**: Tek `SELECT` ile aday havuzu + ağırlık, sonra PHP tarafında kota uygulaması.

```
weight = source_weight
       * (1 - EXP(-days_since_last_shown / 21))     -- cooldown, τ=21
       * (novelty ? 1.5 : 1.0)
       / (equal_source_weighting ? source_highlight_count : 1)
```

Örnekleme, ağırlıklı rastgele seçim için üstel atlama (exponential jump) yöntemiyle:
`ORDER BY -LOG(RAND()) / weight ASC LIMIT k` — ağırlıkla orantılı yer değiştirmesiz
örnekleme (Efraimidis–Spirakis A-Res'in eşdeğeri). `k`, kota nedeniyle istenen kart
sayısının 3 katı alınır; kaynak kotası (FR-033) PHP tarafında sırayla uygulanır ve kota
dolan kaynağın kalan adayları atlanır.

`days_since_last_shown` için `last_shown_at IS NULL` → cooldown terimi `1.0`.

Bu ifadeler Ana Yasa m. III'ün izin verdiği "SPEC 4.1/4.2'deki sabit matematiksel
ifadeler" kapsamındadır: `whereRaw`/`orderByRaw` sabit metin, tüm değişkenler binding.

**Performans**: Filtre indeksleri `highlights(user_id, is_discarded, last_shown_at)` ve
`highlights(source_id)`. 20.000 satırda tam kolon taraması yerine kapsayıcı indeks +
sıralama; hedef <300ms (SC-004). Ölçüm quickstart.md'de bir benchmark testiyle yapılır.

**Elenenler**: Tüm havuzu PHP'ye çekip ağırlıklandırmak (20.000 satır × her gün ×
kullanıcı — bellek ve süre); `ORDER BY RAND()` (ağırlıksız); iki aşamalı ön filtre
(50.000+ pasaj için gerekli, MVP kapsamı dışı — Assumptions'ta bilinen risk).

---

## R-05 — 3 günlük blok ve kaynak kotası (FR-029, FR-033)

**Karar**: Blok, örnekleme sorgusunda `WHERE (last_shown_at IS NULL OR last_shown_at <
:cutoff)` ile uygulanır; `cutoff` = yerel günün başlangıcı − 3 gün (UTC'ye çevrilmiş).
Kota `ceil(n/3)` olarak PHP'de, kaynak bazlı sayaçla.

**Gerekçe**: Blok bir filtre, kota bir seçim kısıtıdır; ikisini aynı SQL'de çözmek
pencere fonksiyonu gerektirir ve okunabilirliği yok eder. SC-009 30 günlük simülasyon
testiyle doğrulanır.

**Elenenler**: `ROW_NUMBER() OVER (PARTITION BY source_id)` ile tek sorguda kota — MySQL 8
destekliyor ama ağırlıklı örnekleme ile birleşince test edilemez bir ifade çıkıyor.

---

## R-06 — Mastery zamanlaması (FR-046..FR-052)

**Karar**: Kartta `half_life_days` (aralık) + `last_reviewed_at` + `due_at` tutulur.
Hatırlanma olasılığı `p(t) = 2^(-Δt / half_life_days)`.

- İlk geri bildirim (`review_count == 0`) mutlak atar: erken → 7, sonra → 14, ara → 28.
- Sonraki geri bildirimler çarpar: erken ×0.5, sonra ×2.0, ara ×3.0.
- Sonuç `clamp(1, 365)`.
- `due_at = last_reviewed_at + half_life_days`.
- "Yeterince öğrendim" → `status = retired`, kayıt silinmez.
- Seçim: vadesi gelmişler arasında `p` en düşük olan önce → pratikte
  `ORDER BY (Δt / half_life_days) DESC`, eşitlikte `RAND()` (FR-051).
- `struggle_count` ("daha erken" sayısı) ≥ 6 → kartta sadeleştirme ipucu gösterilir; sistem
  hiçbir şey değiştirmez (FR-052).

**Gerekçe**: Tek parametre (yarı-ömür) SM-2'nin ease/interval/repetition üçlüsünden hem
daha az durum tutar hem kullanıcının "ne zaman görmek istiyorum" girdisine birebir oturur.
Doğru/yanlış olmadığı için ease faktörünün anlamı zaten yok.

**Elenenler**: SM-2 / Anki algoritması (doğru-yanlış sinyali gerektirir, ürün kararına
aykırı); FSRS (parametre kestirimi için veri ve bağımlılık gerektirir).

---

## R-07 — Tekrar üretiminde idempotency (FR-025, FR-090, SC-015)

**Karar**: `reviews` tablosunda `UNIQUE (user_id, review_date)`. Üretim servisi
`insertOrIgnore` / `firstOrCreate` sonrası `wasRecentlyCreated` kontrolü ile ilerler;
yarış durumunda ikinci istek var olan tekrarı okur. Kart seçkisi aynı transaction içinde
`review_items` olarak yazılır; transaction başarısızsa tekrar da yok sayılır.

**Gerekçe**: Uygulama düzeyi kilit (cache lock) süreç çökmesinde delik bırakır; benzersiz
indeks veritabanı garantisidir. FR-026 (tercih değişikliği bugünü etkilemez) doğrudan
"seçki kalıcı yazılır" kararından gelir.

**Elenenler**: `Cache::lock()` tek başına; advisory lock (`GET_LOCK`) — MySQL bağlantı
ömrüne bağlı.

---

## R-08 — E-posta tekilleştirme ve zamanlama (FR-062, FR-063, SC-013, SC-014)

**Karar**: `email_deliveries` tablosunda `UNIQUE (user_id, dedupe_key)`; anahtar biçimi
`{type}:{local_date}` (ör. `daily:2026-08-22`, `reminder:2026-08-22`). Sıraya alma
`insertOrIgnore` ile; satır eklenemezse iş kuyruğa hiç konmaz. Job çalıştığı anda
durumu **yeniden** okur: tekrar tamamlanmışsa `status = skipped` yazılır ve gönderim
yapılmaz (FR-062). Doğrulanmamış e-posta → `skipped` (FR-008/FR-007).

Zamanlayıcı `DispatchDailyPipeline` her 5 dakikada bir çalışır ve o pencerede yerel
gönderim saati gelen kullanıcıları toplar → SC-013'ün ≤5 dk sapma hedefi.

**Gerekçe**: "Gönderim anında yeniden kontrol" ile "günde en fazla bir" farklı iki
garantidir; birincisi job içinde, ikincisi indekste çözülür.

**Elenenler**: Kullanıcı başına `scheduleAt` ile tek tek iş planlama (1.000 kullanıcı ×
2 e-posta = kuyrukta sürekli bekleyen 2.000 iş, tercih değişince temizlemesi zor).

---

## R-09 — Markdown boru hattı (FR-014..FR-017, SC-012)

**Karar**: Kaynak doğruluk `content_md` (ham metin). Kaydetmede
`MarkdownRenderer::render()` sırasıyla: CommonMark (GFM tablo + strikethrough uzantısı,
`html_input: escape`, `allow_unsafe_links: false`) → HTMLPurifier (beyaz liste:
`h1-h4, p, strong, em, ul, ol, li, blockquote, pre, code, hr, table, thead, tbody, tr,
th, td, a[href|rel|target]`) → `content_html`. Ayrıca arama/önizleme için `content_text`
(düz metin) üretilir. Dış bağlantılara `rel="noopener noreferrer nofollow" target="_blank"`
HTMLPurifier attribute transform'u ile zorunlu eklenir (FR-016).

Blade'de tek istisna:

```blade
{{-- purified: MarkdownRenderer --}}
{!! $highlight->content_html !!}
```

`content_html` her zaman `content_md`'den yeniden üretilebilir → FR-092 için
`highlights:rerender` komutu (chunked, kuyruğa alınabilir).

**Gerekçe**: Çift savunma (escape + purify) SC-012'nin %100 hedefi için gerekli;
CommonMark tek başına `html_input: escape` ile de güvenlidir ama purifier ikinci kapıdır
ve beyaz listeyi tek yerde tutar.

**Elenenler**: Görüntüleme anında render (her kart için CPU, 2 dk ritüelde gereksiz);
yalnızca CommonMark (beyaz liste yönetimi dağılır).

---

## R-10 — Optimistic tekrar ekranı ve çevrimdışı (FR-041, FR-021, FR-087, SC-005)

**Karar**: Tekrar açılışında sunucu tüm kartları tek sayfada gömülü olarak verir (JSON
`<script type="application/json">` bloğu + Blade ile önceden render edilmiş HTML).
`review.js` (vanilla, ~6KB) kart geçişini yerel yapar; her aksiyon önce arayüzü günceller,
sonra `POST /review/items/{item}/action` ile arka planda gönderilir. Başarısız istek
`localStorage` kuyruğuna alınır ve bağlantı gelince tekrar denenir; kullanıcıya net bir
"çevrimdışı" durumu gösterilir.

Editör taslağı (FR-021) `localStorage` içinde `draft:{source_id}` anahtarıyla, 500ms
debounce ile saklanır; başarılı kayıtta temizlenir.

PWA: `manifest.json` + minimal service worker (uygulama kabuğu ve statik varlıklar
önbelleğe alınır; API istekleri network-first). Çevrimdışı açılışta kabuk gelir, durum
bandı gösterilir (SC-016).

**Gerekçe**: Ana Yasa m. II "tekrar ekranı optimistic ve yereldir" der. Kartların önceden
gömülmesi hem <100ms geçiş hem çevrimdışı devam sağlar.

**Elenenler**: Kart başına fetch (ağ gecikmesi ritüeli öldürür); IndexedDB (150KB bütçesi
ve karmaşıklık, MVP için localStorage yeterli).

---

## R-11 — E-posta sağlayıcısı ⚠️ İNSAN ONAYI GEREKLİ

**Karar**: Karar verilmedi. Uygulama `MAIL_MAILER` üzerinden sağlayıcıdan bağımsız yazılır
(Laravel Mail arayüzü). Açılma sinyali (FR-064) sağlayıcı webhook'u ile
`email_deliveries.opened_at` alanına yazılır.

**Neden açık**: Yeni bağımlılık ve dış servis seçimi Ana Yasa m. V uyarınca insana
sorulur. Ayrıca SPF/DKIM/DMARC kurulumu canlıya çıkış ön koşuludur (Assumptions).

**Etki**: Sağlayıcıya özel paket gerekmedikçe (Postmark/SES için resmî Laravel
transport'ları mevcut) kod değişmez; yalnızca `.env.example` + README yapılandırma
tablosu ve bir webhook rotası eklenir.

---

## R-12 — Abonelikten çıkma bağlantısı (FR-065, SC "taklit edilemez")

**Karar**: Laravel imzalı URL (`URL::signedRoute`) + `signed` middleware, süresiz ama
kullanıcı ve e-posta türüne bağlı. Rota `GET /unsubscribe/{user}/{type}`; giriş
gerektirmez, yalnızca ilgili tercihi kapatır ve onay ekranı gösterir.

**Gerekçe**: Uygulama anahtarıyla imzalanır; tahmin edilemez ve sunucuda ek tablo
gerektirmez.

**Elenenler**: Rastgele token tablosu (FR-091 temizliği ve ek şema); `user_id` düz
parametre (IDOR).

---

## R-13 — Kimlik, hız sınırı ve gizlilik (FR-001..FR-009)

**Karar**: Laravel Fortify başsız; view'lar proje içinde Blade. Parola kuralı
`Password::min(10)->uncompromised()` (FR-005, ek karmaşıklık kuralı yok). Giriş ve parola
sıfırlama `RateLimiter` ile (Fortify'ın `login` limiter'ı + `password.reset` için özel
limiter). Parola sıfırlama yanıtı adres kayıtlı olsun olmasın aynı (FR-004) — Laravel'in
varsayılan `ThrottleRequests` + tek tip yanıt.

Hesap silme (FR-008): parola onayı → tüm içerik sert silinir, `users` satırı korunur ama
e-posta `deleted+{hash}@byagain.invalid` ile anonimleştirilir, `status = deleted`.

**Gerekçe**: Ana Yasa "kullanıcı verisi silinmez" kuralının tek istisnası kullanıcının
kendi silme talebidir (FR-008 açık hüküm). Anonimleştirme geri izlenemezliği sağlar.

---

## R-14 — Admin izolasyonu (FR-069..FR-076, SC-018)

**Karar**: Filament v5 paneli `/admin` prefix'inde, `EnsureUserIsAdmin` middleware ile.
Ayrı Vite giriş noktası (`resources/css/admin.css`, Filament kendi varlıklarını yükler);
kullanıcı layout'u yalnızca `resources/js/app.js` + `resources/css/app.css` yükler.
Panel kaynaklarında pasaj tam metni gösterilmez — `content_text` ilk 120 karakter
önizleme kolonu (FR-071). Her admin işlemi `AdminActionLog`'a yazılır; tablo yalnızca
`INSERT` (model `updating`/`deleting` olaylarında istisna fırlatır) → FR-076.

Zamanlayıcı sağlığı: `DispatchDailyPipeline` her turda `cache`/`settings` içine
`last_run_at` yazar; widget iki turdan (10 dk) eski ise belirgin uyarı (FR-074, SC-017).

**Elenenler**: Ayrı admin uygulaması/subdomain (yığın ve dağıtım karmaşıklığı, m. II
tablosunda yok).

---

## R-15 — Varlık bütçesi ve erişilebilirlik (SC-006, SC-007, FR-077..FR-088)

**Karar**: Kullanıcı tarafı JS üç küçük modül (`app.js`, `review.js`, `editor.js`), toplam
hedef <20KB gzip. Tailwind JIT + `content` taraması; kritik CSS tek dosya. Yazı tipi
sistem font yığını (bütçe dışı tutulan yazı tipi indirmesi olmaz). `tokens.css` tasarım
çıktısıdır, kod yazarken değiştirilmez (Ana Yasa m. V).

Erişilebilirlik: dokunma hedefi ≥44×44px, komşu boşluk ≥8px, gövde ≥17px, form alanları
`font-size: 16px+` (iOS otomatik yakınlaştırma engeli), `prefers-reduced-motion` ile
animasyon kapanışı, görünür odak, kontrast ≥4.5:1, ikon düğmelerinde `aria-label`.

Metinler `lang/en/` altında; Blade'e gömülü metin yok (FR-088).

**Elenenler**: Web font indirme (bütçe ve ilk boyama); CSS framework dışı elle yazılmış
sistem (m. II).

---

## Kapanış

Spec'te NEEDS CLARIFICATION işareti yoktu; bu araştırmada açığa çıkan **dört** kalem
Ana Yasa m. V uyarınca insana sorulacak durumdadır (R-02, R-03, R-11 ve spec.md'de
kayıtlı dil kararı çelişkisi). Bunların hiçbiri şema veya ekran tasarımını bloke etmez;
üçü `config/byagain.php` değerlerini, biri belge güncellemesini etkiler.
