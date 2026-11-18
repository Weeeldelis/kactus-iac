# Image de base figee (pas "latest") : reproductible et rollback verifiable
FROM wordpress:6.8-php8.3-apache

# Version affichee sur le site : initiales + numero
ARG VERSION=tb1
ENV KACTUS_VERSION=${VERSION}

# Plugin "must-use" qui affiche la version en pied de page
COPY kactus-version.php /usr/src/wordpress/wp-content/mu-plugins/kactus-version.php

LABEL dsi.kactus/dossier="KCT-2026-1911"
