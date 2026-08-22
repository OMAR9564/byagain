<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Source;
use App\Models\User;
use App\Services\Content\HighlightWriter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * A real library to read: twenty sources a software engineer would actually
 * keep, with Turkish passages worth meeting again.
 *
 *   php artisan db:seed --class=EngineeringLibrarySeeder
 *
 * Unlike DemoSeeder — which bulk-inserts rows to size a performance fixture —
 * this goes through HighlightWriter, so every passage is cleaned, rendered and
 * purified by the same path the editor uses. Seeded content that skipped the
 * pipeline would render differently from content typed in, which is exactly
 * the kind of difference that hides a bug.
 *
 * Sources are spread across the frequency tiers on purpose, so the weighting
 * has something to actually do.
 */
final class EngineeringLibrarySeeder extends Seeder
{
    public function run(): void
    {
        $user = $this->targetUser();

        // HighlightWriter takes ownership from the signed-in user, so the
        // seeder signs in as the target rather than stamping user_id by hand.
        Auth::login($user);

        $writer = app(HighlightWriter::class);
        $sources = 0;
        $highlights = 0;

        foreach ($this->library() as $definition) {
            $source = Source::query()->updateOrCreate(
                ['user_id' => $user->id, 'title' => $definition['title']],
                [
                    'author' => $definition['author'],
                    'type' => $definition['type'],
                    'frequency' => $definition['frequency'],
                ],
            );

            $sources++;

            foreach ($definition['highlights'] as $index => $markdown) {
                $highlight = $writer->create([
                    'source_id' => $source->id,
                    'content_md' => trim($markdown),
                    'location' => 's. '.(($index + 1) * 17),
                ]);

                // Backdate afterwards rather than faking the global clock, so
                // the novelty window and the cooldown have a realistic spread
                // to work with. Carbon::setTestNow() would do it too, but that
                // is a testing helper and does not belong in a seeder.
                $highlight->created_at = Carbon::now()->subDays(($index * 7 + $sources * 3) % 90);
                $highlight->save();

                $highlights++;
            }
        }

        Auth::logout();

        $this->command->info("Seeded {$sources} sources and {$highlights} highlights for {$user->email}.");
    }

    /**
     * Prefer an explicitly named account, then the first existing one, and
     * only create a new reader if the instance is empty.
     */
    private function targetUser(): User
    {
        $email = config('byagain.demo.email');

        if (is_string($email) && $email !== '') {
            return User::query()->firstOrCreate(
                ['email' => $email],
                [
                    'name' => 'Engineer',
                    'password' => Hash::make('password'),
                    'email_verified_at' => Carbon::now(),
                    'timezone' => 'Europe/Istanbul',
                ],
            );
        }

        $existing = User::query()->oldest('id')->first();

        if ($existing instanceof User) {
            return $existing;
        }

        return User::query()->create([
            'name' => 'Engineer',
            'email' => 'muhendis@byagain.test',
            'password' => Hash::make('password'),
            'email_verified_at' => Carbon::now(),
            'timezone' => 'Europe/Istanbul',
        ]);
    }

    /**
     * @return array<int, array{title: string, author: string, type: string, frequency: string, highlights: array<int, string>}>
     */
    private function library(): array
    {
        return [
            [
                'title' => 'Temiz Kod',
                'author' => 'Robert C. Martin',
                'type' => 'book',
                'frequency' => 'often',
                'highlights' => [
                    <<<'MD'
                    Bir ismin neden var olduğunu, ne yaptığını ve nasıl kullanıldığını
                    açıklaması gerekir. **İsim bir yorum gerektiriyorsa, o isim niyeti
                    açıklamıyor demektir.**

                    ```php
                    // kötü
                    $d = 0; // geçen gün sayısı

                    // iyi
                    $elapsedDays = 0;
                    ```
                    MD,
                    <<<'MD'
                    Fonksiyonlar küçük olmalı. Sonra daha da küçük olmalı.

                    Bir fonksiyon **tek bir iş** yapmalı, o işi iyi yapmalı ve yalnızca
                    onu yapmalı. Bir fonksiyonun tek iş yapıp yapmadığını anlamanın yolu:
                    gövdesinden anlamlı başka bir fonksiyon çıkarabiliyorsan, tek iş
                    yapmıyordur.
                    MD,
                    <<<'MD'
                    Yorumlar kötü kodun özrü değildir.

                    Bir yorum yazma ihtiyacı duyduğunda önce şunu sor: bu şeyi kodun
                    kendisi anlatabilir mi? Çoğu zaman cevabı evettir ve çözüm yorum
                    yazmak değil, kodu yeniden adlandırmaktır.

                    > Kötü koda yorum eklemek yerine onu temizle.

                    İyi yorumlar da vardır: bir kararın **neden** böyle alındığını
                    anlatan yorum değerlidir, çünkü bu bilgi koddan okunamaz.
                    MD,
                    <<<'MD'
                    Yan etkiler yalancıdır. Fonksiyon adı bir şey yapacağını söylerken
                    gizlice başka bir şey de yapıyorsa, çağıran taraf bunu ancak
                    gövdesini okuyarak öğrenebilir.

                    `checkPassword()` adlı bir fonksiyon oturum başlatıyorsa, adı yalan
                    söylüyor demektir.
                    MD,
                    <<<'MD'
                    **İzci kuralı:** Kamp alanını bulduğundan daha temiz bırak.

                    Dokunduğun her dosyayı biraz daha iyi bırakırsan, kod tabanı zamanla
                    bozulmak yerine iyileşir. Değişiklik büyük olmak zorunda değil: bir
                    değişken adını düzeltmek, uzun bir fonksiyonu ikiye bölmek yeter.
                    MD,
                ],
            ],
            [
                'title' => 'Pragmatik Programcı',
                'author' => 'Hunt & Thomas',
                'type' => 'book',
                'frequency' => 'very_often',
                'highlights' => [
                    <<<'MD'
                    **DRY — Kendini Tekrar Etme.**

                    Her bilgi parçasının sistem içinde tek, kesin ve yetkili bir
                    temsili olmalıdır. Buradaki tekrar kod tekrarı değil, *bilgi*
                    tekrarıdır: aynı kuralın iki yerde yaşaması, birini değiştirip
                    diğerini unutacağın gün anlamına gelir.
                    MD,
                    <<<'MD'
                    **Ortogonallik.**

                    Birbirinden bağımsız şeyleri bağımsız tut. İki bileşen ortogonalse,
                    birini değiştirmek diğerini etkilemez. Bunun pratik ölçüsü şudur:
                    "Şu gereksinimi kökten değiştirsem kaç modüle dokunmam gerekir?"
                    Cevap büyükse tasarım ortogonal değildir.
                    MD,
                    <<<'MD'
                    **Kırık pencere teorisi.**

                    Terk edilmiş bir binanın çürümesi ilk kırık pencereyle başlar.
                    Kod da öyle: düzeltilmemiş ilk kötü tasarım, ilk susturulmuş test,
                    ilk kopyala-yapıştır blok "burada standart yok" mesajını verir ve
                    gerisi hızlanır.

                    Kırık pencereyi gördüğün anda tamir et. Zamanın yoksa hiç değilse
                    tahtayla kapat: bir `TODO` bırak, bir test yaz, sorunu görünür kıl.
                    MD,
                    <<<'MD'
                    Bilmediğin bir alana girerken **izli mermi** kullan: uçtan uca çalışan
                    en ince dilimi önce yaz. Arayüzden veritabanına kadar gerçekten
                    çalışsın, ama sadece tek bir senaryo için.

                    Prototipten farkı şudur: izli mermi atılıp gitmez, sistemin iskeleti
                    olur.
                    MD,
                    <<<'MD'
                    Tahminlerini birime dikkat ederek söyle. "Üç ay" dediğinde insanlar
                    bunu bir takvim sözü olarak duyar; "90 iş günü" dediğinde de öyle.

                    Belirsizliği saklama, aralık ver: iyimser, gerçekçi ve kötümser
                    senaryoyu birlikte söyle.
                    MD,
                ],
            ],
            [
                'title' => 'Refactoring',
                'author' => 'Martin Fowler',
                'type' => 'book',
                'frequency' => 'normal',
                'highlights' => [
                    <<<'MD'
                    Refactoring, **dış davranışı değiştirmeden** iç yapıyı iyileştirmektir.

                    Bu tanımdaki "değiştirmeden" kısmı pazarlık konusu değildir. Davranışı
                    da değiştiriyorsan yaptığın şey refactoring değil, yeniden yazmadır ve
                    riski bambaşkadır.
                    MD,
                    <<<'MD'
                    Refactoring'in ön koşulu testtir.

                    Sağlam bir test takımın yoksa, yaptığın değişikliğin davranışı koruyup
                    korumadığını bilemezsin. Testsiz refactoring, gözü kapalı ameliyattır.
                    MD,
                    <<<'MD'
                    **Kod kokuları** kesin kurallar değil, "buraya bir bak" işaretleridir:

                    - Uzun fonksiyon
                    - Büyük sınıf
                    - Uzun parametre listesi
                    - Yinelenen kod
                    - Özellik kıskançlığı (bir metot başka sınıfın verisiyle fazla ilgileniyor)
                    - Shotgun surgery (tek değişiklik için çok yere dokunmak)

                    Koku, mutlaka sorun olduğu anlamına gelmez. İncelemeye değer olduğu
                    anlamına gelir.
                    MD,
                    <<<'MD'
                    En sık kullanacağın refactoring **Extract Function**'dır.

                    Ölçüt uzunluk değil, *niyet ile gerçekleme arasındaki mesafedir*.
                    Kodun ne yaptığını anlamak için okumak gerekiyorsa, o parçayı ayır ve
                    ne yaptığını söyleyen bir isim ver. Tek satır bile olsa değer.
                    MD,
                    <<<'MD'
                    Küçük adımlarla ilerle ve her adımda testleri çalıştır.

                    Bir hata yaptığında geri alman gereken mesafe, iki test çalıştırması
                    arasındaki mesafedir. Adımlar büyüdükçe hata ayıklama süresi
                    üstel olarak artar.
                    MD,
                ],
            ],
            [
                'title' => 'Tasarım Kalıpları',
                'author' => 'Gamma, Helm, Johnson, Vlissides',
                'type' => 'book',
                'frequency' => 'low',
                'highlights' => [
                    <<<'MD'
                    **Gerçeklemeye değil, arayüze programla.**

                    Bağımlılığın somut bir sınıfa değil, sözleşmeye olsun. Böylece
                    gerçeklemeyi değiştirmek çağıran tarafı etkilemez.
                    MD,
                    <<<'MD'
                    **Kalıtım yerine nesne kompozisyonunu tercih et.**

                    Kalıtım derleme zamanında sabitlenir ve üst sınıfın iç ayrıntılarını
                    alt sınıfa sızdırır. Kompozisyon çalışma zamanında değiştirilebilir ve
                    yalnızca genel arayüze dayanır.

                    Kalıtım "bir türüdür" ilişkisi içindir; kod paylaşmak için değil.
                    MD,
                    <<<'MD'
                    **Strategy**, değişen algoritmayı kendi nesnesine çıkarır.

                    Bir `if/else` zinciri davranış seçiyorsa ve bu zincir büyümeye devam
                    ediyorsa, orada bir Strategy vardır. Yeni davranış eklemek mevcut kodu
                    değiştirmek yerine yeni bir sınıf eklemek hâline gelir.
                    MD,
                    <<<'MD'
                    Kalıp, çözümü ezberlemek için değil, **problemi tanımak** içindir.

                    Kalıpları koda zorla uydurmak, kalıp bilmemekten daha zararlıdır.
                    Önce problemi gör, kalıp adını sonra koy.
                    MD,
                ],
            ],
            [
                'title' => 'Alan Odaklı Tasarım',
                'author' => 'Eric Evans',
                'type' => 'book',
                'frequency' => 'normal',
                'highlights' => [
                    <<<'MD'
                    **Her yerde aynı dil (ubiquitous language).**

                    Alan uzmanının kullandığı kelimeler kodda da aynen geçmeli. Uzman
                    "poliçe iptali" diyorsa sınıfın adı `PolicyCancellation` olmalı,
                    `RecordDeactivator` değil.

                    Çeviri katmanı her zaman bilgi kaybeder.
                    MD,
                    <<<'MD'
                    **Sınırlandırılmış bağlam (bounded context).**

                    Aynı kelime farklı bağlamlarda farklı şey demektir. Satışta "müşteri"
                    ile muhasebede "müşteri" aynı kavram değildir. Tek bir devasa model
                    kurmaya çalışmak yerine sınırları çiz ve aralarındaki çeviriyi açıkça
                    tanımla.
                    MD,
                    <<<'MD'
                    **Aggregate**, tutarlılık sınırıdır.

                    Bir aggregate içindeki her şey tek işlemde tutarlı olur; dışındakiler
                    sonunda tutarlı olur. Bu yüzden aggregate'i büyük tutmak cazip ama
                    pahalıdır — kilitlenme ve çekişme oradan çıkar.

                    Kural: aggregate'lere **kimlik üzerinden** referans ver, nesne
                    üzerinden değil.
                    MD,
                    <<<'MD'
                    **Kansız alan modeli (anemic domain model)** bir anti-kalıptır.

                    Yalnızca getter/setter içeren varlıklar ve bütün kuralları taşıyan
                    "servis" sınıfları, nesne yönelimli görünen prosedürel koddur. Veriyle
                    onu yöneten davranış aynı yerde olmalıdır.
                    MD,
                ],
            ],
            [
                'title' => 'Veri Yoğun Uygulamalar Tasarlamak',
                'author' => 'Martin Kleppmann',
                'type' => 'book',
                'frequency' => 'very_often',
                'highlights' => [
                    <<<'MD'
                    Her veri sisteminin üç temel kaygısı vardır:

                    - **Güvenilirlik** — arıza olsa da doğru çalışmaya devam etmek
                    - **Ölçeklenebilirlik** — yük büyüdükçe başa çıkabilmek
                    - **Sürdürülebilirlik** — üzerinde çalışan insanların hayatını kolaylaştırmak

                    Üçüncüsü en sık atlanandır ve uzun vadede en pahalıya patlayanıdır.
                    MD,
                    <<<'MD'
                    Ölçeklenebilirliği konuşurken **yükü tanımla**.

                    "Ölçeklenebilir mi?" sorusu tek başına anlamsızdır. Doğru soru:
                    "Yük parametresi şu kadar artarsa, kaynakları sabit tutarsak başarım
                    ne olur?"

                    Ortalama yanıt süresi yerine **yüzdelik dilimlere** bak. p50 iyi
                    görünürken p99 felaket olabilir ve en çok veri üreten müşterin tam
                    olarak p99'da oturuyordur.
                    MD,
                    <<<'MD'
                    CAP teoremi çoğu zaman yanlış anlatılır.

                    "Tutarlılık, erişilebilirlik, bölünme toleransı; üçünden ikisini seç"
                    demek yanıltıcıdır. Ağ bölünmesi bir seçim değil, bir gerçektir.
                    Gerçek seçim şudur: **bölünme olduğunda** tutarlılıktan mı yoksa
                    erişilebilirlikten mi vazgeçeceksin?
                    MD,
                    <<<'MD'
                    **İdempotans**, dağıtık sistemlerde en ucuz sigortadır.

                    Ağ üzerinden giden her istek kaybolabilir, tekrarlanabilir veya geç
                    ulaşabilir. İşlemi ikinci kez uygulamak birinciyle aynı sonucu
                    veriyorsa, "acaba gitti mi?" sorusunun cevabını bilmek zorunda
                    kalmazsın: tekrar dene.

                    Pratikte bu genellikle bir tekilleştirme anahtarı ve bir benzersiz
                    indeks demektir.
                    MD,
                    <<<'MD'
                    İşlem izolasyon seviyeleri, önlediği anomalilerle tanımlanır:

                    | Seviye | Kirli okuma | Tekrarsız okuma | Hayalet |
                    | --- | --- | --- | --- |
                    | Read uncommitted | var | var | var |
                    | Read committed | yok | var | var |
                    | Repeatable read | yok | yok | var |
                    | Serializable | yok | yok | yok |

                    MySQL InnoDB varsayılanı `REPEATABLE READ`, PostgreSQL'inki
                    `READ COMMITTED`'dır. Aynı kodu iki veritabanında çalıştırmak aynı
                    davranışı garanti etmez.
                    MD,
                ],
            ],
            [
                'title' => 'Aylık Adam Efsanesi',
                'author' => 'Frederick P. Brooks',
                'type' => 'book',
                'frequency' => 'rare',
                'highlights' => [
                    <<<'MD'
                    **Brooks Yasası:** Geciken bir yazılım projesine insan eklemek onu
                    daha da geciktirir.

                    Sebep basit: yeni gelenlerin eğitilmesi gerekir ve iletişim
                    kanallarının sayısı kişi sayısının karesiyle artar. `n` kişi için
                    `n(n−1)/2` kanal.
                    MD,
                    <<<'MD'
                    **İkinci sistem etkisi.**

                    Bir mühendisin tasarladığı en tehlikeli sistem ikincisidir. İlkinde
                    tecrübesizlikten kaçındığı bütün süslü fikirleri ikinciye doldurur.
                    MD,
                    <<<'MD'
                    **Kavramsal bütünlük** bir sistemin en önemli özelliğidir.

                    Tek tek iyi ama birbiriyle uyumsuz fikirlerle dolu bir sistem,
                    tutarlı ama daha mütevazı bir sistemden kötüdür. Kullanıcı sistemin
                    ne yapacağını tahmin edebilmelidir.
                    MD,
                    <<<'MD'
                    Gümüş kurşun yoktur.

                    Yazılımın *özsel* karmaşıklığı — problemin kendisinin karmaşıklığı —
                    araçlarla ortadan kaldırılamaz. Araçlar yalnızca *ilineksel*
                    karmaşıklığı azaltır.
                    MD,
                ],
            ],
            [
                'title' => 'Legacy Kodla Etkili Çalışmak',
                'author' => 'Michael Feathers',
                'type' => 'book',
                'frequency' => 'low',
                'highlights' => [
                    <<<'MD'
                    **Legacy kod, testi olmayan koddur.**

                    Yaşına, diline veya kim yazdığına bakmaz. Dün yazdığın testsiz kod da
                    legacy'dir. Bu tanım suçlamayı kaldırır ve yerine bir eylem koyar:
                    test ekle.
                    MD,
                    <<<'MD'
                    **Seam (dikiş)**, kodu düzenlemeden davranışını değiştirebildiğin
                    yerdir.

                    Test yazmak için önce bir dikiş bulman gerekir: bir arayüz, bir
                    parametre, üzerine yazılabilen bir metot. Dikiş yoksa, önce en küçük
                    ve en güvenli müdahaleyle bir tane açarsın.
                    MD,
                    <<<'MD'
                    **Karakterizasyon testi**, kodun ne yapması gerektiğini değil,
                    şu an **ne yaptığını** kaydeder.

                    Doğru davranışı bilmiyor olabilirsin. Önemli değil: mevcut davranışı
                    dondur, sonra güvenle değiştir. Test kırıldığında en azından neyi
                    değiştirdiğini bilirsin.
                    MD,
                    <<<'MD'
                    Değişiklik yapmadan önce sor: bu kodu test altına almak ne kadar
                    sürer?

                    Cevap "çok uzun" ise, çoğu zaman doğru hamle testten vazgeçmek değil,
                    daha küçük bir dikiş bulmaktır.
                    MD,
                ],
            ],
            [
                'title' => 'Test Güdümlü Geliştirme',
                'author' => 'Kent Beck',
                'type' => 'book',
                'frequency' => 'normal',
                'highlights' => [
                    <<<'MD'
                    Döngü: **kırmızı → yeşil → düzenle**.

                    1. Başarısız olan bir test yaz
                    2. Onu geçirecek en basit kodu yaz
                    3. Tekrarı temizle

                    Üçüncü adımı atlamak TDD'yi "önce test yazma"ya indirger; asıl
                    tasarım kazancı orada.
                    MD,
                    <<<'MD'
                    Testi önce yazmanın asıl faydası doğrulama değil, **tasarım
                    geri bildirimidir**.

                    Test yazmak zorsa, tasarım da zordur. Kurulumu on satır süren bir
                    test, o sınıfın çok fazla şey bildiğini söylüyordur.
                    MD,
                    <<<'MD'
                    Yeşile ulaşmanın üç yolu vardır:

                    - **Sahtesini yap** — sabit döndür, sonra gerçeğe doğru genelle
                    - **Bariz gerçekleme** — cevabı biliyorsan doğrudan yaz
                    - **Üçgenleme** — ikinci bir test ekleyip genellemeye zorla

                    Emin olduğunda bariz gerçeklemeyi kullan; tıkandığında sahtesini yap.
                    MD,
                    <<<'MD'
                    Test listesi tut. Aklına gelen her durumu yaz, ama hepsini birden
                    yazmaya çalışma.

                    Bu, hem odağı korur hem de "unutmadan yazayım" dürtüsünü söndürür.
                    MD,
                ],
            ],
            [
                'title' => 'Sürekli Teslimat',
                'author' => 'Humble & Farley',
                'type' => 'book',
                'frequency' => 'often',
                'highlights' => [
                    <<<'MD'
                    **Bir kez derle, her yere aynı artefaktı taşı.**

                    Her ortam için yeniden derlersen, test ettiğin şeyle yayına aldığın
                    şeyin aynı olduğunu kanıtlayamazsın. Ortam farkları koda değil,
                    yapılandırmaya girer.
                    MD,
                    <<<'MD'
                    Yayın süreci acı veriyorsa çözüm daha seyrek yayınlamak değil, **daha
                    sık** yayınlamaktır.

                    Acı veren şeyi sıklaştırmak kulağa ters gelir; ama sıklaştırdığında
                    otomatikleştirmek zorunda kalırsın ve her yayının içerdiği değişiklik
                    küçüldüğü için risk düşer.
                    MD,
                    <<<'MD'
                    **Yayın (deploy) ile sürüm (release) aynı şey değildir.**

                    Kodu üretime taşımak ile o özelliği kullanıcılara açmak ayrı
                    kararlardır. Özellik bayrakları bu ikisini ayırır: kod üretimde
                    kapalı durur, açma kararı ayrı ve geri alınabilir olur.
                    MD,
                    <<<'MD'
                    Yapılandırma sırrı koda girmez, sürüm kontrolüne girmez.

                    Ortam değişkeni, gizli anahtar deposu, ne kullanıyorsan kullan; ama
                    depoya bir kez giren sır, geçmişten silinene kadar sızmış sayılır.
                    MD,
                ],
            ],
            [
                'title' => 'Site Reliability Engineering',
                'author' => 'Google',
                'type' => 'book',
                'frequency' => 'often',
                'highlights' => [
                    <<<'MD'
                    **%100 çalışırlık yanlış hedeftir.**

                    Kullanıcının deneyimi zaten kendi ağı, cihazı ve tarayıcısıyla
                    sınırlıdır; senin %99.99 ile %100 arasındaki farkını göremez. Ama o
                    farkı kovalamanın maliyeti üsteldir.
                    MD,
                    <<<'MD'
                    **Hata bütçesi**, güvenilirlik ile hız arasındaki tartışmayı bitirir.

                    SLO %99.9 ise, ayda yaklaşık 43 dakika hata bütçen var. Bütçe
                    dolmadıysa yeni özellik çıkmaya devam edersin; dolduysa öncelik
                    otomatik olarak istikrara döner.

                    Bu, "geliştirici hızlı ister, operasyon istikrar ister" çekişmesini
                    öznel olmaktan çıkarıp sayıya bağlar.
                    MD,
                    <<<'MD'
                    İzlemenin **dört altın sinyali**:

                    - Gecikme (latency)
                    - Trafik
                    - Hata oranı
                    - Doygunluk (saturation)

                    Yalnızca dördünü ölçebiliyorsan, önce bunları ölç.
                    MD,
                    <<<'MD'
                    **Toil**, tekrarlayan, otomatikleştirilebilir, kalıcı değer üretmeyen
                    ve hizmet büyüdükçe doğrusal artan manuel iştir.

                    Toil'i sıfırlamak hedef değildir, ama ölçmek ve bir üst sınıra bağlamak
                    gerekir. Ölçülmeyen toil bütün mühendislik zamanını sessizce yer.
                    MD,
                    <<<'MD'
                    **Suçsuz postmortem.**

                    İnsanları suçlamak, bir dahaki sefere olayların gizlenmesini sağlar;
                    sistemi düzeltmez. Doğru soru "kim yaptı" değil, "bu hatayı bu kadar
                    kolay yapılabilir kılan neydi"dir.
                    MD,
                ],
            ],
            [
                'title' => 'Release It!',
                'author' => 'Michael T. Nygard',
                'type' => 'book',
                'frequency' => 'normal',
                'highlights' => [
                    <<<'MD'
                    **Zaman aşımı olmayan her çağrı, sonsuza kadar bekleyebilir.**

                    En sık atlanan ayar budur. Zaman aşımı yoksa, uzak servisteki bir
                    yavaşlama senin bütün iş parçacıklarını tüketir ve çökme oraya değil
                    sana yazılır.
                    MD,
                    <<<'MD'
                    **Devre kesici (circuit breaker).**

                    Bir bağımlılık sürekli hata veriyorsa, her istekte tekrar denemek hem
                    seni hem onu boğar. Devreyi aç, bir süre hiç deneme, sonra tek bir
                    yoklama isteğiyle kontrol et.

                    Amaç hatayı gizlemek değil, **hızlı başarısız olmaktır**.
                    MD,
                    <<<'MD'
                    **Bulkhead (bölme).**

                    Gemide bölmeler vardır ki bir bölme su alınca gemi batmasın. Kaynak
                    havuzlarını ayır: rapor sorguları kullanıcı isteklerinin bağlantı
                    havuzunu tüketememeli.
                    MD,
                    <<<'MD'
                    Kaskad arızalar tek bir küçük hatayla başlar, sonra sistemin kendi
                    savunma mekanizmalarıyla büyür.

                    Klasik örnek: bir servis yavaşlar → istemciler tekrar dener → yük
                    artar → daha da yavaşlar. **Yeniden deneme, üstel geri çekilme ve
                    rastgeleleştirme (jitter) olmadan bir saldırı aracıdır.**
                    MD,
                ],
            ],
            [
                'title' => 'Kurumsal Uygulama Mimarisi Kalıpları',
                'author' => 'Martin Fowler',
                'type' => 'book',
                'frequency' => 'low',
                'highlights' => [
                    <<<'MD'
                    **Active Record** ile **Data Mapper** arasındaki seçim, alan
                    mantığının ne kadar karmaşık olduğuna bağlıdır.

                    Active Record basit CRUD'da hızlıdır ve az tören ister. Alan modeli
                    veritabanı şemasından uzaklaşmaya başladığında Data Mapper kazanır,
                    çünkü modelin kalıcılıktan haberi olmaz.
                    MD,
                    <<<'MD'
                    **Unit of Work**, iş boyunca değişen nesneleri takip eder ve hepsini
                    tek bir işlemde yazar.

                    Böylece hem yazma sayısı düşer hem de "yarısı kaydedildi" durumu
                    ortadan kalkar.
                    MD,
                    <<<'MD'
                    **Identity Map**: aynı kayıt tek istekte iki kez yüklenmemeli.

                    Aksi hâlde bellekte aynı satırın iki farklı nesnesi olur ve biri
                    diğerinin değişikliğini ezer.
                    MD,
                    <<<'MD'
                    **N+1 sorgu problemi**, ORM'lerin en yaygın performans tuzağıdır.

                    Bir liste çekersin (1 sorgu), sonra her eleman için ilişkisine
                    dokunursun (N sorgu). Kod masum görünür, çünkü döngüde sorgu yazmazsın
                    — sorguyu ORM senin adına yazar.

                    ```php
                    // 1 + N
                    foreach (Post::all() as $post) {
                        echo $post->author->name;
                    }

                    // 2 sorgu
                    foreach (Post::with('author')->get() as $post) {
                        echo $post->author->name;
                    }
                    ```

                    Geliştirmede tembel yüklemeyi tamamen kapatmak, bunu üretimde değil
                    testte yakalamanı sağlar.
                    MD,
                ],
            ],
            [
                'title' => 'SICP',
                'author' => 'Abelson & Sussman',
                'type' => 'book',
                'frequency' => 'rare',
                'highlights' => [
                    <<<'MD'
                    Programlar insanların okuması için yazılır; makinelerin çalıştırması
                    yalnızca ikincil bir amaçtır.
                    MD,
                    <<<'MD'
                    **Soyutlama**, ayrıntıyı yok etmez; onu isimlendirip bir kenara
                    koymanı sağlar.

                    İyi bir soyutlama, kullanıcısının içine bakmasını gerektirmez.
                    Sızdıran soyutlama, kazandırdığından fazlasını götürür.
                    MD,
                    <<<'MD'
                    Özyinelemeli **süreç** ile özyinelemeli **prosedür** aynı şey değildir.

                    Kuyruk özyinelemeli yazılmış bir prosedür, yinelemeli bir süreç
                    üretir ve yığını büyütmez. Şekle değil, çalışma zamanındaki davranışa
                    bak.
                    MD,
                    <<<'MD'
                    Yüksek mertebeden fonksiyonlar, kalıbın kendisini soyutlamanı sağlar.

                    Toplama, çarpma ve integral almanın ortak iskeleti aynıdır; farklı
                    olan yalnızca içine verilen fonksiyondur.
                    MD,
                ],
            ],
            [
                'title' => 'Algoritmalara Giriş',
                'author' => 'Cormen, Leiserson, Rivest, Stein',
                'type' => 'book',
                'frequency' => 'low',
                'highlights' => [
                    <<<'MD'
                    Asimptotik gösterim, girdi büyüdükçe **büyüme hızını** anlatır;
                    küçük girdilerde hangi algoritmanın hızlı olduğunu söylemez.

                    O(n log n) bir algoritma, sabitleri büyükse n=100 için O(n²) olandan
                    yavaş olabilir.
                    MD,
                    <<<'MD'
                    Sık karşılaşılan karmaşıklıklar:

                    | Yapı | Arama | Ekleme | Not |
                    | --- | --- | --- | --- |
                    | Dizi | O(n) | O(1) sonda | bellekte bitişik |
                    | Sıralı dizi | O(log n) | O(n) | ikili arama |
                    | Hash tablosu | O(1) ort. | O(1) ort. | en kötü O(n) |
                    | Dengeli ağaç | O(log n) | O(log n) | sıralı gezinme |

                    Hash tablosunun "O(1)" olması ortalamadır; kötü hash fonksiyonu veya
                    saldırgan girdi bunu O(n)'e düşürür.
                    MD,
                    <<<'MD'
                    **Amortize analiz**, pahalı ama seyrek işlemlerin maliyetini ucuz ve
                    sık işlemlere yayar.

                    Dinamik dizinin büyümesi tek seferde O(n)'dir, ama n ekleme boyunca
                    ekleme başına amortize maliyet O(1)'dir.
                    MD,
                    <<<'MD'
                    Karşılaştırmaya dayalı hiçbir sıralama algoritması O(n log n)'den iyi
                    olamaz.

                    Bu bir mühendislik sınırı değil, bilgi teorik bir alt sınırdır.
                    Daha hızlısını istiyorsan karşılaştırmayı bırakman gerekir — sayma
                    sıralaması ve radix sıralaması bunu yapar.
                    MD,
                ],
            ],
            [
                'title' => 'Bilgisayar Ağları: Yukarıdan Aşağıya',
                'author' => 'Kurose & Ross',
                'type' => 'book',
                'frequency' => 'normal',
                'highlights' => [
                    <<<'MD'
                    TCP üç yollu el sıkışma ile bağlantı kurar: `SYN` → `SYN-ACK` → `ACK`.

                    Bu, her yeni bağlantının veri göndermeden önce en az bir gidiş-dönüş
                    süresi (RTT) maliyeti olduğu anlamına gelir. Bağlantıyı yeniden
                    kullanmak (keep-alive) bu yüzden önemlidir.
                    MD,
                    <<<'MD'
                    HTTPS'te maliyet yalnızca şifreleme değildir; TLS el sıkışması ek
                    gidiş-dönüş getirir.

                    TLS 1.3 bunu bir RTT'ye indirir, oturum yeniden kullanımında sıfıra
                    yaklaştırır. Uzak bir sunucuya yapılan ilk isteğin neden yavaş
                    olduğunun cevabı genellikle buradadır.
                    MD,
                    <<<'MD'
                    **DNS bir önbellek hiyerarşisidir.**

                    TTL süresince kayıt önbellekte kalır. Bu yüzden DNS değişikliği
                    "hemen" yayılmaz ve geçiş planlarında TTL'i önceden düşürmek gerekir.
                    MD,
                    <<<'MD'
                    Gecikme ile bant genişliği farklı şeylerdir ve biri diğerini
                    kurtarmaz.

                    Bant genişliğini artırarak büyük dosya transferini hızlandırabilirsin;
                    ama ışık hızı sabit olduğu için İstanbul–Kaliforniya arasındaki RTT'yi
                    satın alamazsın. Çok sayıda küçük istek yapan bir arayüz, hızlı
                    bağlantıda bile yavaştır.
                    MD,
                ],
            ],
            [
                'title' => 'İşletim Sistemleri: Üç Kolay Parça',
                'author' => 'Arpaci-Dusseau',
                'type' => 'book',
                'frequency' => 'normal',
                'highlights' => [
                    <<<'MD'
                    İşletim sisteminin üç büyük konusu: **sanallaştırma**, **eşzamanlılık**
                    ve **kalıcılık**.

                    CPU ve bellek sanallaştırılır, eşzamanlılık paylaşılan durumu yönetir,
                    kalıcılık veriyi çökmelere rağmen korur.
                    MD,
                    <<<'MD'
                    **Yarış koşulu**, sonucun zamanlamaya bağlı olmasıdır.

                    `count = count + 1` tek satırdır ama üç makine komutudur: oku,
                    artır, yaz. İki iş parçacığı arasına giren bağlam değişimi, bir
                    artırmayı yok eder.

                    Bu yüzden "veritabanında `UPDATE ... SET n = n + 1`" ile "PHP'de oku,
                    artır, kaydet" aynı şey değildir.
                    MD,
                    <<<'MD'
                    Kilitlenme (deadlock) için dört koşulun **aynı anda** sağlanması
                    gerekir:

                    1. Karşılıklı dışlama
                    2. Tut ve bekle
                    3. Kaynağı zorla alamama
                    4. Döngüsel bekleme

                    Herhangi birini kırmak yeter. Pratikte en kolayı dördüncüsüdür:
                    kilitleri her yerde aynı sırayla al.
                    MD,
                    <<<'MD'
                    Sanal bellek, her sürece kendi kesintisiz adres alanına sahipmiş gibi
                    hissettirir.

                    Sayfa tablosu bu yanılsamayı kurar; TLB ise onu hızlandıran
                    önbellektir. Bellek erişim örüntün TLB dostu değilse, algoritma aynı
                    kalsa bile program yavaşlar.
                    MD,
                ],
            ],
            [
                'title' => 'Yüksek Performanslı MySQL',
                'author' => 'Schwartz, Zaitsev, Tkachenko',
                'type' => 'book',
                'frequency' => 'often',
                'highlights' => [
                    <<<'MD'
                    B-tree indeksi **en soldan önek** kuralıyla çalışır.

                    `(a, b, c)` indeksi şu sorgulara yarar: `a`, `a+b`, `a+b+c`.
                    Yalnızca `b` veya yalnızca `c` üzerinde filtreleyen sorguya yaramaz.

                    Bu yüzden bileşik indekste kolon sırası bir tercih değil, bir
                    tasarımdır.
                    MD,
                    <<<'MD'
                    İndekslenmiş kolonu bir fonksiyona sokarsan indeks kullanılmaz:

                    ```sql
                    -- indeks kullanılmaz
                    WHERE YEAR(created_at) = 2026

                    -- indeks kullanılır
                    WHERE created_at >= '2026-01-01'
                      AND created_at <  '2027-01-01'
                    ```
                    MD,
                    <<<'MD'
                    **Kapsayan indeks (covering index)**, sorgunun ihtiyaç duyduğu bütün
                    kolonları içerir.

                    Bu durumda veritabanı satırın kendisine hiç gitmez; indeksten okuyup
                    işi bitirir. `EXPLAIN` çıktısında `Using index` görüyorsan budur.
                    MD,
                    <<<'MD'
                    `EXPLAIN` okumadan indeks eklemek tahmin yürütmektir.

                    Bakılacak alanlar: `type` (`ALL` tam tarama demektir), `key` (hangi
                    indeks seçildi), `rows` (tahmini taranan satır) ve `Extra`
                    (`Using filesort`, `Using temporary` uyarı işaretleridir).
                    MD,
                    <<<'MD'
                    InnoDB satır seviyesinde kilitler, ama **indeks kayıtlarını** kilitler.

                    Uygun indeks yoksa, tarama sırasında beklediğinden çok daha fazla
                    satır kilitlenir. "Neden bu kadar çekişme var?" sorusunun cevabı
                    genellikle eksik bir indekstir.
                    MD,
                ],
            ],
            [
                'title' => 'Phoenix Projesi',
                'author' => 'Kim, Behr, Spafford',
                'type' => 'book',
                'frequency' => 'rare',
                'highlights' => [
                    <<<'MD'
                    Dört tip iş vardır: planlı proje işi, iç proje işi, değişiklikler ve
                    **plansız iş**.

                    Plansız iş diğer üçünü yer. Görünmez olduğu için de kimse ne kadar
                    zaman harcandığını bilmez.
                    MD,
                    <<<'MD'
                    **Darboğaz dışındaki her iyileştirme yanılsamadır.**

                    Sistemin çıktısı darboğazın kapasitesiyle sınırlıdır. Darboğazdan önce
                    hızlanmak yalnızca kuyruğu büyütür.
                    MD,
                    <<<'MD'
                    Devam eden işi (WIP) sınırlamak, sezgiye aykırı biçimde teslimatı
                    hızlandırır.

                    Aynı anda beş işe başlayan bir ekip, hiçbirini bitirmeden bağlam
                    değiştirmeye harcadığı zamanı da kaybeder.
                    MD,
                ],
            ],
            [
                'title' => 'Peopleware',
                'author' => 'DeMarco & Lister',
                'type' => 'book',
                'frequency' => 'low',
                'highlights' => [
                    <<<'MD'
                    Yazılım projelerinin çoğu teknolojik değil, **sosyolojik** sebeplerle
                    başarısız olur.

                    Buna rağmen yöneticilerin çoğu zamanını teknoloji seçmeye harcar.
                    MD,
                    <<<'MD'
                    **Akış (flow)** durumuna girmek yaklaşık 15 dakika sürer.

                    Bu yüzden "sadece bir saniyeni alacağım" diye başlayan kesinti,
                    bir saniye değil, on beş dakikadır. Açık ofisin asıl maliyeti
                    burada gizlidir.
                    MD,
                    <<<'MD'
                    İnsanlar makine değildir; iki geliştirici arasındaki verimlilik farkı
                    aynı deneyim seviyesinde bile 10 katına çıkabilir.

                    Ama aynı şirketten gelen geliştiriciler birbirine benzer performans
                    gösterir — yani fark büyük ölçüde **ortamdandır**.
                    MD,
                    <<<'MD'
                    Baskı altındaki ekipler daha hızlı çalışmaz; yalnızca daha çok hata
                    yapar ve kestirme yollara sapar.

                    Fazla mesai kısa vadede kazandırdığını, orta vadede tükenmişlik ve
                    işten ayrılmayla fazlasıyla geri öder.
                    MD,
                ],
            ],
        ];
    }
}
