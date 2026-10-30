import { useRef } from 'react';
import type { KeyboardEvent, ReactNode } from 'react';
import { cn } from '@/lib/utils';

export type TabItem<T extends string> = {
    id: T;
    label: string;
    icon?: ReactNode;
    /** A small count or marker next to the label. */
    badge?: ReactNode;
};

type Props<T extends string> = {
    tabs: TabItem<T>[];
    active: T;
    onChange: (id: T) => void;
    label: string;
};

/** A tab list with the arrow-key behaviour of the ARIA tabs pattern. Panels are rendered by the caller with `tabPanelProps`. */
export function Tabs<T extends string>({
    tabs,
    active,
    onChange,
    label,
}: Props<T>) {
    const refs = useRef<Record<string, HTMLButtonElement | null>>({});

    const onKeyDown = (event: KeyboardEvent, index: number) => {
        const last = tabs.length - 1;
        const next =
            event.key === 'ArrowRight'
                ? (index + 1) % tabs.length
                : event.key === 'ArrowLeft'
                  ? (index + last) % tabs.length
                  : event.key === 'Home'
                    ? 0
                    : event.key === 'End'
                      ? last
                      : null;

        if (next === null) return;

        event.preventDefault();
        onChange(tabs[next].id);
        refs.current[tabs[next].id]?.focus();
    };

    return (
        <div
            role="tablist"
            aria-label={label}
            className="flex gap-1 overflow-x-auto border-b border-base-300"
        >
            {tabs.map((tab, index) => (
                <button
                    key={tab.id}
                    ref={(node) => {
                        refs.current[tab.id] = node;
                    }}
                    type="button"
                    role="tab"
                    id={`tab-${tab.id}`}
                    aria-selected={tab.id === active}
                    aria-controls={`panel-${tab.id}`}
                    tabIndex={tab.id === active ? 0 : -1}
                    onClick={() => onChange(tab.id)}
                    onKeyDown={(event) => onKeyDown(event, index)}
                    className={cn(
                        '-mb-px flex shrink-0 items-center gap-2 border-b-2 px-4 py-2.5 text-sm font-medium whitespace-nowrap transition-colors',
                        tab.id === active
                            ? 'border-primary text-primary'
                            : 'border-transparent text-base-content/70 hover:text-base-content',
                    )}
                >
                    {tab.icon}
                    {tab.label}
                    {tab.badge}
                </button>
            ))}
        </div>
    );
}

export function tabPanelProps(id: string) {
    return {
        role: 'tabpanel',
        id: `panel-${id}`,
        'aria-labelledby': `tab-${id}`,
        tabIndex: 0,
    } as const;
}
