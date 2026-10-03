# Contract: Study export text

**Feature**: `003-source-practice`

The text a reader pastes into an LLM. Built by `StudyExportBuilder::build(Source)`;
the same string feeds the screen's textarea, the copy button and the download.

## Shape

```markdown
<instruction>

# <source title>
<author — line omitted when null>

## Passages

### 1
<content_md, verbatim>

_<location>_            ← line omitted when null

### 2
…

## Questions             ← whole section omitted when there are no active cards

### Q1
**Q:** <question>

**A:** <answer>
…
```

## Rules

- Passages: the source's highlights with `is_discarded = false`, ordered by `id` ascending.
- Cards: `status = active`, whose highlight belongs to this source and is not discarded; ordered by their highlight's position, then card `id`.
- `content_md`, `question` and `answer` are emitted verbatim. No HTML.
- Line endings `\n`. Ends with a single trailing newline.
- Numbering is 1-based and contiguous (discarded passages leave no gaps).

## Instruction (`lang/en/practice.php` → `export.instruction`)

Must say, in English, all of:

1. You are my study partner for the passages below, which I saved from what I read.
2. Quiz me one question at a time; wait for my answer before going on.
3. Do not reveal an answer before I have tried.
4. Judge my answer against the passage, quote the relevant line when I am wrong or partly right.
5. Use the "Questions" section, if present, as some of your questions; write the rest from the passages.
6. Talk to me in the language the passages are written in.
7. When I say "stop", summarise which passages I struggled with.

Exact wording is set during implementation; tests assert the presence of the
instruction key's text at the top, not its phrasing.

## File name

`<Str::slug(title)>-<YYYY-MM-DD>.md`, date in the reader's local day
(`LocalDayResolver`). Empty slug falls back to `source-<id>`.
