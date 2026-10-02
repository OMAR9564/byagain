# Quickstart: Kaynağa Göre Pratik, Sabit Kaynakla Ekleme ve LLM ile Tekrar

**Feature**: `003-source-practice`

Uçtan uca doğrulama rehberi. Rotaların ayrıntısı [contracts/routes.md](./contracts/routes.md),
dışa aktarma biçimi [contracts/study-export.md](./contracts/study-export.md),
değişmezler [data-model.md](./data-model.md).

## Önkoşullar

- MySQL 8 çalışıyor (yerelde Docker: `byagain-mysql`), `.env` hazır.
- Geliştirme veritabanında içerikli bir hesap: `demo@byagain.test` / `password`
  (20 kaynak, 85 pasaj, 12 mastery kartı).
- `npm run build` güncel.
- Sunucu: `php artisan serve --host=0.0.0.0`; telefon aynı ağda.
  Not: telefondan `http://<LAN-IP>` güvenli bağlam değildir, pano API'si yoktur —
  "Copy"nin yedek yolu tam olarak bu durumda sınanır.

## Otomatik kapı

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/phpstan analyse
npm run build      # AssetBudgetTest 150KB'ı ayrıca sınar
```

Beklenen: hepsi yeşil; `tests/Feature/Practice/*`, `tests/Feature/Library/StickySourceTest.php`,
`tests/Unit/Practice/*` listede.

## 1. İz bırakmama (US1, SC-201)

1. Tinker'da demo hesabı için önce şu sayıları not et: `streak_days` satırı,
   `reviews` satırı, bir kaynağın pasajlarının `shown_count` toplamı ve en yeni
   `last_shown_at`'i, kartların `due_at` listesi.
2. Telefonda o kaynağın sayfasını aç → "Practice". İlk kart tek dokunuşla gelmeli (SC-202).
3. Ekranda "bu pratik günü saymaz" ifadesini gör.
4. Kartları geç: birini çöpe at, birini favorile, birinde sıklığı değiştir.
5. "Practice finished" ekranı → "Another set": çöpe atılan pasaj yeni sette yok.
6. Tinker'da 1. adımdaki sayıları tekrar oku: **hepsi aynı**. Yalnız çöpe atılan
   pasajın `is_discarded`, favorilenenin `is_favorite` ve kaynağın `frequency`
   değeri değişmiş.
7. Ana sayfa: günün tekrarı yapılmadıysa hâlâ yapılmamış görünüyor.

## 2. Formda kal (US2, SC-203)

1. Alt menüden "Add" → bir kaynak seç → pasaj yaz → kaydet.
2. Form boş, aynı kaynak seçili, başarı mesajında kaynağa bağlantı var.
3. Kaynağa dokunmadan dört pasaj daha kaydet. Kaynak sayfasında beşi de orada.
4. İçeriği boş bırakıp kaydet (doğrulama hatası): kaynak seçimi **kaybolmamalı**.
5. Bir kaynağın sayfasında "Add passage" → form o kaynak seçili açılır.
6. Adres çubuğunda `?source=` değerini var olmayan bir id yap: form hatasız,
   kaynak seçilmemiş açılır.
7. Var olan bir pasajı düzenleyip kaydet: kaynak sayfasına döner (değişmedi).

## 3. Dışa aktarma (US3, SC-204, SC-205)

1. Kartı olan bir kaynağın sayfasında "Study with an AI".
2. Ekranda pasaj ve kart sayısı, metnin dış bir hizmete gideceği notu.
3. "Copy": güvenli bağlamda (masaüstü `localhost`) panoya kopyalandı mesajı.
   Telefonda `http://<LAN-IP>` ile: metin seçilir ve elle kopyalama yolu sunulur.
4. "Download": `<kaynak-slug>-<tarih>.md` iner; iOS Safari ve Chrome/Android'de dene.
5. Dosyayı aç: en üstte talimat, sonra başlık, numaralı pasajlar, "Questions"
   bölümü. Çöpe atılmış pasaj ve emekli kart yok.
6. Metni bir LLM'e yapıştır: tek soru sormalı, cevabı beklemeli, Türkçe konuşmalı.
7. Tinker: 1. bölümdeki sayılar yine aynı.

## 4. Güvenlik (SC-206)

İkinci bir hesapla oturum aç; demo hesabının bir kaynak id'si ile:

- `GET /library/sources/{id}/practice` → 404
- `GET /library/sources/{id}/export` ve `/export/download` → 404
- `POST /library/sources/{kendi-kaynağın}/practice/{demo-pasajı}` → 404
- `GET /add?source={id}` → 200, kaynak seçilmemiş

## 5. Mobil (FR-229, SC-208)

375px gerçek cihazda üç yeni/değişen ekran: yatay kaydırma yok, dokunma hedefleri
≥ 44px. Lighthouse mobil: pratik ve dışa aktarma ekranlarında erişilebilirlik ≥ 95.
