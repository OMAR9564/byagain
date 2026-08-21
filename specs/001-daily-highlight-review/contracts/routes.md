# Kontrat — HTTP Rotaları

Rota adları ve kullanıcıya görünen yollar **İngilizce** (spec.md "Dil" kararı).
Tüm `auth` rotaları `BelongsToUser` scope'u sayesinde başkasının kaydında 404 döner
(FR-010, SC-011). Doğrulama Form Request'te (Ana Yasa m. III).

## Kimlik (Fortify başsız, view'lar proje içi)

| Metot | Yol | Ad | Not |
|---|---|---|---|
| GET/POST | `/register` | `register` | FR-001; kayıt kapalıysa 403 (FR-075) |
| GET/POST | `/login` | `login` | hız sınırlı (FR-006) |
| POST | `/logout` | `logout` | |
| GET/POST | `/forgot-password` | `password.request` | tek tip yanıt (FR-004) |
| GET/POST | `/reset-password/{token}` | `password.reset` | 60 dk (FR-003) |
| GET | `/email/verify/{id}/{hash}` | `verification.verify` | imzalı, 24 sa (FR-002) |
| POST | `/email/verification-notification` | `verification.send` | hız sınırlı |
| DELETE | `/account` | `account.destroy` | parola onayı (FR-008) |

## Uygulama (middleware: `auth`, `ensure.active`)

| Metot | Yol | Ad | Gereksinim |
|---|---|---|---|
| GET | `/` | `home` | boş durumda "ilk kaynağını ekle" (US1-1) |
| GET | `/review` | `review.show` | bugünün tekrarı; yoksa üretir (FR-025) |
| POST | `/review/complete` | `review.complete` | tüm kalemler işlendiyse (FR-054) |
| POST | `/review/items/{item}/action` | `review.item.action` | JSON — bkz. review-actions.md |
| GET | `/library` | `library.index` | FR-023 |
| GET | `/library/sources/create` | `sources.create` | |
| POST | `/library/sources` | `sources.store` | FR-012 |
| GET | `/library/sources/{source}` | `sources.show` | pasaj listesi |
| GET | `/library/sources/{source}/edit` | `sources.edit` | |
| PATCH | `/library/sources/{source}` | `sources.update` | başlık, tür, `frequency`, arşiv |
| GET | `/add` | `highlights.create` | mobil editör (FR-020) |
| POST | `/highlights` | `highlights.store` | FR-013, FR-022 |
| GET | `/highlights/{highlight}/edit` | `highlights.edit` | |
| PATCH | `/highlights/{highlight}` | `highlights.update` | |
| POST | `/highlights/{highlight}/discard` | `highlights.discard` | FR-013 (silmez) |
| POST | `/highlights/{highlight}/favorite` | `highlights.favorite` | toggle, FR-019 |
| POST | `/highlights/{highlight}/mastery` | `mastery.store` | pasajdan kart (FR-044) |
| GET | `/mastery` | `mastery.index` | FR-053 |
| GET | `/mastery/{card}/edit` | `mastery.edit` | |
| PATCH | `/mastery/{card}` | `mastery.update` | |
| POST | `/mastery/{card}/retire` | `mastery.retire` | FR-050 |
| GET | `/streak` | `streak.show` | 90 günlük takvim (FR-056) |
| GET | `/settings` | `settings.edit` | |
| PATCH | `/settings` | `settings.update` | boyut, oran, filtre, saatler, tz |

## Girişsiz

| Metot | Yol | Ad | Not |
|---|---|---|---|
| GET | `/unsubscribe/{user}/{type}` | `unsubscribe` | `signed` middleware (FR-065, R-12) |
| POST | `/webhooks/mail` | `webhooks.mail` | sağlayıcı imzası doğrulanır (FR-064) |

## Admin (`/admin`, middleware: `auth`, `ensure.admin`)

Filament v5 paneli. Kullanıcı tarafı sayfalar bu grubun hiçbir varlığını yüklemez
(SC-018). Erişim `admin` rolü dışında 403 (FR-069).

| Bölüm | Yetenek |
|---|---|
| Dashboard | kayıt sayısı, aktif kullanıcı, tamamlanma oranı, ortalama seri, gönderilen/başarısız e-posta, bekleyen iş, zamanlayıcı yaşı (FR-073) + gecikme uyarısı (FR-074) |
| Users | listele, ara, askıya al, doğrulama e-postası yeniden gönder; başka kullanıcı gibi oturum açma **yok** (FR-070) |
| Sources / Highlights | yalnızca okuma; pasajda 120 karakterlik önizleme (FR-071) |
| Email deliveries | tür/durum süzme, hata detayı, elle yeniden gönderme (FR-072) |
| Settings | bakım modu, kayıt kapatma, varsayılan tekrar boyutu (FR-075) |
| Action log | değiştirilemez kayıt görünümü (FR-076) |

## Hata sözleşmesi

| Durum | Yanıt |
|---|---|
| Başkasının kaydı | 404 (kayıt yokmuş gibi) |
| Yetkisiz admin erişimi | 403 |
| Hız sınırı aşımı | 429 + `Retry-After` |
| Doğrulama hatası | 422 (JSON) / geri yönlendirme + `errors` (HTML) |
| Bakım modu | 503 |
