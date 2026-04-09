export type AuthUser = {
  id: number;
  name: string;
  email: string;
  role: "user" | "admin" | string;
};

export type AuthState = {
  token: string;
  user: AuthUser;
};

const STORAGE_KEY = "biblio_auth";

export function getAuth(): AuthState | null {
  if (typeof window === "undefined") return null;
  const raw = window.localStorage.getItem(STORAGE_KEY);
  if (!raw) return null;

  try {
    return JSON.parse(raw) as AuthState;
  } catch {
    return null;
  }
}

export function setAuth(state: AuthState) {
  window.localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
}

export function clearAuth() {
  window.localStorage.removeItem(STORAGE_KEY);
}
