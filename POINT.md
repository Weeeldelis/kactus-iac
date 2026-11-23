# POINT D'ÉTAPE — TP Kactus IaC (UE3.22) — Tom Bazire

> Fichier de passation : à donner à une nouvelle conversation pour reprendre exactement où on en est.
> Dernière mise à jour : 23/11/2026. Dépôt : https://github.com/Weeeldelis/kactus-iac
> ⚠️ Dépôt PUBLIC : aucun mot de passe ici, ni nulle part dans le dépôt (c'est tout le sujet de l'épreuve 5).

---

## 1. Contexte

- **Cours** : M2-UE3.22 « Administration réseau via une approche DevOps : IaC » (prof : Jimmy Nicolas), 2026-2027.
- **Sujet** : cahier des charges « Kactus » : déployer WordPress + MySQL sur Kubernetes (Minikube) en **GitOps** (Argo CD + Kustomize), avec pipeline CI, Trivy, kube-bench, Prometheus/Grafana, NetworkPolicy, 7 épreuves.
- **TP non noté.** L'enjeu, c'est le **partiel sur papier, sans document** : analyse argumentée de situations comparables aux épreuves. Le sujet le dit : « au partiel, l'expertise sera uniquement la vôtre ».
- **IA autorisée comme assistant**, à condition de savoir justifier chaque commande.
- **Posture attendue** : Tom joue la **MOE**. Il construit, et quand une exigence de la DSI (MOA) est mauvaise, il fait le meilleur choix réaliste et le **justifie** dans le journal de décisions (« proposé / retenu / pourquoi »). Le sujet prévoit qu'on tombe dans des pièges : « si une épreuve échoue, trouvez la décision en cause, corrigez par un commit, rejouez ».
- **Le prof ne valide rien** (« cherche, la réponse est accessible »). Il faut se valider soi-même : épreuves, documentation officielle.

## 2. Méthode de travail (important pour la suite)

- Le 18/11, on est allés **trop vite** : environ 55 % du TP en 1h30, avec Claude qui donnait toutes les commandes. Tom a tout fait marcher, mais **n'a pas eu le temps de digérer** : perdu quand des camarades posent une question sous un autre angle.
- **Nouvelle méthode décidée** :
  1. Claude explique le **but** et le concept, **Tom cherche la commande**, Claude corrige.
  2. Toujours **prédire avant** chaque épreuve (attendu → observé → conclusion DSI).
  3. **Reprise de la prochaine session** : un **schéma complet** de l'environnement (= annexe B.4), que Tom doit savoir **redessiner et expliquer seul**, avant de construire quoi que ce soit de nouveau.
  4. La **reconstruction sur le PC fixe**, c'est Tom qui la fait seul depuis son dépôt. Claude n'intervient que s'il bloque vraiment.
  5. Le **journal de décisions**, c'est Tom qui l'écrit avec ses mots. Claude relit et challenge.
- Tom veut des réponses **courtes, une étape à la fois, en français**. Il se sature vite après 45 min de théorie : alterner théorie courte et pratique.

## 3. Environnement actuel

| Élément | Valeur |
|---|---|
| Machine | **Vivobook** (i5, 16 Go), **WSL2 Ubuntu 24.04**, utilisateur `tom` |
| Mémoire WSL | 7,6 Go → cluster **5,6 Go** (règle du sujet : mémoire VM − 2 Go) |
| Cluster | `minikube start --memory=5600` (driver docker, runtime containerd, Kubernetes v1.37.0) |
| Namespace du site | `kactus-tb` (initiales **tb**) |
| Image web | `docker.io/kakctus/kactus-web:tb1` (et `tb2`), sur Docker Hub |
| Argo CD | **v3.6.1** (version fixée, pas `latest`), namespace `argocd` |
| Comptes | GitHub `Weeeldelis`, Docker Hub `kakctus` (secrets `DOCKERHUB_USERNAME` / `DOCKERHUB_TOKEN` dans GitHub Actions) |
| Accès au site | `kubectl -n kactus-tb port-forward svc/wordpress 8080:80` → http://localhost:8080 |
| Accès Argo CD | `kubectl -n argocd port-forward svc/argocd-server 8443:443` → https://localhost:8443 (admin) |
| Relancer après redémarrage | Ouvrir Ubuntu → `minikube start` (les disques sont conservés) |

**À savoir :**
- Tout se tape dans **Ubuntu** (prompt `tom@Ordinateur`), jamais dans PowerShell. Il existe un vieux Minikube côté Windows, à ignorer.
- Un **port-forward saute à chaque redéploiement** (il vise un Pod précis) : c'est normal, il suffit de le relancer.
- Ne jamais coller plusieurs lignes après une commande qui demande un mot de passe (`sudo`) : elles sont avalées. Enchaîner avec `&&` sur une seule ligne.

**Prévu ensuite** : migrer sur le **PC fixe** (Ryzen 7 9800X3D, 64 Go) dans une VM Ubuntu Server 24.04 (16 Go RAM, 6 vCPU, 80 Go) → cluster de 14 Go, nécessaire pour Prometheus/Grafana et l'annexe A. Minikube y sera démarré **avec `--cni=calico`** (décision D6).

## 4. Structure du dépôt

```
app/Dockerfile              FROM wordpress:6.8-php8.3-apache (version figée), ARG VERSION, mu-plugin
app/kactus-version.php      affiche « Kactus version : <VERSION> » en pied de page (site public, pas /wp-admin)
app/VERSION                 numéro de version lu par la CI (actuellement tb2)
.github/workflows/build.yml CI : sur push dans app/** → build + push kakctus/kactus-web:<VERSION>
k8s/kustomization.yaml      commonAnnotations dsi.kactus/dossier: KCT-2026-1911 + liste des ressources
k8s/namespace.yaml          kactus-tb
k8s/configmap.yaml          DB_NAME, DB_USER, DB_HOST=mysql (non sensible)
k8s/mysql.yaml              Service + StatefulSet 1 réplica, mysql:8.4, PVC 5Gi RWO, revisionHistoryLimit 7
k8s/wordpress.yaml          Service NodePort 30080 + PVC 5Gi RWX + Deployment 2 réplicas, image tb1, revisionHistoryLimit 7
k8s/networkpolicy.yaml      seul app=wordpress peut joindre app=mysql:3306
argocd/application.yaml     Application « kactus » : repo main, path k8s, dest kactus-tb, automated + selfHeal + prune
DECISIONS.md                journal de décisions (D1 à D4 en brouillon, D5 et D6 à écrire)
```

- Le Secret `kactus-db` (clés `db-password`, `root-password`) a été **créé à la main** à l'initialisation (`kubectl create secret`), **pas versionné** (décision D2).
- L'image `tb1` a d'abord été construite localement (`minikube image build`, l'option `--build-opt=build-arg` ne marche pas avec containerd), puis publiée par la CI.
- `argocd/application.yaml` est dans Git mais **n'est pas géré par Argo** (appliqué à la main). Limite connue → amélioration possible : motif **« app of apps »**.

## 5. Avancement

| Phase | État | Détail |
|---|---|---|
| 1. Cluster + manifestes | ✅ | Image tb1, MySQL, WordPress, Kustomize, validation `kubectl apply -k k8s --dry-run=server` (7 ressources OK, 9 annotations car aussi dans les modèles de Pods) |
| 2. GitOps | ✅ | Argo CD branché, épreuve 1 jouée deux fois, D5 |
| 3. Cycle de vie | ✅ | CI GitHub Actions → Docker Hub, épreuves 2 et 3 |
| 4. Sécurité / observabilité | 🟠 ~1/3 | Épreuve 4 faite (**à rejouer avec Calico**). Restent : épreuve 5 (besoin d'un camarade), épreuve 6, Trivy dans la CI, kube-bench, Prometheus + Grafana |
| 5. Annexe A (épreuve 7) | ❌ | **Le manifeste de l'éditeur est « remis à part » : vérifier si le prof l'a donné** |

**Livrables :** dépôt ✅ (à compléter) · `DECISIONS.md` 🟠 (décisions prises, pas toutes écrites) · rapport sécurité ❌ · carnet de bord **manuscrit** ❌ (Claude a gardé les observations ci-dessous, Tom doit les recopier à la main) · schéma B.4 ❌ · trajets B.2 ❌ · mesures Grafana B.3 ❌.

## 6. Observations des tests et épreuves (matière du carnet de bord)

**Test HA** (suppression forcée des 2 Pods WordPress)
- Observé : recréés en ~1 s, nouveaux noms, même ReplicaSet (`96c76dc5c`).
- Conclusion DSI : la panne d'un conteneur est gérée seule. Avec 2 réplicas et un seul Pod supprimé, pas de coupure (en supprimant les 2, coupure d'environ 1 s). Ne protège **pas** de la panne de la machine.

**Expérience MySQL à 2 réplicas** (avant Argo CD)
- Observé : `mysql-1` créé avec **son propre disque vide** (`data-mysql-1`). Le Service répartit les connexions → sur 10 requêtes : 4× `200`, 6× `302 install.php`.
- Conclusion : pas de corruption (disques séparés) mais **incohérence** : deux bases indépendantes, rien ne les synchronise. `replicas: 2` ≠ réplication.
- Solutions : 1 MySQL + disque + sauvegardes (retenu) ; principal + réplique ; Galera ; opérateur. Les solutions sérieuses demandent **3 instances** (quorum, sinon **split-brain**) → **« 2 réplicas » est précisément le mauvais chiffre pour une base.**
- Remis en ordre : retour à 1 réplica + suppression du PVC `data-mysql-1` (un StatefulSet ne supprime jamais ses disques, par sécurité).

**Épreuve 1 : dérive**
- Sans selfHeal : `scale` à 5 à 15:32 → OutOfSync, toujours 5/5 après 3 min 30. Commit sans rapport (README) à 15:37 → retour à 2 en quelques secondes.
- Avec selfHeal : `scale` à 5 → annulé en ~2 s, les Pods supplémentaires n'ont jamais démarré (Argo surveille le cluster en continu, et Git toutes les ~3 min).
- Conclusion DSI : sans selfHeal, l'état réel dépend du hasard des commits. La « main en urgence » est illusoire (effacée au premier commit de n'importe qui). La vraie main en urgence = commit Git, ou suspension volontaire de la synchro.

**Révisions** : la première synchro Argo a créé une nouvelle révision (rev:2) du Deployment et du StatefulSet, parce que Kustomize ajoute l'annotation dans le modèle des Pods. **Modifier le modèle de Pod, même une annotation, déclenche un redéploiement.**

**Épreuve 2 : rollback**
- `app/VERSION` = tb2 → CI publie tb2 → commit `image: tb2` → Argo → rolling update → site « tb2 ». Puis `git revert` du commit de déploiement → site « tb1 » en ~1 min. Kubernetes a réutilisé l'ancien ReplicaSet tb1.
- Conclusion DSI : rollback traçable et rapide grâce aux tags figés. Avec `latest`, le fichier ne change pas entre v1 et v2 → `git revert` ne change rien → **rollback impossible**.

**Épreuve 3 : persistance et maintenance**
- Article publié, suppression des Pods WordPress + `mysql-0` → article toujours là (disque `data-mysql-0`). Argo n'a pas réagi : il compare Git au Deployment, pas aux Pods. C'est le ReplicaSet qui recrée.
- `kubectl drain minikube --ignore-daemonsets --delete-emptydir-data --force` → tout en Pending : site coupé **et Argo CD coupé**. Remise en service : `kubectl uncordon minikube && minikube addons enable storage-provisioner`.
- Conclusion DSI : 2 réplicas sur **un seul nœud** ne protègent pas d'une maintenance. 99,99 % ≈ 52 min d'arrêt par an → impossible. Il faudrait 2-3 nœuds, de l'anti-affinité et un **PodDisruptionBudget**.

**Épreuve 4 : compromission simulée**
- NetworkPolicy déployée par Git (verte dans Argo). Pod `busybox` dans `default` → `nc -zv mysql.kactus-tb.svc.cluster.local 3306` → **`open`** : la règle **n'est pas appliquée** (le CNI par défaut de Minikube ignore les NetworkPolicies).
- Dans le Pod WordPress, l'attaquant trouve : **root** (uid=0), **mot de passe de la base en clair** dans l'environnement + user/host/nom de base, IP des Services, **jeton ServiceAccount**, **accès Internet** (HTTP 200).
- Correctifs : `runAsNonRoot`, `automountServiceAccountToken: false`, NetworkPolicy de sortie (egress), compte de base aux droits minimaux, et un **CNI qui applique les règles** (Calico).
- Conclusion DSI : faux sentiment de sécurité. Il faut **tester**, pas seulement configurer. Défense en couches.

## 7. Journal de décisions (à rédiger par Tom dans DECISIONS.md)

| # | Proposé par la DSI | Retenu | Pourquoi |
|---|---|---|---|
| D1 | tag `latest` | tags figés tb1, tb2… (et WordPress 6.8, MySQL 8.4, Argo CD v3.6.1) | Reproductible, on sait ce qui tourne, rollback possible (épreuve 2) |
| D2 | Secrets versionnés dans Git | Secret créé à la main à l'init, hors Git | Base64 ≠ chiffrement, dépôt public, historique éternel (démontré : `base64 -d`). Mieux : Sealed Secrets / SOPS (approfondissement). Alternative pédagogique : le mettre dans Git, le voir trouvé à l'épreuve 5, puis rotation |
| D3 | 2 réplicas pour la base | 1 MySQL en StatefulSet + PVC (+ sauvegardes) | Expérience : 2 bases indépendantes → site incohérent. Vraie HA = réplication + quorum (3 instances), hors périmètre |
| D4 | Seule l'app web joint la base | NetworkPolicy écrite | Inefficace sans CNI compatible → voir D6 |
| D5 | Sync auto **sans** auto-correction | selfHeal + prune activés | Épreuve 1 : dérive persistante puis effacée au hasard. Urgence = commit ou suspension volontaire de la synchro. Recommandations : RBAC (humains en lecture seule, Argo seul en écriture, compte bris de glace) + alerte OutOfSync. Contre-argument défendable : en prod prudente, on garde selfHeal off + alerte |
| D6 | Minikube avec la seule option mémoire | `--cni=calico` (à la reconstruction sur PC fixe) | Sans lui, la NetworkPolicy ne protège rien (épreuve 4) |

**Autres pièges identifiés, à traiter :**
- « Rapport Trivy à 0 vulnérabilité » → impossible honnêtement. On ne l'obtient qu'en filtrant (épreuve 6). Livrer le rapport complet + exceptions justifiées.
- « Trivy compromis en mars 2026 » → version fixée et vérifiée (empreinte/SHA), jamais `latest`. Même logique pour les actions GitHub (`@v4`, `ubuntu-latest` sont des étiquettes mobiles).
- Annexe A : 2 × (4 CPU + 16 Go) sur un cluster de 14 Go → Pods **Pending**. Mode charge → pression mémoire, **expulsion** des Pods sans requests. Réponse : requests/limits, ResourceQuota par namespace, PriorityClass.
- Autoscaling + selfHeal = conflit sur `replicas` (ignorer le champ ou le retirer du manifeste).
- kube-bench : choisir 5 contrôles en échec et dire s'ils comptent pour un cluster local jetable (discernement).

## 8. Questions de camarades (et réponses validées)

- **Le cluster est-il accessible depuis Internet ?** Non (WSL privé, accès par port-forward local).
- **Les configs « redescendent » depuis Git ?** Oui. Deux étapes, deux sens : l'humain **pousse** vers Git (`git push`), Argo CD **dans** le cluster **tire** depuis Git et applique. **Personne ne pousse dans le cluster.** C'est pour ça que le cluster peut rester fermé à Internet, et qu'aucun identifiant du cluster n'est dans la CI (modèle pull, confirmé par la documentation de référence).
- Une 3e question avait été posée, Tom ne s'en souvenait plus.

## 9. Prochaines étapes

1. **Schéma complet de l'environnement** (B.4) + les 2 trajets (B.2), à savoir redessiner seul.
2. **Écrire `DECISIONS.md`** D1 à D6 (Tom, avec ses mots).
3. **PC fixe** : VM Ubuntu Server + reconstruction **par Tom seul** depuis le dépôt, avec `--cni=calico` → rejouer l'épreuve 4 (attendu : `timed out`).
4. Épreuve 6 + **Trivy dans la CI** (version vérifiée) + **kube-bench**.
5. **Prometheus + Grafana** (mesures B.3 : mémoire outillage vs application).
6. Épreuve 5 avec un camarade. Annexe A / épreuve 7 (manifeste à récupérer auprès du prof).
7. Recopier le carnet de bord **à la main**.

## 10. Projet annexe : accès à distance au PC fixe (NetBird)

- Objectif : accéder au PC fixe depuis le Vivobook, de façon sécurisée, sans ouvrir de port sur la box.
- Fait : NetBird installé et connecté sur le Vivobook (compte via GitHub, IP `100.109.164.217`). Clé SSH du Vivobook publiée sur GitHub.
- Plan défini (non confirmé comme réalisé) :
  1. **2FA sur le compte GitHub** (c'est la clé du réseau NetBird).
  2. PC fixe (Windows Famille, non activé, **simplewall** bloque les installeurs et winget) : autoriser NetBird dans simplewall, installer NetBird, couper la veille (`powercfg /change standby-timeout-ac 0`), suspendre Windows Update.
  3. Prise en main : **RustDesk** (pas de bureau à distance sur Windows Famille), mot de passe permanent fort + **2FA RustDesk**, accès direct par l'IP NetBird.
  4. NetBird : groupes + **règle stricte** Vivobook → PC fixe (un seul port, un seul sens), **suppression de la règle « Default »** (tout le monde parle à tout le monde).
  5. Tester, puis vérifier que tout le reste est bloqué.
- Pièges vécus : faute de frappe `githu.com` = site de typosquatting (ne rien remplir) ; le menu « Setup Keys » est introuvable dans le tableau de bord NetBird actuel → utiliser `netbird up` et valider via l'URL.
