---
paths:
  - app/Models/**
---

# Models

## Fillable and hidden attributes

Declare mass-assignment and visibility with the `#[Fillable([...])]` and `#[Hidden([...])]` PHP attributes on the model class. Do not use `$fillable` or `$hidden` properties.

## Money columns

Cast money attributes with `'decimal:2'` and keep padded values as strings. Never use floats or integer cents for stored amounts.
