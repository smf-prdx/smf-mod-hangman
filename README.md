# Hangman BBCode for SMF 2.1

Hangman BBCode adds a small ASCII-art hangman helper for Simple Machines Forum
topics. It is intended for forum games where players guess letters until they
solve the word or phrase.

## Usage

Render the default simple drawing with the current number of failed attempts:

```bbc
[hangman=3]
```

Override the maximum attempts or the drawing style in a specific post:

```bbc
[hangman=3 max_attempts=8]
[hangman=tries=2 style=detailed]
[hangman=fallos=4 max_intentos=8 estilo=elaborado]
```

The usual default is 6 failed attempts, which maps cleanly to head, body, arms,
and legs. Other values do not need to be multiples of 6; the drawing stage is
scaled proportionally. The tag clamps values outside the configured range.

## Administration

The module uses the shared SMF modification settings page:

```text
?action=admin;area=modsettings
```

It adds two settings under Miscellaneous: the default maximum attempts and the
default drawing style. Per-tag values override these defaults.

## Implementation Notes

The module is hook-only and does not patch SMF files. It registers
`integrate_bbc_codes`, `integrate_general_mod_settings`, and
`integrate_pre_css_output`.
