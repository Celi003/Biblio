"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import { apiFetch } from "@/lib/api";
import { clearAuth, getAuth, type AuthState } from "@/lib/auth";

type LoanRequest = {
  id: number;
  status: string;
  requested_at: string;
  due_at: string | null;
  book: { id: number; title: string; author: string; isbn: string };
};

type Paginated<T> = {
  data: T[];
};

type Fine = {
  id: number;
  amount_cents: number;
  days_overdue: number;
  status: string;
  calculated_at: string;
  loan_request: { id: number; book: { title: string } };
};

type HistoryEvent = {
  id: number;
  type: string;
  created_at: string;
  loan_request: { id: number; book: { title: string } };
};

export default function MePage() {
  const router = useRouter();
  const [auth, setAuthState] = useState<AuthState | null>(null);
  const [authLoaded, setAuthLoaded] = useState(false);

  const [requests, setRequests] = useState<LoanRequest[]>([]);
  const [fines, setFines] = useState<Fine[]>([]);
  const [events, setEvents] = useState<HistoryEvent[]>([]);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    void (async () => {
      setAuthState(getAuth());
      setAuthLoaded(true);
    })();
  }, []);

  useEffect(() => {
    if (!authLoaded) return;
    if (!auth?.token) router.replace("/login");
  }, [authLoaded, auth?.token, router]);

  useEffect(() => {
    if (!authLoaded || !auth?.token) return;

    void (async () => {
      try {
        const [lr, f, h] = await Promise.all([
          apiFetch<Paginated<LoanRequest>>("/loan-requests", { auth: true }),
          apiFetch<Paginated<Fine>>("/fines", { auth: true }),
          apiFetch<Paginated<HistoryEvent>>("/history", { auth: true }),
        ]);

        setRequests(lr.data ?? []);
        setFines(f.data ?? []);
        setEvents(h.data ?? []);
      } catch (err) {
        setError(err instanceof Error ? err.message : "Erreur");
      }
    })();
  }, [authLoaded, auth?.token]);

  async function requestReturn(id: number) {
    setError(null);
    try {
      await apiFetch(`/loan-requests/${id}/request-return`, {
        method: "POST",
        auth: true,
      });
      const [lr, f, h] = await Promise.all([
        apiFetch<Paginated<LoanRequest>>("/loan-requests", { auth: true }),
        apiFetch<Paginated<Fine>>("/fines", { auth: true }),
        apiFetch<Paginated<HistoryEvent>>("/history", { auth: true }),
      ]);
      setRequests(lr.data ?? []);
      setFines(f.data ?? []);
      setEvents(h.data ?? []);
    } catch (err) {
      setError(err instanceof Error ? err.message : "Erreur");
    }
  }

  function formatCents(cents: number) {
    const eur = (cents / 100).toFixed(2);
    return `${eur} €`;
  }

  function logout() {
    clearAuth();
    router.push("/");
  }

  return (
    <div className="flex flex-1 justify-center bg-zinc-50 px-6 py-10">
      <main className="w-full max-w-3xl space-y-6">
        <div className="flex items-center justify-between">
          <h1 className="text-2xl font-semibold text-zinc-950">Mon compte</h1>
          <div className="flex items-center gap-3 text-sm">
            <Link href="/search" className="underline text-zinc-900">
              Recherche
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
          <div className="text-sm text-zinc-600">Informations</div>
          <div className="mt-2 text-sm">
            <div>
              <span className="font-medium">Nom:</span> {auth?.user?.name}
            </div>
            <div>
              <span className="font-medium">Email:</span> {auth?.user?.email}
            </div>
            <div>
              <span className="font-medium">Rôle:</span> {auth?.user?.role}
            </div>
          </div>
        </div>

        <div className="rounded-lg border border-zinc-200 bg-white">
          <div className="border-b border-zinc-200 px-4 py-3 text-sm font-medium text-zinc-900">
            Mes demandes d’emprunt
          </div>
          {error ? (
            <div className="px-4 py-3 text-sm text-red-700">{error}</div>
          ) : null}
          <ul className="divide-y divide-zinc-200">
            {requests.map((r) => (
              <li key={r.id} className="px-4 py-3">
                <div className="flex items-start justify-between gap-3">
                  <div>
                    <div className="font-medium text-zinc-950">{r.book.title}</div>
                    <div className="text-sm text-zinc-600">
                      {r.book.author} — {r.status}
                    </div>
                    <div className="text-xs text-zinc-500">
                      Demandé: {r.requested_at}
                      {r.due_at ? ` — Retour prévu: ${r.due_at}` : ""}
                    </div>
                  </div>

                  {r.status === "approved" ? (
                    <button
                      onClick={() => requestReturn(r.id)}
                      className="inline-flex h-9 items-center justify-center rounded-md border border-zinc-300 bg-white px-3 text-sm font-medium text-zinc-900"
                    >
                      Demander le retour
                    </button>
                  ) : r.status === "return_requested" ? (
                    <span className="text-sm text-zinc-600">Retour demandé</span>
                  ) : null}
                </div>
              </li>
            ))}
            {requests.length === 0 ? (
              <li className="px-4 py-6 text-sm text-zinc-600">
                Aucune demande.
              </li>
            ) : null}
          </ul>
        </div>

        <div className="rounded-lg border border-zinc-200 bg-white">
          <div className="border-b border-zinc-200 px-4 py-3 text-sm font-medium text-zinc-900">
            Mes amendes
          </div>
          <ul className="divide-y divide-zinc-200">
            {fines.map((f) => (
              <li key={f.id} className="px-4 py-3">
                <div className="font-medium text-zinc-950">{f.loan_request.book.title}</div>
                <div className="text-sm text-zinc-600">
                  {formatCents(f.amount_cents)} — {f.status} — {f.days_overdue} jour(s) de retard
                </div>
                <div className="text-xs text-zinc-500">Calculée: {f.calculated_at}</div>
              </li>
            ))}
            {fines.length === 0 ? (
              <li className="px-4 py-6 text-sm text-zinc-600">Aucune amende.</li>
            ) : null}
          </ul>
        </div>

        <div className="rounded-lg border border-zinc-200 bg-white">
          <div className="border-b border-zinc-200 px-4 py-3 text-sm font-medium text-zinc-900">
            Historique récent
          </div>
          <ul className="divide-y divide-zinc-200">
            {events.map((e) => (
              <li key={e.id} className="px-4 py-3">
                <div className="font-medium text-zinc-950">{e.loan_request.book.title}</div>
                <div className="text-sm text-zinc-600">{e.type}</div>
                <div className="text-xs text-zinc-500">{e.created_at}</div>
              </li>
            ))}
            {events.length === 0 ? (
              <li className="px-4 py-6 text-sm text-zinc-600">Aucun événement.</li>
            ) : null}
          </ul>
        </div>
      </main>
    </div>
  );
}
