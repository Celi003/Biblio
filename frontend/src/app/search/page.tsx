"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useMemo, useState } from "react";
import { apiFetch } from "@/lib/api";
import { clearAuth, getAuth, type AuthState } from "@/lib/auth";

type Book = {
  id: number;
  title: string;
  author: string;
  isbn: string;
  available_copies: number;
  total_copies: number;
};

type Paginated<T> = {
  data: T[];
};

type LoanRequest = {
  id: number;
  status: string;
  book_id: number;
};

type PublicSettings = {
  "loan.max_days": number;
};

function formatDateInput(d: Date): string {
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, "0");
  const day = String(d.getDate()).padStart(2, "0");
  return `${y}-${m}-${day}`;
}

export default function SearchPage() {
  const router = useRouter();
  const [auth, setAuthState] = useState<AuthState | null>(null);
  const [authLoaded, setAuthLoaded] = useState(false);

  const [q, setQ] = useState("");
  const [books, setBooks] = useState<Book[]>([]);
  const [activeRequestedBookIds, setActiveRequestedBookIds] = useState<Set<number>>(
    () => new Set()
  );
  const [maxLoanDays, setMaxLoanDays] = useState<number>(30);
  const [pendingLoanBookId, setPendingLoanBookId] = useState<number | null>(null);
  const [pendingDueAt, setPendingDueAt] = useState<string>("");
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  const dueAtMin = useMemo(() => formatDateInput(new Date()), []);
  const dueAtMax = useMemo(() => {
    const today = new Date();
    const max = new Date(today);
    max.setDate(today.getDate() + maxLoanDays);
    return formatDateInput(max);
  }, [maxLoanDays]);

  const defaultDueAt = useMemo(() => {
    const today = new Date();
    const fallback = new Date(today);
    fallback.setDate(today.getDate() + Math.min(14, maxLoanDays));
    return formatDateInput(fallback);
  }, [maxLoanDays]);

  useEffect(() => {
    setAuthState(getAuth());
    setAuthLoaded(true);
  }, []);

  useEffect(() => {
    if (!authLoaded) return;
    if (!auth?.token) router.replace("/login");
  }, [authLoaded, auth?.token, router]);

  useEffect(() => {
    if (authLoaded && auth?.token) {
      // Load initial list of books + my current requests
      void Promise.all([search(), loadMyRequests(), loadSettings()]);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [authLoaded, auth?.token]);

  async function loadSettings() {
    try {
      const res = await apiFetch<PublicSettings>("/settings", { auth: true });
      const max = Number(res["loan.max_days"]);
      if (Number.isFinite(max) && max >= 1 && max <= 365) {
        setMaxLoanDays(max);

        // Clamp pending due date if it exceeds the new max.
        const maxStr = (() => {
          const today = new Date();
          const m = new Date(today);
          m.setDate(today.getDate() + max);
          return formatDateInput(m);
        })();

        setPendingDueAt((current) => {
          if (!current) return current;
          return current > maxStr ? maxStr : current;
        });
      }
    } catch {
      // Optional; keep defaults
    }
  }

  async function loadMyRequests() {
    try {
      const res = await apiFetch<Paginated<LoanRequest>>("/loan-requests?active=1&per_page=100", {
        auth: true,
      });
      const active = new Set<number>();
      for (const r of res.data ?? []) {
        active.add(r.book_id);
      }
      setActiveRequestedBookIds(active);
    } catch {
      // Keep page usable even if this fails
    }
  }

  async function search() {
    setError(null);
    setLoading(true);
    try {
      const res = await apiFetch<Paginated<Book>>(`/books?q=${encodeURIComponent(q)}`);
      setBooks(res.data ?? []);
    } catch (err) {
      setError(err instanceof Error ? err.message : "Erreur");
    } finally {
      setLoading(false);
    }
  }

  function startLoanRequest(bookId: number) {
    setError(null);
    setPendingLoanBookId(bookId);
    setPendingDueAt(defaultDueAt);
  }

  function cancelLoanRequest() {
    setPendingLoanBookId(null);
    setPendingDueAt("");
  }

  async function requestLoan(bookId: number, dueAt: string) {
    setError(null);
    try {
      await apiFetch(`/loan-requests`, {
        method: "POST",
        auth: true,
        body: JSON.stringify({ book_id: bookId, due_at: dueAt }),
      });
      await Promise.all([search(), loadMyRequests()]);
      cancelLoanRequest();
    } catch (err) {
      setError(err instanceof Error ? err.message : "Erreur");
    }
  }

  function logout() {
    clearAuth();
    router.push("/");
  }

  return (
    <div className="flex flex-1 justify-center bg-zinc-50 px-6 py-10">
      <main className="w-full max-w-4xl space-y-6">
        <div className="flex items-center justify-between">
          <h1 className="text-2xl font-semibold text-zinc-950">Recherche</h1>
          <div className="flex items-center gap-3 text-sm">
            <Link href="/me" className="underline text-zinc-900">
              Mon compte
            </Link>
            {auth?.user?.role === "admin" ? (
              <Link href="/admin" className="underline text-zinc-900">
                Admin
              </Link>
            ) : null}
            <button onClick={logout} className="underline text-zinc-600">
              Déconnexion
            </button>
          </div>
        </div>

        <div className="rounded-lg border border-zinc-200 bg-white p-4">
          <div className="flex flex-col gap-3 sm:flex-row">
            <input
              value={q}
              onChange={(e) => setQ(e.target.value)}
              placeholder="Titre, auteur ou ISBN"
              className="h-11 flex-1 rounded-md border border-zinc-300 bg-white px-3 text-sm text-zinc-900 placeholder:text-zinc-400 dark:border-zinc-300 dark:bg-white dark:text-zinc-900"
            />
            <button
              onClick={search}
              disabled={loading}
              className="inline-flex h-11 items-center justify-center rounded-md bg-zinc-900 px-5 text-sm font-medium text-white disabled:opacity-60"
            >
              {loading ? "Recherche…" : "Rechercher"}
            </button>
          </div>

          {error ? (
            <div className="mt-3 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
              {error}
            </div>
          ) : null}
        </div>

        <div className="rounded-lg border border-zinc-200 bg-white">
          <div className="border-b border-zinc-200 px-4 py-3 text-sm font-medium text-zinc-900">
            Résultats
          </div>
          <ul className="divide-y divide-zinc-200">
            {books.map((b) => (
              <li key={b.id} className="px-4 py-3">
                <div className="flex items-center justify-between gap-4">
                  <div>
                  <div className="font-medium text-zinc-950">{b.title}</div>
                  <div className="text-sm text-zinc-600">
                    {b.author} — ISBN {b.isbn}
                  </div>
                  <div className="text-xs text-zinc-500">
                    Disponible: {b.available_copies}/{b.total_copies}
                  </div>
                  </div>

                  {b.available_copies < 1 ? (
                    <span className="text-sm text-zinc-600">Déjà en emprunt</span>
                  ) : activeRequestedBookIds.has(b.id) ? (
                    <span className="text-sm text-zinc-600">Demande déjà envoyée</span>
                  ) : pendingLoanBookId === b.id ? (
                    <span className="text-sm text-zinc-600">Choisir une date</span>
                  ) : (
                    <button
                      onClick={() => startLoanRequest(b.id)}
                      className="inline-flex h-9 items-center justify-center rounded-md border border-zinc-300 bg-white px-3 text-sm font-medium text-zinc-900"
                    >
                      Demande d’emprunt
                    </button>
                  )}
                </div>

                {pendingLoanBookId === b.id ? (
                  <div className="mt-3 rounded-md border border-zinc-200 bg-zinc-50 p-3">
                    <div className="text-sm font-medium text-zinc-900">Date de retour</div>
                    <div className="mt-2 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                      <input
                        type="date"
                        value={pendingDueAt}
                        min={dueAtMin}
                        max={dueAtMax}
                        onChange={(e) => setPendingDueAt(e.target.value)}
                        className="h-11 w-full max-w-xs rounded-md border border-zinc-300 bg-white px-3 text-sm text-zinc-900 dark:border-zinc-300 dark:bg-white dark:text-zinc-900"
                      />
                      <div className="text-xs text-zinc-600">
                        Durée maximale: {maxLoanDays} jours. En cas de retard, des sanctions peuvent s’appliquer.
                      </div>
                    </div>

                    <div className="mt-3 flex flex-col gap-2 sm:flex-row">
                      <button
                        onClick={() => requestLoan(b.id, pendingDueAt)}
                        disabled={!pendingDueAt}
                        className="inline-flex h-10 items-center justify-center rounded-md bg-zinc-900 px-4 text-sm font-medium text-white disabled:opacity-60"
                      >
                        Valider la demande
                      </button>
                      <button
                        onClick={cancelLoanRequest}
                        className="inline-flex h-10 items-center justify-center rounded-md border border-zinc-300 bg-white px-4 text-sm font-medium text-zinc-900"
                      >
                        Annuler
                      </button>
                    </div>
                  </div>
                ) : null}
              </li>
            ))}
            {books.length === 0 ? (
              <li className="px-4 py-6 text-sm text-zinc-600">Aucun résultat.</li>
            ) : null}
          </ul>
        </div>
      </main>
    </div>
  );
}
