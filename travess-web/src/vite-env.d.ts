/// <reference types="vite/client" />

interface ImportMetaEnv {
  /**
   * Origine absolue de l'API pour les enrobages natifs (mobile Capacitor),
   * figée au build. Vide/absente sur le web : la base reste relative « /api/v1 ».
   */
  readonly VITE_API_ORIGINE?: string;
}

interface ImportMeta {
  readonly env: ImportMetaEnv;
}
