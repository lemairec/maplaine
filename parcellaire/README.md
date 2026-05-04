# Gestion Parcellaire

Application de gestion parcellaire agricole avec vue cartographique.
Se connecte à la même base de données MySQL que l'application Symfony maplaine.

## Installation

```bash
cd ~/workspace/maplaine/parcellaire
python3 -m venv venv
source venv/bin/activate
pip3 install -r requirements.txt
```

## Lancement

```bash
cd ~/workspace/maplaine/parcellaire ; source venv/bin/activate
uvicorn main:app --reload --port 8100
```

Ouvrir http://localhost:8100

## Fonctionnalités

- Vue carte (Leaflet + OpenStreetMap) des parcelles avec couleurs par culture
- Ajout / modification / suppression de parcelles avec dessin sur la carte
- Découper une parcelle en deux
- Fusionner plusieurs parcelles
- Créer des interventions (semis, traitement, récolte...) sur une ou plusieurs parcelles
- Associer des produits avec quantités et coûts
- Fiche parcellaire imprimable
- Filtrage par société et campagne
- Multi-société, multi-utilisateur (via la BDD partagée)
