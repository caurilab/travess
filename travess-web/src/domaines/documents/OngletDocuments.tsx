import { useMutation, useQuery } from '@tanstack/react-query';
import { TYPES_DOCUMENT, type DossierDTO, type StatutIngestion, type TypeDocument } from '@travess/shared-types';
import { useState, type FormEvent } from 'react';

import { BadgeStatut } from '../../ui/BadgeStatut.js';
import { Bouton } from '../../ui/Bouton.js';
import { Carte } from '../../ui/Carte.js';
import { libelleTypeDocument, statutIngestion } from '../../lib/statuts.js';
import { chargerExtraction, deposerDocument, lancerExtraction, validerExtraction } from './api.js';

const REQUISES: readonly TypeDocument[] = ['bl', 'facture_charges', 'declaration_douane', 'do', 'bon_livraison'];

export function OngletDocuments({ dossier, onChangement }: { readonly dossier: DossierDTO; readonly onChangement: () => void }) {
  const documents = dossier.documents ?? [];
  const presents = new Set(documents.map((d) => d.type));

  return (
    <div className="detail__colonne">
      <Carte>
        <h2 className="detail__titre-carte">Pièces requises</h2>
        <ul className="checklist">
          {REQUISES.map((t) => (
            <li key={t} className={`checklist__item${presents.has(t) ? ' checklist__item--ok' : ''}`}>
              <span className="checklist__marque">{presents.has(t) ? '✓' : '○'}</span>
              {libelleTypeDocument(t)}
            </li>
          ))}
        </ul>
      </Carte>

      <Carte>
        <h2 className="detail__titre-carte">Documents du dossier</h2>
        {documents.length === 0 ? (
          <p className="tb__vide">Aucun document.</p>
        ) : (
          <div className="docs">
            {documents.map((doc) => (
              <LigneDocument
                key={doc.id}
                documentId={doc.id}
                libelle={libelleTypeDocument(doc.type)}
                nom={doc.nom_original}
                statut={doc.statut_ingestion}
                onChangement={onChangement}
              />
            ))}
          </div>
        )}

        <TeleversementDocument dossierId={dossier.id} onAjout={onChangement} />
      </Carte>
    </div>
  );
}

function LigneDocument({
  documentId,
  libelle,
  nom,
  statut,
  onChangement,
}: {
  readonly documentId: string;
  readonly libelle: string;
  readonly nom: string | null;
  readonly statut: StatutIngestion;
  readonly onChangement: () => void;
}) {
  const [voirProposition, setVoirProposition] = useState(false);
  const si = statutIngestion(statut);

  const extraction = useMutation({
    mutationFn: () => lancerExtraction(documentId),
    onSuccess: onChangement,
  });

  return (
    <div className="doc">
      <div className="doc__ligne">
        <div className="doc__info">
          <span className="doc__type">{libelle}</span>
          <span className="doc__nom">{nom ?? '—'}</span>
        </div>
        <BadgeStatut ton={si.ton}>{si.libelle}</BadgeStatut>
        <div className="doc__actions">
          {statut === 'none' ? (
            <Bouton variante="secondaire" chargement={extraction.isPending} onClick={() => extraction.mutate()}>
              Lancer l'extraction IA
            </Bouton>
          ) : null}
          {statut === 'extrait' ? (
            <Bouton variante="secondaire" onClick={() => setVoirProposition((v) => !v)}>
              {voirProposition ? 'Masquer' : 'Voir la proposition'}
            </Bouton>
          ) : null}
        </div>
      </div>
      {voirProposition ? <PropositionExtraction documentId={documentId} onValide={onChangement} /> : null}
    </div>
  );
}

function PropositionExtraction({ documentId, onValide }: { readonly documentId: string; readonly onValide: () => void }) {
  const extraction = useQuery({
    queryKey: ['extraction', documentId],
    queryFn: ({ signal }) => chargerExtraction(documentId, signal),
  });
  const [corrections, setCorrections] = useState<Record<string, string>>({});
  const [erreur, setErreur] = useState<string | null>(null);

  const validation = useMutation({
    mutationFn: () => validerExtraction(extraction.data?.id ?? '', corrections),
    onSuccess: onValide,
    onError: () => setErreur('Validation refusée (champs requis manquants, ex. armateur pour un BL).'),
  });

  if (extraction.isPending) {
    return <div className="doc__proposition"><span className="tv-spinner" /></div>;
  }
  if (extraction.isError || extraction.data.statut !== 'reussi') {
    return <p className="doc__proposition tb__vide">Extraction indisponible.</p>;
  }

  const champs = Object.entries(extraction.data.champs);

  return (
    <div className="doc__proposition">
      <p className="doc__proposition-titre">Proposition de l'IA — vérifiez puis validez</p>
      <div className="champs">
        {champs.map(([nom, champ]) => (
          <label key={nom} className="champ-extrait">
            <span className="champ-extrait__nom">
              {nom}
              <em className="champ-extrait__confiance">{Math.round(champ.confiance * 100)}%</em>
            </span>
            <input
              className="tv-champ__entree"
              defaultValue={champ.valeur === null || champ.valeur === undefined ? '' : String(champ.valeur)}
              onChange={(e) => setCorrections((c) => ({ ...c, [nom]: e.target.value }))}
            />
          </label>
        ))}
      </div>
      {erreur !== null ? <p className="tv-champ__erreur">{erreur}</p> : null}
      <Bouton chargement={validation.isPending} onClick={() => validation.mutate()}>
        Valider et appliquer au dossier
      </Bouton>
    </div>
  );
}

function TeleversementDocument({ dossierId, onAjout }: { readonly dossierId: string; readonly onAjout: () => void }) {
  const [type, setType] = useState<string>('bl');
  const [fichier, setFichier] = useState<File | null>(null);
  const [erreur, setErreur] = useState<string | null>(null);

  const depot = useMutation({
    mutationFn: () => deposerDocument(dossierId, type, fichier as File),
    onSuccess: () => {
      setFichier(null);
      setErreur(null);
      onAjout();
    },
    onError: () => setErreur('Téléversement refusé (type de fichier ou taille).'),
  });

  function soumettre(ev: FormEvent) {
    ev.preventDefault();
    if (fichier === null) return;
    depot.mutate();
  }

  return (
    <form className="televersement" onSubmit={soumettre}>
      <select className="detail__select-mini" value={type} onChange={(e) => setType(e.target.value)}>
        {TYPES_DOCUMENT.map((t) => (
          <option key={t} value={t}>
            {libelleTypeDocument(t)}
          </option>
        ))}
      </select>
      <input
        type="file"
        accept=".pdf,.jpg,.jpeg,.png"
        onChange={(e) => setFichier(e.target.files?.[0] ?? null)}
      />
      <Bouton type="submit" variante="secondaire" chargement={depot.isPending} disabled={fichier === null}>
        Téléverser
      </Bouton>
      {erreur !== null ? <span className="tv-champ__erreur">{erreur}</span> : null}
    </form>
  );
}
