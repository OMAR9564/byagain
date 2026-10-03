<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Content;

use App\Services\Content\PassageImportParser;
use Tests\TestCase;

final class PassageImportParserTest extends TestCase
{
    public function test_parses_sample_format_with_em_dash(): void
    {
        $markdown = <<<'MD'
## Kart 24 — EXPLAIN ANALYZE ve ölçüm

**Açıklama:** EXPLAIN ANALYZE sorgu planını ve gerçek çalışmanın ölçümlerini gösterir.

**Hatırla:** Planı incele → aynı koşullarda önce/sonra ölç.

**Soru 1:** Index'in işe yaradığını nasıl kontrol edersin?
**Cevap:** EXPLAIN ANALYZE ile planı incelerim.

**Soru 2:** Sorgu 20 ms, API 3 saniye sürüyor. Nereden başlarsın?
**Cevap:** Kalan yaklaşık 2,98 saniyenin nerede geçtiğini araştırırım.
MD;

        $parser = new PassageImportParser;
        $result = $parser->parse($markdown);

        $this->assertCount(1, $result);

        $passage = $result[0];

        // Check title was stripped correctly.
        $this->assertStringContainsString('## EXPLAIN ANALYZE ve ölçüm', $passage['content_md']);

        // Check cards were parsed.
        $this->assertCount(2, $passage['cards']);

        $this->assertSame('qa', $passage['cards'][0]['type']);
        $this->assertStringContainsString('Index', $passage['cards'][0]['question']);
        $this->assertStringContainsString('EXPLAIN ANALYZE', $passage['cards'][0]['answer']);

        $this->assertSame('qa', $passage['cards'][1]['type']);
        $this->assertStringContainsString('Sorgu 20 ms', $passage['cards'][1]['question']);
    }

    public function test_strips_en_dash_prefix(): void
    {
        $markdown = <<<'MD'
## Card 41 – Load balancer

**Açıklama:** Load balancer iki sunucuyu dengeliyor.

**Soru 1:** Nasıl çalışır?
**Cevap:** İsteği dağıtır.
MD;

        $parser = new PassageImportParser;
        $result = $parser->parse($markdown);

        $this->assertCount(1, $result);
        $this->assertStringContainsString('## Load balancer', $result[0]['content_md']);
    }

    public function test_strips_hyphen_prefix(): void
    {
        $markdown = <<<'MD'
## Kart 10 - Simple topic

**Açıklama:** A simple passage.

**Soru 1:** Question?
**Cevap:** Answer.
MD;

        $parser = new PassageImportParser;
        $result = $parser->parse($markdown);

        $this->assertCount(1, $result);
        $this->assertStringContainsString('## Simple topic', $result[0]['content_md']);
    }

    public function test_hatirla_is_optional(): void
    {
        $markdown = <<<'MD'
## Kart 5 — No remember section

**Açıklama:** This passage has no Hatırla.

**Soru 1:** Question?
**Cevap:** Answer.
MD;

        $parser = new PassageImportParser;
        $result = $parser->parse($markdown);

        $this->assertCount(1, $result);

        // Hatırla should not appear in content_md.
        $this->assertStringNotContainsString('**Hatırla:**', $result[0]['content_md']);
    }

    public function test_trims_trailing_spaces(): void
    {
        $markdown = "## Kart 1 — Title  \n\n**Açıklama:** Content with trailing spaces  \n\n**Soru 1:** Question?  \n**Cevap:** Answer  ";

        $parser = new PassageImportParser;
        $result = $parser->parse($markdown);

        $this->assertCount(1, $result);

        // Verify no trailing spaces in the question.
        $this->assertSame('Question?', trim($result[0]['cards'][0]['question']));
        $this->assertSame('Answer', trim($result[0]['cards'][0]['answer']));
    }

    public function test_parses_multiple_passages(): void
    {
        $markdown = <<<'MD'
## Kart 1 — First

**Açıklama:** First passage.

**Soru 1:** Q1?
**Cevap:** A1.

## Kart 2 — Second

**Açıklama:** Second passage.

**Soru 1:** Q2?
**Cevap:** A2.
MD;

        $parser = new PassageImportParser;
        $result = $parser->parse($markdown);

        $this->assertCount(2, $result);
        $this->assertStringContainsString('## First', $result[0]['content_md']);
        $this->assertStringContainsString('## Second', $result[1]['content_md']);
    }

    public function test_parses_three_questions(): void
    {
        $markdown = <<<'MD'
## Kart 3 — Three Questions

**Açıklama:** A passage with three questions.

**Soru 1:** First question?
**Cevap:** First answer.

**Soru 2:** Second question?
**Cevap:** Second answer.

**Soru 3:** Third question?
**Cevap:** Third answer.
MD;

        $parser = new PassageImportParser;
        $result = $parser->parse($markdown);

        $this->assertCount(1, $result);
        $this->assertCount(3, $result[0]['cards']);

        $this->assertStringContainsString('First', $result[0]['cards'][0]['question']);
        $this->assertStringContainsString('Second', $result[0]['cards'][1]['question']);
        $this->assertStringContainsString('Third', $result[0]['cards'][2]['question']);
    }

    public function test_keeps_hatirla_when_blocks_are_separated_by_blank_lines(): void
    {
        $markdown = "## Kart 7 — Başlık\n\n"
            ."**Açıklama:** Açıklama metni.\n\n"
            ."**Hatırla:** Hatırlatma metni.\n\n"
            ."**Soru 1:** Birinci soru?  \n"
            ."**Cevap:** Birinci cevap.\n\n"
            ."**Soru 2:** İkinci soru?  \n"
            ."**Cevap:** İkinci cevap.\n";

        $result = (new PassageImportParser)->parse($markdown);

        $this->assertCount(1, $result);
        $this->assertSame(
            "## Başlık\n\n**Açıklama:** Açıklama metni.\n\n**Hatırla:** Hatırlatma metni.",
            $result[0]['content_md']
        );
        $this->assertSame('İkinci soru?', $result[0]['cards'][1]['question']);
        $this->assertSame('İkinci cevap.', $result[0]['cards'][1]['answer']);
    }

    public function test_omits_hatirla_paragraph_when_label_is_absent(): void
    {
        $markdown = "## Kart 8 — Yalın\n\n**Açıklama:** Sadece açıklama.\n\n"
            ."**Soru 1:** Soru?  \n**Cevap:** Cevap.\n";

        $result = (new PassageImportParser)->parse($markdown);

        $this->assertSame("## Yalın\n\n**Açıklama:** Sadece açıklama.", $result[0]['content_md']);
        $this->assertCount(1, $result[0]['cards']);
    }

    public function test_parses_real_export_sections(): void
    {
        $markdown = <<<'MD'
## Kart 24 — EXPLAIN ANALYZE ve ölçüm

**Açıklama:** EXPLAIN ANALYZE sorgu planını ve gerçek çalışmanın ölçümlerini gösterir; sorguyu gerçekten çalıştırır.

**Hatırla:** Planı incele → aynı koşullarda önce/sonra ölç.

**Soru 1:** Index’in işe yaradığını nasıl kontrol edersin?  
**Cevap:** EXPLAIN ANALYZE ile planı, taranan satırları ve süreleri incelerim.

**Soru 2:** Sorgu 20 ms, API 3 saniye sürüyor. Nereden başlarsın?  
**Cevap:** Kalan yaklaşık 2,98 saniyenin nerede geçtiğini araştırırım.

## Kart 27 — Async ve ağır hesap

**Açıklama:** Async, bir iş I/O cevabı beklerken diğer işlerin ilerlemesini sağlar.

**Hatırla:** Bekleme → async. Ağır hesap → ayrı çalışma kaynağı.

**Soru 1:** Async için daha uygun örnek hangisi: API beklemek mi, ağır hesap mı?  
**Cevap:** API cevabını beklemek.

**Soru 2:** Async 100 DB sorgusunu tek sorguya indirir mi?  
**Cevap:** Hayır. Bekleme biçimini düzenler.

**Soru 3:** Sırayla üç API beklemekle birlikte başlatmak arasındaki fark ne?  
**Cevap:** Sırayla beklemeler toplanır; birlikte başlatınca örtüşür.
MD;

        $result = (new PassageImportParser)->parse($markdown);

        $this->assertCount(2, $result);
        $this->assertCount(2, $result[0]['cards']);
        $this->assertCount(3, $result[1]['cards']);

        foreach ($result as $passage) {
            $this->assertStringContainsString("\n\n**Hatırla:** ", $passage['content_md']);
        }
    }
}
