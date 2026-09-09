-- Provisionnement du rôle applicatif Travess.
--
-- Exécuté par l'entrypoint PostgreSQL au premier démarrage, en tant que
-- superutilisateur admin (POSTGRES_USER=postgres). On crée un rôle applicatif
-- « travess » explicitement NOSUPERUSER, propriétaire de la base : c'est la
-- condition pour que FORCE ROW LEVEL SECURITY s'applique réellement (un
-- superutilisateur contourne la RLS même avec FORCE). Cf. ADR-004.

CREATE ROLE travess WITH LOGIN PASSWORD 'secret' NOSUPERUSER NOCREATEDB NOCREATEROLE;

CREATE DATABASE travess OWNER travess;

-- Base de test (même moteur que la prod, pour exercer la RLS dans la suite).
CREATE DATABASE travess_test OWNER travess;
