# Kontrat — E-posta

Ortak kurallar (FR-066): her e-posta hem HTML hem düz metin sürümü içerir; tek sütun,
dar ekran (320px) ve karanlık modda okunur; altta imzalı tek tıklık "bu e-postayı kapat"
bağlantısı bulunur (FR-065). Metinler `lang/en/` altındadır (FR-088).

## `DailyReviewMail`

| Alan | Değer |
|---|---|
| Tetikleyici | `byagain:dispatch-daily`, kullanıcının yerel `daily_email_at` saati |
| Dedupe | `daily:{local_day}` |
| Konu | `Today's review — {n} cards` |

**İçerik**: O günün `review_items` kartları **gömülü** olarak (FR-060). Her pasaj için
`content_html`'in e-postaya uygun sadeleştirilmiş sürümü (tablo ve kod bloğu korunur,
yatay kaydırma yerine kırpma), kaynak başlığı ve varsa konum. Mastery kartlarında yalnızca
soru görünür; cevap uygulamada açılır.

**Tek çağrı**: "Complete in the app" → `/review` (FR-060).

**Değişmez**: E-postadaki kartlar uygulamadakiyle birebir aynıdır (FR-061) — ikisi de aynı
`review_items` satırlarından üretilir, e-posta anında yeniden örnekleme yapılmaz.

## `EveningReminderMail`

| Alan | Değer |
|---|---|
| Tetikleyici | yerel `reminder_email_at`, tekrar hâlâ `pending` |
| Dedupe | `reminder:{local_day}` |
| Konu | `Your review is waiting` |

**İçerik**: Kart içermez; kalan kart sayısı ve tek bir bağlantı. Suçlayıcı dil yok
(FR-058 ruhu).

**Gönderilmeme koşulları**: tekrar tamamlanmış (gönderim anında yeniden kontrol edilir),
tercih kapalı, e-posta doğrulanmamış, hesap `suspended`, ya da o gün için zaten bir
hatırlatma kaydı var (FR-062, FR-063).

## Kimlik e-postaları

| Mail | Süre | Gereksinim |
|---|---|---|
| Doğrulama | 24 saat | FR-002 |
| Parola sıfırlama | 60 dakika, tek kullanımlık | FR-003 |

Bu ikisi ritüel e-postası değildir; doğrulanmamış kullanıcıya da gider ve
"kapat" bağlantısı içermez.

## Abonelikten çıkma

`GET /unsubscribe/{user}/{type}` — Laravel imzalı URL, giriş gerektirmez, taklit edilemez
(FR-065, R-12). `type` ∈ {`daily`, `reminder`}. Sonuç: ilgili tercih `false` yapılır ve
onay ekranı gösterilir. İşlem idempotenttir.

## Açılma sinyali

Sağlayıcı webhook'u `POST /webhooks/mail` → `email_deliveries.opened_at`. Açılan e-posta
kullanıcının `consecutive_unopened_emails` sayacını sıfırlar; hiç açılmayan her ritüel
e-postası sayacı artırır. 5 ve üzerinde hatırlatma sıklığı haftada 1'e düşer (FR-064).
Sinyalin yaklaşık olduğu kabul edilir (Assumptions).
