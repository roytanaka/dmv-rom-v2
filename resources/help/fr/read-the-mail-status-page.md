# Lire la page d'état du courriel

Vérifiez que l'application envoie le courriel. Vous avez besoin du grade super-niveau.

## Ouvrir la page

1. Sélectionnez votre avatar à droite de la barre du haut.

    ![Le menu de l'avatar : État du courriel entre Renouveler l'adhésion et Se déconnecter](01.png)

2. Sélectionnez **État du courriel**.

    ![La page d'état du courriel : les quatre valeurs, sans avertissement](02.png)

La page montre la file d'attente du courriel et la tâche qui la vide.

## Ce que montre la page

- **Dernière exécution du planificateur** indique quand l'application a vérifié la file pour la dernière fois. Ce devrait être dans la dernière minute.
- **Dernier courriel envoyé** indique quand l'application a envoyé un courriel pour la dernière fois.
- **En attente dans la file** indique combien de courriels attendent de partir.
- **Dernière erreur de connexion** indique la dernière fois où l'application n'a pu joindre le serveur de courriel.

## Les deux avertissements

- **Le cron semble mort** veut dire que le planificateur n'a pas tourné depuis plus de dix minutes. Aucun courriel ne part.
- **Envoi impossible** veut dire que la dernière connexion au serveur de courriel a échoué après le dernier bon envoi.

> **Note :** Une grosse diffusion laisse un nombre en attente élevé pendant jusqu'à une heure. C'est normal.

## Et ensuite

Cette page est le premier endroit où regarder quand un·e membre dit qu'un courriel n'est jamais arrivé.
