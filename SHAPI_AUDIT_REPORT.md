# Shapi Catalog Audit Report

Date: 2026-03-27

## Summary

- Active templates checked: 89
- `db-linked`: 82
- `alias`: 1
- `name-match`: 1
- `none`: 5
- Templates still using placeholder main image (`cover*.jpg` / `rbjlogo.png`): 7

## Resolution Rules In Use

- `db-linked`: the template already points to a `shapi/...` folder through `customization_templates.image_path` or `product_images`, so the catalog/product flow now trusts that folder first.
- `alias`: a manual alias is used for a known naming mismatch.
- `name-match`: fallback token matching is used only when it clears the stricter threshold.
- `none`: no safe `shapi` folder is assigned, so the system stays on the generic image instead of guessing the wrong variants.

## Healthy Mappings

These are already clean because the live DB points to a concrete `shapi` folder:

- IDs `17, 20-28, 30-40, 43, 45, 48-106`

Examples:

- `FULL SMOKE INDO SEAT COVER | UNIVERSAL SEAT` -> `full_smoke_carbon_indo_seat_cover`
- `RACING SEAT INFINITY KNOT WITH FLAME AND` -> `infinity_knot_with_flame_indo_concept_universal_seat_cover_rbj_accessories`
- `DARUMA UNIVERSAL SEAT COVER` -> `daruma_version_2_universal_seat_cover`
- `Indoseat Newdesign` -> `indoseat_newdesign`

## Special Cases

### Alias-Based

- ID `29`: `INDO CONCEPT SEATS | NEW DESIGN` -> `indoseat_newdesign`
- ID `21`: `BRIDE DARK EDITION SEAT COVER | UNIVERSAL` -> `bride_dark_edition_universal_seat_cover`
- ID `45`: `BRIDE UNIVERSAL SEAT COVER` -> `bride_universal_seat_cover`

These now resolve cleanly even though the template names and folder names do not match closely enough on their own.

### Fallback Name Match

- ID `42`: `INFINITY KNOT 3D F1 LIHA TEXTURE | INDO SEAT` -> `3d_carbon_indo_seat_cover_smoke_&_f1_carbon`

This is the only remaining non-DB fallback still considered safe enough by the stricter matcher.

## Needs Manual Review

These templates currently have no safe `shapi` mapping and still use a generic placeholder image:

- ID `16`: `STELLAR SEAT COVER | UNIVERSAL SEAT COVER`
- ID `18`: `PLAIN INDO COVER WITH SPLIT CHECKERED`
- ID `19`: `CLEAR COVER FOR MOTOR SEAT | UNIVERSAL SEAT`
- ID `41`: `YAYAMANIN UNIVERSAL SEAT COVER | RBJ`
- ID `44`: `SKULL PAISLEY SEAT COVER | UNIVERSAL SEAT`

These were intentionally left unresolved because the old matcher could attach them to the wrong `shapi` folder.

## Placeholder Main Images

Templates still showing placeholder-style main paths:

- ID `16`: `cover1.jpg`
- ID `18`: `cover1.jpg`
- ID `19`: `cover1.jpg`
- ID `29`: `cover1.jpg`
- ID `41`: `cover1.jpg`
- ID `42`: `cover1.jpg`
- ID `44`: `cover1.jpg`

Note:
- ID `29` and ID `42` now render real `shapi` images in the catalog/product flow despite the placeholder DB path, because runtime resolution can infer their correct folder.
- IDs `21` and `45` were manually resolved with dedicated `shapi` folders so their catalog main cards and sub cards now stay consistent.
- The other unresolved placeholder templates still need real image/folder assignment in the database if you want proper main cards and sub cards.

## Recommendation

Best next step:

1. Manually decide the correct `shapi` folder for IDs `16, 18, 19, 41, 44`.
2. Update their `customization_templates.image_path` and related `product_images` rows to point at the intended `shapi/...` assets.
3. After that, the catalog and product pages will use the correct main card and sub card flow automatically.
