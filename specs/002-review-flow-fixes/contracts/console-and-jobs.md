# Kontrat — Konsol Komutları ve İşler (bu özellik için)

`specs/001-daily-highlight-review/contracts/console-and-jobs.md` yürürlükte
kalır; burada yalnızca değişen ve eklenen yüzey var.

## Değişen — `byagain:dispatch-daily`

İmza aynı (`--user`, `--now`, `--dry-run`). Komut üçüncü bir pencere kazanır:

| Pencere | Yerel saat | Yapılan iş |
|---|---|---|
| Günlük | `users.daily_email_at` | değişmedi |
| Hatırlatma (e-posta) | `users.reminder_email_at` | değişmedi |
| **Bildirim** | `daily_email_at` + `config('byagain.push.nudge_delay_minutes')` | **yeni** — `PushDispatcher::queueReviewNudge()` |

Gün taşması: `daily_email_at` 23:30 ve gecikme 60 dakika ise pencere ertesi
takvim gününe düşer. Karşılaştırma yerel saat üzerinden yapılır ve gönderim
kararı **e-postanın ait olduğu yerel güne** bağlanır — `dedupe_key` o günün
tarihini taşır, ertesi günün değil.

`--dry-run` bildirim için de hiçbir şey yazmaz ve kuyruğa iş atmaz; rapor
satırı `push_queued=` sayacıyla büyür.

Komut, mevcut sözleşmesini korur: üst üste çalışması zararsızdır, tekillik
veritabanı indeksinden gelir.

## Yeni — `byagain:vapid-keys`

```text
php artisan byagain:vapid-keys
```

Bir VAPID anahtar çifti üretir ve ekrana basar. **Hiçbir dosyaya yazmaz**;
`.env`'i insan doldurur (Ana Yasa III). Çıktı, `.env`'e yapıştırılabilir üç
satırdır:

```text
VAPID_PUBLIC_KEY=…
VAPID_PRIVATE_KEY=…
VAPID_SUBJECT=mailto:…
```

`--force` yok, onay yok: komut zaten hiçbir şeyi değiştirmiyor.

## Yeni iş — `SendReviewNudgePush`

| Alan | Değer |
|---|---|
| Kuyruk | varsayılan (`database` sürücüsü) |
| Yük | `push_deliveries.id` (mevcut mail işleriyle aynı desen: iş, kimliği taşır, modeli değil) |
| `tries` | 1 — bir bildirim geç gelirse değersizdir; ertesi gün yeni karar verilir |
| Gecikme | yok — süpürme zaten doğru dakikada çağırır (R-204) |

İş, göndermeden önce dünyayı yeniden okur; karar tablosu `push.md` içinde.
İşin kendisi hiçbir kullanıcı metni üretmez: başlık ve gövde `lang/en/`
içinden çözülür.

## Değişen — `byagain:prune`

Eski `push_deliveries` satırları da temizlik kapsamına girer; `email_deliveries`
ile aynı saklama süresi kullanılır. `push_subscriptions` **prune edilmez**:
geçerli bir abonelik ne kadar eski olursa olsun çalışır, geçersizi zaten
gönderim anında silinir. Komut hiçbir kaynağa, pasaja, karta, tekrara veya
seri gününe dokunmaz (FR-091).

## Zamanlayıcı

`routes/console.php` içindeki `byagain:dispatch-daily` beş dakikalık kaydı
(`everyFiveMinutes` + `withoutOverlapping`) değişmez. Yeni bir zamanlanmış
kayıt **eklenmez** — kullanıcı başına cron kurmamak, mevcut boru hattının açık
bir kararıdır (SC-013).
