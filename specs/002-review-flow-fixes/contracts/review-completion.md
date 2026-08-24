# Kontrat — Tamamlanmış Gün ve Tur Açma

Issue #4. Değişen tek uç `GET /review`; `POST /review/again` ve
`POST /review/complete` sözleşmeleri aynı kalır (bkz.
`specs/001-daily-highlight-review/contracts/review-actions.md`).

## `GET /review` — karar matrisi

Sıra yukarıdan aşağı, ilk eşleşen kazanır. `latest` = o yerel günün en yüksek
turu (`ReviewBuilder::latestFor`).

| Durum | Bugünkü davranış | **Yeni davranış** |
|---|---|---|
| Hiç uygun pasaj yok | `review.empty` | değişmez |
| `latest` yok | round 1 üretilir, kartlar | değişmez |
| `latest` açık (tamamlanmamış) | kartlar, ilk kararsız karttan | değişmez (FR-106) |
| `latest` tamamlanmış **ve** `roundsToday < daily_review_limit` | **yeni tur üretilir, kartlar gösterilir** | **`review.done` gösterilir; tur üretilmez** (FR-101, FR-103) |
| `latest` tamamlanmış ve kota dolu | `review.done` | değişmez |

Yani `GET /review` bundan sonra **hiçbir koşulda** tur 2 veya sonrasını
üretmez. Round 1 üretimi (günün kendi tekrarı) yerinde kalır — e-posta boru
hattıyla paylaşılan davranıştır.

## `review.done` görünüm durumu

Controller'ın görünüme verdiği dizi büyür:

| Anahtar | Tip | Anlam |
|---|---|---|
| `streak` | `int` | değişmedi |
| `rounds` | `int` | bugün yapılan tur sayısı |
| `limit` | `int` | `users.daily_review_limit` |
| `canRepeat` | `bool` | `rounds < max_rounds_per_day` **ve** `rounds < limit` |
| `hasMaterial` | `bool` | **yeni** — yeni bir tur için uygun kart kaldı mı |

Ekranın üç sonu vardır ve üçü farklı şey söyler:

1. `canRepeat && hasMaterial` → "bir tur daha" düğmesi.
2. `canRepeat && ! hasMaterial` → malzeme bitti metni, düğme yok
   (`review.again.exhausted`).
3. `! canRepeat` → gün kapalı; ne düğme ne malzeme metni (FR-104).

> Bugün `done.blade.php` bu ayrımı `$rounds < $limit` karşılaştırmasından
> çıkarıyor. O çıkarım yalnızca `GET /review` otomatik tur ürettiği için
> doğruydu; otomatik üretim kalkınca malzeme sorusu açıkça sorulmalıdır.

`hasMaterial`, `ReviewBuilder` üzerinde yeni ve **salt-okunur** bir metotla
cevaplanır: örnekleyiciye bir kartlık istek yapar, hiçbir şey yazmaz.

## `POST /review/again`

Sözleşme değişmez. Yeni tur açmanın tek yoludur (FR-102); `throttle:20,1`
korunur. `buildNextRound()` `null` dönerse kullanıcı `review.again.exhausted`
mesajıyla `review.show`'a döner.

## Geri dönüş yok

Tamamlanma ekranından karar akışına dönüş yoktur (FR-107): `review.done`
içinde karta dönen hiçbir bağlantı bulunmaz ve `GET /review` tamamlanmış turun
kartlarını render etmez. Bir tur içindeki "önceki karta bak" davranışı
(`data-review-back`) tur açıkken geçerliliğini korur.

## Testler (feature)

- Tamamlanmış gün + kota boş → `review.done`, veritabanında yeni `reviews`
  satırı **yok**.
- Tamamlanmış gün + `POST /review/again` → yeni tur, `round = 2`.
- Tamamlanmış gün + malzeme yok → `review.done`, düğme yok, `exhausted` metni.
- Kota dolu → düğme yok, `POST /review/again` yeni tur açmaz.
- Yarım tur → kartlar, `startIndex` ilk kararsız kalemde.
- Zamanla ilgili her senaryoda `Carbon::setTestNow()` (Ana Yasa IV).
