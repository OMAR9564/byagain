# Phase 1 — Veri Modeli

**Feature**: 001-daily-highlight-review | **Date**: 2026-08-22

Ortak kurallar:

- Tüm zaman damgaları UTC `DATETIME`; yerel gün alanları `DATE` (bkz. research.md R-01).
- `user_id` taşıyan her tablo `BelongsToUser` global scope'undan geçer (Ana Yasa m. III).
- Modellerde `$guarded = []` yok; `$fillable` açık liste. `final class` varsayılan.
- Kullanıcı verisi silinmez; gizleme `is_discarded` / `is_archived` / `status` ile.
- Şema değişikliği insan onayına tabidir (m. V) — bu belge önerilen şemadır.

---

## users

Fortify'ın ürettiği alanların üzerine byagain alanları eklenir.

| Alan | Tip | Not |
|---|---|---|
| `id` | bigint PK | |
| `name` | varchar | |
| `email` | varchar UNIQUE | silmede anonimleştirilir (FR-008) |
| `email_verified_at` | datetime? | doğrulanmamışsa ritüel e-postası yok (FR-007) |
| `password` | varchar | min 10 + uncompromised (FR-005) |
| `role` | enum(`user`,`admin`) | default `user` (FR-009) |
| `status` | enum(`active`,`suspended`,`deleted`) | default `active` |
| `timezone` | varchar(64) | IANA; default `UTC` |
| `review_size` | tinyint | 5–15, default 8 (FR-024) |
| `mastery_ratio` | tinyint | 0–100, default 50 (FR-034) |
| `quality_filter_enabled` | bool | default true (FR-031) |
| `equal_source_weighting` | bool | default false (FR-032) |
| `daily_email_enabled` | bool | default true |
| `daily_email_at` | time | default 08:00 (yerel) |
| `reminder_email_enabled` | bool | default true |
| `reminder_email_at` | time | default 20:00 (yerel) |
| `consecutive_unopened_emails` | smallint | default 0 (FR-064) |
| `current_streak` | smallint | default 0 |
| `longest_streak` | smallint | default 0 |
| `last_streak_day` | date? | streak hesabı için (FR-057) |
| timestamps | | |

İndeks: `(status)`, `(role)`.

**Doğrulama**: `review_size` 5..15; `mastery_ratio` 0..100; `timezone` geçerli IANA
kimliği; `daily_email_at`/`reminder_email_at` HH:MM.

---

## sources

| Alan | Tip | Not |
|---|---|---|
| `id` | bigint PK | |
| `user_id` | FK users cascade | |
| `title` | varchar(255) | zorunlu (FR-012) |
| `author` | varchar(255)? | |
| `type` | enum(`book`,`article`,`note`,`podcast`,`course`,`other`) | |
| `frequency` | enum(`never`,`rare`,`low`,`normal`,`often`,`very_often`) | default `normal` (FR-028) |
| `is_archived` | bool | default false (FR-030) |
| `highlights_count` | int | denormalize sayaç; `equal_source_weighting` için |
| timestamps | | |

İndeks: `(user_id, is_archived)`, `(user_id, frequency)`.

**İlişki**: `hasMany(Highlight)`.

**Not**: `frequency` değişimi bir sonraki tekrardan itibaren etkilidir (FR-028) — bugünün
tekrarı zaten `review_items` olarak yazılmış olduğu için ek iş gerekmez.

---

## highlights

| Alan | Tip | Not |
|---|---|---|
| `id` | bigint PK | |
| `user_id` | FK users cascade | scope + sorgu kısayolu |
| `source_id` | FK sources cascade | |
| `content_md` | mediumtext | doğruluk kaynağı (FR-017) |
| `content_html` | mediumtext | purified (FR-015, R-09) |
| `content_text` | mediumtext | düz metin; önizleme ve kalite filtresi |
| `note` | text? | kişisel not (FR-018) |
| `location` | varchar(120)? | sayfa/bölüm (FR-018) |
| `is_favorite` | bool | default false (FR-019) |
| `is_discarded` | bool | default false (FR-013, FR-011) |
| `contains_code` | bool | kalite filtresi istisnası (FR-031) |
| `char_count` | int | `content_text` uzunluğu; filtre için |
| `shown_count` | int | default 0 (FR-039) |
| `last_shown_at` | datetime? | UTC (FR-029, FR-039) |
| timestamps | | |

İndeks (örnekleme sıcak yolu — R-04):
`(user_id, is_discarded, last_shown_at)`, `(source_id, is_discarded)`,
`(user_id, is_favorite)`.

**Doğrulama**: `content_md` zorunlu, ≤ 20.000 karakter. `content_html` asla kullanıcıdan
gelmez — yalnızca `MarkdownRenderer` yazar.

**Durum geçişleri**: `active ⇄ discarded` (`is_discarded`). Silme yok.

---

## mastery_cards

| Alan | Tip | Not |
|---|---|---|
| `id` | bigint PK | |
| `user_id` | FK users cascade | |
| `highlight_id` | FK highlights cascade | kaynak pasaj (FR-044) |
| `type` | enum(`qa`,`cloze`) | |
| `question` | text | cloze'da boşluklu metin |
| `answer` | text | |
| `half_life_days` | decimal(6,2) | 1..365 (FR-049) |
| `last_reviewed_at` | datetime? | |
| `due_at` | datetime? | `last_reviewed_at + half_life_days` |
| `review_count` | int | 0 ise ilk geri bildirim mutlak atar (FR-047) |
| `struggle_count` | int | "daha erken" sayısı; ≥6 → ipucu (FR-052) |
| `status` | enum(`active`,`paused`,`retired`) | `retired` = yeterince öğrendim (FR-050) |
| timestamps | | |

İndeks: `(user_id, status, due_at)`.

**Durum geçişleri**: `active ⇄ paused`; `active → retired` (geri dönüş kullanıcı isteğiyle
mümkün, silme yok).

**Aralık kuralları** (R-06): ilk geri bildirim → 7/14/28 gün; sonrakiler ×0.5 / ×2.0 /
×3.0; sonuç `clamp(1, 365)`.

---

## reviews

| Alan | Tip | Not |
|---|---|---|
| `id` | bigint PK | |
| `user_id` | FK users cascade | |
| `review_date` | date | kullanıcının yerel günü (R-01) |
| `size` | tinyint | üretim anındaki kart sayısı |
| `status` | enum(`pending`,`completed`) | default `pending` |
| `started_at` | datetime? | |
| `completed_at` | datetime? | |
| timestamps | | |

**UNIQUE `(user_id, review_date)`** — FR-025, FR-090, SC-015'in tek garantisi.

İndeks: `(user_id, status)`.

**Durum geçişleri**: `pending → completed`, tüm kalemler işlendiğinde (FR-054); geri
dönüş yok.

---

## review_items

| Alan | Tip | Not |
|---|---|---|
| `id` | bigint PK | |
| `user_id` | FK users cascade | scope kolaylığı |
| `review_id` | FK reviews cascade | |
| `position` | smallint | önce pasajlar, sonra mastery (FR-035) |
| `item_type` | enum(`highlight`,`mastery`) | |
| `highlight_id` | FK highlights nullOnDelete? | `item_type=highlight` |
| `mastery_card_id` | FK mastery_cards nullOnDelete? | `item_type=mastery` |
| `action` | enum(`keep`,`discard`)? | null = henüz işlenmedi (FR-040) |
| `mastery_feedback` | enum(`sooner`,`later`,`someday`,`learned`)? | FR-046 |
| `acted_at` | datetime? | |
| timestamps | | |

**UNIQUE `(review_id, position)`**. İndeks: `(review_id, acted_at)`.

**Kural**: Pasaj sonradan çıkarılsa (`is_discarded`) bile o günkü tekrar bozulmaz — kalem
kaydı ve ilişki korunur (Edge Case).

---

## streak_days

| Alan | Tip | Not |
|---|---|---|
| `id` | bigint PK | |
| `user_id` | FK users cascade | |
| `day` | date | yerel gün (FR-055) |
| `created_at` | datetime | |

**UNIQUE `(user_id, day)`**. Takvim son 90 gün için bu tablodan okunur (FR-056).
Zaman dilimi değişimi geçmiş kayıtları etkilemez (Edge Case).

---

## email_deliveries

| Alan | Tip | Not |
|---|---|---|
| `id` | bigint PK | |
| `user_id` | FK users cascade | |
| `type` | enum(`daily`,`reminder`,`verification`,`password_reset`) | |
| `recipient` | varchar(255) | gönderim anındaki adres |
| `dedupe_key` | varchar(64) | `{type}:{local_date}` (R-08) |
| `status` | enum(`queued`,`sent`,`failed`,`skipped`) | |
| `error` | text? | |
| `sent_at` | datetime? | |
| `opened_at` | datetime? | sağlayıcı webhook'u (FR-064) |
| timestamps | | |

**UNIQUE `(user_id, dedupe_key)`** — FR-063, SC-014.
İndeks: `(type, status)`, `(created_at)` (30 günlük temizlik, FR-091).

**Durum geçişleri**: `queued → sent | failed | skipped`. `skipped` = gönderim anında
tekrar tamamlanmış veya e-posta doğrulanmamış (FR-062).

---

## admin_action_logs

| Alan | Tip | Not |
|---|---|---|
| `id` | bigint PK | |
| `admin_id` | FK users restrict | kim |
| `action` | varchar(64) | ne (`suspend_user`, `resend_email`, …) |
| `subject_type` / `subject_id` | morph | hangi kayıt |
| `context` | json? | ek bilgi |
| `created_at` | datetime | ne zaman |

**Değiştirilemez**: model `updating` ve `deleting` olaylarında istisna fırlatır;
`updated_at` kolonu yoktur (FR-076).

---

## settings (tekil yapılandırma)

Bakım modu, kayıt açık/kapalı, varsayılan tekrar boyutu (FR-075) ve zamanlayıcı
`last_run_at` (FR-074) için `key`/`value` (json) tablosu. Kullanıcıya ait değildir,
scope'suzdur.

---

## İlişki özeti

```
User 1─* Source 1─* Highlight 1─* MasteryCard
User 1─* Review 1─* ReviewItem *─1 Highlight | MasteryCard
User 1─* StreakDay
User 1─* EmailDelivery
User(admin) 1─* AdminActionLog
```

## Örnekleme için türetilmiş değerler (saklanmaz)

| Değer | Kaynak |
|---|---|
| `days_since_last_shown` | `NOW() - last_shown_at`, null ise cooldown = 1.0 |
| `cooldown` | `1 - EXP(-days / 21)` (τ config'ten) |
| `novelty` | `last_shown_at IS NULL` veya `created_at ≥ now - N gün` → 1.5 |
| `source_weight` | `sources.frequency` → config eşlemesi (R-02, onay bekliyor) |
| `recall_probability` | `2^(-Δt / half_life_days)` (mastery sıralaması) |
