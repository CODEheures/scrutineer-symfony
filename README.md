# Scrutineer — back Symfony

**Scrutineer** est une librairie **bicéphale** (back + front, pilotée par **contrat**) qui ajoute à *votre*
application une **console de recette manuelle** : préconditions pré-seedées, scénarios joués depuis vos vrais
écrans, résultats horodatés et historisés — **sans coupler la lib à votre domaine**.

Ce dépôt est le **back** — **une** implémentation de référence (bundle Symfony, PHP). Le front vit dans un dépôt à
part : 👉 **[scrutineer-front](https://github.com/CODEheures/scrutineer-front)** (Web Component, sans build). Les
deux se parlent **uniquement** via le **contrat** (OpenAPI + JSON Schema), qui vit **côté front**
([scrutineer-front/contract](https://github.com/CODEheures/scrutineer-front/tree/main/contract)) : le front est le
consommateur invariant, ce back n'est qu'un back **conforme au contrat** (PHP aujourd'hui, un autre langage demain).

> Conception & décisions techniques : [`docs/technical-decisions.md`](docs/technical-decisions.md).

## Ce que fournit la lib / ce que vous câblez

| Fournit (la lib) | Vous câblez (l'hôte) |
|---|---|
| surface HTTP (`catalog` / `availability` / `reset` / `events` / `history` / `publish`) | les **adaptateurs** des ports |
| store d'historique par défaut (Doctrine, surchargeable) | où il vit (`.env` / config) |
| console front `<scrutineer-console>` servie par le back | un `<script>` + 1 appel d'init |
| orchestration reset + gating mode | vos **scénarios** + vos **seeds** |

**La lib ne connaît pas votre domaine.** Tout le métier vit derrière les ports.

## Installation (back)

### 1. Installer le package

```bash
composer require codeheures-fr/scrutineer
```

### 2. Enregistrer et configurer le bundle

```php
// config/bundles.php
return [
    // …
    CODEHeures\Scrutineer\Bridge\Symfony\ScrutineerBundle::class => ['all' => true],
];
```

```yaml
# config/packages/scrutineer.yaml
scrutineer:
    enabled: '%env(bool:SCRUTINEER_ENABLED)%'   # la SEULE barrière serveur (403 si false)
    asset_path: '%kernel.project_dir%/vendor/… /scrutineer-console.js'  # ou le front vendoré
    language: fr
    table: scrutineer_test_event                # nom du journal (schéma-qualifiable)
    # connection: shared                        # connexion DBAL du journal (défaut = celle par défaut)
```

### 3. Monter les routes sous un préfixe

```yaml
# config/routes.yaml
scrutineer:
    resource: '@ScrutineerBundle/Resources/config/routes.php'
    prefix: /scrutineer
```

### 4. Implémenter les ports

3 requis (`ScenarioSeeder`, `ScrutineerContextProvider`, `CatalogProvider`) + 3 optionnels (`ActorResolver`,
`HistoryStore` — un défaut Doctrine existe —, `TicketPublisher`). Chaque port est une interface **documentée dans
[`src/Port/`](src/Port/)** (signatures + docblocks) ; liez chaque interface à votre implémentation avec l'attribut
**`#[AsAlias(<Port>::class)]`** posé sur la classe (ou, si vous préférez, un alias dans `config/services.yaml`).

👉 **Exemple complet et autonome** — un domaine **fictif** « Acme Books » (en mémoire, données factices), les **6
ports** câblés bout à bout + le wiring : **[`example/`](example/)**.

### 5. Appliquer le schéma du journal

Le **seul** objet de schéma que la lib ajoute (sans FK, sans PII). Générez la migration **dans votre propre
pipeline** (elle respecte votre config `doctrine_migrations`) :

```bash
php bin/console scrutineer:generate-migration
php bin/console doctrine:migrations:migrate
```

## Le front (dépôt compagnon)

La console est un Web Component servi par ce back (`GET <prefix>/console.js`). Côté page, chargez le script et
posez l'élément — voir **[scrutineer-front](https://github.com/codeheures/scrutineer-front)** pour les attributs
(`base`, `lang`, `active`, `auth-token`) et les cas même-origine / cross-origine.

## Licence

[MIT](LICENSE).
