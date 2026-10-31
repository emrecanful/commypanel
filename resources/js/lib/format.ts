export function formatDate(iso: string | null): string {
    return iso ? new Date(iso).toLocaleString() : '-';
}

/** "42s", "3m 05s". */
export function formatDuration(seconds: number | null): string {
    if (seconds === null) return '-';
    if (seconds < 60) return `${seconds}s`;

    return `${Math.floor(seconds / 60)}m ${String(seconds % 60).padStart(2, '0')}s`;
}

/** "just now", "5 minutes ago", "3 days ago"; falls back to the date after a month. */
export function timeAgo(iso: string | null): string {
    if (!iso) return '-';

    const seconds = Math.round((Date.now() - new Date(iso).getTime()) / 1000);
    if (seconds < 45) return 'just now';

    const units: [number, string][] = [
        [60, 'minute'],
        [3600, 'hour'],
        [86400, 'day'],
    ];
    const [size, name] =
        seconds < 3600 ? units[0] : seconds < 86400 ? units[1] : units[2];
    const count = Math.floor(seconds / size);

    return seconds >= 30 * 86400
        ? new Date(iso).toLocaleDateString()
        : `${count} ${name}${count === 1 ? '' : 's'} ago`;
}

/** "1.4 GB", "320 MB". */
export function formatBytes(bytes: number): string {
    if (bytes <= 0) return '0 B';

    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    const index = Math.min(
        Math.floor(Math.log(bytes) / Math.log(1024)),
        units.length - 1,
    );
    const value = bytes / 1024 ** index;

    return `${value >= 10 || index === 0 ? Math.round(value) : value.toFixed(1)} ${units[index]}`;
}

/** "3 days 4 hours", "12 minutes". */
export function formatUptime(seconds: number): string {
    const days = Math.floor(seconds / 86400);
    const hours = Math.floor((seconds % 86400) / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const part = (n: number, word: string) =>
        `${n} ${word}${n === 1 ? '' : 's'}`;

    if (days > 0) return `${part(days, 'day')} ${part(hours, 'hour')}`;
    if (hours > 0) return `${part(hours, 'hour')} ${part(minutes, 'minute')}`;

    return part(minutes, 'minute');
}
