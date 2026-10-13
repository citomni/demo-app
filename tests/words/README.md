# Words suite

Isolated checks for `\App\Util\Words`. Words is a pure Util, so the checks call it directly: no app double, no database, no Composer autoloader.

## Run

```
php tests/words/run.php
```

Expected result:

```
8 passed, 0 failed
```

`PASS` lines go to stdout, `FAIL` lines to stderr. The last line holds the totals, and the exit code is 1 when a check fails.

## Checks

| Check | Covers |
|---|---|
| count() is 0 for an empty or whitespace-only text | Empty input |
| count() splits on spaces, tabs and line breaks, also when they repeat | ASCII whitespace |
| count() keeps punctuation and non-ASCII letters inside their word | What a word is |
| count() splits on Unicode spaces such as the no-break space | The `u` modifier |
| count() rejects invalid UTF-8 instead of counting 0 words | `\InvalidArgumentException` instead of a silent 0 |
| readingMinutes() rounds up to whole minutes | Rounding at 1, 200 and 201 words |
| readingMinutes() is 0 for a text without words | Empty input |
| readingMinutes() rejects a reading speed below 1 | `\InvalidArgumentException` |

`php tests/run.php` runs every suite in the app.
