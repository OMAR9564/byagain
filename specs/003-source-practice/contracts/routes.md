# Contract: Routes

**Feature**: `003-source-practice`

Bütün rotalar `auth` + `ensure.active` grubundadır. Başka hesabın kaynağı veya
pasajı `BelongsToUser` scope'u nedeniyle **404** döner, 403 değil (SPEC 2).

## Yeni rotalar

| Method | Path | Name | Middleware | Yanıt |
|---|---|---|---|---|
| GET | `/library/sources/{source}/practice` | `practice.show` | `throttle:30,1` | HTML pratik ekranı; aktif pasaj yoksa `sources.show`'a yönlendirme + `status` |
| POST | `/library/sources/{source}/practice/{highlight}` | `practice.action` | `throttle:120,1`, `scopeBindings` | JSON `{"ok": true}` |
| GET | `/library/sources/{source}/export` | `sources.export` | `throttle:30,1` | HTML dışa aktarma ekranı; aktif pasaj yoksa `sources.show`'a yönlendirme |
| GET | `/library/sources/{source}/export/download` | `sources.export.download` | `throttle:30,1` | `text/markdown; charset=UTF-8`, `Content-Disposition: attachment` |

### `practice.action` gövdesi

`review.item.action` ile aynı biçim, ki `review.js` farkı bilmesin:

```json
{
  "action": "keep | discard",
  "favorite": true,
  "source_frequency": "never | rare | low | normal | often | very_often",
  "client_acted_at": "2026-10-02T08:01:12Z",
  "mastery_feedback": null
}
```

- `action` zorunlu, `keep` veya `discard`.
- `favorite` isteğe bağlı boolean; yalnız `true` etkili.
- `source_frequency` isteğe bağlı, `StoreSourceRequest::frequencies()` içinden;
  `{highlight}`'ın kaynağına (yani `{source}`'a) uygulanır.
- `client_acted_at`, `mastery_feedback` kabul edilir ve yok sayılır (istemci
  uyumluluğu).
- Doğrulama: yeni `PracticeActionRequest` (Form Request, Ana Yasa III).
- Aynı çağrının tekrarı aynı sonucu verir (çevrimdışı kuyruk yeniden oynatır).
- `{highlight}` `{source}`'a ait değilse **404**.
- Çöpe atılmış bir pasaja eylem: 200, etkisiz (kuyruktaki ikinci gönderim).

## Değişen rotalar

| Name | Değişiklik |
|---|---|
| `highlights.create` (`GET /add`) | İsteğe bağlı `?source={id}`. Kullanıcının arşivlenmemiş kaynağıysa ön seçilir; değilse **hata vermeden** yok sayılır (FR-215, FR-227) |
| `highlights.store` (`POST /highlights`) | Başarıda `highlights.create?source={source_id}`'ye yönlenir (eskiden `sources.show`). Flash: `status` + `saved_source_id` |

`highlights.update` değişmez: kaynak sayfasına döner (FR-217).

## Değişmeyen ama dikkat

- `IdorTest::every_id_bearing_route_returns_not_found_for_another_readers_record`
  tek parametreli GET rotalarını kendisi bulur: `practice.show`, `sources.export`,
  `sources.export.download` otomatik kapsanır. `practice.action` iki parametreli
  olduğu için açık test gerekir.
- `docs/SPEC.md` §2 rota tablosu güncellenir.
