<!--
Sync Impact Report
- Version change: (placeholder scaffold) → 1.0.0
- Bump rationale: İlk onaylı sürüm. Şablon yer tutucuları gerçek yönetişim kurallarıyla
  değiştirildi; tüm ilkeler yeni tanımlandı.
- Modified principles:
  - [PRINCIPLE_1_NAME] → I. Ürün Değeri Dokunulmaz
  - [PRINCIPLE_2_NAME] → II. Yığın Sabittir
  - [PRINCIPLE_3_NAME] → III. Güvenlik ve Veri Bütünlüğü (PAZARLIKSIZ)
  - [PRINCIPLE_4_NAME] → IV. Katman Disiplini ve Test
  - [PRINCIPLE_5_NAME] → V. Sabitler Ürün Kararıdır — Dur ve Sor
- Added sections:
  - Ek Kısıtlar ve Kod Standartları (eski [SECTION_2_NAME])
  - Geliştirme Akışı ve Kalite Kapıları (eski [SECTION_3_NAME])
  - Governance
- Removed sections: yok
- Deferred TODOs: yok
-->

# byagain Ana Yasa

> Bu dosyayı her oturumun başında oku. Kod yazmadan önce ilgili SPEC bölümünü de oku.

Öncelik sırası, yukarıdan aşağı: (1) bu belge, (2) genel Laravel/PHP iyi uygulamaları.
Çelişki varsa üstteki kazanır. Ana Yasa ile SPEC çelişiyorsa dur ve sor — hangisinin
doğru olduğuna kendi başına karar verme. Belge kod yazan herkesi bağlar: insan da, LLM de.
Yalnızca LLM'i bağlayan maddeler *(LLM)* ile işaretlidir.

## Core Principles

### I. Ürün Değeri Dokunulmaz

byagain, okunanlardan kaydedilen pasajları her gün kullanıcının karşısına çıkararak
unutmayı engelleyen kişisel bir tekrar uygulamasıdır. Kullanıcı sabah telefonda iki dakika
geçirir. Ürünün değeri iki yerdedir: girilen metnin telefonda kusursuz görünmesi ve doğru
pasajın doğru gün karşıya çıkması.

Bu iki değeri bozan hiçbir değişiklik, ne kadar zarif olursa olsun, kabul EDİLMEZ.
Mobil önceliklidir: 375px genişlik ve dokunma hedefi asıl ölçüttür; masaüstünde çalışan
bir şey "çalışıyor" sayılmaz.

**Gerekçe:** Ürün tek bir günlük ritüele dayanır. O ritüeli bozan optimizasyon, kazandığı
her şeyi kaybettirir.

### II. Yığın Sabittir

Aşağıdaki tablo değiştirilemez:

| Katman | Seçim |
|---|---|
| Framework | Laravel 13.x |
| PHP | 8.3+ |
| Veritabanı | MySQL 8 |
| Auth | Laravel Fortify (başsız) + proje içi Blade view'ları |
| Admin | Filament v5 — yalnızca `/admin` |
| Kullanıcı arayüzü | Blade + Tailwind + proje içi vanilla JS |
| Kuyruk | `database` sürücüsü |
| Markdown | `league/commonmark` + `ezyang/htmlpurifier` |

Yığın değişikliği bir ürün kararıdır, kod kararı değil; önerilebilir, uygulanamaz.
Filament, Livewire ve Alpine yalnızca `/admin` altında yaşar; kullanıcıya bakan hiçbir
sayfa bunları yüklemez. Tekrar ekranı optimistic ve yereldir.

**Gerekçe:** Tekrar ekranında her kaydırmada sunucuya gitmek ürünü bitirir; yığın
tutarlılığı da bakımın tek sigortasıdır.

### III. Güvenlik ve Veri Bütünlüğü (PAZARLIKSIZ)

Aşağıdaki kurallar istisnasızdır:

- `.env` dosyasına dokunulmaz — okuma, yazma, silme, kopyalama yok. Yeni ortam değişkeni
  gerekiyorsa anahtar ve açıklaması `.env.example`'a eklenir, README yapılandırma tablosu
  güncellenir, PR açıklamasında belirtilir. Gerçek değeri insan girer.
- Kullanıcı verisi silinmez. Highlight'lar `is_discarded` ile gizlenir, `DELETE` edilmez.
  `truncate`, `forceDelete`, `migrate:fresh` production yolunda geçmez; toplu veri silen,
  sıfırlayan veya üzerine yazan hiçbir komut/migration insan onayı olmadan yazılmaz.
- Çalışmış migration düzenlenmez. Bir kez `migrate` edilmiş dosya donmuştur; değişiklik
  yeni migration ile yapılır ve `down()` her zaman yazılır.
- `BelongsToUser` global scope'u IDOR'a karşı tek savunma hattıdır. `withoutGlobalScope()`
  yalnızca `app/Filament/` altında kullanılabilir; controller'da, servis'te, komutta
  kaldırılmaz. Bir sorgu "çalışmıyor" diye scope kaldırılmaz — sorgunun kendisi yanlıştır.
- Her sorgu ya `BelongsToUser` scope'undan geçer ya da açık `where('user_id', $user->id)`
  taşır. Route model binding tercih edilir; scope sayesinde başkasının kaydı 404 döner.
- `{!! !!}` yazılmaz. Tek istisna HTMLPurifier'dan geçmiş `content_html`'dir ve zorunlu
  yorum satırıyla birlikte kullanılır:

  ```blade
  {{-- purified: MarkdownRenderer --}}
  {!! $highlight->content_html !!}
  ```

- `$guarded = []` kullanılmaz; her modelde `$fillable` açık liste olarak yazılır.
- Ham SQL'e kullanıcı girdisi konmaz. `whereRaw` / `orderByRaw` yalnızca SPEC 4.1 ve 4.2'deki
  sabit matematiksel ifadeler içindir; değişken gerekiyorsa binding kullanılır. String
  birleştirmeyle SQL kurulmaz.
- Doğrulama Form Request'tedir. Controller'da `$request->validate()` yok; sınıf
  `app/Http/Requests/` altındadır ve `authorize()` gerçekten yetki kontrol eder.

**Gerekçe:** Kullanıcı tek kişi bile olsa güvenlik ilk günden doğru yazılır — sonradan
eklenen güvenlik hiç eklenmez.

### IV. Katman Disiplini ve Test

Controller incedir: istek alır, servisi çağırır, yanıt döndürür. Tekrar oluşturma,
örnekleme, yarı-ömür hesabı, streak — hepsi `app/Services/` altındadır ve testi yazılmıştır.

- Her servis için birim testi, her akış için feature testi zorunludur. Şunlar test
  edilmeden PR AÇILMAZ: tekrar oluşturma, 3 gün bloğu, yarı-ömür güncellemesi, streak gün
  sınırı, hatırlatma tekilleştirme, IDOR.
- Zaman veritabanında UTC'dir; kullanıcının "bugün"ü `users.timezone` ile ve 04:00
  kaydırmasıyla hesaplanır (SPEC 6). `now()` ile `today()` karıştırılmaz. Zaman içeren her
  testte `Carbon::setTestNow()` kullanılır.
- Liste döndüren her sorguda `with()` kullanılır. Geliştirmede `Model::preventLazyLoading()`
  açıktır; patlıyorsa düzeltilir, kapatılmaz.
- Bir testi geçirmek için üretim kodu zayıflatılmaz; test `markTestSkipped` ile susturulmaz.

**Gerekçe:** Zamanlama ve örnekleme mantığı ürünün kendisidir; test edilmemiş bir
zamanlayıcı sessizce yanlış pasaj gösterir ve kimse fark etmez.

### V. Sabitler Ürün Kararıdır — Dur ve Sor

Algoritma sabitleri — yarı-ömür değerleri (7/14/28), çarpanlar (0.5/2.0/3.0), soğuma sabiti
(τ=21), yenilik çarpanı (1.5), 3 günlük blok, kaynak ağırlık basamakları — ürün kararıdır.
Kodda sihirli sayı olarak değil, `config/byagain.php` içinde adlandırılmış sabit olarak
durur; değeri değiştirmek için sorulur.

`resources/css/tokens.css` tasarım sürecinin çıktısıdır; renk, tipografi ölçeği, boşluk
ölçeği kod yazarken değiştirilmez. Token eksikse söylenir, uydurulmaz.

Şu durumlarda kendi başına karar verilmez, insana danışılır:

- Şema değişikliği gerektiren her şey
- Yeni bağımlılık — `composer require` / `npm install` öncesi gerekçe, alternatifler ve
  lisans uyumu (AGPL-3.0) ile birlikte önerilir; GPL-2.0-only ve lisanssız paketler reddedilir
- Algoritma sabiti veya davranışı değişikliği (SPEC 4)
- Güvenlik ödünü gerektiren her durum — "geçici olarak scope'u kaldıralım" diye bir şey yok
- Ana Yasa ile SPEC çelişkisi
- SPEC'in sessiz kaldığı ürün kararı (bir buton nereye gidecek, boş durumda ne yazacak)
- İşin 400 satırı geçeceğinin anlaşıldığı an — böl, planı göster

**Gerekçe:** Tahmin etmek yerine sormak her zaman ucuzdur. Yanlış varsayımla yazılmış 300
satırı atmak, bir soru sormaktan pahalıdır.

## Ek Kısıtlar ve Kod Standartları

- **Pint** (Laravel preset): `vendor/bin/pint` temiz olmadan commit yok.
- **Larastan level 6**: yeni kod uyarı üretmez, baseline'a ekleme yapılmaz.
- Tip belirtimi zorunludur: parametre, dönüş, property.
- `final class` varsayılandır; miras gerekiyorsa gerekçesi olur.
- Servis metodu tek iş yapar ve adı ne yaptığını söyler.
- Yorum "ne" değil "neden" anlatır ve SPEC'e atıf yapar: `// SPEC 4.1 — cooldown`.
- Sihirli sayı yok: eşikler `config/byagain.php` içindedir.
- Arayüzde görünen hiçbir Türkçe metin Blade'e gömülmez; anahtar `lang/tr/` altındadır.
  Kod, değişken, fonksiyon, tablo ve kolon adları İngilizcedir.

Yasaklı desenler:

| Yapma | Yap |
|---|---|
| `Highlight::find($id)` | `Highlight::findOrFail($id)` + scope, ya da route binding |
| `$guarded = []` | `$fillable = ['title', 'weight', …]` |
| `{!! $anything !!}` | `{{ $anything }}` — istisna sadece `content_html` |
| Controller'da `$request->validate([...])` | `StoreHighlightRequest` |
| `DB::statement("... {$var} ...")` | Binding veya query builder |
| `->orderByRaw("... {$userInput}")` | Beyaz listeden eşleşen sabit |
| Controller'da 40 satır iş mantığı | `app/Services/` altında sınıf |
| `if ($halfLife > 365)` | `config('byagain.mastery.max_half_life')` |
| Blade'de `Kaydet` | `{{ __('actions.save') }}` |
| `migrate:fresh` ile şema düzeltme | Yeni migration |
| Testi `markTestSkipped` ile susturma | Testi düzelt veya sor |

Bağlam notları:

- **Depo public.** Commit mesajı, değişken adı, yorum — hepsi vitrindir.
- **Lisans AGPL-3.0-only.** Uyumsuz lisanslı kod kopyalanmaz. Stack Overflow kodu (CC BY-SA)
  atıf gerektirir; kaçınılır.
- **Kullanıcı tek kişi olabilir**, ama IDOR ve yetki kontrolleri ilk günden doğru yazılır.

## Geliştirme Akışı ve Kalite Kapıları

Her görevde bu sıra izlenir:

1. **Oku.** Ana Yasa + ilgili SPEC bölümü. Görev hangi bölüme ait olduğunu söylemiyorsa bul.
2. **Planla.** Ne değişecek, hangi dosyalar, hangi migration, hangi test. Plan yazılıp
   gösterilir, onay alınır. Büyük işte kod yazmadan önce plan onayı zorunludur.
3. **Dal aç.**
4. **Yaz.** Önce test sonra kod tercih edilir; en azından ikisi aynı PR'da olur.
5. **Öz-denetim.** Aşağıdaki liste kendi başına geçilir.
6. **PR aç.** Şablon gerçekten doldurulur — "ne değişti / neden / nasıl test edildi"
   kutuları boş bırakılmaz.

Git protokolü:

```
git switch -c feat/mastery-scheduler
… çalış, atomik commit'ler at …
git push -u origin feat/mastery-scheduler
… PR aç, şablonu doldur …
… insan gözden geçirir, squash merge eder …
```

- `main` dalına doğrudan commit atılmaz *(LLM)*. Her iş bir dalda başlar ve PR ile biter.
- Commit mesajı biçimi:

  ```
  <tip>(<kapsam>): <özet>

  <neden bu değişiklik gerekti — ne yapıldığını diff söylüyor>

  Refs: #12
  ```

  Tipler: `feat` `fix` `docs` `refactor` `perf` `test` `chore` `build` `ci` `revert`.
  Kapsamlar: `auth` `review` `mastery` `editor` `mail` `admin` `streak` `db` `ui` `pwa` `deps`.
  Özet ≤72 karakter, emir kipi, küçük harf, sonda nokta yok, İngilizce. Bir commit bir
  mantıksal değişikliktir; biçimlendirme ile işlev aynı commit'te olmaz.
- LLM'in yazdığı her commit'in sonunda `Co-Authored-By: Claude <noreply@anthropic.com>`
  satırı bulunur *(LLM)*. Yazar insandır; kodu gözden geçirip sorumluluğunu alan odur.
- Yasak: force push · `main`'e rebase · geçmiş yeniden yazma · başkasının dalına push ·
  `--no-verify` ile hook atlama.

Bitmiş sayılma ölçütü — hepsi doğru olmadan iş bitmez:

- [ ] `vendor/bin/pint --test` temiz
- [ ] `vendor/bin/phpstan analyse` temiz (level 6)
- [ ] `php artisan test` yeşil, yeni davranış için yeni test var
- [ ] `composer audit` temiz
- [ ] Yeni sorgular sahiplik kontrolünden geçiyor
- [ ] Yeni `{!! !!}` yok (izinli tek yer hariç)
- [ ] Yeni sihirli sayı yok
- [ ] Migration'ın `down()`'ı çalışıyor
- [ ] Kullanıcıya görünen metin `lang/tr/` içinde
- [ ] SPEC değiştiyse `docs/SPEC.md` aynı PR'da güncellendi
- [ ] Kurulum/yapılandırma etkilendiyse README ve `.env.example` güncellendi
- [ ] Kullanıcı tarafına yeni JS eklendiyse varlık bütçesi (150KB) hâlâ tutuyor

## Governance

Bu Ana Yasa diğer tüm uygulama ve alışkanlıkların üzerindedir. Çelişki hâlinde bu belge
kazanır; belge ile SPEC çelişiyorsa iş durur ve insana danışılır.

**Değişiklik usulü:** Ana Yasa değişikliği bir PR ile önerilir. PR açıklaması neyin
değiştiğini, gerekçesini ve mevcut koda etkisini (gerekiyorsa geçiş planını) yazar.
Depo sahibinin onayı olmadan merge edilmez. LLM kendi başına Ana Yasa değiştirmez.

**Sürümleme:** Semantik sürümleme uygulanır.
MAJOR — bir ilkenin kaldırılması veya geriye uyumsuz yeniden tanımı.
MINOR — yeni ilke/bölüm eklenmesi veya rehberliğin maddi genişlemesi.
PATCH — açıklama, ifade düzeltmesi, anlam değiştirmeyen incelikler.

**Uyum denetimi:** Her PR gözden geçirmesi bu belgeye uyumu doğrular; "Bitmiş sayılma
ölçütü" listesi PR'ın kalite kapısıdır. Ek karmaşıklık gerekçelendirilir. Günlük
geliştirme rehberliği için `docs/SPEC.md` ve ajan rehber dosyaları kullanılır; bunlar bu
belgeye tabidir ve onu geçersiz kılamaz.

**Version**: 1.0.0 | **Ratified**: 2026-08-22 | **Last Amended**: 2026-08-22
