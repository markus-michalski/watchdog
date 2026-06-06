# Commit Message

```
chore(env): move DEFAULT_URI from compose to .env

Port corrected from 8086 to 8087 (stage port).
DEFAULT_URI now lives in .env with a local dev default and
is documented in .env.example with instructions to override
in .env.local for production — consistent with all other
deployment-specific env vars.

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
```
