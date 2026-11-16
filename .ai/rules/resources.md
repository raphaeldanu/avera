---
paths:
  - app/Filament/Resources/**
---

# Resources

## Form and table configuration

Keep resource forms and tables in `Schemas/*Form` and `Tables/*Table` classes exposing a static `configure()` method. Resource classes only delegate to them.

## Save-time logic

Put reusable save-time normalization as a public static method on the form class. Do not introduce Form Requests, Actions, Services, or repositories for it.
