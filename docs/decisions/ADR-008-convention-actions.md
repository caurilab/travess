# ADR-008 — Convention : Actions pour les mutations, contrôleurs minces pour les lectures

## Contexte
Le domaine Identity (Lot 0) plaçait sa logique dans des contrôleurs (lectures et flux d'auth simples). Le Lot 1 introduit une logique de mutation plus riche (instanciation de workflow, clôture, réordonnancement) qui doit rester testable, transactionnelle et auditée.

## Décision
- **Une classe Action par cas d'usage de mutation**, `App\Domains\<Domaine>\Actions`, nommée verbe+objet (`CreerDossier`, `MettreAJourDossier`, `CloturerDossier`, `AssignerAgents`…). Méthode unique `executer(...)` recevant des données **déjà validées** (pas la `Request`), donc testable sans HTTP.
- L'Action est **le seul endroit** qui mute l'état + audite (ADR-007) + éventuellement met en queue, le tout dans `DB::transaction`.
- **Contrôleur mince** : `Gate::authorize` (Policy) → FormRequest → `Action::executer` → Resource.
- **Les lectures** (`index`/`show`, listes filtrables) **restent au contrôleur** (avec un query builder), sans Action — on n'escalade la structure que là où la complexité le justifie.

## Alternatives écartées
- **Tout dans les contrôleurs** : logique de mutation non réutilisable, difficilement testable hors HTTP, audit dispersé. Rejeté pour le métier riche.
- **Actions aussi pour les lectures** : cérémonie inutile pour du read simple. Rejeté.

## Conséquences
- Point de passage unique pour l'audit et les transactions.
- Convention à respecter dans tous les domaines métier à partir du Lot 1.

## Date
2026-09-10
