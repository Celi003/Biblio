import { getAuth } from "@/lib/auth";

export const API_BASE =
  process.env.NEXT_PUBLIC_API_BASE_URL ?? "http://localhost:8000/api";

export async function apiFetch<T>(
  path: string,
  init: RequestInit & { auth?: boolean } = { auth: false }
): Promise<T> {
  const url = path.startsWith("http") ? path : `${API_BASE}${path}`;

  const method = (init.method ?? "GET").toUpperCase();
  const cache: RequestCache | undefined =
    init.cache ?? (method === "GET" ? "no-store" : undefined);

  const headers = new Headers(init.headers);
  headers.set("Accept", "application/json");

  const wantsAuth = init.auth ?? false;
  if (wantsAuth) {
    const auth = getAuth();
    if (auth?.token) headers.set("Authorization", `Bearer ${auth.token}`);
  }

  if (init.body && !headers.has("Content-Type")) {
    headers.set("Content-Type", "application/json");
  }

  const res = await fetch(url, { ...init, headers, cache });

  const text = await res.text();
  const data = text ? JSON.parse(text) : null;

  if (!res.ok) {
    const message =
      (data && (data.message as string)) || `HTTP ${res.status} ${res.statusText}`;
    throw new Error(message);
  }

  return data as T;
}
