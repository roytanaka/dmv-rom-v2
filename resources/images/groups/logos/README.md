# Group logos

A Group's identity mark on the Dashboard launcher (PRD #253). One file per
`App\Enums\GroupLogo` case, named for the case **value** (the filename stem), plus
the generic fallback `generic.svg`. Format is mixed — most are SVG, a few raster —
so the client resolver keys on the stem and lets the extension vary
(`resources/js/groups/logos.ts`). A unit test (`tests/Unit/GroupLogoTest.php`)
guards that every case ships exactly one asset.

These assets are **provisional**. The design's whole point is that a **reskin** is a
drop-in file swap (same filename, no code) and an **expand** is one enum case + one
`logos.ts` entry + one seeder assignment. Keep it that way.

> **Each mark must carry its own background.** The launcher tile is transparent — it
> draws no card behind the mark (`GroupTile.vue`), because these marks are already
> self-contained icons on their own colour ground and a tile chrome would just double
> it. A mark exported with a _transparent_ background floats bare against the page and
> breaks the grid's consistency. Bake a background into the art (a filled rounded
> square, matching the others) as part of the optimize-on-import step below. The
> totality test guards that a file exists, not that it has a background — this is on you.

## Adding or reskinning a mark (optimize-on-import)

The source art is legacy CorelDRAW SVG exports (and one raster). Do **not** commit
them raw — they are heavy, and several layer a vectorized trace under a broken
`<image xlink:href="..._Images\..._ImgID*.png"/>` pointing at a sub-raster that was
never preserved (a Windows-path reference to a file absent from every repo). The
visible mark is the vector trace, so that `<image>` is dead markup — drop it.
Embedded base64 (`href="data:..."`) images **are** the mark — keep those.

1. **Strip dead external rasters** (SVG only): remove every self-closing `<image .../>`
   whose `href` is not a `data:` URI. (Keep base64 `data:` images.)
2. **Optimize.**
    - **SVG** — [SVGO](https://github.com/svg/svgo). Keep the `viewBox`, drop the fixed
      `width`/`height` so the tile scales, strip metadata/comments/DOCTYPE:
        ```
        pnpm dlx svgo --multipass \
          --enable=removeDimensions,removeXMLProcInst,removeComments,removeMetadata \
          -i in.svg -o resources/images/groups/logos/<value>.svg
        ```
    - **Raster** — resize to ~256px, quantize with
      [pngquant](https://pngquant.org), then [oxipng](https://github.com/oxipng/oxipng):
        ```
        magick in.png -resize 256x256 -strip <value>.png
        pngquant --force --quality=60-85 --strip --output <value>.png <value>.png
        oxipng -o 4 --strip safe <value>.png
        ```
3. **Rename to the enum value.** Legacy stems don't all match the intended key
   (e.g. `docent.svg` → `docents`, and `special.svg` is actually Visitor Wayfinders),
   so name the output for the `GroupLogo` case value, not the source file.
4. **Wire it up**: add the `GroupLogo` case, import + map it in `logos.ts`, and assign
   `logo_key` on the node in `database/seeders/DemoSeeder.php`. Run
   `pnpm sail test tests/Unit/GroupLogoTest.php` to confirm the totality guard is green.

> Production cutover (out of scope of the seeder): the legacy import will map each
> committee to its logo 1:1 by legacy committee code, in a legacy-migration script —
> deferred to the (unscoped) legacy-data-migration PRD, not built here.
