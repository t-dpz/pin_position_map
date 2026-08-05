# pin

Plug-and-play "pin an issue's location on a floor plan" component, for
embedding into an existing issue tracker. No GPS/geolocation — just: pick a
floor, click a point on its PDF plan, store `(floor, x, y)` against your
issue id.

Live demo: `demo/issue_list.php` (start there — links to the report form and
issue detail pages). Read `demo/issue_new.php` and `demo/issue_view.php`
alongside this file — they are the two snippets below, in full, wired up.

## How it works

- `config.php` maps a **floor number** (whatever key you want to store, e.g.
  `"1"`) to a **label** (`"Verdieping 1"`) and a **PDF filename** in the
  `maps/` folder (next to this README). This is the only file you should
  need to edit when floors change — see
  [Editing the floor list](#editing-the-floor-list).
- `map.php` streams the right PDF for a floor. The floor plan is rendered
  client-side with pdf.js, so there's no server-side rasterisation step and
  no re-upload/pre-processing when a PDF changes — just replace the file.
- `pin_locations` is a small standalone table: `id, issue_id, floor, pin_x,
  pin_y`. `pin_x`/`pin_y` are fractions (0–1) of the plan's width/height, so
  they're resolution-independent. There's no foreign key to your issues
  table — this component only needs an integer issue id, it doesn't own it.
- There are exactly two integration points: **input side** (the form where
  someone reports an issue) and **reporting side** (the list/detail pages
  where someone looks up where the issue is). Both are one `require` plus
  one function call.

Everything below assumes:

```php
require '/var/www/playground/pin/lib.php';
```

at the top of your PHP file (adjust the path if you copy the folder
elsewhere — see [Notes](#notes--things-a-real-integration-should-decide)).

## 1. Input side — the "report an issue" form

This is the form where a reporter types a title/description and picks a
location. Drop the picker widget inside your existing `<form>`:

```php
<form method="post">
    <input type="text" name="title" required>
    <textarea name="description"></textarea>

    <?php pin_render_picker(['field_prefix' => 'location']); ?>

    <button type="submit">Submit issue</button>
</form>
```

`pin_render_picker()` draws the floor dropdown and the interactive map, and
adds three fields to your form's POST body (named after `field_prefix`,
default `location`):

| Field             | Meaning                                    |
|-------------------|---------------------------------------------|
| `location_floor`  | the selected floor's key from `config.php` |
| `location_x`      | pin X, 0–1 fraction of the plan width      |
| `location_y`      | pin Y, 0–1 fraction of the plan height     |

All three are empty strings if the reporter didn't pick a floor/pin —
treat the location as optional unless you want to make it required.

On submit, **after** you've inserted your own issue row and have its id:

```php
$issueId = /* … your own INSERT + lastInsertId() … */;

if ($_POST['location_floor'] !== '') {
    pin_save_location(
        $issueId,
        $_POST['location_floor'],
        (float)$_POST['location_x'],
        (float)$_POST['location_y']
    );
}
```

`pin_save_location()` inserts or updates — calling it again for the same
`$issueId` (e.g. the reporter edits the pin later) just moves the existing
pin rather than creating a duplicate.

If you redisplay the form after a validation error, pass the previous
selection back in so the reporter doesn't lose their pin:

```php
pin_render_picker([
    'field_prefix' => 'location',
    'floor'        => $_POST['location_floor'] ?? '',
    'x'            => $_POST['location_x'] ?? '',
    'y'            => $_POST['location_y'] ?? '',
]);
```

Full working version: `demo/issue_new.php`.

## 2. Reporting side — issue list & detail pages

This is for whoever looks at an issue afterwards to find and fix it.

**List page** — show which floor an issue is on:

```php
$loc   = pin_get_location($issue['id']);        // null if nothing was pinned
$floor = $loc ? pin_floor_info($loc['floor']) : null;

echo $floor ? htmlspecialchars($floor['label']) : '—';
```

Full working version: `demo/issue_list.php`.

**Detail page** — show the map with the pin on it:

```php
$loc = pin_get_location($issue['id']);

if ($loc) {
    pin_render_viewer([
        'floor' => $loc['floor'],
        'x'     => $loc['pin_x'],
        'y'     => $loc['pin_y'],
    ]);
} else {
    echo 'No location was pinned for this issue.';
}
```

`pin_render_viewer()` prints the floor's label and a read-only map. It
starts **zoomed in on the pin** so the location is unambiguous at a glance,
with +/−/reset zoom controls so whoever's fixing it can zoom back out for
full-floor context.

Full working version: `demo/issue_view.php`.

## Editing the floor list

Open `config.php`. Each entry is:

```php
'3' => ['label' => 'Verdieping 3', 'file' => '404_plan_software-3.pdf', 'enabled' => true],
```

- Add/rename/remove entries freely.
- To retire a floor without breaking issues that already reference it, set
  `'enabled' => false` instead of deleting the entry — it disappears from
  the input-side dropdown but the reporting-side viewer still renders it
  correctly for existing pins.
- The array key is what gets stored in `pin_locations.floor`. Don't reuse a
  retired key for a different floor.

## Notes / things a real integration should decide

- `PIN_WIDGET_BASE_URL` in `config.php` must match wherever this folder is
  served from (defaults to `/pin`, matching this repo's layout under the
  `playground.404.gent` document root). Both the input and reporting sides
  load `assets/pin-widget.js`/`.css` and hit `map.php` at that URL.
- `pin_render_picker()`/`pin_render_viewer()` each pull in the widget's CSS
  and pdf.js once per page automatically — safe to call both (or several
  pickers/viewers) on the same page.
- The demo's `pin_demo_issues` table (see `demo/schema_demo.sql`) is only
  there so the example pages have something to attach locations to —
  delete `demo/` once you've wired the two snippets above into your real
  issue tracker.
- DB connection lives in `config.php` (`PIN_DB_*` constants) / `db.php`,
  same MySQL instance as the rest of this playground.
