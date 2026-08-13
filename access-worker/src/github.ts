const REPO_OWNER = 'sandruhill';
const REPO_NAME = 'mclair';

function githubHeaders(token: string): HeadersInit {
  return {
    Authorization: `Bearer ${token}`,
    Accept: 'application/vnd.github+json',
    'X-GitHub-Api-Version': '2022-11-28',
    'User-Agent': 'mclair-access-worker',
  };
}

export async function githubUserExists(token: string, username: string): Promise<boolean> {
  const res = await fetch(`https://api.github.com/users/${encodeURIComponent(username)}`, {
    headers: githubHeaders(token),
    signal: AbortSignal.timeout(10_000),
  });
  return res.status === 200;
}

export async function isAlreadyCollaborator(token: string, username: string): Promise<boolean> {
  const res = await fetch(
    `https://api.github.com/repos/${REPO_OWNER}/${REPO_NAME}/collaborators/${encodeURIComponent(username)}`,
    { headers: githubHeaders(token), signal: AbortSignal.timeout(10_000) }
  );
  return res.status === 204;
}

export async function addCollaborator(token: string, username: string): Promise<boolean> {
  const res = await fetch(
    `https://api.github.com/repos/${REPO_OWNER}/${REPO_NAME}/collaborators/${encodeURIComponent(username)}`,
    {
      method: 'PUT',
      headers: { ...githubHeaders(token), 'Content-Type': 'application/json' },
      body: JSON.stringify({ permission: 'push' }),
      signal: AbortSignal.timeout(10_000),
    }
  );
  return res.status === 201 || res.status === 204;
}
