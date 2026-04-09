import Link from "next/link";

export default function Home() {
  return (
    <div className="flex flex-1 items-center justify-center bg-zinc-50 px-6 py-16">
      <main className="w-full max-w-3xl rounded-lg border border-zinc-200 bg-white p-10">
        <h1 className="text-3xl font-semibold tracking-tight text-zinc-950">
          Bibliothèque en ligne
        </h1>
        <p className="mt-3 text-zinc-600">
          Recherchez des livres, faites des demandes d’emprunt, et gérez la
          bibliothèque (admin).
        </p>

        <div className="mt-8 flex flex-col gap-3 sm:flex-row">
          <Link
            href="/signup"
            className="inline-flex h-11 items-center justify-center rounded-md bg-zinc-900 px-5 text-sm font-medium text-white"
          >
            Inscription (usager)
          </Link>
          <Link
            href="/login"
            className="inline-flex h-11 items-center justify-center rounded-md border border-zinc-300 bg-white px-5 text-sm font-medium text-zinc-900"
          >
            Connexion
          </Link>
        </div>

        <div className="mt-10 text-sm text-zinc-600">
          <p>
            Démo rapide : connectez-vous en admin via <code>admin@biblio.local</code>
            / <code>admin12345</code> (modifiable via <code>ADMIN_EMAIL</code> et
            <code> ADMIN_PASSWORD</code> côté backend).
          </p>
        </div>
      </main>
    </div>
  );
}
