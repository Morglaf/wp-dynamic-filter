# Dynamic Filter

Plugin WordPress pour filtrer une **Query Loop** Gutenberg par taxonomie, en AJAX (sans rechargement de page), avec persistance d’URL (`history.pushState`).

Compatible avec les templates de boucle complexes (grille, bindings **Dynamic Metafield**, etc.) : le serveur renvoie le HTML déjà rendu du Query Loop.

## Prérequis

- WordPress **6.7+**
- PHP **8.1+**
- Node.js 18+ (uniquement pour compiler les assets éditeur)

Aucune dépendance jQuery / CSS externe.

## Installation

1. Copier ce dossier dans `wp-content/plugins/dynamic-filter`
2. Compiler les assets éditeur :

```bash
npm install
npm run build
```

3. Activer le plugin dans l’admin WordPress

En développement : `npm start` (watch).

Pour un zip prêt à installer : script local `build-zip.local.ps1` (ignoré par git).

## Utilisation

1. Dans l’éditeur, insérer le bloc **Dynamic Filter Query**  
   **ou** sélectionner une Query Loop existante → **Transformer** → **Dynamic Filter Query**
2. Placer / laisser une **Query Loop** à l’intérieur du conteneur
3. Dans le panneau latéral **Filtre** :
   - Taxonomie cible (`category`, `post_tag`, ou taxonomie publique custom)
   - Affichage : boutons ou menu déroulant
   - Bouton « tous » (affichage + libellé personnalisable)
   - Sélection multiple (optionnel, logique OU)
   - Compteurs par terme (optionnel)
4. Publier et tester sur le front

### Front

- Clic sur un terme → requête REST → remplacement du HTML de la grille
- Classe `.is-loading` pendant le chargement
- URL mise à jour : `?ddf_id=…&ddf_tax=…&ddf_term=slug` (plusieurs slugs séparés par des virgules en multi-sélection)
- Assets JS/CSS chargés uniquement si le bloc est présent sur la page

## Options du bloc

| Option | Description |
|--------|-------------|
| Taxonomie | Termes listés pour le filtre |
| Affichage | `boutons` ou `menu déroulant` |
| Sélection multiple | Cumuler plusieurs termes |
| Afficher le bouton « tous » | Remet le filtre à zéro |
| Libellé du bouton « tous » | Texte libre (défaut : Tous) |
| Compteur d’articles | Affiche le `count` natif du terme |
| Message si vide | Texte si aucun résultat |

## Structure

```
dynamic-filter.php
includes/          # PHP (plugin, assets, bloc, REST, rendu)
blocks/query/      # block.json
src/               # éditeur Gutenberg (source)
build/             # JS/CSS éditeur compilés
assets/front/      # JS/CSS front (vanilla)
```

## API REST

`POST /wp-json/dynamic-filter/v1/query`

Corps JSON typique :

```json
{
  "post_id": 123,
  "instance_id": "uuid-du-bloc",
  "taxonomy": "category",
  "terms": ["slug-a", "slug-b"],
  "paged": 1
}
```

- Nonce REST requis (`X-WP-Nonce` / `wp_rest`)
- Réponse : `{ "html": "…", "terms": ["…"] }`

## Licence

GPL-2.0-or-later

## Auteur

Morglaf
