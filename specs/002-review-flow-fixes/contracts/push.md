# Kontrat — Tarayıcı Bildirimi (Web Push)

Issue #3. Rota adları ve yolları İngilizce (spec.md "Dil" kararı). Tüm uçlar
`auth` + `ensure.active` altında; `BelongsToUser` kapsamı sayesinde başkasının
aboneliğine erişim 404 döner.

## HTTP rotaları

| Metot | Yol | Ad | Not |
|---|---|---|---|
| POST | `/push/subscriptions` | `push.subscribe` | abonelik oluşturur veya tazeler; `throttle:20,1` |
| DELETE | `/push/subscriptions` | `push.unsubscribe` | gövdedeki `endpoint`'i kaldırır; `throttle:20,1` |

Bildirim aç/kapa tercihi ayrı bir uç değildir: mevcut `PATCH /settings`
(`settings.update`) `push_enabled` alanını da taşır.

### `POST /push/subscriptions`

İstek (JSON):

```json
{
  "endpoint": "https://…",
  "keys": { "p256dh": "…", "auth": "…" },
  "content_encoding": "aes128gcm"
}
```

Doğrulama `StorePushSubscriptionRequest` içindedir (alan kuralları:
`data-model.md`). Yanıtlar:

| Kod | Ne zaman | Gövde |
|---|---|---|
| `201` | yeni abonelik yazıldı | `{"status":"subscribed"}` |
| `200` | aynı `endpoint` tazelendi | `{"status":"subscribed"}` |
| `422` | doğrulama hatası | Laravel standart hata gövdesi |
| `503` | VAPID anahtarları yapılandırılmamış | `{"message": …}` — arayüz anahtarı devre dışı gösterir |

Başka bir kullanıcıya ait `endpoint` gelirse kayıt **devredilir**: satırın
`user_id`'si isteği yapan kullanıcı olur. Aynı telefonu iki hesabın kullandığı
durumda tarayıcı tek uç verir; iki satır tutmak, bildirimi yanlış hesaba
göndermek demektir.

### `DELETE /push/subscriptions`

İstek: `{"endpoint": "https://…"}`. Yanıt her durumda `204` — var olmayan bir
aboneliği silmek hata değildir (istemci `unsubscribe()` sonrası çağırır).

## Service worker sözleşmesi (`public/sw.js`)

İki yeni dinleyici; mevcut önbellek stratejileri değişmez. `VERSION` `v2`'ye
yükseltilir, yoksa kurulu service worker bu olayları hiç duymaz.

**`push` olayı** — payload JSON:

```json
{
  "title": "…",
  "body": "…",
  "url": "/review",
  "tag": "byagain-nudge-2026-08-24"
}
```

- `tag` aynı gün için sabittir: ikinci bir bildirim gelse bile ekranda tek
  bildirim durur.
- Payload **pasaj içeriği taşımaz** (FR-148). Metinler sunucuda `lang/en/`
  içinden çözülür; `sw.js` hiçbir kullanıcı metnini kendi içinde tutmaz
  (Ana Yasa: kullanıcıya görünen metin JS'e gömülmez).
- Payload çözülemezse bildirim yine gösterilir: sabit başlık, `/review` bağlantısı.

**`notificationclick` olayı**: bildirim kapatılır; açık bir byagain sekmesi
varsa ona odaklanılır (`clients.matchAll` + `focus`), yoksa `payload.url`
açılır (`clients.openWindow`).

## Gönderim akışı

```text
byagain:dispatch-daily (5 dakikada bir)
  └─ yerel saat == daily_email_at + nudge_delay_minutes ise
       └─ PushDispatcher::queueReviewNudge(user, localDay)
            ├─ push_enabled false            → atla (kayıt açılmaz)
            ├─ o gün 'daily' e-posta sent değil → atla (FR-144)
            ├─ round 1 yok veya tamamlanmış   → atla (FR-142)
            ├─ abonelik yok                   → atla
            └─ insertOrIgnore(push_deliveries) kazandıysa → SendReviewNudgePush::dispatch()
                 └─ işçi: durumu YENİDEN okur, hâlâ uygunsa gönderir
```

İşçinin gönderim anındaki kararları:

| Bulgu | Sonuç |
|---|---|
| Tekrar bu arada tamamlandı | `skipped`, sebep `review_completed` |
| Ayar bu arada kapandı | `skipped`, sebep `disabled` |
| Abonelik kalmadı | `skipped`, sebep `no_subscription` |
| En az bir abonelik `201/204` döndü | `sent`, `sent_at` yazılır, `last_used_at` tazelenir |
| Abonelik `404`/`410` döndü | o abonelik **silinir**; başkası kaldıysa gönderim sürer |
| Ağ/kütüphane hatası | `failed`, `error` yazılır; iş yeniden denenmez (ertesi gün yeni karar) |

Tekillik `UNIQUE (user_id, dedupe_key)` ile veritabanında sağlanır — süpürme
üst üste binse de kullanıcı günde bir bildirim alır (FR-143).

## Testler

**Feature** (`tests/Feature/Push/`):

- Abonelik oluşturma, tazeleme, devretme, silme; başkasının aboneliğine 404.
- Anahtar yokken `503`; doğrulama hataları `422`.
- Süpürme: pencere içinde tekrar tamamlanmamışsa tam bir `push_deliveries`
  satırı ve tam bir kuyruk işi; ikinci süpürme ikinci satır açmaz.
- Tamamlanmış tekrar, kapalı ayar, gönderilmemiş günlük e-posta → gönderim yok.
- `410` dönen abonelik gönderim sonrası tabloda yok.

**Unit** (`tests/Unit/Push/`): `PushDispatcher` karar sırası; `WebPushSender`
sahte (fake) ile çağrılır — testte gerçek ağ yok.

Zaman içeren her testte `Carbon::setTestNow()`; kullanıcı yerel günü
`LocalDayResolver` üzerinden kurulur.
