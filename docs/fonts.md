# Fonts

Modern Job Board uses a single primary UI typeface across the marketing theme, frontend plugin chrome, and WordPress admin screens.

## Primary typeface

**DM Sans**

| Surface | CSS token / stack |
|--------|-------------------|
| Theme | `--font-sans: 'DM Sans', sans-serif` |
| Theme (serif alias) | `--font-serif: var(--font-sans)` (same as sans) |
| Plugin frontend | `--mjb-font: var(--font-sans, 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif)` |
| Plugin WP admin | `--mjb-font-sans: 'DM Sans', sans-serif` |

### Loaded variants (Google Fonts)

- Family: **DM Sans**
- Optical size (`opsz`) axis enabled
- Weights: **400**, **500**, **600**, **700**
- Italic: **400**

Example enqueue (plugin frontend / theme):

```text
https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap
```

Admin CSS also imports the same family via `@import` in `assets/css/mjb-admin.css`.

## Secondary / monospace

Used only for code-like UI (docs, tooling snippets), **not** for body copy or dashboard chrome:

```text
ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace
```

Some legacy rules use a bare `monospace` stack.

## Practical summary

| Role | Font |
|------|------|
| Brand / UI / dashboards / admin | **DM Sans** |
| Code / technical snippets | System **monospace** stacks |
| Fallbacks | System UI sans (`-apple-system`, `BlinkMacSystemFont`, `Segoe UI`, `sans-serif`) |

There is no separate display or serif brand face: headings and body share **DM Sans**, differentiated by weight and size only.
