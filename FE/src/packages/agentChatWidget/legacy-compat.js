export class LoginTool {
  async execute() {
    throw new Error('LoginTool legacy facade is not wired directly. Use the registered GR45 runtime tools.');
  }
}

export class RegisterTool {
  async execute() {
    throw new Error('RegisterTool legacy facade is not wired directly. Use the registered GR45 runtime tools.');
  }
}

export function persistLogin(payload = {}) {
  if (typeof localStorage === 'undefined') return;
  const token = String(payload.token || payload.access_token || '').trim();
  if (token) {
    localStorage.setItem('auth.client.token', token);
  }
  if (payload.user) {
    localStorage.setItem('auth.client.user', JSON.stringify(payload.user));
  }
}

export function clearLogin() {
  if (typeof localStorage === 'undefined') return;
  localStorage.removeItem('auth.client.token');
  localStorage.removeItem('auth.client.user');
}

export function buildContextSuggestions() {
  return [];
}
