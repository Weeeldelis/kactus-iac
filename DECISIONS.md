# Journal de décisions - Kactus IaC (Tom Bazire, kactus-tb)

Format : ce qui était proposé / ce que j'ai retenu / pourquoi.

## D1 - Tag des images
- Proposé : tag `latest` pour toutes les images.
- Retenu : versions figées (`wordpress:6.8-php8.3-apache`, image web `tb1`, `tb2`...).
- Pourquoi : `latest` change sans prévenir. Impossible de savoir ce qui tourne,
  de reproduire un déploiement ou de prouver un rollback (épreuve 2).

## D2 - Secrets
- Proposé : Secrets Kubernetes versionnés en clair dans le dépôt Git (public).
- Retenu : A COMPLETER
- Pourquoi : un Secret Kubernetes n'est que du base64, pas du chiffrement.
  Dans un dépôt public, n'importe qui le lit, historique compris (épreuve 5).

## D3 - Base de données
- Proposé : 2 réplicas pour tous les services, base comprise.
- Retenu : A COMPLETER
- Pourquoi : deux MySQL sur les mêmes données sans réplication = corruption.

## D4 - Règles réseau
- Proposé : seule l'application web peut joindre la base.
- Retenu : A COMPLETER
