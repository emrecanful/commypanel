import { useSyncExternalStore } from 'react';
import {
    readPreference,
    savePreference,
    subscribeToPreference,
} from '@/lib/theme';
import type { ThemePreference } from '@/lib/theme';

export function useTheme(): [
    ThemePreference,
    (value: ThemePreference) => void,
] {
    const preference = useSyncExternalStore(
        subscribeToPreference,
        readPreference,
        () => 'system' as const,
    );

    return [preference, savePreference];
}
