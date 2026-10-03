# Data Model: Kaynağa Göre Pratik, Sabit Kaynakla Ekleme ve LLM ile Tekrar

**Feature**: `003-source-practice` | **Date**: 2026-10-02

**Şema değişikliği yok.** Migration yazılmaz, sütun eklenmez (research.md R-301,
R-310). Bu belge, var olan varlıkların bu özellikte nasıl okunduğunu ve hangi
alanların **yazılmadığını** kaydeder — "iz bırakmaz" kuralı bir veri kuralıdır.

---

## Okunan varlıklar

### Source (`sources`)

| Alan | Kullanım |
|---|---|
| `id`, `user_id` | Rota bağlama; `BelongsToUser` scope'u başka hesabı 404 yapar |
| `title`, `author` | Pratik başlığı, dışa aktarma başlığı, dosya adı (slug) |
| `is_archived` | Pratik ve dışa aktarmayı **engellemez**; ön seçimli eklemeyi engeller (FR-215) |
| `frequency` | Pratiği engellemez (`never` dahil). Pratik kartındaki sıklık seçicisinin başlangıç değeri |

### Highlight (`highlights`)

| Alan | Kullanım |
|---|---|
| `source_id` | Pratik ve dışa aktarmanın kapsamı; `practice.action`'da kapsamlı bağlama |
| `is_discarded` | `false` olanlar "aktif" — pratik ve dışa aktarma yalnız bunları alır |
| `content_md` | Dışa aktarmada pasaj metni (ham biçim) |
| `content_html` | Pratik kartında görüntü (mevcut `x-highlight-content`) |
| `location` | Dışa aktarmada pasajın altında, varsa |
| `is_favorite` | Pratik kartında favori durumu |

### MasteryCard (`mastery_cards`)

| Alan | Kullanım |
|---|---|
| `highlight_id` | Kaynağa bağlanma yolu (kart → pasaj → kaynak) |
| `status` | Yalnız `active` dışa aktarılır; `paused`, `retired` dışarıda |
| `question`, `answer` | Dışa aktarmada soru-cevap |

### User (`users`)

| Alan | Kullanım |
|---|---|
| `review_size` | Pratik setinin boyutu (FR-202) |
| `timezone` | Dışa aktarma dosya adındaki yerel tarih (`LocalDayResolver`) |

---

## Yazılan alanlar — tam liste

Pratik eylemi (`practice.action`) yalnız bunları yazabilir:

| Varlık | Alan | Koşul |
|---|---|---|
| Highlight | `is_discarded` → `true` | `action = discard` |
| Highlight | `is_favorite` → `true` | `favorite = true` (asla `false`'a çekmez) |
| Source | `frequency` | `source_frequency` gönderildiyse, geçerli değerse |

Kaydetme akışı (`highlights.store`) değişmez; yalnız yönlendirme hedefi değişir.

Dışa aktarma hiçbir şey yazmaz.

## Yazılmayan alanlar — korunacak değişmezler (SC-201)

Pratik veya dışa aktarma sonrası bunlar **birebir aynı** kalmalıdır; testler bu
listeyi önce/sonra anlık görüntüsüyle karşılaştırır:

- `highlights.shown_count`, `highlights.last_shown_at`
- `reviews` ve `review_items` satır sayısı ve içeriği
- `streak_days` satır sayısı; `users` üzerindeki seri önbellek sütunları
- `mastery_cards.half_life_days`, `last_reviewed_at`, `due_at`, `review_count`, `struggle_count`
- `email_deliveries`, `push_deliveries` satır sayısı

---

## Kalıcı olmayan yapılar

### Practice set

`PracticeSampler::draw(User, Source): Collection<Highlight>` — istek anında
üretilir, sayfaya gömülür, saklanmaz. Boyut `min(review_size, aktif pasaj sayısı)`.

### Study export

`StudyExportBuilder::build(Source): string` — istek anında üretilen Markdown.
Biçim research.md R-307'de. Sayılar (`passages`, `cards`) ekranda gösterilmek
üzere ayrıca döner: `StudyExportBuilder::counts(Source): array{passages: int, cards: int}`.
