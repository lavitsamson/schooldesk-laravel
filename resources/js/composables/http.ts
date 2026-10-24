export class HttpError extends Error {
    constructor(
        public readonly status: number,
        public readonly body: unknown,
    ) {
        super(`Request failed with status ${status}`);
    }
}

/**
 * Laravel keeps the CSRF token in the (encrypted) XSRF-TOKEN cookie and accepts it back in
 * the X-XSRF-TOKEN header, as axios would send it.
 */
function xsrfToken(): string {
    const cookie = document.cookie
        .split('; ')
        .find((c) => c.startsWith('XSRF-TOKEN='));

    return cookie ? decodeURIComponent(cookie.split('=')[1] ?? '') : '';
}

/**
 * A JSON request outside Inertia, for autosave. Throws HttpError for non-2xx responses,
 * with the parsed body (e.g. Laravel's 422 validation errors).
 */
export async function sendJson<T>(
    method: 'PUT' | 'POST',
    url: string,
    body: unknown,
): Promise<T> {
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        // Lets a last autosave finish when the page is left.
        keepalive: true,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        body: JSON.stringify(body),
    });

    const data: unknown = await response.json().catch(() => null);

    if (!response.ok) {
        throw new HttpError(response.status, data);
    }

    return data as T;
}
