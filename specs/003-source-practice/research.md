# Research: Kaynağa Göre Pratik, Sabit Kaynakla Ekleme ve LLM ile Tekrar

**Feature**: `003-source-practice` | **Date**: 2026-10-02

Teknik bağlamda açık soru kalmadı; aşağıdaki kararlar mevcut kod okunarak verildi.
Her kararda dosya ve satır, bugünkü durumu gösterir.

---

## R-301 — Pratik kalıcı mı olsun?

**Decision**: Pratik saklanmaz. Set, `GET` isteğinde çekilir ve sayfaya gömülür;
`reviews` / `review_items` tablolarına satır yazılmaz. Yeni tablo yok.

**Rationale**: Spec'in "hiç iz bırakmaz" kararı (FR-206) en sağlam biçimde, iz
bırakacak bir kayıt hiç oluşturulmayarak sağlanır. `reviews` tablosuna bir `kind`
sütunu eklemek, `ReviewBuilder::find()`'ın, e-posta süpürmesinin, push
kararının (`PushDispatcher::reasonNotToSend`), seri hesabının ve Filament
panelinin her birine "pratik değilse" koşulu eklemeyi gerektirirdi — birini
unutmak, pratiğin seriyi saymasıdır. Kayıt yoksa unutulacak koşul da yok.

**Alternatives considered**:
- `reviews.kind = 'practice'` sütunu: yarım kalan pratiğe devam edilebilirdi, ama
  şema değişikliği (Ana Yasa V) ve yukarıdaki beş okuyucuda koşul gerektiriyor.
  Spec yarım pratiğe devamı istemiyor (Edge Cases).
- Pratiği `localStorage`'da tutmak: sunucu tarafında hiçbir şey kazandırmıyor,
  istemciye yük ekliyor.

## R-302 — Pratik ekranı tekrar ekranını nasıl yeniden kullanır?

**Decision**: `resources/js/review.js` olduğu gibi kullanılır; her kart zaten
kendi `data-action-url`'ini taşıyor (`resources/views/review/show.blade.php:58`)
ve betik eylemi o adrese gönderiyor (`review.js:183`). Pratik kartları
`practice.action` adresini taşır. Betikte tek değişiklik: tamamlama çağrısı
yalnız kök elemanda `data-complete-url` varsa yapılır.

Görünüm tarafında, tekrar ekranındaki pasaj kartı gövdesi (`show.blade.php:69-177`)
ve geri alma çubuğu (`:224-241`) `review/partials/` altına çıkarılır; hem
`review/show` hem yeni `practice/show` bunları kullanır.

**Rationale**: Kaydırma jesti, geri alma penceresi, çevrimdışı kuyruk ve #1
düzeltmesi (kaydırılabilir kutuda jest başlamaz) tek yerde yaşar. İkinci bir
kopya, 002'de düzeltilen hataların pratik ekranında geri gelmesi demektir.

**Bulgu (düzeltilmeli)**: `review.js` son kartta `completeReview()` çağırıyor;
yanıt seri taşımıyorsa `postCompletion()` `fetch(root.dataset.completeUrl)`
yapıyor (`review.js:333-350`). Pratikte `data-complete-url` olmayacağı için bu,
`fetch("undefined")` — sayfaya göreli `/library/sources/5/undefined` — olurdu.
Koruma şart.

**Alternatives considered**:
- Pratik için ayrı `practice.js`: 660 satırlık davranışın kopyası; reddedildi.
- Pratik kartlarına eylem adresi vermemek (yalnız istemcide): çöpe at ve favori
  kalıcı olmalı (FR-208), sunucuya gitmek zorunda.

## R-303 — Pratik eylemi neyi değiştirir?

**Decision**: Yeni `POST /library/sources/{source}/practice/{highlight}`.
Gövde `review.item.action` ile aynı biçimdedir (`action`, `favorite`,
`source_frequency`, `client_acted_at`) ki `review.js` farkı bilmesin. Sunucu
yalnız açık kararları uygular:

| Alan | Etki |
|---|---|
| `action = discard` | `is_discarded = true` (`HighlightWriter::discard`) |
| `favorite = true` | `is_favorite = true` (asla false'a çekmez — günlük tekrarla aynı, `ReviewItemActions.php:100`) |
| `source_frequency` | Kaynağın sıklığı |
| `action = keep` | Hiçbir şey |

`shown_count`, `last_shown_at`, seri, tur: **dokunulmaz** (FR-206). Yanıt
`{"ok": true}`; tekrar eden çağrı aynı sonucu verir, bu yüzden çevrimdışı
kuyruğun yeniden oynatması güvenlidir.

**Rationale**: `ReviewItemActions::recordAction` tam olarak "gösterildi" kaydını
yazan yer (`ReviewItemActions.php:96-97`); pratiğin onu çağırmaması gerekir.
Ortak kısım (çöpe at / favori / sıklık) küçük; yeni bir servis sınıfında
(`PracticeActions`) yazılır.

**Alternatives considered**: Mevcut `highlights.discard` ve `highlights.favorite`
rotalarına `fetch` atmak — ikisi de form rotası, `redirect()->back()` döner,
favori **tersine çevirir** (`toggleFavorite`); kuyruktan yeniden oynatıldığında
favoriyi geri alırdı. Reddedildi.

## R-304 — Rota bağlama: pasaj o kaynağın mı?

**Decision**: `practice.action` rotası `scopeBindings()` ile tanımlanır;
`{highlight}`, `{source}`'un `highlights()` ilişkisinden çözülür. Başka kaynağın
(veya başka hesabın) pasajı 404 döner. `BelongsToUser` scope'u ayrıca geçerli.

**Rationale**: Ana Yasa III — kimlik yalnız URL'deki kayıtta değil, her id'de
sınanır. `IdorTest`'in otomatik süpürmesi yalnız tek parametreli rotaları
tarıyor (`IdorTest.php`, `parameterisedRoutes`); bu iki parametreli rota için
açık test yazılır.

## R-305 — Pratik seti nasıl çekilir?

**Decision**: `$source->highlights()->where('is_discarded', false)->inRandomOrder()->limit($user->review_size)->with('source')->get()`
— yeni `App\Services\Practice\PracticeSampler`.

**Rationale**: FR-203 günlük seçkinin ağırlıklarını açıkça dışarıda bırakıyor;
düz rastgele seçim doğru davranış. Kaynak başına pasaj sayısı yüzlerle sınırlı;
`ORDER BY RAND()` tek kaynakta ucuz. `HighlightSampler` (SPEC 4.1) yeniden
kullanılmaz — onun filtreleri tam olarak istenmeyen şey.

## R-306 — Kaydettikten sonra formda kalma

**Decision**: `HighlightController::store` → `redirect()->route('highlights.create', ['source' => $highlight->source_id])`,
`status` mesajı + `saved_source_id` flash'ı (kaynak sayfası bağlantısı için).
`create()` `?source=` sorgu parametresini okur: kullanıcının (scope), arşivlenmemiş
kaynağıysa ön seçilir; değilse sessizce yok sayılır (FR-215). Kaynak sayfasına
"bu kaynağa pasaj ekle" bağlantısı aynı parametreyle gider.

**Bulgu (düzeltilmeli)**: `resources/views/components/editor/form.blade.php:41`
`@selected(old('source_id', $highlight?->source_id) === $source->id)` — `old()`
oturumdan **string** döner, `$source->id` int'tir; katı karşılaştırma doğrulama
hatasından sonra hiçbir zaman eşleşmez ve kaynak seçimi kaybolur. FR-216 bugün
zaten ihlal ediliyor. Karşılaştırma int'e çevrilir; testi yazılır.

**Taslak etkileşimi**: `editor.js` taslağı `submit` anında siliyor
(`editor.js:61-64`) ve yalnız alan boşsa geri yüklüyor; kayıttan sonra boş form
eski taslağı geri getirmez. Değişiklik gerekmez.

**Alternatives considered**: Son kullanılan kaynağı `users` tablosunda veya
`localStorage`'da hatırlamak — şema değişikliği ya da istemci durumu; spec
yalnız "kaydettikten sonra" istiyor, sorgu parametresi yeterli.

## R-307 — Dışa aktarma metni ve biçimi

**Decision**: Markdown düz metin, yeni `App\Services\Practice\StudyExportBuilder::build(Source): string`.
Sıra (FR-220):

```text
<talimat — lang/en/practice.php, export.instruction>

# <kaynak adı>
<yazar, varsa>

## Passages
### 1
<content_md>
_<location>_       ← varsa (tam biçim: contracts/study-export.md)
…

## Questions        ← yalnız aktif kart varsa
### Q1
**Q:** <question>
**A:** <answer>
```

Kaynak: `content_md` (kullanıcının yazdığı biçim), `content_html` değil. Kartlar:
`status = active` (paused ve retired dışarıda), pasajı çöpe atılmamış olanlar.
Sıralama: pasajlar `id` artan (ekleniş sırası), kartlar pasaj sırasına göre.

**Rationale**: LLM'ler Markdown'ı en iyi okuyan biçim; kod blokları ve tablolar
olduğu gibi korunur (Edge Cases). Talimat İngilizce (Ana Yasa: tek arayüz dili)
ama "pasajların dilinde konuş" der (FR-221).

## R-308 — Panoya kopyalama ve indirme

**Decision**:
- Ekran metni salt-okunur bir `<textarea>` içinde gösterir; bu hem önizleme hem
  pano çalışmazsa elle kopyalama yoludur (FR-223).
- "Copy": yeni küçük modül `resources/js/copy.js` — `navigator.clipboard.writeText`;
  yoksa veya reddedilirse textarea'yı seçer, `document.execCommand('copy')` dener,
  o da olmazsa "seçildi, elle kopyala" der. Vite girdilerine eklenir.
- "Download": ayrı `GET` rota, `response()->streamDownload()` ile
  `text/markdown; charset=UTF-8`, dosya adı `Str::slug($title).'-'.<yerel tarih>.'.md'`;
  slug boşsa `source-<id>`. Laravel ASCII yedek dosya adını kendisi üretir.

**Rationale**: `navigator.clipboard` yalnız güvenli bağlamda (HTTPS veya
localhost) var; telefondan LAN üzerinden `http://` ile denenirken yok olur. Üç
kademeli yedek, kullanıcının hiçbir durumda eli boş kalmamasını sağlar.
`execCommand` kullanımdan kalkmış ama iOS Safari'de hâlâ çalışan tek eşzamanlı yol.

## R-309 — Erişim ve sınırlama

**Decision**: Yeni rotalar `auth` + `ensure.active` grubunda. `practice.show`
`throttle:30,1` (FR-211 — her yükleme bir `ORDER BY RAND()`), `practice.action`
`throttle:120,1` (tekrar ekranıyla aynı), dışa aktarma rotaları `throttle:30,1`.
Boş kaynak (aktif pasaj yok): `practice.show` ve `sources.export` kaynak sayfasına
`status` mesajıyla yönlenir; kaynak sayfası bu eylemleri zaten göstermez.

## R-310 — Bağımlılık ve şema

**Decision**: Yok. Composer/npm paketi eklenmez, migration yazılmaz.
`vite.config.js`'e bir girdi (`copy.js`) eklenir. Varlık bütçesi (150KB) için
`AssetBudgetTest` mevcut; `copy.js` < 1KB beklenir.
