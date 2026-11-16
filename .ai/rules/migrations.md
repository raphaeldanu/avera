---
paths:
  - database/migrations/**
---

# Migrations

## Money columns

Store money as `decimal(..., 20, 2)` columns and cast them with `'decimal:2'` on the model. Never use floats or integer cents.
