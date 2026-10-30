/** POST without a page visit and read the JSON answer (Laravel's XSRF cookie is sent as a header). */
export async function postJson<T>(
    url: string,
    body: Record<string, unknown> = {},
): Promise<T> {
    const token = decodeURIComponent(
        document.cookie
            .split('; ')
            .find((row) => row.startsWith('XSRF-TOKEN='))
            ?.slice('XSRF-TOKEN='.length) ?? '',
    );

    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-XSRF-TOKEN': token,
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(body),
    });

    if (!response.ok) {
        throw new Error(`Request failed (${response.status})`);
    }

    return (await response.json()) as T;
}
