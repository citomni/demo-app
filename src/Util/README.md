# Util

A Util holds pure functions: the same arguments always give the same result. No App, no cfg, no IO, no clock, no logging, no caching, no SQL and no mutable state.

## Rules

- `class Name` in `src/Util/` with public static methods. Never instantiated: no constructor, no properties.
- Everything a function needs arrives as arguments. A function that has to read cfg, the clock, the filesystem or the environment belongs in Support.
- Class constants are fine for fixed rules such as patterns and limits.
- Invalid input throws `\InvalidArgumentException`, or yields a documented null or problem value when that is the contract.
- Document each function's contract precisely; Utils are reused widely, so edge cases belong in the PHPDoc.

## Example

[`Words.php`](Words.php): `count()` and `readingMinutes()`, used by `PublishingPolicy` and `ArticleController`. Invalid UTF-8 throws `\InvalidArgumentException` instead of counting as 0 words.

[`ContactMessage.php`](ContactMessage.php): `problems()` returns a reason code per refused field of the contact form. Its limits are class constants that match the columns of `demo_contact_messages`, so `SendContactMessage`, `ContactController` and the template read them from one place.

Tests: [`tests/words/run.php`](../../tests/words/run.php) and [`tests/contact/run.php`](../../tests/contact/run.php), isolated.
