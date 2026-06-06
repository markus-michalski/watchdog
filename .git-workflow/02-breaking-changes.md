# Breaking Change Analysis

## Breaking Changes
None.

## Analysis
- No API changes
- No schema changes
- No dependency changes
- DEFAULT_URI value changes from 8086 → 8087: only affects existing .env files on running instances
  - Anyone with an already-deployed instance who had DEFAULT_URI in their .env.local will not be affected
  - Fresh installs get the correct 8087 port from the start

## Migration Requirements
None required. Any existing .env.local on a server must have DEFAULT_URI set to the actual domain — the .env default only matters for local dev.
