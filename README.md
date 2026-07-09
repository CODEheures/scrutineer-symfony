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
| **capture des mails de recette** (optionnel) → boîte in-console scopée par poste | activer + recopier 1 header sur le mail |

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

### 4. Configurer `security.yaml`

Vos routes de recette (§3) montées, autorisez-les. Celles **lues avant authentification** (le catalogue sur
`/login`, la boîte mail pendant un login 2FA) passent en `PUBLIC_ACCESS` — la barrière reste `SCRUTINEER_ENABLED`
(403 si off), pas la session ; les mutations exigent un user connecté. `access_control` est **first-match-wins**
(règles publiques **avant** le fourre-tout) :

```yaml
# config/packages/security.yaml
    access_control:
        - { path: ^/scrutineer/catalog$,      roles: PUBLIC_ACCESS }
        - { path: ^/scrutineer/availability$, roles: PUBLIC_ACCESS }
        - { path: ^/scrutineer/console,       roles: PUBLIC_ACCESS }
        - { path: ^/scrutineer/poste$,        roles: PUBLIC_ACCESS }   # capture mail (§7)
        - { path: ^/scrutineer/mails$,        roles: PUBLIC_ACCESS }   # capture mail (§7)
        - { path: ^/scrutineer/,              roles: IS_AUTHENTICATED_FULLY }
```

### 5. Implémenter les ports

3 requis (`ScenarioSeeder`, `ScrutineerContextProvider`, `CatalogProvider`) + 3 optionnels (`ActorResolver`,
`HistoryStore` — un défaut Doctrine existe —, `TicketPublisher`). Chaque port est une interface **documentée dans
[`src/Port/`](src/Port/)** (signatures + docblocks) ; liez chaque interface à votre implémentation avec l'attribut
**`#[AsAlias(<Port>::class)]`** posé sur la classe (ou, si vous préférez, un alias dans `config/services.yaml`).

👉 **Exemple complet et autonome** — un domaine **fictif** « Acme Books » (en mémoire, données factices), les **6
ports** câblés bout à bout + le wiring : **[`example/`](example/)**.

### 6. Appliquer le schéma du journal

Le **seul** objet de schéma que la lib ajoute (sans FK, sans PII). Générez la migration **dans votre propre
pipeline** (elle respecte votre config `doctrine_migrations`) :

```bash
php bin/console scrutineer:generate-migration
php bin/console doctrine:migrations:migrate
```

*(Capture des mails activée → la même commande émet aussi la table `scrutineer_captured_mail`.)*

### 7. (Optionnel) Capturer les mails d'un domaine

En recette prod-like (pas de serveur mail), les mails aux personas seedés (2FA, invitation, reset) n'ont pas de
boîte réelle → on les **capture** dans une boîte in-console (scopée par « poste ») au lieu de les envoyer. Nécessite
`symfony/mailer`.

**1. Activer** (`config/packages/scrutineer.yaml`, sous `scrutineer:`) :

```yaml
    mail_capture:
        enabled: true                    # off par défaut — la lib ne touche votre mailer que si activé
        domain: scrutineer.invalid       # destinataires @<domain> → capturés (seedez vos personas ainsi)
        table: scrutineer_captured_mail  # 2e (et seul autre) objet de schéma de la lib
        retention_days: 7                # purge auto au mint du jeton de poste (aucun cron)
```

**2. Poser le jeton de poste sur le mail sortant** — le **seul contrat hôte**. La lib lit puis strippe ce header au
transport ; à vous de l'y mettre (sync, Messenger, workers… votre ressort) :

```php
// $posteToken = cookie httpOnly `scrutineer_poste` (posé par POST <prefix>/poste), présent sur la requête
$email->getHeaders()->addTextHeader('X-Scrutineer-Poste', $posteToken);
```

La console gagne alors un onglet « Boîte mail » ; le tag `sha256(jeton)` isole les recetteurs en // sur la même
persona. Routes `/poste` + `/mails` à exposer en public (cf. §4).

## Le front (dépôt compagnon)

La console est un Web Component servi par ce back (`GET <prefix>/console.js`). Côté page, chargez le script et
posez l'élément — voir **[scrutineer-front](https://github.com/codeheures/scrutineer-front)** pour les attributs
(`base`, `lang`, `active`, `auth-token`) et les cas même-origine / cross-origine.

## Licence

[MIT](LICENSE).
