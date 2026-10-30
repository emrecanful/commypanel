import { Check, Monitor, Moon, Sun, SunDim } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { Dropdown, DropdownItem } from '@/components/ui/dropdown';
import { useTheme } from '@/hooks/use-theme';
import { THEME_OPTIONS } from '@/lib/theme';
import type { ThemePreference } from '@/lib/theme';

export const THEME_ICONS: Record<ThemePreference, LucideIcon> = {
    system: Monitor,
    light: Sun,
    dim: SunDim,
    dark: Moon,
};

export function ThemeMenu() {
    const [preference, setPreference] = useTheme();
    const Icon = THEME_ICONS[preference];

    return (
        <Dropdown label="Change theme" trigger={<Icon className="size-5" />}>
            {(close) =>
                THEME_OPTIONS.map((option) => {
                    const OptionIcon = THEME_ICONS[option.value];

                    return (
                        <DropdownItem
                            key={option.value}
                            radio
                            selected={preference === option.value}
                            onSelect={() => {
                                setPreference(option.value);
                                close();
                            }}
                        >
                            <OptionIcon className="size-4" />
                            <span className="flex-1">{option.label}</span>
                            {preference === option.value && (
                                <Check className="size-4" />
                            )}
                        </DropdownItem>
                    );
                })
            }
        </Dropdown>
    );
}
