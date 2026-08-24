# Phase 1 — Veri Modeli: Tekrar Akışı Düzeltmeleri ve Tarayıcı Hatırlatması

**Feature**: `002-review-flow-fixes` · **Tarih**: 2026-08-24

#1, #2 ve #4 hiçbir şema değişikliği istemez — üçü de davranış ve düzen
sorunudur. Aşağıdaki her şey yalnızca #3 (tarayıcı bildirimi) içindir.

Ana Yasa III: çalışmış hiçbir migration düzenlenmez; her değişiklik yeni
migration ile gelir ve `down()` yazılır.

---

## Yeni tablo — `push_subscriptions`

Bir kullanıcının bir tarayıcı/cihazının bildirim alma yetkisi. Kullanıcı birden
çok cihazdan izin verebilir (FR-153).

| Sütun | Tip | Not |
|---|---|---|
| `id` | `bigIncrements` | |
| `user_id` | `foreignId` → `users` | `cascadeOnDelete`; `BelongsToUser` kapsamı buradan çalışır |
| `endpoint` | `string(512)` | Tarayıcının verdiği push ucu. **UNIQUE** |
| `public_key` | `string(255)` | Abonelikteki `p256dh` |
| `auth_token` | `string(255)` | Abonelikteki `auth` |
| `content_encoding` | `string(32)` | Varsayılan `aes128gcm` |
| `user_agent` | `string(255)`, nullable | Kullanıcının kendi cihazını ayırt etmesi için |
| `last_used_at` | `timestamp`, nullable | Son başarılı gönderim |
| `created_at` / `updated_at` | `timestamps` | |

**İndeksler**: `unique(endpoint)`, `index(user_id)`.

**Kurallar**:

- Aynı `endpoint` ikinci kez gelirse yeni satır açılmaz; mevcut satır güncellenir
  (tarayıcı aboneliği yeniler, uç aynı kalır).
- Gönderimde `404`/`410` alınan abonelik **silinir** (FR-150). Bu kullanıcı
  içeriği değil, cihazın geri çektiği bir yetkidir; Ana Yasa III'ün "kullanıcı
  verisi silinmez" kuralı highlight'lar içindir.
- Kullanıcı ayarı kapatırsa (`push_enabled = false`) satırlar silinmez, gönderim
  yapılmaz — anahtarı geri açan kullanıcı yeniden izin vermek zorunda kalmaz.

**Doğrulama** (`StorePushSubscriptionRequest`): `endpoint` zorunlu, `https://`
ile başlayan geçerli URL, ≤512 karakter; `public_key` ve `auth_token` zorunlu,
base64url karakter kümesi; `content_encoding` beyaz listeden
(`aes128gcm`, `aesgcm`).

---

## Yeni tablo — `push_deliveries`

Bir gönderim denemesi ve sonucu. `email_deliveries` ile aynı desen: tekillik
uygulamanın değil, veritabanının garantisi (`MailDispatcher` sınıf yorumu).

| Sütun | Tip | Not |
|---|---|---|
| `id` | `bigIncrements` | |
| `user_id` | `foreignId` → `users` | `cascadeOnDelete` |
| `type` | `string(32)` | Şimdilik tek değer: `nudge` |
| `dedupe_key` | `string(64)` | `nudge:YYYY-MM-DD` (kullanıcının yerel günü) |
| `status` | `enum` | `queued` · `sent` · `failed` · `skipped` |
| `error` | `string(255)`, nullable | Atlama nedeni de buraya yazılır |
| `sent_at` | `timestamp`, nullable | |
| `created_at` / `updated_at` | `timestamps` | |

**İndeksler**: `unique(user_id, dedupe_key)`, `index(user_id, status)`.

**Durum geçişleri**:

```text
queued ──► sent      (en az bir aboneliğe teslim edildi)
queued ──► skipped   (tekrar bu arada tamamlandı / abonelik kalmadı / ayar kapandı)
queued ──► failed    (kütüphane veya ağ hatası; abonelik geçerli kaldı)
```

`skipped` kaydı silinmez: "neden bildirim gelmedi" sorusunun cevabı odur
(`MailDispatcher::markSkipped` ile aynı gerekçe).

---

## Değişen tablo — `users`

| Sütun | Tip | Not |
|---|---|---|
| `push_enabled` | `boolean`, default `false` | Varsayılan **kapalı**: izin istenmeden hiçbir şey açılmaz (FR-145) |

Yeri: `reminder_email_at` sütunundan sonra — e-posta tercihlerinin yanında.
Bildirimin 60 dakikalık gecikmesi kullanıcı tercihi değildir; ürün sabiti olarak
`config/byagain.php` içinde durur (Ana Yasa V).

---

## Değişmeyen ama yeniden okunan modeller

- **`Review`** — tamamlanma kararı yalnızca `status`, `round` ve `review_date`
  üzerinden verilir; yeni sütun yok. Bildirim gönderimi round 1'e bakar
  (`ReviewBuilder::find()`), ek turlara değil.
- **`EmailDelivery`** — bildirim, o gün `daily` türünde `sent` bir kayıt olup
  olmadığına bakar (FR-144). Bu tabloya hiç yazmaz.
- **`User`** — yeni ilişkiler: `pushSubscriptions()` (hasMany),
  `pushDeliveries()` (hasMany).

---

## Yapılandırma (şema değil, ama veri kararı)

`config/byagain.php` içine yeni blok:

```text
'push' => [
    // Günlük e-postadan sonra kaç dakika beklenir (FR-141).
    'nudge_delay_minutes' => 60,

    // Yerel gün başına kullanıcıya giden en fazla bildirim (FR-143).
    'max_per_day' => 1,
],
```

`config/services.php` içine `vapid` bloğu: `public_key`, `private_key`,
`subject` — üçü de `.env`'den okunur, değerleri insan girer (R-207).
