# Kontrat — Tekrar Kartı Aksiyon Ucu (JSON)

Tekrar ekranı optimistic çalışır (Ana Yasa m. II, FR-041): arayüz önce güncellenir,
istek arka planda gider. Bu uç ritüelin tek sıcak yolu olduğu için küçük ve idempotenttir.

## POST `/review/items/{item}/action`

**Middleware**: `auth`, `ensure.active`. `{item}` route model binding ile çözülür;
başkasının kalemi 404 (FR-010).

### İstek gövdesi

```json
{
  "action": "keep",
  "mastery_feedback": null,
  "favorite": false,
  "source_frequency": null,
  "client_acted_at": "2026-08-22T06:14:03Z"
}
```

| Alan | Tip | Kural |
|---|---|---|
| `action` | `keep` \| `discard` | zorunlu (FR-038) |
| `mastery_feedback` | `sooner` \| `later` \| `someday` \| `learned` \| null | yalnızca `item_type=mastery` (FR-046) |
| `favorite` | bool \| null | true ise pasaj favoriye alınır (FR-038) |
| `source_frequency` | `never`…`very_often` \| null | kartın kaynağının sıklığını değiştirir (FR-038); bir sonraki tekrardan itibaren etkili (FR-028) |
| `client_acted_at` | ISO-8601 \| null | çevrimdışı kuyruktan gelen geç istekler için |

### Yan etkiler

| Koşul | Etki |
|---|---|
| `action=keep` ve `item_type=highlight` | `shown_count++`, `last_shown_at = now()` (FR-039) |
| `action=discard` | `highlights.is_discarded = true` (silme yok, FR-011) |
| `mastery_feedback` verildi | `MasteryScheduler` aralığı günceller (FR-047..FR-049); `learned` → `status=retired` (FR-050); `sooner` → `struggle_count++` |
| Tüm kalemler işlendi | `reviews.status=completed`, `completed_at`, `StreakDay` yazılır (FR-054, FR-055) |

### Yanıt 200

```json
{
  "item_id": 4211,
  "acted_at": "2026-08-22T06:14:03Z",
  "review": { "remaining": 3, "completed": false },
  "streak": null,
  "mastery": { "half_life_days": 14.0, "due_at": "2026-09-05T06:14:03Z", "hint": null }
}
```

`review.completed = true` olduğunda `streak` doldurulur:

```json
"streak": { "current": 5, "longest": 12, "day": "2026-08-22" }
```

`mastery.hint` yalnızca `struggle_count >= 6` iken doludur (FR-052); sistem kartı
kendiliğinden değiştirmez.

### Idempotency

Aynı kalem için ikinci çağrı **hata değildir**: kalem zaten işlenmişse ilk aksiyon
korunur, mevcut durum 200 ile döner. Çevrimdışı kuyruğun (R-10) tekrar denemesi bu
sayede güvenlidir. Yan etkiler (sayaç artışı, aralık güncellemesi) yalnızca ilk çağrıda
uygulanır.

### Hatalar

| Durum | Gövde |
|---|---|
| 404 | başkasının kalemi veya yok |
| 409 | tekrar zaten `completed` ve kalem işlenmemiş (tutarsız istemci durumu) |
| 422 | `{"errors": {"action": ["..."]}}` |
| 429 | hız sınırı (kullanıcı başına dakikada 120) |

## POST `/review/complete`

İstemci son kartı geçtiğinde çağırır. Sunucu işlenmemiş kalem varsa 409 döner; yoksa
tamamlanmayı ve seri gününü yazar (idempotent — ikinci çağrı aynı sonucu döner).

```json
{ "completed_at": "2026-08-22T06:15:40Z", "streak": { "current": 5, "longest": 12 } }
```
