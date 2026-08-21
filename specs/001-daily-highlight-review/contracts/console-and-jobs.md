# Kontrat — Konsol Komutları, Zamanlayıcı ve Kuyruk İşleri

## Zamanlayıcı

| Komut | Sıklık | Sorumluluk |
|---|---|---|
| `byagain:dispatch-daily` | her 5 dakika (`withoutOverlapping`) | FR-089, FR-090, SC-013 |
| `byagain:prune` | günlük 03:30 UTC | FR-091 |

## `php artisan byagain:dispatch-daily`

**Seçenekler**: `--user=`, `--dry-run`, `--now=` (test için sanal an).

**Akış** (her tur):

1. `settings.scheduler_last_run_at = now()` yaz (FR-074, SC-017).
2. Bu 5 dakikalık pencerede yerel `daily_email_at` saati gelen, `active`, e-postası
   doğrulanmış ve `daily_email_enabled` olan kullanıcıları topla.
3. Her kullanıcı için `ReviewBuilder::buildFor($user, $localDay)`:
   - `reviews` satırını `(user_id, review_date)` benzersizliğiyle oluştur (R-07).
   - Uygun pasaj yoksa tekrar **oluşturma** ve e-posta sıraya **alma** (FR-037, US2-7).
4. Tekrar oluştuysa `email_deliveries` satırını `dedupe_key = daily:{local_day}` ile
   `insertOrIgnore`; eklenebildiyse `SendDailyReviewEmail` kuyruğa (FR-067).
5. Yerel `reminder_email_at` saati gelen kullanıcılar için: bugünün tekrarı `pending` ise
   `dedupe_key = reminder:{local_day}` ile aynı yöntemle `SendEveningReminderEmail`.
   `consecutive_unopened_emails >= 5` ise hatırlatma haftada 1'e düşer (FR-064).

**Garantiler**: Komut aynı anda iki kez çalışsa bile yinelenen tekrar veya yinelenen
e-posta üretilmez (FR-090) — garanti uygulama mantığında değil, benzersiz indekstedir.

**Çıktı** (`--dry-run` ile aynı biçim): işlenen kullanıcı sayısı, üretilen tekrar,
sıraya alınan daily/reminder sayısı, atlanan sayısı ve nedenleri.

## `php artisan byagain:prune`

- Süresi geçmiş doğrulama/parola sıfırlama kayıtları
- 30 günden eski `email_deliveries` satırları
- Eski `failed_jobs` kayıtları

Kullanıcı içeriğine (kaynak, pasaj, kart, tekrar) **dokunmaz** (Ana Yasa m. III).

## `php artisan byagain:rerender-highlights`

FR-092. `content_md` → `content_html` + `content_text` yeniden üretimi. Chunk'lı,
`--user=` ve `--dry-run` destekli; ham metne dokunmaz.

## `php artisan byagain:promote-admin {email}`

İlk yönetici hesabının kurulum sırasında elle yetkilendirilmesi (Assumptions). Arayüzden
rol yükseltme yoktur.

---

## Kuyruk işleri (`database` sürücüsü)

| İş | Girdi | Yeniden deneme |
|---|---|---|
| `SendDailyReviewEmail` | `EmailDelivery` id | `tries=5`, `backoff=[60,300,900,3600,10800]` (FR-067) |
| `SendEveningReminderEmail` | `EmailDelivery` id | aynı |

**Ortak sözleşme** (her iki iş):

1. Çalışma anında durumu **yeniden oku**.
2. Kullanıcı `suspended`/`deleted`, e-posta doğrulanmamış veya ilgili tercih kapalıysa →
   `status = skipped`, gönderim yok (FR-007, FR-062).
3. Hatırlatma özel: tekrar bu arada `completed` olduysa → `status = skipped` ve neden
   kaydedilir (FR-062, SC-014).
4. Gönderim başarılı → `status = sent`, `sent_at`.
5. Son denemede de başarısız → `status = failed`, `error` doldurulur (FR-068).

`failed()` kancası her zaman `status = failed` yazar; sessiz kaybolan gönderim olmaz.
