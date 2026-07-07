# Exemple — implémenter les ports

Un exemple **complet et autonome** d'intégration de Scrutineer dans une application hôte
**fictive** : une petite librairie « Acme Books », entièrement **en mémoire** (aucune infra,
aucune base, aucune donnée réelle). Il montre les **6 ports** câblés de bout en bout.

> Ce code est **illustratif** : recopiez-le dans votre application sous **votre** namespace, en
> remplaçant le domaine `Acme\Bookshop` par le vôtre (base de données, services…). Rien ici n'est
> spécifique à un quelconque projet — que des données factices.

## Le domaine fictif

[`src/Bookshop.php`](src/Bookshop.php) — un magasin de livres en mémoire (livres + prêts) avec un
`reset()`. Il tient lieu de « votre domaine » ; **rien** dedans ne connaît Scrutineer.

## Les ports

| Port | Implémentation | Ce qu'il illustre |
|---|---|---|
| `CatalogProvider` *(requis)* | [`AcmeCatalogProvider`](src/AcmeCatalogProvider.php) | 4 scénarios déclarés en code, **filtrés par rôle** |
| `ScrutineerContextProvider` *(requis)* | [`AcmeContextProvider`](src/AcmeContextProvider.php) | qui agit / quel périmètre — `actorRef` **opaque, sans PII** |
| `ScenarioSeeder` *(requis)* | [`AcmeScenarioSeeder`](src/AcmeScenarioSeeder.php) | `purge()` puis `seed()` reconstruisent le décor par scénario (dont un **décor temporel** : prêt en retard) |
| `ActorResolver` *(optionnel)* | [`AcmeActorResolver`](src/AcmeActorResolver.php) | `actorRef` → libellé humain, résolu **au rendu** |
| `HistoryStore` *(optionnel)* | [`AcmeHistoryStore`](src/AcmeHistoryStore.php) | journal **append-only** en mémoire (surcharge le défaut Doctrine) |
| `TicketPublisher` *(optionnel)* | [`AcmeTicketPublisher`](src/AcmeTicketPublisher.php) | publication (idempotente) des non-conformités vers un tracker |

## Le câblage

Chaque implémentation déclare **elle-même** le port qu'elle fournit, via l'attribut
**`#[AsAlias(<Port>::class)]`** posé sur la classe — **aucun alias manuel**. Dans une vraie app,
l'autodiscovery `App\: resource` que tu as déjà (avec `autoconfigure: true`) suffit : tu écris
**zéro** câblage spécifique à Scrutineer.

- [`config/services.yaml`](config/services.yaml) — seulement l'autodiscovery (pour rendre l'exemple autonome).
- [`config/scrutineer.yaml`](config/scrutineer.yaml) — la config du bundle.

> **Cas particulier — surcharge d'un défaut.** `AcmeHistoryStore` **remplace** le store Doctrine
> livré par la lib ; `#[AsAlias(HistoryStore::class)]` surcharge alors l'alias par défaut du bundle
> (la config de l'app l'emporte sur celle du bundle). Les 5 autres ports sont simplement *fournis*
> par l'hôte, sans défaut concurrent. Si jamais un hôte rencontrait un souci d'ordre sur cette
> surcharge, un alias explicite dans `services.yaml` reste le repli fiable.

## Le parcours de bout en bout

1. Le front demande `GET /catalog` → `AcmeCatalogProvider::scenarios('member')` renvoie les
   scénarios jouables par un membre.
2. « Réinitialiser » un scénario → `POST /reset` → la lib appelle `AcmeScenarioSeeder::purge()`
   puis `seed()` → le `Bookshop` est reconstruit dans l'état attendu (décor prêt à faire feu).
3. Le recetteur joue le scénario sur la vraie UI, puis `POST /events` → `AcmeHistoryStore::append()`.
4. `GET /history` → `AcmeHistoryStore::timeline()`, les `actorRef` étant résolus en libellés via
   `AcmeActorResolver`.
