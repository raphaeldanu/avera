---
paths:
  - tests/Feature/**
---

# Feature

## Filament panel setup

Set the panel in `beforeEach` with `Filament::setCurrentPanel('admin')` before testing resource pages.

## Per-file test helpers

Declare shared helper functions at the top of the test file. Do not add them to `tests/Pest.php`.
