# Scrutineer — choix techniques actés

- **Date** — 2026-06-30
- **Statut** — Accepted (gel de conception)
- **Nature** — librairie autonome **bicéphale** (back PHP + front JS vanilla), pilotée par **contrat**.
- **En lien avec** — le [README](../README.md) (guide d'intégration).

> Ce document est la **mémoire de conception de la lib**, il voyage avec le package. Le « quoi / pourquoi » de la
> lib vit ici ; le « pourquoi tel hôte l'adopte » vit côté hôte, dans le repo du consommateur.

## 1. But

**Scrutineer** rend la **recette manuelle** pilotable **depuis l'application hôte elle-même** : une console
intégrée, consciente de l'**utilisateur connecté**, sert au recetteur les **scénarios jouables**, déclenche un
**environnement pré-seedé prêt à l'emploi**, capture le **résultat** et tient un **historique**. Esprit : les
**préconditions sont déjà réunies**, le recetteur **exécute** et **constate** — il ne monte plus l'état lui-même.

Cibles : **PHP = implémentation #1** (le back est ré-implémentable dans un autre langage, cf. §2), front
**vanilla** réutilisable tel quel, le tout **extractable** en package distribuable.

## 2. Principe directeur — ports & adaptateurs (hexagonal)

Le **contrat** est l'artefact central, **langage-neutre** — pas le code d'une implémentation. Il a trois faces :

1. **OpenAPI** — la surface HTTP (`catalog`, `reset`, `events`, `history`). Tout back conforme est interchangeable ;
   le front ne connaît **que** ça → il marche contre n'importe quelle implémentation conforme sans changer une ligne.
2. **JSON Schema** — le descripteur de **scénario** (id, label, rôle requis, cas, liaison au seed).
3. **Ports** — les interfaces que l'hôte câble (cf. §4).

Deux **coutures** à ports, même idée des deux côtés (*dépendre d'un contrat, jamais d'une implémentation*) :
**front ↔ back** = HTTP/OpenAPI ; **lib ↔ hôte (même process)** = interfaces du langage du back.

**Invariant d'extractabilité : zéro couplage au domaine de l'hôte dans la lib.** Tout ce qui est spécifique à
l'hôte vit **du côté hôte du port**.

## 3. Partage des responsabilités

| | **La lib (Scrutineer)** | **L'hôte (le consommateur)** |
|---|---|---|
| Back | routes, cycle de vie du reset, store d'historique par défaut, gating mode, **sert l'asset JS** | implémente les **ports** + déclare ses **scénarios** |
| Contrat | possède OpenAPI + JSON Schema + ports | s'y conforme |
| Front | web component vanilla `<scrutineer-console>`, servi par le back | `<script src=…>` + 1 appel d'init |
| Domaine | **n'en connaît rien** | ses entités et concepts métier (identité, rôles, données applicatives) |

## 4. Les ports (interfaces définies par la lib, implémentées par l'hôte)

```php
// « Fais exister / détruis les données du scénario X. » La lib ignore ce qu'elle recrée.
interface ScenarioSeeder {
    public function seed(string $scenarioId, ScrutineerContext $ctx): void;
    public function purge(string $scenarioId, ScrutineerContext $ctx): void;
}

// « Qui est connecté, quel rôle, quelle version, est-on en contexte de recette ? » Identité et périmètre = concepts hôte.
interface ScrutineerContextProvider {
    public function actorRef(): ?string;        // OPAQUE, fourni par l'hôte (ex. id utilisateur abstrait) — JAMAIS de PII
    public function role(): ?string;
    public function appVersion(): string;        // l'hôte (ex. tag de release courant)
    public function isScrutineerContext(): bool; // l'hôte marque le contexte de recette (un préfixe, un flag… à son choix)
    public function scopeKey(): ?string;        // clé opaque du périmètre hôte courant (workspace, espace… au choix de l'hôte)
}

// Journal d'historique — APPEND-ONLY (aucun update/delete). Impl SQL par défaut livrée par la lib (cf. §8).
interface HistoryStore {
    public function append(ScrutineerTestEvent $e): void;
    public function timeline(HistoryQuery $q): array;   // filtrable : scénario / version / acteur / périmètre
}

// Catalogue des scénarios — DÉCLARÉ par l'hôte (fichier, code ou table : son choix). PAS une table imposée.
interface CatalogProvider {
    public function scenarios(?string $role): array;    // descripteurs conformes au JSON Schema, filtrés rôle
}

// Optionnel — résolution lisible d'un actorRef pour le RENDU (id opaque → libellé). Résolution LIVE, côté hôte.
interface ActorResolver {
    public function resolve(array $actorRefs): array;   // [ref => libellé] ; ref inconnu → placeholder
}
```

La dépendance pointe **hôte → lib**, jamais l'inverse : la lib appelle ses **propres** interfaces, l'hôte vient
s'y brancher au démarrage.

## 5. Le seed — générique, contractuel, versionné par git

- **Inversion de contrôle** : au reset, la lib fait `purge(scenario, ctx)` puis `seed(scenario, ctx)` — sans jamais
  savoir ce qu'elle recrée. L'hôte génère ses seeds au regard de ce que la lib attend = ce port.
- **Un seul seed, versionné par git.** Le seed valable pour les tests à une version `x.y.z` = **le seed présent au
  tag `x.y.z`** (code hôte porté par le repo). Un checkout = un **snapshot cohérent** `{migrations + catalogue +
  scénarios + seed}`. Le reset **matérialise** le décor de ce snapshot. Aucun registre de seeds parallèle.
- **Propriété clé** : entre deux tags qui n'ont pas touché le seed, le décor est **byte-identique** → un re-test
  après un fix isole proprement la variable (seul le **code sous test** a changé). Un rollback vers un tag
  antérieur reconstruit le décor *tel qu'à ce tag* (drop + remigrate + reseed). *(Caveat hors lib : un éventuel
  schéma partagé entre versions suit la politique de migration de l'hôte — orthogonal au seed.)*

## 6. Isolation — **politique de l'hôte, pas de la lib**

- La lib reçoit `actorRef` à **chaque** opération et ne fait **aucune** hypothèse d'effet inter-acteurs. **Toute la
  mécanique de reset** (drop de schéma, espace par acteur, suppression ciblée…) vit **côté hôte**, dans l'adaptateur
  `ScenarioSeeder`.
- Exemple de politique hôte : un knob `ISOLATION = per-actor | shared`. Un hôte peut retenir `per-actor` (un
  **espace isolé** par `(scénario × actorRef)`), **matérialisé à la demande** au premier chargement (« reset pour A »
  *est déjà* « crée le décor de A si absent ») → pas de pré-seed combinatoire `N×M`.

## 7. Historique des résultats — event-sourcing (pas d'override)

- **Événements immuables, append-only.** On ne stocke **pas un statut courant** : une **suite de faits**, dont tout
  se dérive. *Statut = projection* : absence d'événement pour `(scénario, version)` = « pas encore testé » ;
  « OK en 0.2.0 / KO en 0.2.1 » tombe de la timeline.
- **Forme** : `{ id, occurredAt, appVersion, actorRef, scenarioId, outcome, comment, scopeKey }`.
- **Outcome = vocabulaire ouvert** déclaré au contrat : socle `conforme` / `non-conforme`, extensible (`bloqué`…).
  Le front rend les valeurs connues avec leurs couleurs et tolère l'inconnu.
- **Commentaire** : libre / optionnel partout.
- **Pas de PII** : `actorRef` **opaque** (cf. §4). Le libellé humain (« Prénom Nom ») est résolu **live** côté
  hôte via `ActorResolver` — *arbitrage assumé* : le **fait** est immuable, seul le **libellé** est résolu à la
  volée (acteur supprimé → placeholder).
- **Phrases = projections**, jamais stockées. La lib stocke des faits structurés ; la console / l'export *rendent*.

## 8. Propriété du schéma — schéma par défaut livré + port de surcharge (hybride)

- **La lib possède ET livre le schéma d'historique par défaut** (entité + store `scrutineer_test_event`).
  Install → ça marche. Le **port `HistoryStore` reste la trappe de surcharge**.
- Le schéma par défaut est **sans clé étrangère + identifiants opaques** *par conception* (host-agnostique) — d'où,
  **gratuitement** : « survit au reset » (clé de périmètre sans FK) **et** « sans PII ». C'est de la **donnée QA,
  pas un registre légal** : pas de chaîne d'intégrité cryptographique.
- **Les scénarios ne sont PAS une table imposée** : ils restent **déclarés par l'hôte** (`CatalogProvider`).
- **Nuance côté hôte** : la lib définit le **mapping** ; l'hôte l'applique **où** il veut. Le **nom de la table**
  et la **connexion** sont des clés de config (`table`, `connection`) → *la config suffit à installer le journal*.
  Pour matérialiser le schéma, la lib livre **`scrutineer:generate-migration`** : elle écrit une migration Doctrine
  **dans le pipeline de l'hôte** en s'appuyant sur sa propre config `doctrine_migrations` (chemins, namespace,
  organisation) — **aucun chemin en dur côté lib** ; le fichier est ensuite appliqué par la commande de migration
  habituelle (ex. au déploiement). Un hôte sans le bundle migrations reste libre d'appliquer
  `ScrutineerTestEventSchema::define()` à la main ou en auto-create. *La lib pilote le **quoi**, l'hôte le **où**.*

## 9. Configuration — `.env` comme base

Clés de la **lib** (bundle `scrutineer`, défauts raisonnables) : `enabled`, `asset_path`, `language`, **`table`**
(nom du journal, éventuellement schéma-qualifié) et **`connection`** (connexion du journal ; `null` = connexion par
défaut). Route montée sous un préfixe hôte. Clés **hôte** (indépendantes de la lib) : ex. une politique
d'isolation, une marque de contexte de recette. L'hôte configure — **et installe le schéma** (cf. §8) — **sans
toucher au code**.

## 10. Front — Web Component (indépendant de tout framework)

- **Web Component + Shadow DOM** : style **isolé** (aucun clash CSS avec l'hôte), s'embarque dans **n'importe quel**
  front — avec ou sans framework — via un simple `<script>` + init, **zéro** dépendance. **Servi par le back**
  (asset de la lib).
- **Panneau dockable** présent **sur les vrais écrans** : le test se joue sur la **vraie** UI de l'hôte, pas sur une
  page à part. *(Alternative écartée : page console autonome servie par le back.)*

## 11. Mode recette — **concern hôte**

La lib ne décide **ni** comment on entre dans le mode, **ni** comment on bascule de périmètre (auth/isolation =
hôte). L'hôte gère un **état de session « mode recette »**, **off par défaut**, **gardé par capacité** (ex. une
permission dédiée) :

- **off** → les **espaces de recette masqués** des sélecteurs normaux ; console **absente** ;
- **on** → console **montée**, contexte de recette actif, `isScrutineerContext()` vrai ;
- **entrer dans un scénario** = l'hôte orchestre `provision décor par acteur (lazy) + accorde le rôle requis (champ
  `role` du catalogue) + bascule la session + appelle seed(scenario, ctx)`.

## 12. Rapport — 3 tiers

| Tier | Possession | Statut |
|---|---|---|
| ① Export | **lib** : commande **`scrutineer:export`** → CSV (projection neutre, ouvrable dans un tableur) | fait |
| ② UI testeur | **lib, cœur** (timeline dans la console) | inclus |
| ③ UI d'administration / reporting | **pur hôte**, lit l'API d'historique de la lib | hors lib (concern hôte) |

## 13. Surface HTTP (contrat)

- `GET  …/catalog`  → scénarios + cas applicables à l'utilisateur / au périmètre courant (filtré rôle) ;
- `POST …/reset`    → (re)charge le décor pour l'acteur courant ;
- `GET  …/history`  → timeline (filtrable scénario / version / acteur / périmètre) ;
- `POST …/events`   → ajoute un événement de résultat.

Toutes **gated** : `SCRUTINEER_ENABLED` + `isScrutineerContext()`.

## 14. Non-buts

- Pas de **chaîne d'intégrité cryptographique** sur l'historique (donnée QA, pas registre légal) — réouvrable si un
  consommateur l'exige.
- Pas de **génération automatique des seeds** par la lib : c'est l'hôte (port).
- Un back **non-PHP** (Python ou autre) est **prévu par le contrat** mais **non réalisé** à ce jour — le front,
  conforme au seul contrat, le supporterait sans changement.
