---
name: scrutineer
description: >-
  Integrate or operate the Scrutineer in-app acceptance-testing console: a host-agnostic,
  contract-driven package with a PHP Symfony bundle plus a vanilla-JS
  <scrutineer-console> web component. Use for any task that touches this library — wiring its six
  ports and Symfony bundle, mounting the console and pointing it at the API (base attribute /
  CORS / CSP), authoring the scenario catalogue, or working with SCRUTINEER_ENABLED, the
  /availability pre-flight, the /publish ticketing endpoint, or the append-only test ledger.
---

# Using the Scrutineer package

Scrutineer adds an **in-app manual acceptance-testing console** to a host app: pre-seeded
preconditions, scenarios played on the host's **real screens** by the logged-in user, one-click
decor reset, and an **append-only history** of results. It is **host-agnostic** — the host
implements ports; nothing of the host's domain leaks into the library.

## Mental model (read this first)

- **Ports & adapters, contract-first.** The library owns the contract (OpenAPI + JSON Schema +
  PHP port interfaces) and calls its **own** interfaces; the host implements them. There is **no
  `use App\…` inside the library** — keep it that way.
- **Two seams.** front ↔ back = HTTP/OpenAPI (`scrutineer-front/contract/openapi.yaml`); the web component
  speaks **only** that, so any conforming back-end works (PHP today, Python tomorrow). lib ↔ host
  (same process) = PHP interfaces in `../src/Port/`.
- **One server gate.** The `SCRUTINEER_ENABLED` env-var. Off ⇒ every route **and** the JS asset
  return **403**, whatever a client's local flag says. A `localStorage` flag alone grants nothing.
- **Ledger.** `scrutineer_test_event` — append-only, **FK-free**, **PII-free** (opaque
  `actor_ref`). A scenario's status is a **projection** over events (no stored "current status");
  it survives a decor reset (the schema is dropped, the ledger is not).

## Integrate it — follow the recipe

The authoritative, step-by-step guide is the **[README](../README.md)**.
In short (back, 5 steps):

1. **Install** — `composer require codeheures-fr/scrutineer`, or (until published) copy the repo into
   the host and PSR-4-autoload `CODEHeures\Scrutineer\` → `src/`.
2. **Register + configure the bundle** — `config/packages/scrutineer.yaml`: `enabled`
   (`%env(bool:SCRUTINEER_ENABLED)%`, the sole 403 gate), `asset_path` (disk path to the served
   `console.js`), `language` (the CLI/CSV back strings — the **front** follows its `lang` attribute).
3. **Mount the routes** under a prefix (`config/routes.yaml`). **This prefix MUST equal the
   front's `base` attribute.**
4. **Implement the ports** (below) and bind each with `#[AsAlias(<Port>::class)]` on your impl
   (or, if you prefer, a `config/services.yaml` alias).
5. **Apply the ledger schema** — a migration (or auto-create) for `scrutineer_test_event`.

**Front.** Load `console.js` from the API, then drop `<scrutineer-console base="…" active>`.
`base` is the **only** place the API address is declared (default `/scrutineer`, same origin).
For a **cross-origin** API pass an absolute URL and wire: CORS **allow-credentials** (the
component fetches with `credentials:"include"` so the auth cookie flows), and the front CSP
`script-src` + `connect-src` for the API origin. `active` present ⇒ the panel shows (the host
reflects a per-browser flag onto it); the catalogue is public, so it works logged-out on `/login`.

## The ports (implemented by the host)

Full signatures are the interfaces in `src/Port/*.php` (docblocks included). **3 required** —
`ScenarioSeeder` (seed/purge a scenario's decor; **the host decides the reset scope**),
`ScrutineerContextProvider` (`actorRef` **OPAQUE, no PII**, plus `role`, `appVersion`,
`isScrutineerContext`, `scopeKey`), `CatalogProvider` (`scenarios(?string $role): list<Scenario>`).
**3 optional** — `ActorResolver` (ref → human label, resolved at render), `HistoryStore` (a
Doctrine default ships; implement only for another back-end), `TicketPublisher` (unbound ⇒
`POST /publish` answers `{status:"unsupported"}`).

## Author scenarios

Scenarios are declared **in code** by the host's `CatalogProvider`, conforming to
`scrutineer-front/contract/scenario.schema.json`. A `Scenario` carries `id`, `label`, `steps`,
`expectedResult`, `introducedIn` (release — groups scenarios), `status` (`active` | `obsolete`),
and `lot` (the isolated test scope it maps to for a reset).

**Convention:** a real scenario's **first step is "Log in as `<email>`"** — the persona/role to
play it as (acceptance = "test *as* user X"). Passwords come from a **vault**, never the scenario.

## Guardrails

- Never couple the library to a host (`use App\…`, a host class name, a host env-var name). New
  host-specific behaviour goes in an **adapter**, behind a port.
- Touch the contract (`scrutineer-front/contract/*`) ⇒ regenerate whatever depends on it before shipping.
- The ledger is **append-only** — never update/delete an event.
- Destructive ops (`reset`, staging masking) stay gated by `SCRUTINEER_ENABLED`.

## Reference map (paths relative to this skill)

- `../README.md` — integration guide (install → bundle → routes → ports → schema) + front pointer.
- `../src/Port/*.php` — the port interfaces the host implements (signatures + docblocks).
- `../docs/technical-decisions.md` — design memory (why ports, event-sourcing, schema ownership, isolation = host policy).
- `scrutineer-front/contract/openapi.yaml` — HTTP surface (catalog · availability · reset · events · history · publish).
- `scrutineer-front/contract/scenario.schema.json` — the scenario descriptor.
- `scrutineer-front/src/scrutineer-console.js` — the web component (+ `scrutineer-console.<lang>.js` locale add-ons).
