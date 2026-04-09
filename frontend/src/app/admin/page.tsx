"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import { apiFetch } from "@/lib/api";
import { clearAuth, getAuth, type AuthState } from "@/lib/auth";

type Stats = {
  books_count: number;
  users_count: number;
  loan_requests_pending_count: number;
  books_borrowed_count: number;
};

type ReportsOverview = {
  overdue_loans_count: number;
  due_soon_loans_count: number;
  fines_unpaid_total_cents: number;
  fines_paid_total_cents: number;
};

type Settings = Record<string, string>;

type Book = {
  id: number;
  title: string;
  author: string;
  isbn: string;
  total_copies: number;
  available_copies: number;
};

type User = { id: number; name: string; email: string; role: string };

type LoanRequest = {
  id: number;
  status: string;
  requested_at: string;
  due_at?: string | null;
  user: User;
  book: Book;
};

type Fine = {
  id: number;
  amount_cents: number;
  days_overdue: number;
  status: string;
  calculated_at: string;
  user: User;
  loan_request: { id: number; book: Book };
};

type HistoryEvent = {
  id: number;
  type: string;
  created_at: string;
  user: User | null;
  loan_request: { id: number; book: Book };
};

type Paginated<T> = { data: T[] };

export default function AdminPage() {
  const router = useRouter();
  const [auth, setAuthState] = useState<AuthState | null>(null);
  const [authLoaded, setAuthLoaded] = useState(false);

  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [stats, setStats] = useState<Stats | null>(null);
  const [reports, setReports] = useState<ReportsOverview | null>(null);
  const [settings, setSettings] = useState<Settings>({});
  const [savingSettings, setSavingSettings] = useState(false);

  const [books, setBooks] = useState<Book[]>([]);
  const [users, setUsers] = useState<User[]>([]);
  const [loanRequests, setLoanRequests] = useState<LoanRequest[]>([]);
  const [fines, setFines] = useState<Fine[]>([]);
  const [history, setHistory] = useState<HistoryEvent[]>([]);
  const [loanActionLoadingId, setLoanActionLoadingId] = useState<number | null>(null);

  const [newBook, setNewBook] = useState({
    title: "",
    author: "",
    isbn: "",
    total_copies: 1,
  });

  useEffect(() => {
    setAuthState(getAuth());
    setAuthLoaded(true);
  }, []);

  useEffect(() => {
    if (!authLoaded) return;

    if (!auth?.token) {
      router.replace("/login");
      return;
    }

    if (auth.user.role !== "admin") {
      router.replace("/search");
    }
  }, [authLoaded, auth?.token, auth?.user?.role, router]);

  async function refreshAll() {
    setError(null);
    try {
      const [s, b, u, lr, rep, set, fn, hist] = await Promise.all([
        apiFetch<Stats>("/admin/stats", { auth: true }),
        apiFetch<Paginated<Book>>("/books", { auth: false }),
        apiFetch<Paginated<User>>("/admin/users", { auth: true }),
        apiFetch<Paginated<LoanRequest>>("/admin/loan-requests", { auth: true }),
        apiFetch<ReportsOverview>("/admin/reports/overview", { auth: true }),
        apiFetch<Settings>("/admin/settings", { auth: true }),
        apiFetch<Paginated<Fine>>("/admin/fines?status=unpaid", { auth: true }),
        apiFetch<Paginated<HistoryEvent>>("/admin/history", { auth: true }),
      ]);

      setStats(s);
      setBooks(b.data ?? []);
      setUsers(u.data ?? []);
      setLoanRequests(lr.data ?? []);
      setReports(rep);
      setSettings(set);
      setFines(fn.data ?? []);
      setHistory(hist.data ?? []);
    } catch (err) {
      setError(err instanceof Error ? err.message : "Erreur");
    }
  }

  useEffect(() => {
    if (authLoaded && auth?.token && auth.user.role === "admin") {
      refreshAll();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [authLoaded, auth?.token]);

  async function createBook() {
    setError(null);
    try {
      await apiFetch<Book>("/admin/books", {
        method: "POST",
        auth: true,
        body: JSON.stringify(newBook),
      });
      setNewBook({ title: "", author: "", isbn: "", total_copies: 1 });
      await refreshAll();
    } catch (err) {
      setError(err instanceof Error ? err.message : "Erreur");
    }
  }

  async function deleteBook(id: number) {
    setError(null);
    try {
      await apiFetch(`/admin/books/${id}`, { method: "DELETE", auth: true });
      await refreshAll();
    } catch (err) {
      setError(err instanceof Error ? err.message : "Erreur");
    }
  }

  async function updateLoanStatus(id: number, status: string) {
    setError(null);
    setLoanActionLoadingId(id);
    try {
      await apiFetch(`/admin/loan-requests/${id}`, {
        method: "PUT",
        auth: true,
        body: JSON.stringify({ status }),
      });
      await refreshAll();
    } catch (err) {
      setError(err instanceof Error ? err.message : "Erreur");
    } finally {
      setLoanActionLoadingId(null);
    }
  }

  async function saveSettings() {
    setError(null);
    setSuccess(null);
    setSavingSettings(true);
    try {
      await apiFetch<Settings>("/admin/settings", {
        method: "PUT",
        auth: true,
        body: JSON.stringify({
          "loan.max_days": Number(settings["loan.max_days"]),
          "loan.grace_days": Number(settings["loan.grace_days"]),
          "loan.block_on_overdue": settings["loan.block_on_overdue"] === "1" || settings["loan.block_on_overdue"] === "true",
          "loan.block_on_unpaid_fines": settings["loan.block_on_unpaid_fines"] === "1" || settings["loan.block_on_unpaid_fines"] === "true",
          "loan.max_unpaid_fines_cents": Number(settings["loan.max_unpaid_fines_cents"]),
          "fine.per_day_cents": Number(settings["fine.per_day_cents"]),
          "fine.cap_cents": Number(settings["fine.cap_cents"]),
          "reminder.due_soon_days_before": Number(settings["reminder.due_soon_days_before"]),
          "reminder.overdue_frequency_days": Number(settings["reminder.overdue_frequency_days"]),
        }),
      });
      await refreshAll();
      setSuccess("Paramètres enregistrés.");
      document.getElementById("admin-settings")?.scrollIntoView({ block: "start" });
      window.setTimeout(() => setSuccess(null), 5000);
    } catch (err) {
      setError(err instanceof Error ? err.message : "Erreur");
    } finally {
      setSavingSettings(false);
    }
  }

  async function updateFineStatus(id: number, status: string) {
    setError(null);
    try {
      await apiFetch(`/admin/fines/${id}`, {
        method: "PUT",
        auth: true,
        body: JSON.stringify({ status }),
      });
      await refreshAll();
    } catch (err) {
      setError(err instanceof Error ? err.message : "Erreur");
    }
  }

  function formatCents(cents: number) {
    const eur = (cents / 100).toFixed(2);
    return `${eur} €`;
  }

  function formatDateOnly(value: string | null | undefined) {
    if (!value) return "—";
    const tIndex = value.indexOf("T");
    if (tIndex > 0) return value.slice(0, tIndex);
    const spaceIndex = value.indexOf(" ");
    if (spaceIndex > 0) return value.slice(0, spaceIndex);
    return value;
  }

  function logout() {
    clearAuth();
    router.push("/");
  }

  return (
    <div className="flex flex-1 justify-center bg-zinc-50 px-6 py-10">
      <main className="w-full max-w-5xl space-y-6">
        <div className="flex items-center justify-between">
          <h1 className="text-2xl font-semibold text-zinc-950">Administration</h1>
          <div className="flex items-center gap-3 text-sm">
            <Link href="/search" className="underline text-zinc-900">
              Recherche
            </Link>
            <Link href="/me" className="underline text-zinc-900">
              Mon compte
            </Link>
            <button onClick={logout} className="underline text-zinc-600">
              Déconnexion
            </button>
          </div>
        </div>

        {error ? (
          <div className="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
            {error}
          </div>
        ) : null}

        {success ? (
          <div className="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
            {success}
          </div>
        ) : null}

        <section className="rounded-lg border border-zinc-200 bg-white p-4">
          <div className="text-sm font-medium text-zinc-900">Statistiques</div>
          <div className="mt-3 grid gap-3 sm:grid-cols-4">
            <StatItem label="Livres" value={stats?.books_count} />
            <StatItem label="Utilisateurs" value={stats?.users_count} />
            <StatItem label="Demandes en cours" value={stats?.loan_requests_pending_count} />
            <StatItem label="Emprunts" value={stats?.books_borrowed_count} />
          </div>
        </section>

        <section className="rounded-lg border border-zinc-200 bg-white p-4">
          <div className="text-sm font-medium text-zinc-900">Reporting avancé</div>
          <div className="mt-3 grid gap-3 sm:grid-cols-4">
            <StatItem label="Retards" value={reports?.overdue_loans_count} />
            <StatItem label="Bientôt dus" value={reports?.due_soon_loans_count} />
            <StatItem label="Amendes impayées" value={reports ? formatCents(reports.fines_unpaid_total_cents) : undefined} />
            <StatItem label="Amendes payées" value={reports ? formatCents(reports.fines_paid_total_cents) : undefined} />
          </div>
        </section>

        <section id="admin-settings" className="rounded-lg border border-zinc-200 bg-white">
          <div className="border-b border-zinc-200 px-4 py-3 text-sm font-medium text-zinc-900">
            Paramètres (règles)
          </div>
          <div className="p-4">
            <div className="grid gap-3 sm:grid-cols-4">
              <div>
                <div className="text-xs text-zinc-600">Durée max (jours)</div>
                <input
                  value={settings["loan.max_days"] ?? ""}
                  onChange={(e) => setSettings((s) => ({ ...s, "loan.max_days": e.target.value }))}
                  type="number"
                  min={1}
                  className="mt-1 h-10 w-full rounded-md border border-zinc-300 bg-white px-3 text-sm text-zinc-900 dark:border-zinc-300 dark:bg-white dark:text-zinc-900"
                />
              </div>
              <div>
                <div className="text-xs text-zinc-600">Grâce (jours)</div>
                <input
                  value={settings["loan.grace_days"] ?? ""}
                  onChange={(e) => setSettings((s) => ({ ...s, "loan.grace_days": e.target.value }))}
                  type="number"
                  min={0}
                  className="mt-1 h-10 w-full rounded-md border border-zinc-300 bg-white px-3 text-sm text-zinc-900 dark:border-zinc-300 dark:bg-white dark:text-zinc-900"
                />
              </div>
              <div>
                <div className="text-xs text-zinc-600">Amende / jour (cents)</div>
                <input
                  value={settings["fine.per_day_cents"] ?? ""}
                  onChange={(e) => setSettings((s) => ({ ...s, "fine.per_day_cents": e.target.value }))}
                  type="number"
                  min={0}
                  className="mt-1 h-10 w-full rounded-md border border-zinc-300 bg-white px-3 text-sm text-zinc-900 dark:border-zinc-300 dark:bg-white dark:text-zinc-900"
                />
              </div>
              <div>
                <div className="text-xs text-zinc-600">Plafond (cents)</div>
                <input
                  value={settings["fine.cap_cents"] ?? ""}
                  onChange={(e) => setSettings((s) => ({ ...s, "fine.cap_cents": e.target.value }))}
                  type="number"
                  min={0}
                  className="mt-1 h-10 w-full rounded-md border border-zinc-300 bg-white px-3 text-sm text-zinc-900 dark:border-zinc-300 dark:bg-white dark:text-zinc-900"
                />
              </div>
            </div>
            <div className="mt-4 flex items-center justify-end">
              {success ? (
                <div className="mr-auto rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                  {success}
                </div>
              ) : null}
              <button
                onClick={saveSettings}
                disabled={savingSettings}
                className="inline-flex h-10 items-center justify-center rounded-md bg-zinc-900 px-4 text-sm font-medium text-white"
              >
                {savingSettings ? "Enregistrement…" : "Enregistrer"}
              </button>
            </div>
          </div>
        </section>

        <section className="rounded-lg border border-zinc-200 bg-white">
          <div className="border-b border-zinc-200 px-4 py-3 text-sm font-medium text-zinc-900">
            Livres
          </div>
          <div className="p-4">
            <div className="grid gap-3 sm:grid-cols-4">
              <input
                value={newBook.title}
                onChange={(e) => setNewBook((s) => ({ ...s, title: e.target.value }))}
                placeholder="Titre"
                className="h-10 rounded-md border border-zinc-300 bg-white px-3 text-sm text-zinc-900 placeholder:text-zinc-400 dark:border-zinc-300 dark:bg-white dark:text-zinc-900"
              />
              <input
                value={newBook.author}
                onChange={(e) => setNewBook((s) => ({ ...s, author: e.target.value }))}
                placeholder="Auteur"
                className="h-10 rounded-md border border-zinc-300 bg-white px-3 text-sm text-zinc-900 placeholder:text-zinc-400 dark:border-zinc-300 dark:bg-white dark:text-zinc-900"
              />
              <input
                value={newBook.isbn}
                onChange={(e) => setNewBook((s) => ({ ...s, isbn: e.target.value }))}
                placeholder="ISBN"
                className="h-10 rounded-md border border-zinc-300 bg-white px-3 text-sm text-zinc-900 placeholder:text-zinc-400 dark:border-zinc-300 dark:bg-white dark:text-zinc-900"
              />
              <div className="flex gap-2">
                <input
                  value={newBook.total_copies}
                  onChange={(e) =>
                    setNewBook((s) => ({ ...s, total_copies: Number(e.target.value) }))
                  }
                  type="number"
                  min={0}
                  className="h-10 w-24 rounded-md border border-zinc-300 bg-white px-3 text-sm text-zinc-900 placeholder:text-zinc-400 dark:border-zinc-300 dark:bg-white dark:text-zinc-900"
                />
                <button
                  onClick={createBook}
                  className="inline-flex h-10 flex-1 items-center justify-center rounded-md bg-zinc-900 px-4 text-sm font-medium text-white"
                >
                  Ajouter
                </button>
              </div>
            </div>
          </div>
          <div className="overflow-x-auto">
            <table className="min-w-full text-sm">
              <thead className="bg-zinc-50 text-zinc-600">
                <tr>
                  <th className="px-4 py-2 text-left font-medium">Titre</th>
                  <th className="px-4 py-2 text-left font-medium">Auteur</th>
                  <th className="px-4 py-2 text-left font-medium">ISBN</th>
                  <th className="px-4 py-2 text-left font-medium">Dispo</th>
                  <th className="px-4 py-2 text-left font-medium"></th>
                </tr>
              </thead>
              <tbody className="divide-y divide-zinc-200">
                {books.map((b) => (
                  <tr key={b.id}>
                    <td className="px-4 py-2">{b.title}</td>
                    <td className="px-4 py-2">{b.author}</td>
                    <td className="px-4 py-2">{b.isbn}</td>
                    <td className="px-4 py-2">
                      {b.available_copies}/{b.total_copies}
                    </td>
                    <td className="px-4 py-2">
                      <button
                        onClick={() => deleteBook(b.id)}
                        className="underline text-zinc-700"
                      >
                        Supprimer
                      </button>
                    </td>
                  </tr>
                ))}
                {books.length === 0 ? (
                  <tr>
                    <td className="px-4 py-4 text-zinc-600" colSpan={5}>
                      Aucun livre.
                    </td>
                  </tr>
                ) : null}
              </tbody>
            </table>
          </div>
        </section>

        <section className="rounded-lg border border-zinc-200 bg-white">
          <div className="border-b border-zinc-200 px-4 py-3 text-sm font-medium text-zinc-900">
            Amendes (impayées)
          </div>
          <div className="overflow-x-auto">
            <table className="min-w-full text-sm">
              <thead className="bg-zinc-50 text-zinc-600">
                <tr>
                  <th className="px-4 py-2 text-left font-medium">Usager</th>
                  <th className="px-4 py-2 text-left font-medium">Livre</th>
                  <th className="px-4 py-2 text-left font-medium">Montant</th>
                  <th className="px-4 py-2 text-left font-medium">Retard</th>
                  <th className="px-4 py-2 text-left font-medium"></th>
                </tr>
              </thead>
              <tbody className="divide-y divide-zinc-200">
                {fines.map((f) => (
                  <tr key={f.id}>
                    <td className="px-4 py-2">{f.user.email}</td>
                    <td className="px-4 py-2">{f.loan_request.book.title}</td>
                    <td className="px-4 py-2">{formatCents(f.amount_cents)}</td>
                    <td className="px-4 py-2">{f.days_overdue} j</td>
                    <td className="px-4 py-2">
                      <div className="flex gap-3">
                        <button
                          onClick={() => updateFineStatus(f.id, "paid")}
                          className="underline text-zinc-700"
                        >
                          Marquer payé
                        </button>
                        <button
                          onClick={() => updateFineStatus(f.id, "waived")}
                          className="underline text-zinc-700"
                        >
                          Annuler
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
                {fines.length === 0 ? (
                  <tr>
                    <td className="px-4 py-4 text-zinc-600" colSpan={5}>
                      Aucune amende impayée.
                    </td>
                  </tr>
                ) : null}
              </tbody>
            </table>
          </div>
        </section>

        <section className="rounded-lg border border-zinc-200 bg-white">
          <div className="border-b border-zinc-200 px-4 py-3 text-sm font-medium text-zinc-900">
            Historique (global)
          </div>
          <ul className="divide-y divide-zinc-200">
            {history.map((h) => (
              <li key={h.id} className="px-4 py-3">
                <div className="font-medium text-zinc-950">{h.loan_request.book.title}</div>
                <div className="text-sm text-zinc-600">
                  {h.type}
                  {h.user ? ` — ${h.user.email}` : ""}
                </div>
                <div className="text-xs text-zinc-500">{h.created_at}</div>
              </li>
            ))}
            {history.length === 0 ? (
              <li className="px-4 py-6 text-sm text-zinc-600">Aucun événement.</li>
            ) : null}
          </ul>
        </section>

        <section className="rounded-lg border border-zinc-200 bg-white">
          <div className="border-b border-zinc-200 px-4 py-3 text-sm font-medium text-zinc-900">
            Utilisateurs
          </div>
          <div className="overflow-x-auto">
            <table className="min-w-full text-sm">
              <thead className="bg-zinc-50 text-zinc-600">
                <tr>
                  <th className="px-4 py-2 text-left font-medium">Nom</th>
                  <th className="px-4 py-2 text-left font-medium">Email</th>
                  <th className="px-4 py-2 text-left font-medium">Rôle</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-zinc-200">
                {users.map((u) => (
                  <tr key={u.id}>
                    <td className="px-4 py-2">{u.name}</td>
                    <td className="px-4 py-2">{u.email}</td>
                    <td className="px-4 py-2">{u.role}</td>
                  </tr>
                ))}
                {users.length === 0 ? (
                  <tr>
                    <td className="px-4 py-4 text-zinc-600" colSpan={3}>
                      Aucun utilisateur.
                    </td>
                  </tr>
                ) : null}
              </tbody>
            </table>
          </div>
        </section>

        <section className="rounded-lg border border-zinc-200 bg-white">
          <div className="border-b border-zinc-200 px-4 py-3 text-sm font-medium text-zinc-900">
            Demandes d’emprunt
          </div>
          <div className="overflow-x-auto">
            <table className="min-w-full text-sm">
              <thead className="bg-zinc-50 text-zinc-600">
                <tr>
                  <th className="px-4 py-2 text-left font-medium">Usager</th>
                  <th className="px-4 py-2 text-left font-medium">Livre</th>
                  <th className="px-4 py-2 text-left font-medium">Date de retour</th>
                  <th className="px-4 py-2 text-left font-medium">Statut</th>
                  <th className="px-4 py-2 text-left font-medium"></th>
                </tr>
              </thead>
              <tbody className="divide-y divide-zinc-200">
                {loanRequests.map((r) => (
                  <tr key={r.id}>
                    <td className="px-4 py-2">{r.user.email}</td>
                    <td className="px-4 py-2">{r.book.title}</td>
                    <td className="px-4 py-2">{formatDateOnly(r.due_at)}</td>
                    <td className="px-4 py-2">{r.status}</td>
                    <td className="px-4 py-2">
                      {r.status === "pending" ? (
                        <div className="flex gap-3">
                          <button
                            onClick={() => updateLoanStatus(r.id, "approved")}
                            disabled={loanActionLoadingId === r.id}
                            className="underline text-zinc-700 disabled:opacity-60"
                          >
                            Approuver
                          </button>
                          <button
                            onClick={() => updateLoanStatus(r.id, "rejected")}
                            disabled={loanActionLoadingId === r.id}
                            className="underline text-zinc-700 disabled:opacity-60"
                          >
                            Rejeter
                          </button>
                        </div>
                      ) : r.status === "return_requested" ? (
                        <button
                          onClick={() => updateLoanStatus(r.id, "returned")}
                          disabled={loanActionLoadingId === r.id}
                          className="underline text-zinc-700 disabled:opacity-60"
                        >
                          Confirmer retour
                        </button>
                      ) : (
                        <span className="text-zinc-500">—</span>
                      )}
                    </td>
                  </tr>
                ))}
                {loanRequests.length === 0 ? (
                  <tr>
                    <td className="px-4 py-4 text-zinc-600" colSpan={5}>
                      Aucune demande.
                    </td>
                  </tr>
                ) : null}
              </tbody>
            </table>
          </div>
        </section>

        <div className="text-sm text-zinc-600">
          SwaggerUI: <a className="underline" href="http://localhost:8000/api/documentation" target="_blank" rel="noreferrer">http://localhost:8000/api/documentation</a>
        </div>
      </main>
    </div>
  );
}

function StatItem({ label, value }: { label: string; value: string | number | undefined }) {
  return (
    <div className="rounded-md border border-zinc-200 bg-white px-3 py-2">
      <div className="text-xs text-zinc-600">{label}</div>
      <div className="mt-1 text-lg font-semibold text-zinc-950">{value ?? "—"}</div>
    </div>
  );
}
