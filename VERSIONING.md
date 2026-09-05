# Versioning

Two shapes, and which one a number has is what tells you where it came
from.

| Shape | Example | What it is |
|---|---|---|
| Three parts | `0.5.1` | A production release. Every client site gets it. |
| Four parts | `0.5.0.1` | A beta build. Only sites with beta updates enabled. |

## How a release cycle runs

`0.4.0` is live. Work starts on `beta`:

```
0.4.0.1   first beta build
0.4.0.2   next one
0.4.0.3   …
```

The three-part base stays at the **last released version** the whole
time; only the fourth part moves. When it is ready, `beta` merges to
`main` and is tagged — and the tag bumps the *patch*:

```
v0.4.1    production
```

Then beta work resumes on top of it: `0.4.1.1`, `0.4.1.2`, and so on.

A bigger change takes the minor instead — `v0.5.0` — and its betas would
have been `0.4.x.N` beforehand.

## Why it is this shape and not another

PHP's `version_compare` is what WordPress uses to decide whether an
update exists, and it orders these correctly with no special handling:

```
0.4.0.1-beta  >  0.4.0           a beta supersedes the release it sits on
0.4.0.2-beta  >  0.4.0.1-beta    betas increment
0.4.1         >  0.4.0.9-beta    the release supersedes its own betas
```

The `-beta` suffix is appended by the beta workflow; it is not written
into the plugin file.

## Where the number lives

- `g6-client-dashboard.php` — `G6_DASHBOARD_VERSION` and the `Version:`
  header. **This is what the beta workflow reads.** Both must agree.
- `plugin-manifest.json` — `version` is set by the *release* workflow
  from the tag, and by the beta workflow for the beta manifest. Do not
  edit it by hand.

## The mistake this is written down to prevent

Comparing a beta against the number already in the plugin file rather
than against what is INSTALLED. `0.4.0.1-beta` is not greater than
`0.4.0.1` — but nobody has `0.4.0.1` installed. They have `0.4.0`, the
released version, and `0.4.0.1-beta` is greater than that.

Reasoning from the wrong comparison once produced a `0.5.0` in the
plugin file, which is a production-shaped number shipped from `beta`.
Both workflows now refuse the wrong shape rather than building it.
