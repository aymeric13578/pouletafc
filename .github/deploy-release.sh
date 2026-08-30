#!/usr/bin/env bash
#
# Mise en production sur le serveur, exécutée à distance par ssh.
#
# Ce script vivait dans le fichier du workflow, sous une action tierce. Chaque
# action doit d'abord être téléchargée depuis codeload.github.com avant que
# quoi que ce soit ne démarre, et ce point d'entrée répond régulièrement 429 ou
# 503 : quatre déploiements d'affilée se sont arrêtés à « Prepare all required
# actions », sans qu'aucune étape ne tourne. En appelant ssh directement, il n'y
# a plus rien à télécharger — et le script gagne au passage un fichier à lui,
# relisible et vérifiable par un analyseur shell.
#
# Les variables DEPLOY_PATH, BACKUP_PATH, WEBROOT_PATH et ARCHIVE_NAME sont
# transmises par l'appelant.

set -euo pipefail
ARCHIVE="$HOME/${ARCHIVE_NAME}"

# Dossier d'extraction à part, jamais servi.
#
# L'ancienne méthode extrayait directement dans $DEPLOY_PATH : le webroot PHP
# lisait donc un dossier à moitié écrit pendant toute la durée de l'extraction
# (plusieurs minutes sur ce disque mutualisé), ce qui produisait des 500 sur
# tout le site — sans lien avec la charge de visiteurs, uniquement le fait
# d'ouvrir un fichier qui n'existait pas encore. La bascule vers $DEPLOY_PATH
# ne se fait plus qu'une fois l'extraction terminée ET vérifiée (voir plus
# bas), par un simple mv : quasi instantané, sur le même volume.
#
# Nettoyé en tout début de script : un run précédent interrompu en pleine
# extraction (timeout, connexion coupée) y laisserait un dossier à moitié
# écrit, qu'il ne faut jamais confondre avec une extraction en cours.
STAGING_PATH="${DEPLOY_PATH}_nouvelle"
rm -rf "$STAGING_PATH"

# Jalon horodaté avant chaque étape. Sans ça, quand le déploiement dépasse
# le timeout on ne sait pas laquelle a traîné : la sortie s'arrête net sur
# la dernière ligne affichée, qui ne dit rien de l'étape en cours.
DEPLOY_T0=$(date +%s)
step() {
  echo "[+$(( $(date +%s) - DEPLOY_T0 ))s] === $1"
}

# 0 bis. Filet de sécurité sur les images.
#
#        Les photos des fiches produits ont déjà été perdues une fois, et
#        les renvoyer coûte des heures. Aucune opération de ce script ne
#        doit plus pouvoir y toucher — mais un raisonnement ne vaut pas une
#        garantie, et c'est ce que pose ce bloc.
#
#        L'instantané est fait de liens durs : il ne recopie aucun octet et
#        n'occupe presque rien, tout en survivant à la suppression des
#        originaux. Effacer un fichier ne libère la place que lorsque le
#        dernier lien disparaît.
UPLOAD_DIR="$WEBROOT_PATH/upload"
INSTANTANES="$HOME/uploads_instantanes"
INSTANTANE="$INSTANTANES/upload_$(date +%Y%m%d%H%M%S)"
UPLOADS_AVANT=0

if [ -d "$UPLOAD_DIR" ]; then
  UPLOADS_AVANT=$( (find "$UPLOAD_DIR" -type f 2>/dev/null || true) | wc -l )
  mkdir -p "$INSTANTANES"
  cp -al "$UPLOAD_DIR" "$INSTANTANE" 2>/dev/null || cp -a "$UPLOAD_DIR" "$INSTANTANE"
  echo "Images : $UPLOADS_AVANT fichiers, instantané dans $INSTANTANE"

  # On garde les trois derniers : au-delà, ce sont des liens vers des
  # fichiers depuis longtemps remplacés.
  # « || true » indispensable : au premier passage aucun instantané
  # n'existe, ls sort en erreur, et pipefail ferait échouer tout le
  # déploiement pour un simple ménage.
  ls -1dt "$INSTANTANES"/upload_* 2>/dev/null | tail -n +4 | xargs -r rm -rf || true
fi

# 0. Sauvegarde défensive et explicite du .env serveur AVANT toute opération
#    destructive (le mv/rm ci-dessous). Ces copies vivent dans un dossier dédié
#    qui n'est JAMAIS purgé par le déploiement : le .env de production reste donc
#    récupérable même si le backup principal ($BACKUP_PATH) est écrasé ou si un
#    déploiement échoue en cours de route.
ENV_BACKUP_DIR="$HOME/pouletafc_env_backups"
if [ -f "$DEPLOY_PATH/.env" ]; then
  mkdir -p "$ENV_BACKUP_DIR"
  cp -p "$DEPLOY_PATH/.env" "$ENV_BACKUP_DIR/.env.$(date +%Y%m%d%H%M%S)"
  cp -p "$DEPLOY_PATH/.env" "$ENV_BACKUP_DIR/.env.latest"
  # Ne conserve que les 10 sauvegardes horodatées les plus récentes.
  ls -1t "$ENV_BACKUP_DIR"/.env.20* 2>/dev/null | tail -n +11 | xargs -r rm -f
  echo ".env serveur sauvegardé dans $ENV_BACKUP_DIR (+ .env.latest)"
else
  echo "Aucun .env courant à sauvegarder (premier déploiement ?)"
fi

# 0 bis. Contrôle de l'espace disque AVANT toute opération destructive.
#
#        Un déploiement a déjà échoué faute de place : l'extraction s'est
#        arrêtée en chemin, laissant une release sans "artisan", donc un
#        site en erreur 500. Le mal était fait puisque l'étape 1 avait déjà
#        écrasé la sauvegarde précédente. On refuse maintenant de démarrer
#        plutôt que de détruire le filet de sécurité.
#
#        Facteur 3 : l'archive reste sur le disque pendant l'extraction, son
#        contenu décompressé pèse environ 2,5 fois son poids, et la copie
#        vers le webroot demande un peu de marge. Volontairement calé au
#        plus juste : un seuil trop large bloquerait des déploiements
#        parfaitement viables, ce qui serait pire que le mal.
#
#        + taille de la release courante : avec l'extraction à part (voir
#        STAGING_PATH plus haut), l'ancienne release ($DEPLOY_PATH) reste
#        intacte pendant toute l'extraction de la nouvelle, au lieu d'être
#        basculée en sauvegarde avant — donc les deux coexistent un instant,
#        ce que l'ancienne méthode ne demandait pas.
step "0 bis - controle de l'espace disque"
ESPACE_DISPO=$(df -Pk "$HOME" | awk 'NR==2 {print $4}')
TAILLE_ARCHIVE=$(du -k "$ARCHIVE" | awk '{print $1}')
TAILLE_RELEASE_ACTUELLE=0
if [ -d "$DEPLOY_PATH" ]; then
  TAILLE_RELEASE_ACTUELLE=$(du -sk "$DEPLOY_PATH" | awk '{print $1}')
fi
REQUIS=$(( TAILLE_ARCHIVE * 3 + TAILLE_RELEASE_ACTUELLE ))

echo "Disponible : $(( ESPACE_DISPO / 1024 )) Mo | requis : $(( REQUIS / 1024 )) Mo"

if [ "$ESPACE_DISPO" -lt "$REQUIS" ]; then
  echo "ERREUR: espace disque insuffisant, déploiement annulé." >&2
  echo "Aucune modification n'a été faite, le site reste en ligne." >&2
  df -h "$HOME" >&2
  echo "Pistes : rm -rf ~/pouletafc_casse ~/release-*.tar.gz" >&2
  exit 1
fi

step "2/3 - extraction dans un dossier a part (site inchange pendant ce temps)"
# 2. Crée le dossier d'extraction à part (jamais $DEPLOY_PATH directement — voir
#    STAGING_PATH en tête de script).
mkdir -p "$STAGING_PATH"

# 3. Extrait la nouvelle release dans ce dossier à part. « ionice »/« nice »
#    abaissent la priorité disque/CPU de cette étape : elle reste la plus
#    lourde en écriture du déploiement, et le site continue de servir
#    l'ancienne release pendant qu'elle tourne — mais un compte mutualisé
#    partage aussi le disque avec d'autres clients, autant y peser le moins
#    possible. « ionice -c3 » (best-effort au repos) et « nice -n19 »
#    (priorité CPU minimale) sont sans effet notable sur la durée totale ici,
#    l'extraction étant de toute façon dominée par l'attente disque.
if command -v ionice >/dev/null 2>&1; then
  ionice -c3 nice -n19 tar -xzf "$ARCHIVE" -C "$STAGING_PATH"
else
  nice -n19 tar -xzf "$ARCHIVE" -C "$STAGING_PATH"
fi
rm -f "$ARCHIVE"

# 3 ter. Vérifie que la release extraite est complète avant d'y toucher.
#
#        Une extraction tronquée passait jusqu'ici inaperçue : le script
#        continuait, écrasait le webroot, et n'échouait qu'à l'étape 9 sur
#        « Could not open input file: artisan » — trop tard, le site était
#        déjà cassé et la sauvegarde consommée. Ici, rien n'a encore été
#        touché à $DEPLOY_PATH ni $BACKUP_PATH : une extraction ratée se
#        contente d'être effacée, le site en ligne n'a jamais vu la
#        différence.
if [ ! -f "$STAGING_PATH/artisan" ] || [ ! -d "$STAGING_PATH/public/build" ]; then
  echo "ERREUR: release incomplète après extraction." >&2
  ls -la "$STAGING_PATH" | head -20 >&2
  df -h "$HOME" >&2
  echo "Le site en ligne n'a pas été touché : aucune restauration nécessaire." >&2
  rm -rf "$STAGING_PATH"
  exit 1
fi

step "1 - bascule atomique (sauvegarde de l'ancienne release, activation de la nouvelle)"
# 1. Bascule : l'ancienne release ($DEPLOY_PATH) devient la sauvegarde, et la
#    nouvelle (déjà extraite et vérifiée dans $STAGING_PATH) prend sa place.
#    Deux « mv » sur le même volume : quasi instantanés, contrairement à
#    l'extraction qui les précède — c'est là, et seulement là, que le site
#    voit changer sa release, sans la fenêtre de plusieurs minutes qu'ouvrait
#    l'extraction directe dans $DEPLOY_PATH.
if [ -d "$DEPLOY_PATH" ]; then
  echo "Sauvegarde de la release existante vers $BACKUP_PATH"
  rm -rf "$BACKUP_PATH"
  mv "$DEPLOY_PATH" "$BACKUP_PATH"
else
  echo "Aucune release existante, pas de sauvegarde nécessaire"
fi
mv "$STAGING_PATH" "$DEPLOY_PATH"

# 3 bis. Publie les assets buildés vers le webroot IMMÉDIATEMENT, sans --delete.
#
#        L'étape 1 a déjà basculé la release : le PHP servi lit donc le nouveau
#        manifest et réclame les nouveaux fichiers hashés. Or le webroot n'était
#        synchronisé qu'à l'étape 6, après plusieurs minutes de rsync sur ~5500
#        fichiers. Pendant tout cet intervalle, chaque page renvoyait du HTML
#        pointant vers des CSS et JS absents : site sans aucun style, ou en 500.
#
#        En copiant "build" tout de suite et sans --delete, les anciens et les
#        nouveaux fichiers hashés coexistent : les pages déjà ouvertes continuent
#        de fonctionner, les nouvelles trouvent leurs assets. L'étape 6 fera le
#        ménage des fichiers devenus inutiles.
if [ -d "$DEPLOY_PATH/public/build" ]; then
  mkdir -p "$WEBROOT_PATH/build"
  rsync -a "$DEPLOY_PATH/public/build/" "$WEBROOT_PATH/build/"
  step "3 bis - assets publies (fenetre sans style refermee)"
fi

# 3 quater. Rétablit tout de suite le lien vers le dossier des images.
#
#           L'archive n'embarque pas "public/upload" : après extraction, le
#           dossier n'existe pas dans la release. Le lien n'était rétabli qu'à
#           l'étape 7 bis, soit après le rsync de ~5500 fichiers vers le webroot
#           — plusieurs minutes pendant lesquelles le site est déjà en ligne
#           sans que PHP ne retrouve la moindre photo.
#
#           Les images restaient visibles pour un visiteur, le serveur web les
#           servant directement depuis le webroot. Mais tout ce qui passe par PHP
#           échouait en silence : constaté sur un aperçu de partage fabriqué dans
#           cet intervalle, qui portait le logo au lieu du plat. Un envoi d'image
#           tombé là aurait fini ailleurs que dans le dossier servi.
#
#           L'étape 7 bis conserve les droits et réaffirme le lien : elle reste
#           utile, mais n'est plus le premier moment où il existe.
mkdir -p "$WEBROOT_PATH/upload"
rm -rf "$DEPLOY_PATH/public/upload"
ln -sfn "$WEBROOT_PATH/upload" "$DEPLOY_PATH/public/upload"
step "3 quater - lien des images retabli"

# 4. Restaure le .env (jamais écrasé/regénéré par le pipeline). Ordre de priorité :
#    a) le backup de la release précédente, b) la sauvegarde dédiée .env.latest,
#    sinon on échoue proprement plutôt que de démarrer sans configuration.
if [ -f "$BACKUP_PATH/.env" ]; then
  cp -p "$BACKUP_PATH/.env" "$DEPLOY_PATH/.env"
  echo ".env restauré depuis $BACKUP_PATH"
elif [ -f "$ENV_BACKUP_DIR/.env.latest" ]; then
  cp -p "$ENV_BACKUP_DIR/.env.latest" "$DEPLOY_PATH/.env"
  echo ".env restauré depuis $ENV_BACKUP_DIR/.env.latest"
elif [ ! -f "$DEPLOY_PATH/.env" ]; then
  echo "ERREUR: aucun fichier .env trouvé (ni sauvegarde, ni existant)." >&2
  echo "Configure-le manuellement sur le serveur avant de redéployer." >&2
  exit 1
fi

# 4 bis. Bascule des courriels sur la messagerie du serveur.
#
#        Le .env de production envoyait par smtp.gmail.com, avec le mot de passe
#        d'application d'une boîte gmail personnelle. Les messages partaient,
#        mais sous une autre identité que le domaine — et un mot de passe
#        d'application révoqué suffit à tout arrêter sans prévenir.
#
#        « sendmail » remet le message à l'Exim du serveur, sous infos@pouletafc.com.
#        Aucun identifiant à stocker, aucun service tiers dans la chaîne, et
#        l'expéditeur relève du domaine — ce que les filtres anti-spam vérifient.
#
#        Opération unique : le marqueur ci-dessous empêche de rejouer la bascule
#        à chaque déploiement. Le jour où tu préfères un SMTP authentifié sur la
#        boîte infos@, tu modifies le .env à la main et le script n'y touchera
#        plus — c'est tout l'intérêt du marqueur.
step "4 bis - messagerie du serveur"
MARQUEUR="# courriel-serveur-applique"
if grep -qF "$MARQUEUR" "$DEPLOY_PATH/.env"; then
  echo "Bascule déjà appliquée, .env laissé tel quel."
else
  ENV_FICHIER="$DEPLOY_PATH/.env"

  regler() {
    # Remplace la valeur si la clé existe, l'ajoute sinon.
    if grep -qE "^[[:space:]]*$1=" "$ENV_FICHIER"; then
      sed -i "s|^[[:space:]]*$1=.*|$1=$2|" "$ENV_FICHIER"
    else
      echo "$1=$2" >> "$ENV_FICHIER"
    fi
  }

  regler MAIL_MAILER sendmail
  regler MAIL_FROM_ADDRESS '"infos@pouletafc.com"'
  regler MAIL_FROM_NAME '"Poulet AFC"'

  # Les réglages SMTP de gmail deviennent inertes avec « sendmail », mais on les
  # met en commentaire plutôt que de les effacer : si la bascule déçoit, la
  # configuration précédente est là, sous les yeux, prête à être remise.
  # Délimiteur « @ » : « | » sépare déjà les alternatives du motif.
  sed -i -E 's@^[[:space:]]*(MAIL_HOST|MAIL_PORT|MAIL_USERNAME|MAIL_PASSWORD|MAIL_ENCRYPTION)=@# (avant bascule) \1=@' "$ENV_FICHIER"

  echo "$MARQUEUR" >> "$ENV_FICHIER"
  echo "Courriels basculés sur la messagerie du serveur (infos@pouletafc.com)."
fi

# 4 ter. Adresse publique de l'application.
#
#        APP_URL valait « http://localhost » en production. Laravel s'en sert
#        partout où il doit fabriquer une adresse absolue sans requête sous la
#        main : liens de réinitialisation de mot de passe, tâches en file,
#        commandes artisan. Ces liens pointaient donc vers la machine du
#        destinataire — et un courriel qui renvoie vers localhost est aussi ce
#        qu'un filtre anti-spam relève en premier.
step "4 ter - adresse publique"
MARQUEUR_URL="# url-application-corrigee"
if grep -qF "$MARQUEUR_URL" "$DEPLOY_PATH/.env"; then
  echo "Adresse publique déjà corrigée."
else
  if grep -qE "^[[:space:]]*APP_URL=" "$DEPLOY_PATH/.env"; then
    sed -i 's|^[[:space:]]*APP_URL=.*|APP_URL=https://pouletafc.com|' "$DEPLOY_PATH/.env"
  else
    echo "APP_URL=https://pouletafc.com" >> "$DEPLOY_PATH/.env"
  fi

  echo "$MARQUEUR_URL" >> "$DEPLOY_PATH/.env"
  echo "APP_URL fixé à https://pouletafc.com."
fi

# 5. Restaure les fichiers persistants depuis la sauvegarde
step "5 - restauration storage/app"
if [ -d "$BACKUP_PATH/storage/app" ]; then
  rsync -a "$BACKUP_PATH/storage/app/" "$DEPLOY_PATH/storage/app/"
fi

# Les logs ne sont PLUS recopiés d'une release à l'autre. Un laravel.log
# non tourné atteint facilement plusieurs Go sur un projet ancien, et le
# recopier intégralement à chaque déploiement sur ce disque mutualisé
# faisait dépasser le command_timeout de 30 min : le déploiement
# s'interrompait avant d'avoir synchronisé le webroot.
# Les anciens logs restent consultables dans $BACKUP_PATH/storage/logs.
step "5 - logs (ignorés volontairement, voir commentaire)"

# 5 bis. Images produits et pièces jointes envoyées depuis l'app mobile et le
#        back-office. Elles sont écrites par PHP dans public_path('upload')
#        (33 appels) et doivent survivre à chaque mise en production : ni
#        l'archive, qui les exclut, ni le rsync --delete de l'étape 6 ne les
#        épargnent. Leur original vit désormais dans le webroot (étape 7 bis) ;
#        c'est donc là qu'on les rassemble, en trois temps.
#
#        Temps 1 : rétablir un vrai dossier si un déploiement antérieur avait
#        fait du webroot un lien vers la release, en récupérant son contenu.
#        À faire d'abord : sans cela, les copies suivantes écriraient à travers
#        le lien, donc dans l'ancienne release qu'on s'apprête à effacer.
if [ -L "$WEBROOT_PATH/upload" ]; then
  ANCIEN_LIEN="$(readlink -f "$WEBROOT_PATH/upload" || true)"
  rm -f "$WEBROOT_PATH/upload"
  mkdir -p "$WEBROOT_PATH/upload"

  if [ -n "$ANCIEN_LIEN" ] && [ -d "$ANCIEN_LIEN" ]; then
    rsync -a "$ANCIEN_LIEN/" "$WEBROOT_PATH/upload/"
    echo "Uploads repris depuis l'ancien lien : $(ls -1 "$WEBROOT_PATH/upload" | wc -l) fichiers"
  fi
fi

mkdir -p "$WEBROOT_PATH/upload"

#        Temps 2 : compléter avec ce que porte la release sauvegardée, sans
#        écraser ce que le webroot a déjà — il peut être plus récent.
#        -L déréférence, la sauvegarde pouvant elle-même contenir un lien.
#        On ratisse toutes les copies plausibles, jamais une seule.
#
#        Vingt-six images de fiches produits ont disparu du serveur : elles
#        ne sont ni dans le webroot, ni dans la dernière sauvegarde, ni
#        versionnées — le dépôt ne porte qu'un lot bien plus ancien. Elles
#        peuvent en revanche subsister dans une release antérieure oubliée.
#        Ce balayage coûte quelques secondes et n'écrase jamais un fichier
#        présent ; il ne peut donc que rendre ce qui a été perdu.
AVANT_RECUPERATION=$(ls -1 "$WEBROOT_PATH/upload" 2>/dev/null | wc -l)

for SOURCE in "$BACKUP_PATH/public/upload" "$HOME"/pouletafc*/public/upload; do
  # Le motif ci-dessus reste littéral s'il ne correspond à rien, et
  # une source peut être le dossier de destination lui-même.
  if [ ! -d "$SOURCE" ]; then
    continue
  fi

  # Écrit en « if » et non en « [ … ] && continue » : sous set -e, un
  # test faux fait renvoyer un code non nul à la liste entière et
  # interrompt le déploiement.
  if [ "$(readlink -f "$SOURCE")" = "$(readlink -f "$WEBROOT_PATH/upload")" ]; then
    continue
  fi

  rsync -aL --ignore-existing "$SOURCE/" "$WEBROOT_PATH/upload/" 2>/dev/null || true
done

APRES_RECUPERATION=$(ls -1 "$WEBROOT_PATH/upload" 2>/dev/null | wc -l)
echo "Uploads : $APRES_RECUPERATION fichiers ($(( APRES_RECUPERATION - AVANT_RECUPERATION )) récupérés)"

step "6 - rsync vers le webroot"
# 6. Synchronise les assets buildés vers le webroot réel (public_html n'est pas un
#    symlink vers erpndazoa/public sur ce serveur, donc il faut copier le build à
#    chaque déploiement, sinon le site sert d'anciens fichiers JS/CSS -> page cassée).
#    On préserve l'index.php spécifique du webroot (chemins ../erpndazoa/...) et
#    .well-known.
mkdir -p "$WEBROOT_PATH"
rsync -a --delete \
  --exclude='index.php' \
  --exclude='.well-known' \
  --exclude='storage' \
  --exclude='upload' \
  "$DEPLOY_PATH/public/" "$WEBROOT_PATH/"

# 6 bis. "upload" est exclu ci-dessus parce que le webroot en détient
#        désormais l'original (voir étape 7 bis) : sans l'exclusion,
#        rsync --delete effacerait toutes les images à chaque mise en
#        production.

# 7 bis. CAUSE RACINE des images produits invisibles : PHP écrit les fichiers
#        envoyés depuis le back-office et l'app mobile dans
#        public_path('upload') = "$DEPLOY_PATH/public/upload", alors que le
#        webserver sert "$WEBROOT_PATH", qui en est une COPIE distincte. Une
#        image ajoutée depuis le tableau de bord était donc bien enregistrée sur
#        le disque, mais jamais servie : elle n'apparaissait qu'après le
#        déploiement suivant, et seulement si elle avait été committée.
#
#        Le lien va du dossier de release VERS le webroot, et non
#        l'inverse.
#
#        On avait d'abord fait pointer public_html/upload vers la
#        release : LiteSpeed refuse de servir à travers un lien dont
#        la cible sort du dossier web, et renvoyait 403 sur toutes les
#        images — y compris sur des fichiers inexistants, preuve que
#        le refus portait sur le chemin et non sur les fichiers.
#
#        Dans ce sens-ci, le serveur sert un vrai dossier et PHP écrit
#        à travers le lien : PHP, lui, suit les liens sans restriction.
UPLOAD_REEL="$WEBROOT_PATH/upload"
mkdir -p "$UPLOAD_REEL"

# Lisible et traversable par le serveur web, faute de quoi il refuse
# l'accès quel que soit le contenu.
chmod a+rX "$UPLOAD_REEL"
find "$UPLOAD_REEL" -type d -exec chmod a+rx {} + 2>/dev/null || true
find "$UPLOAD_REEL" -type f -exec chmod a+r {} + 2>/dev/null || true

rm -rf "$DEPLOY_PATH/public/upload"
ln -sfn "$UPLOAD_REEL" "$DEPLOY_PATH/public/upload"
echo "public/upload de la release pointe vers $UPLOAD_REEL ($(ls -1 "$UPLOAD_REEL" | wc -l) fichiers)"

# 7. Lien storage accessible publiquement : recréé s'il est absent ou s'il ne pointe
#    pas vers le bon chemin (ne touche pas à un lien déjà valide).
if [ "$(readlink "$WEBROOT_PATH/storage" 2>/dev/null || true)" != "$DEPLOY_PATH/storage/app/public" ]; then
  ln -sfn "$DEPLOY_PATH/storage/app/public" "$WEBROOT_PATH/storage"
fi

step "8 - permissions"
# 8. Permissions
chmod -R ug+rwx "$DEPLOY_PATH/storage" "$DEPLOY_PATH/bootstrap/cache"

step "9 - taches artisan (migrate / config / view)"
# 9. Tâches de release Laravel
cd "$DEPLOY_PATH"
php artisan migrate --force
step "9 - migrate termine, config:cache"
php artisan config:cache
# route:cache désactivé : routes/web.php contient des noms de route dupliqués
# (dashboard, home, status.change, ...) qui font échouer la compilation du cache.
php artisan view:cache
php artisan queue:restart || true

step "10 - controle des images"
# 10. Dernier mot sur les images.
#
#     Si leur nombre a baissé, quelque chose les a effacées malgré tout :
#     on les remet depuis l'instantané et on fait échouer le déploiement,
#     pour que la perte se voie au lieu de passer inaperçue jusqu'à ce
#     qu'un client tombe sur une fiche sans photo.
UPLOADS_APRES=$( (find "$UPLOAD_DIR" -type f 2>/dev/null || true) | wc -l )

if [ "$UPLOADS_APRES" -lt "$UPLOADS_AVANT" ]; then
  echo "ALERTE : $UPLOADS_AVANT images avant, $UPLOADS_APRES après. Restauration." >&2

  if [ -d "$INSTANTANE" ]; then
    mkdir -p "$UPLOAD_DIR"
    rsync -a --ignore-existing "$INSTANTANE/" "$UPLOAD_DIR/"
    echo "Restaurées : $(find "$UPLOAD_DIR" -type f | wc -l) fichiers" >&2
  fi

  echo "Le déploiement est marqué en échec : cette perte doit être examinée." >&2
  exit 1
fi

echo "Images : $UPLOADS_APRES fichiers (aucune perte)."

echo "Déploiement terminé. Sauvegarde disponible dans $BACKUP_PATH en cas de problème."
