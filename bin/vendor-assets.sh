#!/usr/bin/env bash

# Les bibliothèques tierces que UiBundle sert lui-même (Leaflet, vanilla-cookieconsent, canvas-confetti) : elles ne passent par aucun gestionnaire de paquets, donc rien ne dit tout seul qu'une version est sortie. Ce script le demande, et rapatrie celle qu'on lui nomme.
# Il n'a pas sa place dans un commit : il sort sur le réseau. Le bon moment est celui où les dépendances sont déjà montées (ComposerUpdate.sh). La cohérence entre le manifeste et les fichiers livrés, elle, est vérifiée hors-ligne par VendorAssetsTest, donc à chaque commit.
#
#   bin/vendor-assets.sh              → liste les versions livrées et celles publiées
#   bin/vendor-assets.sh leaflet 1.9.5 → rapatrie cette version et met le manifeste à jour

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
MANIFEST="$ROOT/UiBundle/config/vendor-assets.json"

if [ ! -f "$MANIFEST" ]; then
    echo "Manifeste introuvable : $MANIFEST" >&2
    exit 1
fi

# La dernière version publiée sur npm, sans dépendre de npm lui-même : le registre répond en JSON à une simple requête
published() {
    curl -sS -f "https://registry.npmjs.org/$1/latest" | php -r 'echo json_decode(stream_get_contents(STDIN), true)["version"] ?? "?";'
}

check() {
    php -r '
        $manifest = json_decode(file_get_contents($argv[1]), true);
        foreach ($manifest as $library) {
            echo $library["name"], " ", $library["version"], " ", $library["scope"], "\n";
        }
    ' "$MANIFEST" | while read -r name version scope; do
        latest="$(published "$name" || echo '?')"

        [ "$scope" = "tests" ] && suffix=' (fixture de test)' || suffix=''

        if [ "$latest" = "$version" ]; then
            printf '  %-24s %-10s à jour%s\n' "$name" "$version" "$suffix"
        else
            printf '  %-24s %-10s → %s%s\n' "$name" "$version" "$latest" "$suffix"
        fi
    done
}

update() {
    local name="$1" version="$2"

    # Les chemins et les urls sortent du manifeste, jamais d'une liste tenue ici : c'est le manifeste qui fait foi (voir VendorAssetsTest)
    php -r '
        $manifest = json_decode(file_get_contents($argv[1]), true);
        foreach ($manifest as $library) {
            if ($library["name"] !== $argv[2]) {
                continue;
            }
            foreach ($library["files"] as $path => $url) {
                echo $path, " ", str_replace("%version%", $argv[3], $url), "\n";
            }
            exit(0);
        }
        fwrite(STDERR, sprintf("Aucune bibliothèque nommée \"%s\" dans le manifeste.\n", $argv[2]));
        exit(1);
    ' "$MANIFEST" "$name" "$version" | while read -r path url; do
        echo "→ $path"
        # Le dossier peut ne pas exister encore : une feuille vendorisée amène ses propres images dans un sous-dossier que curl ne crée pas
        mkdir -p "$(dirname "$ROOT/UiBundle/$path")"
        curl -sS -f -o "$ROOT/UiBundle/$path" "$url"
    done

    # Le numéro n'est bougé qu'une fois les fichiers effectivement rapatriés : l'inverse laisserait le manifeste annoncer une version que le bundle ne sert pas, ce que le test refuse
    # L'empreinte est recalculée dans la foulée, pour les bibliothèques dont l'amont n'imprime aucune version fiable (voir VendorAssetsTest) : la laisser derrière rendrait la suite rouge sans que rien ne sache la réparer
    php -r '
        $manifest = json_decode(file_get_contents($argv[1]), true);
        foreach ($manifest as &$library) {
            if ($library["name"] !== $argv[2]) {
                continue;
            }
            $library["version"] = $argv[3];
            if (null === $library["marker"]) {
                $library["sha256"] = hash_file("sha256", dirname($argv[1], 3) . "/UiBundle/" . array_key_first($library["files"]));
            }
        }
        file_put_contents($argv[1], json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
    ' "$MANIFEST" "$name" "$version"

    echo
    echo "✔ $name en $version. Relancer la suite : le test refuse un fichier qui ne porte pas la version annoncée."
}

if [ $# -eq 0 ]; then
    echo "Bibliothèques servies par UiBundle :"
    check
    exit 0
fi

if [ $# -ne 2 ]; then
    echo "Usage : bin/vendor-assets.sh [<nom> <version>]" >&2
    exit 1
fi

update "$1" "$2"
