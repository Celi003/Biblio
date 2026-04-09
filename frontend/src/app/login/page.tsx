"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { apiFetch } from "@/lib/api";
import { setAuth } from "@/lib/auth";

type LoginResponse = {
  access_token: string;
  user: { id: number; name: string; email: string; role: string };
};

export default function LoginPage() {
  const router = useRouter();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setLoading(true);

    try {
      const data = await apiFetch<LoginResponse>("/auth/login", {
        method: "POST",
        body: JSON.stringify({ email, password }),
      });

      setAuth({ token: data.access_token, user: data.user });

      if (data.user.role === "admin") router.push("/admin");
      else router.push("/search");
    } catch (err) {
      setError(err instanceof Error ? err.message : "Erreur");
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="flex flex-1 items-center justify-center bg-zinc-50 px-6 py-16">
      <main className="w-full max-w-md rounded-lg border border-zinc-200 bg-white p-8">
        <h1 className="text-2xl font-semibold text-zinc-950">Connexion</h1>
        <p className="mt-2 text-sm text-zinc-600">
          Usager et administrateur utilisent le même formulaire.
        </p>

        <form onSubmit={onSubmit} className="mt-6 space-y-4">
          <label className="block">
            <span className="text-sm font-medium text-zinc-900">Email</span>
            <input
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              type="email"
              required
              className="mt-1 w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 placeholder:text-zinc-400 dark:border-zinc-300 dark:bg-white dark:text-zinc-900"
            />
          </label>

          <label className="block">
            <span className="text-sm font-medium text-zinc-900">Mot de passe</span>
            <input
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              type="password"
              required
              className="mt-1 w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 placeholder:text-zinc-400 dark:border-zinc-300 dark:bg-white dark:text-zinc-900"
            />
          </label>

          {error ? (
            <div className="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
              {error}
            </div>
          ) : null}

          <button
            disabled={loading}
            className="inline-flex h-11 w-full items-center justify-center rounded-md bg-zinc-900 px-5 text-sm font-medium text-white disabled:opacity-60"
          >
            {loading ? "Connexion…" : "Se connecter"}
          </button>
        </form>

        <div className="mt-6 flex items-center justify-between text-sm">
          <Link href="/" className="text-zinc-600 underline">
            Retour
          </Link>
          <Link href="/signup" className="text-zinc-900 underline">
            Créer un compte
          </Link>
        </div>
      </main>
    </div>
  );
}
