# Image Comparison — Piwigo plugin

Compare two photos **side by side** or with a **draggable reveal slider**, with
**synchronised zoom & pan**. Built for RAW‑vs‑JPEG checks, edit‑before/after, and
version culling — plus admin‑curated **saved comparison pairs**.

* Plugin id: `image_comparison`
* Compatible with Piwigo 16.x (PHP 7.4 → 8.4)
* Repo: <https://github.com/akhoacoby/piwigo-plugin-photo-compare> · branch `feat/with-skill`

## Features

- **Two view modes**
  - *Side by side* — both photos next to each other.
  - *Slider* — the photos overlap and a draggable divider reveals one over the other.
- **Synchronised zoom & pan** — scroll to zoom (cursor‑centred), drag to pan,
  double‑click to toggle zoom; in slider mode the two images always stay aligned.
- **Compare buttons** on photo pages (pre‑selects the current photo) and album
  pages, rendered through Piwigo's native toolbar helpers.
- **Guided picker** — pick the first then the second photo from an album when you
  haven't supplied both.
- **Saved comparisons** — administrators can save a pair (optional title); saved
  pairs are featured on the `/compare` landing page and managed from the admin
  page. Created and deleted through CSRF‑protected web‑service methods.
- **Theme‑aware, accessible** — colour‑inheriting CSS that reads correctly on the
  `default`, `modus` (incl. dark skins) and `bootstrap_darkroom` gallery themes;
  keyboard‑operable divider; respects `prefers-reduced-motion`.
- **English (en_UK) and French (fr_FR)** translations.

## How it works

The plugin registers a virtual section at **`index.php?/compare`** (claimed on
`loc_end_section_init`) and renders its body early on `loc_begin_index` so the
bundled CSS lands in `<head>`. Parameters:

```
index.php?/compare&left=<image_id>&right=<image_id>&cat=<album_id>&mode=<side_by_side|slider>
```

- `left` + `right` valid → the dual viewer.
- `cat` (or only `left`) set → the photo picker.
- nothing set → saved comparisons (or a short help message).

Only **derivative** image URLs are ever emitted (never original file paths), and
every image query is filtered with `get_sql_condition_FandF`, so a visitor only
ever sees photos they are allowed to see (fail‑closed).

## Settings (Admin → Plugins → Image Comparison)

| Setting | Description | Default |
|---|---|---|
| Default mode | Side by side / Slider | Side by side |
| Image size shown in the viewer | Derivative size (`small`…`xxlarge`) | `large` |
| Synchronise zoom & pan by default | Initial sync state | on |
| Show a Compare button on photo pages | | on |
| Show a Compare button on album pages | | on |

The **Saved comparisons** tab lists stored pairs with previews and a delete action.

## Web‑service methods

Both are `post_only` + `admin_only` and require a valid `pwg_token`:

- `image_comparison.savePair` — `left_id`, `right_id`, `mode`, `title`, `pwg_token`
- `image_comparison.deletePair` — `pair_id`, `pwg_token`

## Storage

- One serialized config entry: `$conf['image_comparison']`.
- One table: `<prefix>image_comparison_pairs`.

A clean install → uninstall cycle leaves no residue (only the config key and the
table are added, then removed).

## Development

Built from the `piwigo-plugin-starter` scaffold and guidelines.

```bash
# Lint
find . -name '*.php' -not -path './.git/*' -exec php -l {} \;
```
